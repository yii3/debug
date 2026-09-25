<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Middleware;

use Closure;
use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\Panel\Db\{DbSnapshot, QueryRow};
use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;
use PHPForge\Debug\Panel\Request\RequestSnapshot;
use PHPForge\Debug\Storage\{RequestSummary, StorageException};
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Throwable;
use Yii3\Debug\Capture\DeferredCapture;
use Yii3\Debug\Collector\{DbCollector, ProfilingCollector, RequestCollector};
use Yii3\Debug\Middleware\{RequestCaptureMiddleware, ToolbarOptions};
use Yii3\Debug\Tests\Provider\RequestCaptureMiddlewareProvider;
use Yii3\Debug\Tests\Support\{HelperFactory, MiddlewareFactory};
use Yii3\Debug\Tests\Support\Stubs\{LazyBodyStreamStub, RequestObserverCollectorStub};

use function array_keys;
use function array_map;
use function array_values;
use function json_encode;
use function microtime;

use const JSON_THROW_ON_ERROR;

/**
 * Unit tests for {@see RequestCaptureMiddleware} capturing requests, deferring finalization, and injecting the toolbar.
 *
 * {@see RequestCaptureMiddlewareProvider} for test case data providers.
 */
#[Group('toolbar')]
final class RequestCaptureMiddlewareTest extends TestCase
{
    public function testDatabaseTotalsReachHistoryAndCollectorStopsAfterTheRequest(): void
    {
        $store = MiddlewareFactory::store();

        $collector = new DbCollector();

        $middleware = MiddlewareFactory::requestCapture($store, new CollectorCoordinator([$collector]));

        $handler = new readonly class ($collector) implements RequestHandlerInterface {
            public function __construct(private DbCollector $collector) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->collector->observe(QueryRow::create('SELECT 1', 1.0, 1000.0));
                $this->collector->observe(QueryRow::create('SELECT 2', 2.0, 2000.0));

                return HelperFactory::createResponse(204);
            }
        };

        $response = $middleware->process(
            HelperFactory::createRequest('GET', '/', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
            $handler,
        );

        $snapshot = $store->readSnapshot($response->getHeaderLine('X-Debug-Tag'));

        self::assertNotNull(
            $snapshot,
            'Database requests must be persisted.',
        );
        self::assertSame(
            2,
            $snapshot->summary->sqlCount,
            'History must receive the captured query total.',
        );
        self::assertArrayHasKey(
            'db',
            $snapshot->panels,
            'The canonical Database payload must be stored.',
        );
        self::assertNull(
            $collector->capture(),
            'Request completion must stop Database capture.',
        );
    }

    public function testDeferredCaptureIncludesWorkObservedAfterTheHandlerReturned(): void
    {
        $store = MiddlewareFactory::store();

        $collector = new DbCollector();

        $deferredCapture = MiddlewareFactory::deferredCapture();

        $middleware = MiddlewareFactory::requestCapture($store, new CollectorCoordinator([$collector]), $deferredCapture);

        $body = new LazyBodyStreamStub(
            HelperFactory::createStream('<html><body>App</body></html>'),
            static function () use ($collector): void {
                $collector->observe(QueryRow::create('SELECT rendered', 1.0, 1000.0));
            },
        );

        $response = $middleware->process(
            HelperFactory::createRequest(
                'GET',
                'https://example.test/',
                serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
            ),
            MiddlewareFactory::handler(HelperFactory::createResponse(200, ['Content-Type' => 'text/html'], $body)),
        );

        $collector->observe(QueryRow::create('SELECT emitted', 2.0, 2000.0));

        $deferredCapture->finalize();

        $snapshot = $store->readSnapshot($response->getHeaderLine('X-Debug-Tag'));

        self::assertNotNull(
            $snapshot,
            'Finalization must persist the capture.',
        );
        self::assertSame(
            2,
            $snapshot->summary->sqlCount,
            'Both the rendered and the emitted statement must be counted.',
        );
        self::assertArrayHasKey(
            'db',
            $snapshot->panels,
            'The canonical Database payload must be stored.',
        );
        self::assertSame(
            ['SELECT rendered', 'SELECT emitted'],
            array_map(
                static fn(QueryRow $row): string => $row->getQuery(),
                DbSnapshot::fromArray($snapshot->panels['db'], '$.panels.db')->entries(),
            ),
            'Lazy rendering and emission must both reach the panel.',
        );
        self::assertStringContainsString(
            '<yii-debug-toolbar',
            (string) $response->getBody(),
            'HTML responses must still receive toolbar markup.',
        );
    }

    public function testDeferredCaptureIsWrittenWhenTheScriptEndsInsideTheHandler(): void
    {
        $store = MiddlewareFactory::store();

        $dbCollector = new DbCollector();
        $requestCollector = new RequestCollector();

        $shutdown = [];

        $middleware = MiddlewareFactory::requestCapture(
            $store,
            new CollectorCoordinator([$requestCollector, $dbCollector]),
            self::recordingDeferredCapture($shutdown),
        );

        $handler = new readonly class (
            $dbCollector,
            static function () use (&$shutdown): void {
                self::runShutdown($shutdown);
            },
        ) implements RequestHandlerInterface {
            /**
             * @param DbCollector $collector Collector observing the query run before the script ends.
             * @param Closure(): void $exit Runs the PHP shutdown fallback, as `exit` or `dd()` would.
             */
            public function __construct(
                private DbCollector $collector,
                private Closure $exit,
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->collector->observe(QueryRow::create('SELECT 1', 1.0, 1000.0));

                ($this->exit)();

                throw new RuntimeException('Nothing runs after the script ends.');
            }
        };

        try {
            $middleware->process(
                HelperFactory::createRequest(
                    'get',
                    'https://example.test/diary',
                    serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
                ),
                $handler,
            );
        } catch (RuntimeException) {
        }

        $summaries = array_values($store->loadManifest());

        self::assertCount(
            1,
            $summaries,
            'The capture in progress must be written at shutdown.',
        );
        self::assertSame(
            'https://example.test/diary',
            $summaries[0]->url,
            'The summary must come from the request alone.',
        );
        self::assertSame(
            'GET',
            $summaries[0]->method,
            'The method must be normalized as in a completed capture.',
        );
        self::assertStringNotContainsString(
            '.',
            $summaries[0]->tag,
            'The tag must stay dot-free.',
        );
        self::assertLessThan(
            60.0,
            $summaries[0]->processingTime ?? 60.0,
            'Duration must stay scoped to the current request.',
        );
        self::assertSame(
            1,
            $summaries[0]->sqlCount,
            'Work done before the script ended must be counted.',
        );
        self::assertSame(
            0,
            RequestSnapshot::fromArray(
                $store->readSnapshot($summaries[0]->tag)?->panels['request'] ?? [],
                '$.panels.request',
            )->statusCode,
            'The Request panel must be kept with the status PHP reports.',
        );
        self::assertNull(
            $dbCollector->capture(),
            'Writing the capture must stop the collectors.',
        );
    }

    public function testDeferredCaptureWithoutCollectorsWritesTheSnapshotImmediately(): void
    {
        $store = MiddlewareFactory::store();
        $deferredCapture = MiddlewareFactory::deferredCapture();
        $response = MiddlewareFactory::requestCapture($store, deferredCapture: $deferredCapture)
            ->process(
                HelperFactory::createRequest('GET', '/', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
                MiddlewareFactory::handler(HelperFactory::createResponse(204)),
            );

        self::assertFalse(
            $deferredCapture->isPending(),
            'Without collectors nothing may be deferred.',
        );
        self::assertNotNull(
            $store->readSnapshot($response->getHeaderLine('X-Debug-Tag')),
            'The fallback summary must be written inside the pipeline.',
        );
    }

    public function testDeferredFinalizationFailureStillStopsTheCollectors(): void
    {
        $collector = new DbCollector();

        $deferredCapture = MiddlewareFactory::deferredCapture();

        MiddlewareFactory::requestCapture(
            MiddlewareFactory::unwritableStore(),
            new CollectorCoordinator([$collector]),
            $deferredCapture,
        )
            ->process(
                HelperFactory::createRequest('GET', '/', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
                MiddlewareFactory::handler(HelperFactory::createResponse(204)),
            );

        $failure = null;

        try {
            $deferredCapture->finalize();
        } catch (Throwable $throwable) {
            $failure = $throwable;
        }

        self::assertInstanceOf(
            StorageException::class,
            $failure,
            'The storage failure must reach the shutdown phase.',
        );
        self::assertNull(
            $collector->capture(),
            'Collectors must stop even when the capture cannot be written.',
        );
    }

    public function testDeferredHandlerFailureCancelsThePendingCaptureAndStopsCollectors(): void
    {
        $store = MiddlewareFactory::store();

        $collector = new DbCollector();

        $shutdown = [];

        $deferredCapture = self::recordingDeferredCapture($shutdown);

        $stale = 0;

        $middleware = MiddlewareFactory::requestCapture($store, new CollectorCoordinator([$collector]), $deferredCapture);

        $handler = new readonly class (
            $deferredCapture,
            static function () use (&$stale): void {
                $stale++;
            },
        ) implements RequestHandlerInterface {
            /**
             * @param DeferredCapture $deferredCapture Holder of the pending finalizer.
             * @param Closure(): void $finalizer Capture deferred while the request is handled.
             */
            public function __construct(
                private DeferredCapture $deferredCapture,
                private Closure $finalizer,
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->deferredCapture->defer($this->finalizer);

                throw new RuntimeException('Handler failed.');
            }
        };

        $failure = null;

        try {
            $middleware->process(
                HelperFactory::createRequest('GET', '/', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
                $handler,
            );
        } catch (Throwable $throwable) {
            $failure = $throwable;
        }

        self::assertInstanceOf(
            RuntimeException::class,
            $failure,
            'The application failure must reach the caller.',
        );
        self::assertSame(
            'Handler failed.',
            $failure->getMessage(),
            'Cleanup must never replace the primary failure.',
        );
        self::assertSame(
            0,
            $stale,
            'A failed request must drop the capture left pending.',
        );
        self::assertFalse(
            $deferredCapture->isPending(),
            'Nothing may stay pending after a failure.',
        );
        self::assertNull(
            $collector->capture(),
            'A failed request must stop the collectors.',
        );

        self::runShutdown($shutdown);

        self::assertSame(
            [],
            $store->loadManifest(),
            'A failed request must not be captured, not even at shutdown.',
        );
    }

    public function testDeferredProcessWritesThePendingCaptureBeforeTheCollectorsRestart(): void
    {
        $store = MiddlewareFactory::store();

        $collector = new DbCollector();

        $deferredCapture = MiddlewareFactory::deferredCapture();

        $observed = [];

        $deferredCapture->defer(
            static function () use ($collector, &$observed): void {
                $observed[] = $collector->capture();
            },
        );

        $middleware = MiddlewareFactory::requestCapture($store, new CollectorCoordinator([$collector]), $deferredCapture);

        $handler = new readonly class ($collector) implements RequestHandlerInterface {
            public function __construct(private DbCollector $collector) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->collector->observe(QueryRow::create('SELECT 1', 1.0, 1000.0));

                return HelperFactory::createResponse(204);
            }
        };

        $response = $middleware->process(
            HelperFactory::createRequest('GET', '/', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
            $handler,
        );

        self::assertSame(
            [null],
            $observed,
            'Collectors must still be stopped while the earlier capture is written.',
        );

        $deferredCapture->finalize();

        $snapshot = $store->readSnapshot($response->getHeaderLine('X-Debug-Tag'));

        self::assertNotNull(
            $snapshot,
            'Finalization must persist the capture.',
        );
        self::assertSame(
            1,
            $snapshot->summary->sqlCount,
            'The restarted cycle must keep the statements of its own request.',
        );
    }

    public function testDeferredProcessWritesTheSnapshotOnlyWhenTheApplicationFinalizes(): void
    {
        $store = MiddlewareFactory::store();

        $collector = new DbCollector();

        $deferredCapture = MiddlewareFactory::deferredCapture();

        $middleware = MiddlewareFactory::requestCapture($store, new CollectorCoordinator([$collector]), $deferredCapture);

        $handler = new readonly class ($collector) implements RequestHandlerInterface {
            public function __construct(private DbCollector $collector) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->collector->observe(QueryRow::create('SELECT 1', 1.0, 1000.0));

                return HelperFactory::createResponse(204);
            }
        };

        $response = $middleware->process(
            HelperFactory::createRequest('GET', '/', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
            $handler,
        );

        self::assertSame(
            [],
            $store->loadManifest(),
            'Nothing may be written inside the pipeline.',
        );
        self::assertTrue(
            $deferredCapture->isPending(),
            'The capture must wait for the shutdown event.',
        );
        self::assertNotNull(
            $collector->capture(),
            'Collectors must stay active until the application finalizes.',
        );

        $resumeAt = microtime(true) + 0.02;

        while (microtime(true) < $resumeAt) {
            // Busy-wait on the clock the middleware samples, so the deferred duration holds on every platform.
        }

        $deferredCapture->finalize();

        $snapshot = $store->readSnapshot($response->getHeaderLine('X-Debug-Tag'));

        self::assertNotNull(
            $snapshot,
            'Finalization must persist the capture.',
        );
        self::assertSame(
            1,
            $snapshot->summary->sqlCount,
            'History must receive the captured query total.',
        );
        self::assertNull(
            $collector->capture(),
            'Finalization must stop the collectors.',
        );
        self::assertFalse(
            $deferredCapture->isPending(),
            'Nothing may stay pending once the capture is written.',
        );

        $processingTime = $snapshot->summary->processingTime;

        self::assertNotNull(
            $processingTime,
            'The summary must retain its duration.',
        );
        self::assertGreaterThanOrEqual(
            0.02,
            $processingTime,
            'Duration must span the work that follows the pipeline.',
        );
        self::assertLessThan(
            60.0,
            $processingTime,
            'Duration must stay scoped to the current request.',
        );
        self::assertLessThan(
            20.0,
            (float) $response->getHeaderLine('X-Debug-Duration'),
            'The duration header must keep reporting handler time, in milliseconds.',
        );
    }

    public function testDeferredShutdownAfterTheHandlerReturnedWritesOnlyTheCompleteCapture(): void
    {
        $store = MiddlewareFactory::store();

        $shutdown = [];

        $middleware = MiddlewareFactory::requestCapture(
            $store,
            new CollectorCoordinator([new DbCollector()]),
            self::recordingDeferredCapture($shutdown),
        );

        $middleware->process(
            HelperFactory::createRequest('GET', '/', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
            MiddlewareFactory::handler(HelperFactory::createResponse(204)),
        );

        self::runShutdown($shutdown);

        self::assertSame(
            [204],
            array_values(
                array_map(static fn(RequestSummary $summary): int => $summary->statusCode, $store->loadManifest()),
            ),
            'Only the complete capture may be written once the handler returned.',
        );
    }

    public function testExcessiveCallerThresholdOnlyCountsCallersWhenConfigured(): void
    {
        $store = MiddlewareFactory::store();

        $collector = new DbCollector();

        $coordinator = new CollectorCoordinator([$collector]);

        $base = MiddlewareFactory::requestCapture($store, $coordinator);
        $configured = MiddlewareFactory::requestCapture(
            $store,
            $coordinator,
            options: new ToolbarOptions(excessiveCallerThreshold: 2),
        );

        $handler = new readonly class ($collector) implements RequestHandlerInterface {
            public function __construct(private DbCollector $collector) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->collector->observe(QueryRow::create('SELECT 1', 1.0, 1000.0)->withTraceHash('site-a'));
                $this->collector->observe(QueryRow::create('SELECT 2', 2.0, 2000.0)->withTraceHash('site-a'));

                return HelperFactory::createResponse(204);
            }
        };

        $counts = [];

        foreach (['configured' => $configured, 'default' => $base] as $key => $middleware) {
            $response = $middleware->process(
                HelperFactory::createRequest('GET', '/', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
                $handler,
            );
            $snapshot = $store->readSnapshot($response->getHeaderLine('X-Debug-Tag'));

            self::assertNotNull(
                $snapshot,
                'Database requests must be persisted.',
            );

            $counts[$key] = $snapshot->summary->excessiveCallersCount;
        }

        self::assertSame(
            ['configured' => 1, 'default' => 0],
            $counts,
            'Only the configured threshold may flag the call site.',
        );
    }

    public function testProcessAddsAjaxMetadataWithoutInjectingMarkup(): void
    {
        $store = MiddlewareFactory::store();
        $request = HelperFactory::createRequest(
            'GET',
            'https://example.test/data',
            ['X-Requested-With' => 'XMLHttpRequest'],
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        );
        $response = MiddlewareFactory::requestCapture($store)
            ->process(
                $request,
                MiddlewareFactory::handler(
                    HelperFactory::createResponse(
                        200,
                        ['Content-Type' => 'application/json'],
                        '{"ok":true}',
                    ),
                ),
            );

        self::assertNotSame(
            '',
            $response->getHeaderLine('X-Debug-Tag'),
            'AJAX requests must expose a tag.',
        );
        self::assertNotSame(
            '',
            $response->getHeaderLine('X-Debug-Duration'),
            'AJAX requests must expose duration.',
        );
        self::assertSame(
            '{"ok":true}',
            (string) $response->getBody(),
            'AJAX bodies must remain unchanged.',
        );
        self::assertStringNotContainsString(
            '<yii-debug-toolbar',
            (string) $response->getBody(),
            'AJAX responses must not receive toolbar markup.',
        );

        $manifest = $store->loadManifest();

        self::assertCount(
            1,
            $manifest,
            'AJAX requests must be available in history.',
        );

        $summary = array_values($manifest)[0];

        self::assertTrue(
            $summary->ajax,
            'Captured AJAX requests must retain their request type.',
        );
        self::assertSame(
            'https://example.test/data',
            $summary->url,
            'Captured AJAX requests must retain their URL.',
        );
    }

    public function testProcessAppliesTheConfiguredOptions(): void
    {
        $store = MiddlewareFactory::store();
        $middleware = MiddlewareFactory::requestCapture(
            $store,
            options: new ToolbarOptions(
                routePrefix: '/developer/debug/',
                historySize: 1,
                skipUrls: ['/health'],
                position: 'top',
                height: 65,
            ),
        );
        $html = MiddlewareFactory::handler(
            HelperFactory::createResponse(200, ['Content-Type' => 'text/html'], '<html><body>App</body></html>'),
        );

        $middleware->process(
            HelperFactory::createRequest('GET', '/first', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
            $html,
        );

        $response = $middleware->process(
            HelperFactory::createRequest('GET', '/second', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
            $html,
        );

        $tag = $response->getHeaderLine('X-Debug-Tag');
        $body = (string) $response->getBody();

        self::assertSame(
            "/developer/debug/view?tag={$tag}&panel=config",
            $response->getHeaderLine('X-Debug-Link'),
            'Debug link must use the trimmed route prefix.',
        );
        self::assertStringContainsString(
            "data-url=\"/developer/debug/toolbar?tag={$tag}\"",
            $body,
            'Toolbar data URL must use the trimmed route prefix.',
        );
        self::assertStringContainsString(
            "data-skip-urls='[&quot;/health&quot;]'",
            $body,
            'Skipped URLs must reach the toolbar element.',
        );
        self::assertStringContainsString(
            'data-position="top"',
            $body,
            'Position must reach the toolbar element.',
        );
        self::assertStringContainsString(
            'data-height="65"',
            $body,
            'Height must reach the toolbar element.',
        );
        self::assertSame(
            [$tag],
            array_keys($store->loadManifest()),
            'History size must rotate the older capture out.',
        );
    }

    public function testProcessBypassesDebugRouteAndDeniedClients(): void
    {
        $store = MiddlewareFactory::store();
        $debugRequest = HelperFactory::createRequest(
            'GET',
            '/debug/toolbar',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        );
        $deniedRequest = HelperFactory::createRequest(
            'GET',
            '/',
            serverParams: ['REMOTE_ADDR' => '203.0.113.10'],
        );

        $middleware = MiddlewareFactory::requestCapture($store);

        $debugResponse = $middleware->process(
            $debugRequest,
            MiddlewareFactory::handler(HelperFactory::createResponse(204)),
        );
        $deniedResponse = $middleware->process(
            $deniedRequest,
            MiddlewareFactory::handler(HelperFactory::createResponse(204)),
        );

        self::assertSame(
            204,
            $debugResponse->getStatusCode(),
            'Debugger paths must reach the next handler.',
        );
        self::assertSame(
            '',
            $debugResponse->getHeaderLine('X-Debug-Tag'),
            'Debugger routes must bypass request capture.',
        );
        self::assertSame(
            '',
            $deniedResponse->getHeaderLine('X-Debug-Tag'),
            'Denied clients must not receive a debug tag.',
        );
        self::assertSame(
            [],
            $store->loadManifest(),
            'Bypassed requests must not be captured.',
        );
    }

    public function testProcessDispatchesRequestAndResponseToObserverCollectors(): void
    {
        $store = MiddlewareFactory::store();

        $collector = new RequestObserverCollectorStub();
        $coordinator = new CollectorCoordinator([$collector]);

        $request = HelperFactory::createRequest(
            'POST',
            'https://example.test/users?page=2',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        );

        $body = '{"ok":true}';

        $response = MiddlewareFactory::requestCapture($store, $coordinator)->process(
            $request,
            MiddlewareFactory::handler(
                HelperFactory::createResponse(201, ['Content-Type' => 'application/json'], $body),
            ),
        );

        self::assertSame(
            $body,
            (string) $response->getBody(),
            'JSON bodies must remain unchanged.',
        );
        self::assertStringNotContainsString(
            '<yii-debug-toolbar',
            (string) $response->getBody(),
            'JSON responses must not receive toolbar markup.',
        );

        $snapshot = $store->readSnapshot($response->getHeaderLine('X-Debug-Tag'));

        self::assertNotNull(
            $snapshot,
            'Observed requests must persist a debug snapshot.',
        );
        self::assertArrayHasKey(
            'observer',
            $snapshot->panels,
            'The observing collector must persist its panel.',
        );
        self::assertSame(
            ['method' => 'POST', 'statusCode' => 201],
            $snapshot->panels['observer'],
            'Both request and response must reach the observer by interface, not by provider name.',
        );
    }

    public function testProcessInjectsToolbarAndDebugMetadataIntoHtml(): void
    {
        $store = MiddlewareFactory::store();
        $request = HelperFactory::createRequest(
            'GET',
            'https://example.test/',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1', 'REQUEST_TIME_FLOAT' => 1_700_000_000.0],
        );
        $response = MiddlewareFactory::requestCapture($store)
            ->process(
                $request,
                MiddlewareFactory::handler(
                    HelperFactory::createResponse(
                        200,
                        ['Content-Type' => 'text/html'],
                        '<html><body>App</body></html>',
                    ),
                ),
            );

        self::assertNotSame(
            '',
            $response->getHeaderLine('X-Debug-Tag'),
            'HTML requests must expose a debug tag.',
        );
        self::assertNotSame(
            '',
            $response->getHeaderLine('X-Debug-Duration'),
            'HTML requests must expose their processing duration.',
        );
        self::assertSame(
            '/debug/view?tag=' . $response->getHeaderLine('X-Debug-Tag') . '&panel=config',
            $response->getHeaderLine('X-Debug-Link'),
            'Debug link must fall back to Config when no Request collector is registered.',
        );
        self::assertStringContainsString(
            '<yii-debug-toolbar',
            (string) $response->getBody(),
            'HTML responses must receive toolbar markup.',
        );
        self::assertStringContainsString(
            '/debug/toolbar?tag=',
            (string) $response->getBody(),
            'Injected toolbar markup must reference its data endpoint.',
        );

        $manifest = $store->loadManifest();

        self::assertCount(
            1,
            $manifest,
            'HTML requests must be available in history.',
        );

        $summary = array_values($manifest)[0];

        self::assertSame(
            $response->getHeaderLine('X-Debug-Tag'),
            $summary->tag,
            'Stored summary tag must match the response metadata.',
        );
        self::assertSame(
            'GET',
            $summary->method,
            'Stored summary must retain the request method.',
        );
        self::assertSame(
            'https://example.test/',
            $summary->url,
            'Stored summary must retain the request URL.',
        );
        self::assertSame(
            200,
            $summary->statusCode,
            'Stored summary must retain the response status code.',
        );
        self::assertSame(
            '127.0.0.1',
            $summary->ip,
            'Stored summary must retain the client IP address.',
        );
        self::assertFalse(
            $summary->ajax,
            'Regular HTML requests must not be marked as AJAX.',
        );
        self::assertNotNull(
            $summary->processingTime,
            'Stored summary must include the processing duration.',
        );
        self::assertNotNull(
            $summary->peakMemory,
            'Stored summary must include peak memory usage.',
        );
    }

    public function testProcessKeepsFallbackSummaryAndProfilingTimingCoherent(): void
    {
        $store = MiddlewareFactory::store();

        $coordinator = new CollectorCoordinator([new ProfilingCollector()]);

        $request = HelperFactory::createRequest(
            'GET',
            'https://example.test/profile',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        );

        $response = MiddlewareFactory::requestCapture($store, $coordinator)
            ->process(
                $request,
                MiddlewareFactory::handler(HelperFactory::createResponse(204)),
            );
        $snapshot = $store->readSnapshot($response->getHeaderLine('X-Debug-Tag'));

        self::assertNotNull(
            $snapshot,
            'Profiling requests must persist a debug snapshot.',
        );
        self::assertArrayHasKey(
            'profiling',
            $snapshot->panels,
            'Profiling requests must retain their collector payload.',
        );

        $profiling = ProfilingSnapshot::fromArray($snapshot->panels['profiling'], '$.panels.profiling');

        $processingTime = $snapshot->summary->processingTime;

        self::assertNotNull(
            $processingTime,
            'The request summary must retain its processing duration.',
        );
        self::assertGreaterThanOrEqual(
            $processingTime,
            $profiling->time,
            'Profiling capture must end after request handling while sharing the summary request origin.',
        );
        self::assertLessThan(
            0.05,
            $profiling->time - $processingTime,
            'Summary and profiling fallback timing must differ only by snapshot-capture overhead.',
        );
    }

    public function testProcessPassesOnlyTheConfiguredDebuggerPathsThrough(): void
    {
        $store = MiddlewareFactory::store();
        $middleware = MiddlewareFactory::requestCapture(
            $store,
            options: new ToolbarOptions(routePrefix: '/developer/debug'),
        );

        $debugger = $middleware->process(
            HelperFactory::createRequest('GET', '/developer/debug/toolbar', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
            MiddlewareFactory::handler(HelperFactory::createResponse(204)),
        );
        $application = $middleware->process(
            HelperFactory::createRequest('GET', '/debug/toolbar', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
            MiddlewareFactory::handler(HelperFactory::createResponse(204)),
        );

        self::assertSame(
            '',
            $debugger->getHeaderLine('X-Debug-Tag'),
            'Paths under the configured prefix must not be captured.',
        );
        self::assertSame(
            [$application->getHeaderLine('X-Debug-Tag')],
            array_keys($store->loadManifest()),
            'The default prefix must be an ordinary application path.',
        );
    }

    public function testProcessPassesPathsSharingTheDebugPrefixToTheApplication(): void
    {
        $response = MiddlewareFactory::requestCapture(MiddlewareFactory::store())
            ->process(
                HelperFactory::createRequest(
                    'GET',
                    '/debugger',
                    serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
                ),
                MiddlewareFactory::handler(HelperFactory::createResponse(204)),
            );

        self::assertSame(
            204,
            $response->getStatusCode(),
            'A path that only shares the prefix must reach the application.',
        );
        self::assertNotSame(
            '',
            $response->getHeaderLine('X-Debug-Tag'),
            'A path that only shares the prefix must still be captured.',
        );
    }

    public function testProcessPersistsASecretFreeRequestPanel(): void
    {
        $store = MiddlewareFactory::store();

        $coordinator = new CollectorCoordinator([new RequestCollector()]);

        $request = HelperFactory::createRequest(
            'POST',
            'https://example.test/login?token=query-secret&tab=profile',
            [
                'Authorization' => 'Bearer header-secret',
                'Content-Type' => 'application/json',
            ],
            ['password' => 'body-secret'],
            serverParams: [
                'REMOTE_ADDR' => '127.0.0.1',
                'DB_PASSWORD' => 'server-secret',
            ],
        )
        ->withBody(HelperFactory::createStream('{"password":"body-secret"}'))
        ->withCookieParams(['session_id' => 'cookie-secret']);

        $response = MiddlewareFactory::requestCapture($store, $coordinator)
            ->process(
                $request,
                MiddlewareFactory::handler(
                    HelperFactory::createResponse(
                        201,
                        [
                            'Content-Type' => 'text/html',
                            'Content-Length' => '13',
                            'Set-Cookie' => 'session_id=response-secret',
                        ],
                        '<html></html>',
                    ),
                ),
            );

        $tag = $response->getHeaderLine('X-Debug-Tag');
        $snapshot = $store->readSnapshot($tag);

        self::assertSame(
            "/debug/view?tag={$tag}&panel=request",
            $response->getHeaderLine('X-Debug-Link'),
            'Debug link must open Request when its collector is registered.',
        );
        self::assertNotNull(
            $snapshot,
            'A request collector cycle must persist its snapshot.',
        );
        self::assertArrayHasKey(
            'request',
            $snapshot->panels,
            'Request payload must use the stable panel ID.',
        );
        self::assertSame(
            'https://example.test/login?token=%5Bredacted%5D&tab=profile',
            $snapshot->summary->url,
            'Stored summary URL must redact sensitive query values used by the Request hero.',
        );

        $data = RequestSnapshot::fromArray($snapshot->panels['request'], '$.panels.request')->data();

        $responseHeaders = $data['responseHeaders'] ?? null;

        self::assertIsArray(
            $responseHeaders,
            'Captured response headers must remain an array.',
        );
        self::assertSame(
            201,
            $data['statusCode'] ?? null,
            'Request payload must retain the response status.',
        );
        self::assertSame(
            $response->getHeaderLine('X-Debug-Link'),
            $responseHeaders['X-Debug-Link'] ?? null,
            'Request payload must retain the debugger link among response headers.',
        );
        self::assertArrayNotHasKey(
            'Content-Length',
            $responseHeaders,
            'Captured headers must match the final response after toolbar injection changes its body.',
        );

        $stored = json_encode($snapshot, JSON_THROW_ON_ERROR);

        foreach (
            [
                'query-secret',
                'header-secret',
                'body-secret',
                'cookie-secret',
                'response-secret',
                'server-secret',
            ] as $secret
        ) {
            self::assertStringNotContainsString(
                $secret,
                $stored,
                "Persisted Request data must not contain the $secret fixture.",
            );
        }
    }

    public function testProcessProvidesCurrentRequestTimingToProfilingCollector(): void
    {
        $store = MiddlewareFactory::store();

        $coordinator = new CollectorCoordinator([new ProfilingCollector()]);

        $request = HelperFactory::createRequest(
            'GET',
            'https://example.test/profile',
            serverParams: [
                'REMOTE_ADDR' => '127.0.0.1',
                'REQUEST_TIME_FLOAT' => microtime(true) - 5.0,
            ],
        );

        $response = MiddlewareFactory::requestCapture($store, $coordinator)
            ->process(
                $request,
                MiddlewareFactory::handler(HelperFactory::createResponse(204)),
            );
        $snapshot = $store->readSnapshot($response->getHeaderLine('X-Debug-Tag'));

        self::assertNotNull(
            $snapshot,
            'Profiling requests must persist a debug snapshot.',
        );
        self::assertArrayHasKey(
            'profiling',
            $snapshot->panels,
            'The Profiling collector must persist its request-scoped metrics.',
        );

        $profiling = ProfilingSnapshot::fromArray($snapshot->panels['profiling'], '$.panels.profiling');

        self::assertGreaterThan(
            4.0,
            $profiling->time,
            'Middleware must pass the current request start timestamp to the Profiling collector.',
        );
        self::assertLessThan(
            60.0,
            $profiling->time,
            'Profiling time must remain scoped to the current request.',
        );
    }

    /**
     * @param string $path Path of the request that passes through.
     * @param string $clientIp Remote address of the request that passes through.
     */
    #[DataProviderExternal(RequestCaptureMiddlewareProvider::class, 'passThroughRequests')]
    public function testProcessWritesThePendingCaptureBeforePassingARequestThrough(string $path, string $clientIp): void
    {
        $store = MiddlewareFactory::store();

        $collector = new DbCollector();

        $deferredCapture = MiddlewareFactory::deferredCapture();

        $middleware = MiddlewareFactory::requestCapture($store, new CollectorCoordinator([$collector]), $deferredCapture);

        $tag = $middleware
            ->process(
                HelperFactory::createRequest('GET', '/', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
                MiddlewareFactory::queryingHandler($collector),
            )
            ->getHeaderLine('X-Debug-Tag');

        self::assertTrue(
            $deferredCapture->isPending(),
            'The earlier capture must still be waiting for the shutdown event.',
        );

        $response = $middleware->process(
            HelperFactory::createRequest('GET', $path, serverParams: ['REMOTE_ADDR' => $clientIp]),
            MiddlewareFactory::handler(HelperFactory::createResponse(204)),
        );

        self::assertSame(
            '',
            $response->getHeaderLine('X-Debug-Tag'),
            'The request passing through must not be captured.',
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
        self::assertFalse(
            $deferredCapture->isPending(),
            'Nothing may stay pending once the capture is written.',
        );
    }

    /**
     * Builds a deferred capture whose PHP shutdown fallback is recorded instead of registered.
     *
     * @param list<Closure(): void> $shutdown Receives the fallback.
     */
    private static function recordingDeferredCapture(array &$shutdown): DeferredCapture
    {
        return new DeferredCapture(
            static function (callable $fallback) use (&$shutdown): void {
                $shutdown[] = $fallback(...);
            },
        );
    }

    /**
     * Runs the recorded shutdown fallbacks, as PHP does when the script ends.
     *
     * @param list<Closure(): void> $shutdown Recorded fallbacks.
     */
    private static function runShutdown(array $shutdown): void
    {
        foreach ($shutdown as $fallback) {
            $fallback();
        }
    }
}
