<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Middleware;

use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\Panel\Db\QueryRow;
use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;
use PHPForge\Debug\Panel\Request\RequestSnapshot;
use PHPForge\Debug\Storage\SnapshotStore;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionProperty;
use Yii3\Debug\Collector\DbCollector;
use Yii3\Debug\Collector\{ProfilingCollector, RequestCollector};
use Yii3\Debug\Middleware\ToolbarMiddleware;
use Yii3\Debug\Tests\Support\HelperFactory;
use Yii3\Debug\Tests\Support\Stubs\RequestObserverCollectorStub;
use Yii3\Debug\Web\ToolbarRenderer;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};
use Yiisoft\NetworkUtilities\IpRanges;
use Yiisoft\View\WebView;

use function json_encode;
use function microtime;
use function sys_get_temp_dir;

use const JSON_THROW_ON_ERROR;

/**
 * Unit tests for toolbar injection and AJAX response metadata.
 */
#[Group('toolbar')]
final class ToolbarMiddlewareTest extends TestCase
{
    public function testConfigurationMethodsPreserveExistingSettings(): void
    {
        $middleware = $this->middleware($this->store());

        $capturePolicy = new CapturePolicy(maxBodyBytes: 4096);
        $collectorCoordinator = new CollectorCoordinator([]);

        $originalState = self::configurationState($middleware);

        $defaultCapturePolicy = $originalState['capturePolicy'] ?? null;

        self::assertInstanceOf(
            CapturePolicy::class,
            $defaultCapturePolicy,
            'Middleware must create the default capture policy.',
        );

        $withCapturePolicy = $middleware
            ->withCapturePolicy($capturePolicy);
        $withCollectorCoordinator = $withCapturePolicy
            ->withCollectorCoordinator($collectorCoordinator);
        $withHistorySize = $withCollectorCoordinator
            ->withHistorySize(25);
        $withPresentation = $withHistorySize
            ->withPresentation('top', 65);
        $withRoutePrefix = $withPresentation
            ->withRoutePrefix('/developer/debug/');
        $configured = $withRoutePrefix
            ->withSkipUrls(['/health']);

        $defaultState = [
            'capturePolicy' => $defaultCapturePolicy,
            'collectorCoordinator' => null,
            'height' => 50,
            'historySize' => 50,
            'position' => 'bottom',
            'routePrefix' => '/debug',
            'skipUrls' => [],
        ];
        $withCapturePolicyState = [
            ...$defaultState,
            'capturePolicy' => $capturePolicy,
        ];
        $withCollectorCoordinatorState = [
            ...$withCapturePolicyState,
            'collectorCoordinator' => $collectorCoordinator,
        ];
        $withHistorySizeState = [
            ...$withCollectorCoordinatorState,
            'historySize' => 25,
        ];
        $withPresentationState = [
            ...$withHistorySizeState,
            'height' => 65,
            'position' => 'top',
        ];
        $withRoutePrefixState = [
            ...$withPresentationState,
            'routePrefix' => '/developer/debug',
        ];
        $configuredState = [
            ...$withRoutePrefixState,
            'skipUrls' => ['/health'],
        ];

        self::assertSame(
            [
                $defaultState,
                $withCapturePolicyState,
                $withCollectorCoordinatorState,
                $withHistorySizeState,
                $withPresentationState,
                $withRoutePrefixState,
                $configuredState,
            ],
            [
                self::configurationState($middleware),
                self::configurationState($withCapturePolicy),
                self::configurationState($withCollectorCoordinator),
                self::configurationState($withHistorySize),
                self::configurationState($withPresentation),
                self::configurationState($withRoutePrefix),
                self::configurationState($configured),
            ],
            'Each immutable copy must preserve its own state and settings applied by earlier methods.',
        );
    }
    public function testDatabaseTotalsReachHistoryAndCollectorStopsAfterTheRequest(): void
    {
        $store = $this->store();
        $collector = new DbCollector();
        $middleware = $this->middleware($store, new CollectorCoordinator([$collector]));
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
        self::assertNotNull($snapshot, 'Database requests must be persisted.');
        self::assertSame(2, $snapshot->summary->sqlCount, 'History must receive the captured query total.');
        self::assertArrayHasKey('db', $snapshot->panels, 'The canonical Database payload must be stored.');
        self::assertNull($collector->capture(), 'Request completion must stop Database capture.');
    }

    public function testProcessAddsAjaxMetadataWithoutInjectingMarkup(): void
    {
        $store = $this->store();

        $request = HelperFactory::createRequest(
            'GET',
            'https://example.test/data',
            ['X-Requested-With' => 'XMLHttpRequest'],
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        );

        $response = $this->middleware($store)
            ->process(
                $request,
                $this->handler(
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

    public function testProcessBypassesDebugRouteAndDeniedClients(): void
    {
        $store = $this->store();

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

        $middleware = $this->middleware($store);

        $debugResponse = $middleware->process(
            $debugRequest,
            $this->handler(HelperFactory::createResponse(204)),
        );
        $deniedResponse = $middleware->process(
            $deniedRequest,
            $this->handler(HelperFactory::createResponse(204)),
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
        $store = $this->store();

        $collector = new RequestObserverCollectorStub();
        $coordinator = new CollectorCoordinator([$collector]);

        $request = HelperFactory::createRequest(
            'POST',
            'https://example.test/users?page=2',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        );

        $body = '{"ok":true}';

        $response = $this->middleware($store, $coordinator)->process(
            $request,
            $this->handler(
                HelperFactory::createResponse(201, ['Content-Type' => 'application/json'], $body),
            ),
        );

        self::assertSame($body, (string) $response->getBody(), 'JSON bodies must remain unchanged.');
        self::assertStringNotContainsString(
            '<yii-debug-toolbar',
            (string) $response->getBody(),
            'JSON responses must not receive toolbar markup.',
        );

        $snapshot = $store->readSnapshot($response->getHeaderLine('X-Debug-Tag'));

        self::assertNotNull($snapshot, 'Observed requests must persist a debug snapshot.');
        self::assertArrayHasKey('observer', $snapshot->panels, 'The observing collector must persist its panel.');
        self::assertSame(
            ['method' => 'POST', 'statusCode' => 201],
            $snapshot->panels['observer'],
            'Both request and response must reach the observer by interface, not by provider name.',
        );
    }

    public function testProcessInjectsToolbarAndDebugMetadataIntoHtml(): void
    {
        $store = $this->store();

        $request = HelperFactory::createRequest(
            'GET',
            'https://example.test/',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1', 'REQUEST_TIME_FLOAT' => 1_700_000_000.0],
        );

        $response = $this->middleware($store)
            ->process(
                $request,
                $this->handler(
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
        $store = $this->store();

        $coordinator = new CollectorCoordinator([new ProfilingCollector()]);

        $request = HelperFactory::createRequest(
            'GET',
            'https://example.test/profile',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        );

        $response = $this->middleware($store, $coordinator)
            ->process(
                $request,
                $this->handler(HelperFactory::createResponse(204)),
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

    public function testProcessPersistsASecretFreeRequestPanel(): void
    {
        $store = $this->store();

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

        $response = $this->middleware($store, $coordinator)
            ->process(
                $request,
                $this->handler(
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
        $store = $this->store();

        $coordinator = new CollectorCoordinator([new ProfilingCollector()]);

        $request = HelperFactory::createRequest(
            'GET',
            'https://example.test/profile',
            serverParams: [
                'REMOTE_ADDR' => '127.0.0.1',
                'REQUEST_TIME_FLOAT' => microtime(true) - 5.0,
            ],
        );

        $response = $this->middleware($store, $coordinator)
            ->process(
                $request,
                $this->handler(HelperFactory::createResponse(204)),
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

    public function testReturnNewInstanceWhenSettingConfiguration(): void
    {
        $middleware = $this->middleware($this->store());

        self::assertNotSame(
            $middleware,
            $middleware->withCapturePolicy(new CapturePolicy()),
            'Should return a new instance when setting the capture policy, ensuring immutability.',
        );
        self::assertNotSame(
            $middleware,
            $middleware->withCollectorCoordinator(new CollectorCoordinator([])),
            'Should return a new instance when setting the collector coordinator, ensuring immutability.',
        );
        self::assertNotSame(
            $middleware,
            $middleware->withHistorySize(25),
            'Should return a new instance when setting the history size, ensuring immutability.',
        );
        self::assertNotSame(
            $middleware,
            $middleware->withPresentation('top', 65),
            'Should return a new instance when setting the presentation, ensuring immutability.',
        );
        self::assertNotSame(
            $middleware,
            $middleware->withRoutePrefix('/developer/debug'),
            'Should return a new instance when setting the route prefix, ensuring immutability.',
        );
        self::assertNotSame(
            $middleware,
            $middleware->withSkipUrls(['/health']),
            'Should return a new instance when setting skipped URLs, ensuring immutability.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function configurationState(ToolbarMiddleware $middleware): array
    {
        $state = [];

        foreach (
            [
                'capturePolicy',
                'collectorCoordinator',
                'height',
                'historySize',
                'position',
                'routePrefix',
                'skipUrls',
            ] as $property
        ) {
            $state[$property] = (new ReflectionProperty(ToolbarMiddleware::class, $property))
                ->getValue($middleware);
        }

        return $state;
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
        $streamFactory = HelperFactory::createStreamFactory();
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-middleware-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
            ],
        );
        $assets = (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));

        $middleware = new ToolbarMiddleware(
            new ToolbarRenderer(
                new WebView(),
                $assets,
                $aliases->get('@vendor/php-forge/debug-core/resources/views'),
            ),
            $streamFactory,
            $store,
            new IpRanges(['127.0.0.1', '::1']),
        );

        return $collectorCoordinator === null
            ? $middleware
            : $middleware->withCollectorCoordinator($collectorCoordinator);
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
