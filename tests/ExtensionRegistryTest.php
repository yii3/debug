<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use Closure;
use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\Panel\Inertia\InertiaSnapshot;
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary};
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Collector\InertiaCollector;
use Yii3\Debug\{ConfigDataFactory, ExtensionRegistry};
use Yii3\Debug\Panel\InertiaPanel;
use Yii3\Debug\ToolbarDataFactory;
use Yii3\Debug\Web\DebugPageRenderer;
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
        $coordinator = $coordinatorFactory($registry);
        self::assertInstanceOf(
            CollectorCoordinator::class,
            $coordinator,
            'Collector coordinator factory must return its service.',
        );
        self::assertFalse(
            $coordinator->hasCollector('inertia'),
            'Default DI must not infer an Inertia collector from installed classes or interfaces.',
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

        $collector = new InertiaCollector();
        $panel = new InertiaPanel();
        $registry = new ExtensionRegistry([$collector], [$panel]);
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-extension-registry-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__) . '/vendor',
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

        $coordinator = $coordinatorFactory($registry);

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

        $toolbarFactory = $definitions[ToolbarDataFactory::class] ?? null;

        self::assertInstanceOf(
            Closure::class,
            $toolbarFactory,
            'Toolbar data factory definition must be present.',
        );

        $toolbarDataFactory = $toolbarFactory($assetManager, $registry);

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
