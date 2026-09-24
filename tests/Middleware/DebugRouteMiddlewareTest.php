<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Middleware;

use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use Yii3\Debug\Capture\DeferredCapture;
use Yii3\Debug\Collector\DbCollector;
use Yii3\Debug\Middleware\{DebugRouteMiddleware, ToolbarOptions};
use Yii3\Debug\Tests\Support\{HelperFactory, MiddlewareFactory};

use function array_keys;

/**
 * Unit tests for {@see DebugRouteMiddleware} serving the debugger endpoints after finalizing a pending capture.
 */
#[Group('toolbar')]
final class DebugRouteMiddlewareTest extends TestCase
{
    public function testPipelineServesDebuggerPagesUncapturedWhicheverMiddlewareRunsFirst(): void
    {
        foreach (['route first' => true, 'capture first' => false] as $order => $routeFirst) {
            $store = MiddlewareFactory::store();
            $route = MiddlewareFactory::debugRoute();
            $capture = MiddlewareFactory::requestCapture($store);
            $pipeline = MiddlewareFactory::pipeline(
                $routeFirst ? [$route, $capture] : [$capture, $route],
                MiddlewareFactory::handler(HelperFactory::createResponse(204)),
            );

            $debugger = $pipeline->handle(
                HelperFactory::createRequest('GET', '/debug/toolbar', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
            );

            $application = $pipeline->handle(
                HelperFactory::createRequest('GET', '/', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
            );

            self::assertSame(
                'toolbar',
                $debugger->getHeaderLine('X-Debug-Action'),
                "{$order}: the debugger must answer its own path.",
            );
            self::assertSame(
                [$application->getHeaderLine('X-Debug-Tag')],
                array_keys($store->loadManifest()),
                "{$order}: only the application request may be captured.",
            );
        }
    }

    public function testProcessDelegatesDebugRequestsToTheHandler(): void
    {
        $response = MiddlewareFactory::debugRoute()->process(
            HelperFactory::createRequest('GET', '/debug/toolbar', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
            MiddlewareFactory::handler(HelperFactory::createResponse(204)),
        );

        self::assertSame(
            200,
            $response->getStatusCode(),
            'The debugger must answer its own paths.',
        );
        self::assertSame(
            'toolbar',
            $response->getHeaderLine('X-Debug-Action'),
            'Path must select its own endpoint.',
        );
        self::assertSame(
            'no-store',
            $response->getHeaderLine('Cache-Control'),
            'Captured data must never be cached.',
        );
    }

    public function testProcessFinalizesThePendingCaptureWhenOnlyThisMiddlewareRuns(): void
    {
        $store = MiddlewareFactory::store();

        $collector = new DbCollector();

        $deferredCapture = MiddlewareFactory::deferredCapture();
        $tag = MiddlewareFactory::requestCapture($store, new CollectorCoordinator([$collector]), $deferredCapture)
            ->process(
                HelperFactory::createRequest('GET', '/', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
                MiddlewareFactory::queryingHandler($collector),
            )
            ->getHeaderLine('X-Debug-Tag');

        self::assertTrue(
            $deferredCapture->isPending(),
            'The earlier capture must still be waiting for the shutdown event.',
        );

        $application = new class ($deferredCapture) implements RequestHandlerInterface {
            /**
             * Whether a capture was still pending when the application handled the request, or `null` before then.
             */
            public bool|null $pendingOnArrival = null;

            public function __construct(private readonly DeferredCapture $deferredCapture) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->pendingOnArrival = $this->deferredCapture->isPending();

                return HelperFactory::createResponse(204);
            }
        };

        $response = MiddlewareFactory::debugRoute($deferredCapture)->process(
            HelperFactory::createRequest('GET', '/', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
            $application,
        );

        self::assertSame(
            204,
            $response->getStatusCode(),
            'An application path must reach the next handler.',
        );
        self::assertFalse(
            $application->pendingOnArrival,
            'The earlier capture must be written before the application runs.',
        );

        $snapshot = $store->readSnapshot($tag);

        self::assertNotNull(
            $snapshot,
            'The earlier capture must reach the store.',
        );
        self::assertSame(
            1,
            $snapshot->summary->sqlCount,
            'The snapshot must hold the statements of the earlier request only.',
        );
        self::assertNull(
            $collector->capture(),
            'Collectors must be stopped once the earlier capture is written.',
        );
    }

    public function testProcessForbidsDebugRequestsFromDeniedClients(): void
    {
        $response = MiddlewareFactory::debugRoute()->process(
            HelperFactory::createRequest('GET', '/debug/toolbar', serverParams: ['REMOTE_ADDR' => '203.0.113.10']),
            MiddlewareFactory::handler(HelperFactory::createResponse(204)),
        );

        self::assertSame(
            403,
            $response->getStatusCode(),
            'A denied client must not reach the debugger.',
        );
        self::assertSame(
            'no-store',
            $response->getHeaderLine('Cache-Control'),
            'Captured data must never be cached.',
        );
        self::assertSame(
            '',
            (string) $response->getBody(),
            'Rejection must expose no diagnostics.',
        );
    }

    public function testProcessForbidsDebugRequestsWithoutAValidClientAddress(): void
    {
        foreach (['missing' => [], 'malformed' => ['REMOTE_ADDR' => 'localhost']] as $case => $serverParams) {
            $response = MiddlewareFactory::debugRoute()->process(
                HelperFactory::createRequest('GET', '/debug', serverParams: $serverParams),
                MiddlewareFactory::handler(HelperFactory::createResponse(204)),
            );

            self::assertSame(
                403,
                $response->getStatusCode(),
                "A {$case} address must be rejected.",
            );
        }
    }

    public function testProcessPassesApplicationPathsThroughUntouched(): void
    {
        $middleware = MiddlewareFactory::debugRoute();

        foreach (['/', '/debugger', '/site/debug'] as $path) {
            $expected = HelperFactory::createResponse(204);

            self::assertSame(
                $expected,
                $middleware->process(
                    HelperFactory::createRequest('GET', $path, serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
                    MiddlewareFactory::handler($expected),
                ),
                "Path {$path} must reach the application unchanged.",
            );
        }
    }

    public function testProcessServesTheDebuggerUnderTheConfiguredRoutePrefix(): void
    {
        $middleware = MiddlewareFactory::debugRoute(options: new ToolbarOptions(routePrefix: '/developer/debug/'));

        $relocated = $middleware->process(
            HelperFactory::createRequest('GET', '/developer/debug/toolbar', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
            MiddlewareFactory::handler(HelperFactory::createResponse(204)),
        );
        $former = $middleware->process(
            HelperFactory::createRequest('GET', '/debug/toolbar', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
            MiddlewareFactory::handler(HelperFactory::createResponse(204)),
        );

        self::assertSame(
            'toolbar',
            $relocated->getHeaderLine('X-Debug-Action'),
            'The configured prefix must reach the debugger.',
        );
        self::assertSame(
            204,
            $former->getStatusCode(),
            'The default prefix must reach the application.',
        );
    }

    public function testProcessWritesThePendingCaptureBeforeRejectingADeniedClient(): void
    {
        $store = MiddlewareFactory::store();

        $collector = new DbCollector();

        $deferredCapture = MiddlewareFactory::deferredCapture();

        $tag = MiddlewareFactory::requestCapture($store, new CollectorCoordinator([$collector]), $deferredCapture)
            ->process(
                HelperFactory::createRequest('GET', '/', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
                MiddlewareFactory::queryingHandler($collector),
            )
            ->getHeaderLine('X-Debug-Tag');

        $response = MiddlewareFactory::debugRoute($deferredCapture)->process(
            HelperFactory::createRequest('GET', '/debug/toolbar', serverParams: ['REMOTE_ADDR' => '203.0.113.10']),
            MiddlewareFactory::handler(HelperFactory::createResponse(204)),
        );

        self::assertSame(
            403,
            $response->getStatusCode(),
            'A denied client must still be rejected.',
        );

        $snapshot = $store->readSnapshot($tag);

        self::assertNotNull(
            $snapshot,
            'The earlier capture must reach the store before the rejection.',
        );
        self::assertSame(
            1,
            $snapshot->summary->sqlCount,
            'The snapshot must hold the statements of the earlier request only.',
        );
        self::assertNull(
            $collector->capture(),
            'Collectors must be stopped once the rejection is answered.',
        );
        self::assertFalse(
            $deferredCapture->isPending(),
            'Nothing may stay pending once the capture is written.',
        );
    }

    public function testProcessWritesThePendingCaptureBeforeServingTheDebuggerPage(): void
    {
        $store = MiddlewareFactory::store();

        $collector = new DbCollector();

        $deferredCapture = MiddlewareFactory::deferredCapture();
        $tag = MiddlewareFactory::requestCapture($store, new CollectorCoordinator([$collector]), $deferredCapture)
            ->process(
                HelperFactory::createRequest('GET', '/', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
                MiddlewareFactory::queryingHandler($collector),
            )
            ->getHeaderLine('X-Debug-Tag');
        $response = MiddlewareFactory::debugRoute($deferredCapture)->process(
            HelperFactory::createRequest('GET', '/debug/toolbar', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
            MiddlewareFactory::handler(HelperFactory::createResponse(204)),
        );

        self::assertSame(
            200,
            $response->getStatusCode(),
            'The debugger must still answer its own paths.',
        );

        $snapshot = $store->readSnapshot($tag);

        self::assertNotNull(
            $snapshot,
            'The earlier capture must reach the store before the page is served.',
        );
        self::assertSame(
            1,
            $snapshot->summary->sqlCount,
            'The snapshot must hold the statements of the earlier request only.',
        );
        self::assertNull(
            $collector->capture(),
            'Collectors must be stopped while the debugger page is served.',
        );
        self::assertFalse(
            $deferredCapture->isPending(),
            'Nothing may stay pending once the capture is written.',
        );
    }
}
