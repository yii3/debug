<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Middleware;

use GuzzleHttp\Psr7\{HttpFactory, Response, ServerRequest};
use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\Panel\Inertia\InertiaSnapshot;
use PHPForge\Debug\Storage\SnapshotStore;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use Yii3\Debug\Collector\InertiaCollector;
use Yii3\Debug\Middleware\ToolbarMiddleware;
use Yii3\Debug\Web\ToolbarRenderer;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};
use Yiisoft\NetworkUtilities\IpRanges;
use Yiisoft\View\WebView;

use function sys_get_temp_dir;

/**
 * Unit tests for toolbar injection and AJAX response metadata.
 */
#[Group('toolbar')]
final class ToolbarMiddlewareTest extends TestCase
{
    public function testProcessAddsAjaxMetadataWithoutInjectingMarkup(): void
    {
        $store = $this->store();

        $request = (new ServerRequest('GET', 'https://example.test/data', serverParams: ['REMOTE_ADDR' => '127.0.0.1']))
            ->withHeader('X-Requested-With', 'XMLHttpRequest');

        $response = $this->middleware($store)
            ->process(
                $request,
                $this->handler(new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}')),
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

    public function testProcessBypassesDebugRouteAndDeniedClients(): void
    {
        $store = $this->store();

        $debugRequest = new ServerRequest('GET', '/debug/toolbar', serverParams: ['REMOTE_ADDR' => '127.0.0.1']);
        $deniedRequest = new ServerRequest('GET', '/', serverParams: ['REMOTE_ADDR' => '203.0.113.10']);

        $middleware = $this->middleware($store);

        $debugResponse = $middleware->process(
            $debugRequest,
            $this->handler(new Response(204)),
        );
        $deniedResponse = $middleware->process(
            $deniedRequest,
            $this->handler(new Response(204)),
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
    public function testProcessCapturesResolvedInertiaPageWithoutChangingJsonResponse(): void
    {
        $store = $this->store();
        $collector = new InertiaCollector();
        $coordinator = new CollectorCoordinator([$collector]);
        $request = (new ServerRequest(
            'POST',
            'https://example.test/users?page=2',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        ))
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Partial-Component', 'Users/Index')
            ->withHeader('X-Inertia-Partial-Data', 'users')
            ->withHeader('X-Inertia-Version', 'v2');
        $body = '{"component":"Users/Index","props":{"users":[{"id":7}]},"url":"/users?page=2","version":"v2"}';

        $response = $this->middleware($store, $coordinator)->process(
            $request,
            new readonly class ($collector, $body) implements RequestHandlerInterface {
                public function __construct(
                    private InertiaCollector $collector,
                    private string $body,
                ) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    $this->collector->observe(
                        [
                            'component' => 'Users/Index',
                            'props' => [
                                'auth' => ['user' => ['id' => 1]],
                                'users' => [['id' => 7]],
                            ],
                            'url' => '/users?page=2',
                            'version' => 'v2',
                        ],
                        ['auth'],
                    );

                    return new Response(
                        200,
                        [
                            'Content-Type' => 'application/json',
                            'X-Inertia' => 'true',
                        ],
                        $this->body,
                    );
                }
            },
        );

        self::assertSame(
            $body,
            (string) $response->getBody(),
            'Inertia JSON response bodies must remain unchanged.',
        );
        self::assertSame(
            'true',
            $response->getHeaderLine('X-Inertia'),
            'Existing Inertia response metadata must remain unchanged.',
        );
        self::assertStringNotContainsString(
            '<yii-debug-toolbar',
            (string) $response->getBody(),
            'Inertia JSON responses must not receive toolbar markup.',
        );

        $snapshot = $store->readSnapshot($response->getHeaderLine('X-Debug-Tag'));

        self::assertNotNull(
            $snapshot,
            'Captured Inertia requests must persist a debug snapshot.',
        );
        self::assertSame(
            'POST',
            $snapshot->summary->method,
            'The request method must be retained.',
        );
        self::assertSame(
            'https://example.test/users?page=2',
            $snapshot->summary->url,
            'The request URL must be retained.',
        );
        self::assertSame(
            200,
            $snapshot->summary->statusCode,
            'The response status must be retained.',
        );
        self::assertArrayHasKey(
            'inertia',
            $snapshot->panels,
            'A resolved Inertia page must persist its panel.',
        );

        $inertia = InertiaSnapshot::fromArray($snapshot->panels['inertia'], '$.panels.inertia')->data();

        self::assertSame(
            [
                'location' => null,
                'page' => [
                    'component' => 'Users/Index',
                    'props' => [
                        'auth' => ['user' => ['id' => 1]],
                        'users' => [['id' => 7]],
                    ],
                    'url' => '/users?page=2',
                    'version' => 'v2',
                ],
                'requestHeaders' => [
                    'X-Inertia' => 'true',
                    'X-Inertia-Partial-Component' => 'Users/Index',
                    'X-Inertia-Partial-Data' => 'users',
                    'X-Inertia-Version' => 'v2',
                ],
                'sharedKeys' => ['auth'],
                'statusCode' => 200,
            ],
            $inertia,
            'The Inertia panel must retain the page, shared keys, and request and response metadata.',
        );
    }

    public function testProcessInjectsToolbarAndDebugMetadataIntoHtml(): void
    {
        $store = $this->store();

        $request = new ServerRequest(
            'GET',
            'https://example.test/',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1', 'REQUEST_TIME_FLOAT' => 1_700_000_000.0],
        );

        $response = $this->middleware($store)->process(
            $request,
            $this->handler(new Response(200, ['Content-Type' => 'text/html'], '<html><body>App</body></html>')),
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
            '',
            $response->getHeaderLine('X-Debug-Link'),
            'No debugger page must be linked.',
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

    public function testProcessRetainsEmptyInertiaSnapshotForPlainResponseDiagnostics(): void
    {
        $store = $this->store();
        $coordinator = new CollectorCoordinator([new InertiaCollector()]);
        $request = new ServerRequest(
            'GET',
            'https://example.test/api/status',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        );

        $response = $this->middleware($store, $coordinator)->process(
            $request,
            $this->handler(new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}')),
        );
        $snapshot = $store->readSnapshot($response->getHeaderLine('X-Debug-Tag'));

        self::assertNotNull(
            $snapshot,
            'Plain responses must still persist their request summary.',
        );
        self::assertArrayHasKey(
            'inertia',
            $snapshot->panels,
            'Plain responses must retain an empty Inertia snapshot for directly addressed diagnostics.',
        );
        self::assertSame(
            [
                'location' => null,
                'page' => null,
                'requestHeaders' => [],
                'sharedKeys' => [],
                'statusCode' => 200,
            ],
            InertiaSnapshot::fromArray($snapshot->panels['inertia'], '$.panels.inertia')->data(),
            'Plain responses must not fabricate page or negotiation data.',
        );
    }

    private function handler(ResponseInterface $response): RequestHandlerInterface
    {
        return new readonly class ($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }

    private function middleware(
        SnapshotStore $store,
        CollectorCoordinator|null $collectorCoordinator = null,
    ): ToolbarMiddleware {
        $factory = new HttpFactory();
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-middleware-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
            ],
        );
        $assets = (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));

        return new ToolbarMiddleware(
            new ToolbarRenderer(
                new WebView(),
                $assets,
                $aliases->get('@vendor/php-forge/debug-core/resources/views'),
            ),
            $factory,
            $store,
            new IpRanges(['127.0.0.1', '::1']),
            collectorCoordinator: $collectorCoordinator,
        );
    }

    private function store(): SnapshotStore
    {
        return new SnapshotStore(
            sys_get_temp_dir() . '/yii3-debug-middleware-' . uniqid(),
            0o700,
            0o600,
        );
    }
}
