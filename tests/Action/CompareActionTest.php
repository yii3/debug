<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Action;

use Closure;
use GuzzleHttp\Psr7\{HttpFactory, ServerRequest};
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary, SnapshotStore};
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Action\CompareAction;
use Yii3\Debug\ConfigDataFactory;
use Yii3\Debug\Web\DebugPageRenderer;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};
use Yiisoft\Router\{Group as RouteGroup, RouteCollection, RouteCollector};
use Yiisoft\View\WebView;

use function file_put_contents;
use function json_encode;
use function sys_get_temp_dir;
use function unlink;

use const JSON_THROW_ON_ERROR;

/**
 * Unit tests for {@see CompareAction}.
 */
final class CompareActionTest extends TestCase
{
    public function testCorruptSnapshotReturnsNotFound(): void
    {
        [$store, $path] = $this->storeWithPair();

        self::assertSame(
            1,
            file_put_contents("{$path}/request-newest.json", '{'),
            'The target snapshot fixture must be replaceable with invalid JSON.',
        );

        $response = ($this->action($store))(new ServerRequest('GET', '/debug/compare'));

        self::assertSame(
            404,
            $response->getStatusCode(),
            'A corrupt snapshot must return not found.',
        );
        self::assertSame(
            "Unable to find debug data tagged with 'request-newest'.",
            (string) $response->getBody(),
            'The error must identify the corrupt target tag without exposing storage diagnostics.',
        );
    }

    public function testDefaultsToPreviousAndNewestCapturesAndPropagatesTheme(): void
    {
        [$store] = $this->storeWithPair();

        $request = (new ServerRequest('GET', '/debug/compare?yii_debug_theme=dark'))
            ->withQueryParams(['yii_debug_theme' => 'dark']);

        $response = ($this->action($store))($request);

        $body = (string) $response->getBody();

        self::assertSame(
            200,
            $response->getStatusCode(),
            'A retained capture pair must render successfully.',
        );
        self::assertSame(
            'text/html; charset=UTF-8',
            $response->getHeaderLine('Content-Type'),
            'Comparison pages must use the HTML content type.',
        );
        self::assertStringContainsString(
            'data-yii-debug-theme="dark"',
            $body,
            'The comparison page must receive the resolved request theme.',
        );
        self::assertStringContainsString(
            '+5.00 ms (+50.0%)',
            $body,
            'The previous capture must be the baseline and the newest capture must be the target.',
        );
    }

    public function testExplicitScalarSelectionIsKept(): void
    {
        [$store] = $this->storeWithPair();

        $request = (new ServerRequest('GET', '/debug/compare'))
            ->withQueryParams(
                [
                    'baseline' => 'request-newest',
                    'target' => 'request-older',
                ],
            );

        $response = ($this->action($store))($request);

        self::assertSame(
            200,
            $response->getStatusCode(),
            'Explicit retained tags must render successfully.',
        );
        self::assertStringContainsString(
            '-5.00 ms (-33.3%)',
            (string) $response->getBody(),
            'Explicit scalar tags must not be replaced by the default selection.',
        );
    }

    public function testProtectedCompareGetRouteIsPublished(): void
    {
        $params = require dirname(__DIR__, 2) . '/config/params.php';

        self::assertIsArray(
            $params,
            'Parameter configuration must return an array.',
        );

        $debug = $params['yii3/debug'] ?? null;

        self::assertIsArray(
            $debug,
            'Debug parameters must be present.',
        );

        $debug['routePrefix'] = '/developer/debug';
        $params['yii3/debug'] = $debug;

        $routes = require dirname(__DIR__, 2) . '/config/routes.php';

        self::assertIsArray(
            $routes,
            'Route configuration must return an array.',
        );
        self::assertCount(
            1,
            $routes,
            'Debugger routes must share one protected group.',
        );

        $group = $routes[0] ?? null;

        self::assertInstanceOf(
            RouteGroup::class,
            $group,
            'Debugger routes must be grouped.',
        );

        $groupMiddlewares = $group->getData('enabledMiddlewares');

        self::assertCount(
            1,
            $groupMiddlewares,
            'The debugger route group must retain its IP filter.',
        );
        self::assertInstanceOf(
            Closure::class,
            $groupMiddlewares[0],
            'The route group must create the IP filter.',
        );

        $collector = new RouteCollector();

        $collector->addRoute($group);

        $compareRoute = null;

        foreach ((new RouteCollection($collector))->getRoutes() as $route) {
            if ($route->getData('name') === 'yii3-debug/compare') {
                $compareRoute = $route;

                break;
            }
        }

        self::assertNotNull(
            $compareRoute,
            'The named comparison route must be published.',
        );
        self::assertSame(
            '/developer/debug/compare',
            $compareRoute->getData('pattern'),
            'The comparison path must honor the configured debugger prefix.',
        );
        self::assertSame(
            ['GET'],
            $compareRoute->getData('methods'),
            'Capture comparison must be read-only.',
        );
        self::assertSame(
            CompareAction::class,
            $compareRoute->getData('enabledMiddlewares')[1] ?? null,
            'The protected route must dispatch the comparison action after the group middleware.',
        );
    }

    public function testRequiresTwoCapturesWhenASelectionMustBeDefaulted(): void
    {
        [$store] = $this->storeWithPair();

        $store->clear();

        $this->writeSnapshot(
            $store,
            'request-only',
            0.01,
        );

        $request = (new ServerRequest('GET', '/debug/compare'))
            ->withQueryParams(['baseline' => 'request-only']);

        $response = ($this->action($store))($request);

        self::assertSame(
            404,
            $response->getStatusCode(),
            'An unavailable default pair must return not found.',
        );
        self::assertSame(
            'text/plain; charset=UTF-8',
            $response->getHeaderLine('Content-Type'),
            'Comparison errors must use the plain-text content type.',
        );
        self::assertSame(
            'At least two captured requests are required for comparison.',
            (string) $response->getBody(),
            'The response must explain the minimum capture requirement.',
        );
    }

    public function testRequiresTwoCapturesWhenHistoryIsEmpty(): void
    {
        [$store] = $this->storeWithPair();

        $store->clear();

        $response = ($this->action($store))(new ServerRequest('GET', '/debug/compare'));

        self::assertSame(
            404,
            $response->getStatusCode(),
            'An empty history must return not found.',
        );
        self::assertSame(
            'At least two captured requests are required for comparison.',
            (string) $response->getBody(),
            'An empty history must explain the minimum capture requirement.',
        );
    }

    public function testRequiresTwoCapturesWhenOnlyTargetIsSelected(): void
    {
        [$store] = $this->storeWithPair();

        $store->clear();

        $this->writeSnapshot(
            $store,
            'request-only',
            0.01,
        );

        $request = (new ServerRequest('GET', '/debug/compare'))
            ->withQueryParams(['target' => 'request-only']);

        $response = ($this->action($store))($request);

        self::assertSame(
            404,
            $response->getStatusCode(),
            'A missing baseline default must return not found.',
        );
        self::assertSame(
            'At least two captured requests are required for comparison.',
            (string) $response->getBody(),
            'The minimum capture requirement must apply regardless of which selection is explicit.',
        );
    }

    public function testRotatedSnapshotReturnsNotFound(): void
    {
        [$store, $path] = $this->storeWithPair();

        self::assertTrue(
            unlink("{$path}/request-older.json"),
            'The baseline snapshot fixture must be removable.',
        );

        $response = ($this->action($store))(new ServerRequest('GET', '/debug/compare'));

        self::assertSame(
            404,
            $response->getStatusCode(),
            'A rotated snapshot must return not found.',
        );
        self::assertSame(
            "Unable to find debug data tagged with 'request-older'.",
            (string) $response->getBody(),
            'The error must identify the rotated baseline tag.',
        );
    }

    public function testSnapshotOutsideManifestReturnsNotFound(): void
    {
        [$store, $path] = $this->storeWithPair();

        $orphan = new DebugSnapshot(
            RequestSummary::create('request-orphan')
                ->withRequest('https://example.test/orphan', 'GET', '127.0.0.1', 1_725_000_600.0)
                ->withResponse(200)
                ->withProfiling(0.02, 1_048_576),
            [],
            [],
        );

        self::assertNotFalse(
            file_put_contents("{$path}/request-orphan.json", json_encode($orphan, JSON_THROW_ON_ERROR)),
            'The orphan snapshot fixture must be writable.',
        );

        $request = (new ServerRequest('GET', '/debug/compare'))
            ->withQueryParams(
                [
                    'baseline' => 'request-orphan',
                    'target' => 'request-newest',
                ],
            );

        $response = ($this->action($store))($request);

        self::assertSame(
            404,
            $response->getStatusCode(),
            'Snapshots outside the retained manifest must be rejected.',
        );
        self::assertSame(
            "Unable to find debug data tagged with 'request-orphan'.",
            (string) $response->getBody(),
            'The error must identify the unretained snapshot tag.',
        );
    }

    public function testUnknownExplicitTagReturnsNotFound(): void
    {
        [$store] = $this->storeWithPair();
        $request = (new ServerRequest('GET', '/debug/compare'))
            ->withQueryParams(
                [
                    'baseline' => 'request-older',
                    'target' => 'request-unknown',
                ],
            );

        $response = ($this->action($store))($request);

        self::assertSame(
            404,
            $response->getStatusCode(),
            'Unknown tags must return not found.',
        );
        self::assertSame(
            "Unable to find debug data tagged with 'request-unknown'.",
            (string) $response->getBody(),
            'The error must identify the unavailable explicit tag.',
        );
    }

    private function action(SnapshotStore $store): CompareAction
    {
        $factory = new HttpFactory();

        return new CompareAction($store, $this->renderer(), $factory, $factory);
    }

    private function renderer(): DebugPageRenderer
    {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-compare-action-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
            ],
        );
        $assetManager = (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));

        return new DebugPageRenderer(
            new WebView(),
            $assetManager,
            new ConfigDataFactory(),
            $aliases->get('@vendor/php-forge/debug-core/resources/views'),
        );
    }

    /**
     * @return array{SnapshotStore, string}
     */
    private function storeWithPair(): array
    {
        $path = sys_get_temp_dir() . '/yii3-debug-compare-action-' . uniqid();

        $store = new SnapshotStore($path, 0o700, 0o600);

        $this->writeSnapshot($store, 'request-older', 0.01);
        $this->writeSnapshot($store, 'request-newest', 0.015);

        return [$store, $path];
    }

    private function writeSnapshot(SnapshotStore $store, string $tag, float $processingTime): void
    {
        $store->writeSnapshot(
            new DebugSnapshot(
                RequestSummary::create($tag)
                    ->withRequest("https://example.test/{$tag}", 'GET', '127.0.0.1', 1_725_000_756.0)
                    ->withResponse(200)
                    ->withProfiling($processingTime, 1_048_576),
                [],
                [],
            ),
            50,
        );
    }
}
