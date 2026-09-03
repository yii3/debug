<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use Closure;
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\Panel\Inertia\InertiaSnapshot;
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary, SnapshotStore};
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Yii3\Debug\Collector\{EventCollector, InertiaCollector, LogCollector, ProfilingCollector, RequestCollector};
use Yii3\Debug\{ConfigDataFactory, ExtensionRegistry};
use Yii3\Debug\Log\DebugLogTarget;
use Yii3\Debug\Middleware\ToolbarMiddleware;
use Yii3\Debug\Panel\{EventPanel, InertiaPanel, LogPanel, ProfilingPanel, RequestPanel};
use Yii3\Debug\Tests\Support\HelperFactory;
use Yii3\Debug\ToolbarDataFactory;
use Yii3\Debug\Web\{DebugPageRenderer, ToolbarRenderer};
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};
use Yiisoft\View\WebView;

use function sys_get_temp_dir;

/**
 * Unit tests for explicit debug-extension registration.
 */
final class ExtensionRegistryTest extends TestCase
{
    public function testCreateNormalizesIterablesAndPreservesOrder(): void
    {
        $inertiaCollector = new InertiaCollector();
        $profilingCollector = new ProfilingCollector();
        $inertiaPanel = new InertiaPanel();
        $profilingPanel = new ProfilingPanel();

        $registry = ExtensionRegistry::create(
            collectors: (
                static function () use ($inertiaCollector, $profilingCollector): iterable {
                    yield 'inertia' => $inertiaCollector;
                    yield 'profiling' => $profilingCollector;
                }
            )(),
            panels: (
                static function () use ($inertiaPanel, $profilingPanel): iterable {
                    yield 'inertia' => $inertiaPanel;
                    yield 'profiling' => $profilingPanel;
                }
            )(),
        );

        self::assertSame(
            [$inertiaCollector, $profilingCollector],
            $registry->collectors(),
            'The named factory must normalize collector iterables without changing their order.',
        );
        self::assertSame(
            [$inertiaPanel, $profilingPanel],
            $registry->panels(),
            'The named factory must normalize panel iterables without changing their order.',
        );
    }
    public function testDefaultRegistryIsEmptyAndCoordinatorDoesNotInferExtensions(): void
    {
        $params = require dirname(__DIR__) . '/config/params.php';

        self::assertIsArray(
            $params,
            'Package parameters must return an array.',
        );

        $definitions = require dirname(__DIR__) . '/config/di.php';

        self::assertIsArray(
            $definitions,
            'Package DI configuration must return an array.',
        );

        $registry = new ExtensionRegistry();

        self::assertSame(
            [],
            $registry->collectors(),
            'No extension collector may be enabled implicitly.',
        );
        self::assertSame(
            [],
            $registry->panels(),
            'No extension panel may be enabled implicitly.',
        );

        $coordinatorFactory = $definitions[CollectorCoordinator::class] ?? null;

        self::assertInstanceOf(
            Closure::class,
            $coordinatorFactory,
            'Collector coordinator must be built from the extension registry.',
        );

        $requestCollector = new RequestCollector();
        $logCollector = new LogCollector(new DebugLogTarget());
        $eventCollector = new EventCollector();
        $profilingCollector = new ProfilingCollector();

        $coordinator = $coordinatorFactory(
            $requestCollector,
            $logCollector,
            $eventCollector,
            $profilingCollector,
            $registry,
        );

        self::assertInstanceOf(
            CollectorCoordinator::class,
            $coordinator,
            'Collector coordinator factory must return its service.',
        );
        self::assertSame(
            [
                'request' => $requestCollector,
                'log' => $logCollector,
                'event' => $eventCollector,
                'profiling' => $profilingCollector,
            ],
            (new ReflectionProperty(CollectorCoordinator::class, 'collectors'))->getValue($coordinator),
            'Default DI must register Request, Log, Event, and Profiling collectors in built-in order.',
        );
        self::assertFalse(
            $coordinator->hasCollector('inertia'),
            'Default DI must not infer an Inertia collector from installed classes or interfaces.',
        );
        self::assertSame(
            $requestCollector,
            $coordinator->collector('request'),
            'Request must be registered independently from the empty extension registry.',
        );
        self::assertSame(
            $logCollector,
            $coordinator->collector('log'),
            'Log must be registered independently from the empty extension registry.',
        );
        self::assertSame(
            $eventCollector,
            $coordinator->collector('event'),
            'Event must be registered independently from the empty extension registry.',
        );
        self::assertSame(
            $profilingCollector,
            $coordinator->collector('profiling'),
            'Profiling must be registered independently from the empty extension registry.',
        );
    }

    public function testDependencyInjectionFactoriesConsumeTheExplicitRegistry(): void
    {
        $params = require dirname(__DIR__) . '/config/params.php';

        self::assertIsArray(
            $params,
            'Package parameters must return an array.',
        );

        $definitions = require dirname(__DIR__) . '/config/di.php';

        self::assertIsArray(
            $definitions,
            'Package DI configuration must return an array.',
        );

        $capturePolicy = new CapturePolicy(maxBodyBytes: 4096);
        $collector = new InertiaCollector();
        $builtInRequestCollector = new RequestCollector(capturePolicy: $capturePolicy);
        $requestCollectorOverride = new RequestCollector(capturePolicy: $capturePolicy);
        $builtInLogCollector = new LogCollector(new DebugLogTarget());
        $logCollectorOverride = new LogCollector(new DebugLogTarget());
        $builtInEventCollector = new EventCollector();
        $eventCollectorOverride = new EventCollector();
        $builtInProfilingCollector = new ProfilingCollector();
        $profilingCollectorOverride = new ProfilingCollector();
        $panel = new InertiaPanel();
        $builtInRequestPanel = new RequestPanel();
        $requestPanelOverride = new RequestPanel();
        $builtInLogPanel = new LogPanel();
        $logPanelOverride = new LogPanel();
        $builtInEventPanel = new EventPanel();
        $eventPanelOverride = new EventPanel();
        $builtInProfilingPanel = new ProfilingPanel();
        $profilingPanelOverride = new ProfilingPanel();

        $registry = ExtensionRegistry::create(
            collectors: [
                $collector,
                $requestCollectorOverride,
                $logCollectorOverride,
                $eventCollectorOverride,
                $profilingCollectorOverride,
            ],
            panels: [
                $panel,
                $requestPanelOverride,
                $logPanelOverride,
                $eventPanelOverride,
                $profilingPanelOverride,
            ],
        );

        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-extension-registry-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__) . '/vendor',
                '@yii3DebugViews' => '@vendor/php-forge/debug-core/resources/views',
            ],
        );
        $assetManager = (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));

        $coordinatorFactory = $definitions[CollectorCoordinator::class] ?? null;

        self::assertInstanceOf(
            Closure::class,
            $coordinatorFactory,
            'Collector coordinator factory must be present.',
        );

        self::assertSame(
            [
                $requestCollectorOverride,
                $collector,
                $logCollectorOverride,
                $eventCollectorOverride,
                $profilingCollectorOverride,
            ],
            $registry->collectorsWithBuiltIn($builtInRequestCollector),
            'An explicit Request collector override must replace the built-in instance and remain first.',
        );
        self::assertSame(
            [
                $requestPanelOverride,
                $panel,
                $logPanelOverride,
                $eventPanelOverride,
                $profilingPanelOverride,
            ],
            $registry->panelsWithBuiltIn($builtInRequestPanel),
            'An explicit Request panel override must replace the built-in instance and remain first.',
        );
        self::assertSame(
            [
                $requestCollectorOverride,
                $logCollectorOverride,
                $eventCollectorOverride,
                $profilingCollectorOverride,
                $collector,
            ],
            $registry->collectorsWithBuiltIns(
                [$builtInRequestCollector, $builtInLogCollector, $builtInEventCollector, $builtInProfilingCollector],
            ),
            'Every explicit collector override must replace its built-in instance in built-in order.',
        );
        self::assertSame(
            [$requestPanelOverride, $logPanelOverride, $eventPanelOverride, $profilingPanelOverride, $panel],
            $registry->panelsWithBuiltIns(
                [$builtInRequestPanel, $builtInLogPanel, $builtInEventPanel, $builtInProfilingPanel],
            ),
            'Every explicit panel override must replace its built-in instance in built-in order.',
        );
        self::assertSame(
            [
                $collector,
                $requestCollectorOverride,
                $logCollectorOverride,
                $eventCollectorOverride,
                $profilingCollectorOverride,
            ],
            $registry->collectors(),
            'Built-in composition must not alter the explicit collector registry.',
        );
        self::assertSame(
            [
                $panel,
                $requestPanelOverride,
                $logPanelOverride,
                $eventPanelOverride,
                $profilingPanelOverride,
            ],
            $registry->panels(),
            'Built-in composition must not alter the explicit panel registry.',
        );

        $coordinator = $coordinatorFactory(
            $builtInRequestCollector,
            $builtInLogCollector,
            $builtInEventCollector,
            $builtInProfilingCollector,
            $registry,
        );

        self::assertInstanceOf(
            CollectorCoordinator::class,
            $coordinator,
            'Collector factory must return its service.',
        );
        self::assertSame(
            [
                'request' => $requestCollectorOverride,
                'log' => $logCollectorOverride,
                'event' => $eventCollectorOverride,
                'profiling' => $profilingCollectorOverride,
                'inertia' => $collector,
            ],
            (new ReflectionProperty(CollectorCoordinator::class, 'collectors'))->getValue($coordinator),
            'Collector factory must preserve built-in order before explicitly registered extensions.',
        );
        self::assertSame(
            $collector,
            $coordinator->collector('inertia'),
            'Collector coordinator must use the explicitly registered collector instance.',
        );
        self::assertSame(
            $requestCollectorOverride,
            $coordinator->collector('request'),
            'Collector coordinator must use an explicit Request override without creating a duplicate ID.',
        );
        self::assertSame(
            $logCollectorOverride,
            $coordinator->collector('log'),
            'Collector coordinator must use an explicit Log override without creating a duplicate ID.',
        );
        self::assertSame(
            $eventCollectorOverride,
            $coordinator->collector('event'),
            'Collector coordinator must use an explicit Event override without creating a duplicate ID.',
        );
        self::assertSame(
            $profilingCollectorOverride,
            $coordinator->collector('profiling'),
            'Collector coordinator must use an explicit Profiling override without creating a duplicate ID.',
        );

        $rendererFactory = $definitions[DebugPageRenderer::class] ?? null;

        self::assertInstanceOf(
            Closure::class,
            $rendererFactory,
            'Debug page renderer factory must be present.',
        );

        $renderer = $rendererFactory(
            new WebView(),
            $assetManager,
            new ConfigDataFactory(['name' => 'Test application']),
            $aliases,
            $builtInRequestPanel,
            $builtInLogPanel,
            $builtInEventPanel,
            $builtInProfilingPanel,
            $registry,
        );

        self::assertInstanceOf(
            DebugPageRenderer::class,
            $renderer,
            'Renderer factory must return its service.',
        );
        self::assertSame(
            [
                'request' => $requestPanelOverride,
                'log' => $logPanelOverride,
                'event' => $eventPanelOverride,
                'profiling' => $profilingPanelOverride,
                'inertia' => $panel,
            ],
            (new ReflectionProperty(DebugPageRenderer::class, 'extensionPanels'))->getValue($renderer),
            'Renderer factory must preserve built-in order before explicitly registered extension panels.',
        );
        self::assertTrue(
            $renderer->hasExtensionPanel('inertia'),
            'Debug page renderer must receive the explicitly registered panel.',
        );
        self::assertTrue(
            $renderer->hasExtensionPanel('request'),
            'Debug page renderer must receive the resolved Request panel without a duplicate ID.',
        );
        self::assertTrue(
            $renderer->hasExtensionPanel('log'),
            'Debug page renderer must receive the resolved Log panel without a duplicate ID.',
        );
        self::assertTrue(
            $renderer->hasExtensionPanel('event'),
            'Debug page renderer must receive the resolved Event panel without a duplicate ID.',
        );
        self::assertTrue(
            $renderer->hasExtensionPanel('profiling'),
            'Debug page renderer must receive the resolved Profiling panel without a duplicate ID.',
        );

        $toolbarFactory = $definitions[ToolbarDataFactory::class] ?? null;

        self::assertInstanceOf(
            Closure::class,
            $toolbarFactory,
            'Toolbar data factory definition must be present.',
        );

        $toolbarDataFactory = $toolbarFactory(
            $assetManager,
            $builtInRequestPanel,
            $builtInLogPanel,
            $builtInEventPanel,
            $builtInProfilingPanel,
            $registry,
        );

        self::assertInstanceOf(
            ToolbarDataFactory::class,
            $toolbarDataFactory,
            'Toolbar data factory definition must return its service.',
        );
        self::assertSame(
            [
                'request' => $requestPanelOverride,
                'log' => $logPanelOverride,
                'event' => $eventPanelOverride,
                'profiling' => $profilingPanelOverride,
                'inertia' => $panel,
            ],
            (new ReflectionProperty(ToolbarDataFactory::class, 'extensionPanels'))->getValue($toolbarDataFactory),
            'Toolbar factory must preserve built-in order before explicitly registered extension panels.',
        );

        $snapshot = new DebugSnapshot(
            RequestSummary::create('request-1'),
            [
                'inertia' => InertiaSnapshot::capture(
                    null,
                    ['component' => 'Site/Index', 'props' => [], 'url' => '/', 'version' => null],
                    [],
                    [],
                    200,
                )->jsonSerialize(),
            ],
            [],
        );

        $items = $toolbarDataFactory->createForSnapshot($snapshot)->jsonSerialize()['items'];

        self::assertCount(
            1,
            $items,
            'Toolbar data factory must receive the explicitly registered panel.',
        );
        self::assertSame(
            'inertia',
            $items[0]['id'],
            'Toolbar data must expose the registered panel ID.',
        );

        $middlewareFactory = $definitions[ToolbarMiddleware::class] ?? null;

        self::assertInstanceOf(
            Closure::class,
            $middlewareFactory,
            'Toolbar middleware factory definition must be present.',
        );

        $streamFactory = HelperFactory::createStreamFactory();

        $middleware = $middlewareFactory(
            new ToolbarRenderer(
                new WebView(),
                $assetManager,
                $aliases->get('@vendor/php-forge/debug-core/resources/views'),
            ),
            $streamFactory,
            new SnapshotStore(
                sys_get_temp_dir() . '/yii3-debug-extension-registry-store',
                0o700,
                0o600,
            ),
            $coordinator,
            $capturePolicy,
        );

        self::assertInstanceOf(
            ToolbarMiddleware::class,
            $middleware,
            'Toolbar middleware factory definition must return its service.',
        );
        self::assertSame(
            $capturePolicy,
            (new ReflectionProperty(ToolbarMiddleware::class, 'capturePolicy'))->getValue($middleware),
            'Toolbar middleware and collectors must receive the shared capture policy service.',
        );
    }

    public function testRegistrationIsExplicitOrderedAndImmutable(): void
    {
        $collector = new InertiaCollector();
        $panel = new InertiaPanel();
        $empty = new ExtensionRegistry();

        $withCollector = $empty->withCollector($collector);
        $complete = $withCollector->withPanel($panel);

        $fromKeyedIterables = new ExtensionRegistry(['collector' => $collector], ['panel' => $panel]);

        self::assertSame(
            [],
            $empty->collectors(),
            'Adding a collector must not mutate the original registry.',
        );
        self::assertSame(
            [],
            $empty->panels(),
            'Adding a panel must not mutate the original registry.',
        );
        self::assertSame(
            [$collector],
            $withCollector->collectors(),
            'The explicitly registered collector must retain its instance and order.',
        );
        self::assertSame(
            [],
            $withCollector->panels(),
            'Collector registration must not imply a matching panel.',
        );
        self::assertSame(
            [$collector],
            $complete->collectors(),
            'Panel registration must preserve existing collectors.',
        );
        self::assertSame(
            [$panel],
            $complete->panels(),
            'The explicitly registered panel must retain its instance and order.',
        );
        self::assertSame(
            [$collector],
            $fromKeyedIterables->collectors(),
            'Collector iterables must be normalized to an ordered list.',
        );
        self::assertSame(
            [$panel],
            $fromKeyedIterables->panels(),
            'Panel iterables must be normalized to an ordered list.',
        );
    }
}
