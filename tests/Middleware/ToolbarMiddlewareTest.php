<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Middleware;

use GuzzleHttp\Psr7\{HttpFactory, Response, ServerRequest};
use PHPForge\Debug\Collector\{CollectorCoordinator, CollectorInterface};
use PHPForge\Debug\Storage\SnapshotStore;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use Yii3\Debug\Collector\RequestCollector;
use Yii3\Debug\Middleware\ToolbarMiddleware;
use Yii3\Debug\Tests\Support\CustomCollector;
use Yii3\Debug\Web\{LocalAccessChecker, ToolbarRenderer};
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};
use Yiisoft\View\WebView;

use function is_dir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Unit tests for {@see ToolbarMiddleware} persisting native collectors and injecting the toolbar safely.
 */
#[Group('toolbar')]
final class ToolbarMiddlewareTest extends TestCase
{
    private string $path = '';

    public function testProcessBypassesDebugEndpointWithoutRecursiveCapture(): void
    {
        [$middleware, $store] = $this->middleware();
        $request = new ServerRequest(
            'GET',
            'https://example.test/debug',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        );
        $response = $middleware->process(
            $request,
            $this->handler(new Response(200, ['Content-Type' => 'text/html'], '<p>History</p>')),
        );

        self::assertSame('<p>History</p>', (string) $response->getBody(), 'Debug page body must remain unchanged.');
        self::assertFalse($response->hasHeader('X-Debug-Tag'), 'Debug page must not emit recursive debug headers.');
        self::assertSame([], $store->loadManifest(), 'Debug endpoint must not create a recursive snapshot.');
    }

    public function testProcessDoesNotInjectIntoAjaxResponse(): void
    {
        [$middleware, $store] = $this->middleware();
        $request = new ServerRequest(
            'GET',
            'https://example.test/data',
            ['X-Requested-With' => 'XMLHttpRequest'],
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        );
        $response = $middleware->process(
            $request,
            $this->handler(new Response(200, ['Content-Type' => 'text/html'], '<p>Partial</p>')),
        );

        self::assertSame('<p>Partial</p>', (string) $response->getBody(), 'AJAX body must remain unchanged.');
        self::assertNotSame('', $response->getHeaderLine('X-Debug-Tag'), 'AJAX response must retain debug headers.');
        self::assertNotNull(
            $store->readSnapshot($response->getHeaderLine('X-Debug-Tag')),
            'AJAX request snapshot must still be persisted.',
        );
    }

    public function testProcessIsolatesFailingCustomCollector(): void
    {
        [$middleware, $store] = $this->middleware([new CustomCollector(failCapture: true)]);
        $response = $middleware->process(
            new ServerRequest(
                'GET',
                'https://example.test/',
                serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
            ),
            $this->handler(new Response(200, ['Content-Type' => 'application/json'], '{}')),
        );

        $snapshot = $store->readSnapshot($response->getHeaderLine('X-Debug-Tag'));

        self::assertNotNull($snapshot, 'Snapshot must remain loadable after one collector fails.');
        self::assertArrayHasKey('request', $snapshot->panels, 'Successful native collector must remain persisted.');
        self::assertArrayHasKey('app.example', $snapshot->failures, 'Custom capture failure must be persisted by ID.');
    }

    public function testProcessPersistsNativeAndCustomCollectorsAndInjectsToolbar(): void
    {
        [$middleware, $store] = $this->middleware([new CustomCollector()]);
        $request = new ServerRequest(
            'GET',
            'https://example.test/',
            serverParams: [
                'REMOTE_ADDR' => '127.0.0.1',
                'REQUEST_TIME_FLOAT' => microtime(true) - 0.01,
            ],
        );
        $response = $middleware->process(
            $request,
            $this->handler(
                new Response(
                    200,
                    ['Content-Type' => 'text/html; charset=UTF-8', 'Content-Length' => '31'],
                    '<html><body>Home</body></html>',
                ),
            ),
        );

        self::assertStringContainsString(
            '<yii-debug-toolbar',
            (string) $response->getBody(),
            'HTML response must contain the shared custom element.',
        );
        self::assertStringContainsString(
            '/debug/toolbar?tag=',
            (string) $response->getBody(),
            'Custom element must load data for the active request.',
        );
        self::assertNotSame('', $response->getHeaderLine('X-Debug-Tag'), 'Response must expose its debug tag.');
        self::assertSame(
            '/debug/view?tag=' . $response->getHeaderLine('X-Debug-Tag') . '&panel=request',
            $response->getHeaderLine('X-Debug-Link'),
            'Debug link must target the captured summary.',
        );
        self::assertFalse($response->hasHeader('Content-Length'), 'Stale body length must be removed.');

        $snapshot = $store->readSnapshot($response->getHeaderLine('X-Debug-Tag'));

        self::assertNotNull($snapshot, 'Captured request snapshot must be loadable from the shared store.');
        self::assertArrayHasKey('request', $snapshot->panels, 'Native request payload must be persisted by stable ID.');
        self::assertSame(
            ['value' => 'custom payload'],
            $snapshot->panels['app.example'] ?? null,
            'Custom collector payload must be persisted by its application ID.',
        );
    }

    /**
     * Creates an isolated temporary storage path.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir() . '/yii3-debug-middleware-' . uniqid('', true);
    }

    /**
     * Removes temporary storage files.
     */
    protected function tearDown(): void
    {
        $files = glob($this->path . '/*');

        foreach ($files === false ? [] : $files as $file) {
            unlink($file);
        }

        if (is_dir($this->path)) {
            rmdir($this->path);
        }

        parent::tearDown();
    }

    /**
     * Creates aliases for shared views and the test asset runtime.
     *
     * @return Aliases Configured aliases.
     */
    private function aliases(): Aliases
    {
        return new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-toolbar-middleware-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
                '@yii3DebugViews' => '@vendor/php-forge/debug-core/resources/views',
            ],
        );
    }

    /**
     * Creates an asset manager that publishes into the test runtime.
     *
     * @param Aliases $aliases Alias resolver.
     *
     * @return AssetManager Configured asset manager.
     */
    private function assetManager(Aliases $aliases): AssetManager
    {
        return (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));
    }

    /**
     * Creates a request handler returning a fixed response.
     *
     * @param ResponseInterface $response Fixed response.
     *
     * @return RequestHandlerInterface Fixed response handler.
     */
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

    /**
     * Creates middleware with native collectors and shared storage.
     *
     * @param list<CollectorInterface> $collectors Additional application collectors.
     *
     * @return array{ToolbarMiddleware, SnapshotStore} Configured middleware and its store.
     */
    private function middleware(array $collectors = []): array
    {
        $aliases = $this->aliases();
        $requestCollector = new RequestCollector();
        $store = new SnapshotStore($this->path, 0o777, null);
        $middleware = new ToolbarMiddleware(
            new CollectorCoordinator([$requestCollector, ...$collectors]),
            $requestCollector,
            $store,
            new ToolbarRenderer(
                new WebView(),
                $this->assetManager($aliases),
                $aliases->get('@yii3DebugViews'),
            ),
            new HttpFactory(),
            new LocalAccessChecker(),
        );

        return [$middleware, $store];
    }
}
