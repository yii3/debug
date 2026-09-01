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
use Yii3\Debug\Collector\{InertiaCollector, RequestCollector};
use Yii3\Debug\{ConfigDataFactory, ExtensionRegistry};
use Yii3\Debug\Middleware\ToolbarMiddleware;
use Yii3\Debug\Panel\{InertiaPanel, RequestPanel};
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

        $coordinator = $coordinatorFactory($requestCollector, $registry);

        self::assertInstanceOf(
            CollectorCoordinator::class,
            $coordinator,
            'Collector coordinator factory must return its service.',
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
        $panel = new InertiaPanel();
        $builtInRequestPanel = new RequestPanel();
        $requestPanelOverride = new RequestPanel();
        $registry = new ExtensionRegistry(
            [$collector, $requestCollectorOverride],
            [$panel, $requestPanelOverride],
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
            [$requestCollectorOverride, $collector],
            $registry->collectorsWithBuiltIn($builtInRequestCollector),
            'An explicit Request collector override must replace the built-in instance and remain first.',
        );
        self::assertSame(
            [$requestPanelOverride, $panel],
            $registry->panelsWithBuiltIn($builtInRequestPanel),
            'An explicit Request panel override must replace the built-in instance and remain first.',
        );
        self::assertSame(
            [$collector, $requestCollectorOverride],
            $registry->collectors(),
            'Built-in composition must not alter the explicit collector registry.',
        );
        self::assertSame(
            [$panel, $requestPanelOverride],
            $registry->panels(),
            'Built-in composition must not alter the explicit panel registry.',
        );

        $coordinator = $coordinatorFactory($builtInRequestCollector, $registry);

        self::assertInstanceOf(
            CollectorCoordinator::class,
            $coordinator,
            'Collector factory must return its service.',
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
            $registry,
        );

        self::assertInstanceOf(
            DebugPageRenderer::class,
            $renderer,
            'Renderer factory must return its service.',
        );
        self::assertTrue(
            $renderer->hasExtensionPanel('inertia'),
            'Debug page renderer must receive the explicitly registered panel.',
        );
        self::assertTrue(
            $renderer->hasExtensionPanel('request'),
            'Debug page renderer must receive the resolved Request panel without a duplicate ID.',
        );

        $toolbarFactory = $definitions[ToolbarDataFactory::class] ?? null;

        self::assertInstanceOf(
            Closure::class,
            $toolbarFactory,
            'Toolbar data factory definition must be present.',
        );

        $toolbarDataFactory = $toolbarFactory($assetManager, $builtInRequestPanel, $registry);

        self::assertInstanceOf(
            ToolbarDataFactory::class,
            $toolbarDataFactory,
            'Toolbar data factory definition must return its service.',
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
