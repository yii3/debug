<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use GuzzleHttp\Psr7\{Response, ServerRequest};
use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\Storage\{RequestSummary, SnapshotStore};
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Yii3\Debug\Collector\RequestCollector;
use Yii3\Debug\Tests\Support\{CustomCollector, CustomPanel, FakeSession, InMemoryIdentityRepository};
use Yii3\Debug\Web\DebugPageRenderer;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Di\{Container, ContainerConfig};
use Yiisoft\Session\SessionInterface;

use function is_dir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Unit tests for the package DI configuration registering application collectors and panels.
 */
final class DependencyInjectionTest extends TestCase
{
    private string $path = '';

    public function testDefinitionsRegisterPersistAndRenderCustomCollector(): void
    {
        $params = require dirname(__DIR__) . '/config/params.php';
        self::assertIsArray($params, 'Package parameters must return an array.');

        $debug = $params['yii3/debug'] ?? null;
        self::assertIsArray($debug, 'Package debug parameters must be present.');

        $debug['path'] = $this->path;
        $debug['collectors'] = [CustomCollector::class];
        $debug['panels'] = [CustomPanel::class];
        $params['yii3/debug'] = $debug;
        $definitions = require dirname(__DIR__) . '/config/di.php';
        self::assertIsArray($definitions, 'Package DI definitions must return an array.');

        $aliases = $this->aliases();
        $container = new Container(
            ContainerConfig::create()->withDefinitions(
                [
                    ...$definitions,
                    Aliases::class => $aliases,
                    AssetManager::class => $this->assetManager($aliases),
                    EventDispatcherInterface::class => new class implements EventDispatcherInterface {
                        public function dispatch(object $event): object
                        {
                            return $event;
                        }
                    },
                    IdentityRepositoryInterface::class => new InMemoryIdentityRepository(),
                    SessionInterface::class => new FakeSession(),
                ],
            ),
        );

        $coordinator = $container->get(CollectorCoordinator::class);
        $requestCollector = $container->get(RequestCollector::class);
        self::assertInstanceOf(CollectorCoordinator::class, $coordinator, 'DI must build the collector coordinator.');
        self::assertInstanceOf(RequestCollector::class, $requestCollector, 'DI must share the native request collector.');

        self::assertTrue($coordinator->hasCollector('config'), 'DI must register the configuration collector.');
        self::assertTrue($coordinator->hasCollector('request'), 'DI must register the native request collector.');
        self::assertTrue($coordinator->hasCollector('profiling'), 'DI must register the profiling collector.');
        self::assertTrue($coordinator->hasCollector('app.example'), 'DI must register the application collector.');

        $coordinator->startup();
        $requestCollector->collectRequest(new ServerRequest('GET', 'https://example.test/'));
        $requestCollector->collectResponse(new Response(200));
        $snapshot = $coordinator->capture($this->summary());
        $coordinator->shutdown();

        $store = $container->get(SnapshotStore::class);
        self::assertInstanceOf(SnapshotStore::class, $store, 'DI must build the shared snapshot store.');

        $store->writeSnapshot($snapshot, 10);
        $loaded = $store->readSnapshot('request-1');

        self::assertNotNull($loaded, 'Snapshot captured through DI must be loadable.');
        self::assertSame(
            ['value' => 'custom payload'],
            $loaded->panels['app.example'] ?? null,
            'DI-registered custom collector payload must survive storage.',
        );

        $renderer = $container->get(DebugPageRenderer::class);
        self::assertInstanceOf(DebugPageRenderer::class, $renderer, 'DI must build the configured page renderer.');

        $html = $renderer->snapshot($loaded, 'app.example');

        self::assertStringContainsString(
            '<div class="app-example-panel">',
            $html,
            'DI-registered custom panel must replace the JSON fallback.',
        );
    }

    /**
     * Creates an isolated temporary storage path.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir() . '/yii3-debug-di-' . uniqid('', true);
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
                '@assets' => sys_get_temp_dir() . '/yii3-debug-di-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__) . '/vendor',
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
     * Creates representative request metadata.
     *
     * @return RequestSummary Representative request summary.
     */
    private function summary(): RequestSummary
    {
        return new RequestSummary(
            tag: 'request-1',
            url: 'https://example.test/',
            ajax: false,
            method: 'GET',
            ip: '127.0.0.1',
            time: 1_700_000_000.0,
            statusCode: 200,
            sqlCount: 0,
            excessiveCallersCount: 0,
            mailCount: 0,
            mailFiles: [],
            processingTime: 0.01,
            peakMemory: 2_097_152,
        );
    }
}
