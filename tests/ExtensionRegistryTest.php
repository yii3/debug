<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use PHPForge\Debug\Helper\Trace;
use PHPForge\Inertia\Debug\InertiaPanel;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Collector\{DbCollector, EventCollector, LogCollector, ProfilingCollector, RequestCollector};
use Yii3\Debug\Db\DbExplain;
use Yii3\Debug\ExtensionRegistry;
use Yii3\Debug\Log\DebugLogTarget;
use Yii3\Debug\Panel\{DbPanel, EventPanel, LogPanel, ProfilingPanel, ProviderPanel, RequestPanel};
use Yii3\Debug\Tests\Support\Stubs\ExtensionCollectorStub;
use Yii3\Debug\Web\DebugUrlGenerator;

/**
 * Unit tests for explicit debug-extension registration through {@see ExtensionRegistry}.
 */
final class ExtensionRegistryTest extends TestCase
{
    public function testBuiltInCompositionReplacesOverriddenIdsAndKeepsTheRegistryIntact(): void
    {
        $collector = new ExtensionCollectorStub();
        $builtInRequestCollector = new RequestCollector();
        $requestCollectorOverride = new RequestCollector();
        $builtInLogCollector = new LogCollector(new DebugLogTarget());
        $logCollectorOverride = new LogCollector(new DebugLogTarget());
        $builtInEventCollector = new EventCollector();
        $eventCollectorOverride = new EventCollector();
        $builtInProfilingCollector = new ProfilingCollector();
        $profilingCollectorOverride = new ProfilingCollector();
        $panel = new ProviderPanel(new InertiaPanel());
        $builtInRequestPanel = new RequestPanel();
        $requestPanelOverride = new RequestPanel();
        $builtInLogPanel = new LogPanel(Trace::create());
        $logPanelOverride = new LogPanel(Trace::create());
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

        self::assertSame(
            [
                $requestCollectorOverride,
                $collector,
                $logCollectorOverride,
                $eventCollectorOverride,
                $profilingCollectorOverride,
            ],
            $registry->collectorsWithBuiltIn($builtInRequestCollector),
            'Collector override must replace the built-in and stay first.',
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
            'Panel override must replace the built-in and stay first.',
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
            'Order: built-ins first, extras last.',
        );
        self::assertSame(
            [$requestPanelOverride, $logPanelOverride, $eventPanelOverride, $profilingPanelOverride, $panel],
            $registry->panelsWithBuiltIns(
                [$builtInRequestPanel, $builtInLogPanel, $builtInEventPanel, $builtInProfilingPanel],
            ),
            'Order: built-ins first, extras last.',
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
            'Composition must not mutate the collector registry.',
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
            'Composition must not mutate the panel registry.',
        );
    }

    public function testCreateNormalizesIterablesAndPreservesOrder(): void
    {
        $dbCollector = new DbCollector();
        $profilingCollector = new ProfilingCollector();
        $inertiaPanel = new ProviderPanel(new InertiaPanel());
        $profilingPanel = new ProfilingPanel();

        $registry = ExtensionRegistry::create(
            collectors: (
                static function () use ($dbCollector, $profilingCollector): iterable {
                    yield 'db' => $dbCollector;
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
            [$dbCollector, $profilingCollector],
            $registry->collectors(),
            'Collector iterables must keep their order.',
        );
        self::assertSame(
            [$inertiaPanel, $profilingPanel],
            $registry->panels(),
            'Panel iterables must keep their order.',
        );
    }

    public function testDefaultRegistryIsEmptyAndComposesBuiltInsAlone(): void
    {
        $registry = new ExtensionRegistry();
        $requestCollector = new RequestCollector();
        $dbCollector = new DbCollector();
        $requestPanel = new RequestPanel();
        $dbPanel = new DbPanel(new DbExplain(), new DebugUrlGenerator(), Trace::create());

        self::assertSame(
            [],
            $registry->collectors(),
            'No collector may be enabled implicitly.',
        );
        self::assertSame(
            [],
            $registry->panels(),
            'No panel may be enabled implicitly.',
        );
        self::assertSame(
            [$requestCollector, $dbCollector],
            $registry->collectorsWithBuiltIns([$requestCollector, $dbCollector]),
            'Built-in collectors must pass through unchanged.',
        );
        self::assertSame(
            [$requestPanel, $dbPanel],
            $registry->panelsWithBuiltIns([$requestPanel, $dbPanel]),
            'Built-in panels must pass through unchanged.',
        );
    }

    public function testRegistrationIsExplicitOrderedAndImmutable(): void
    {
        $collector = new ExtensionCollectorStub();
        $panel = new ProviderPanel(new InertiaPanel());
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
            'The registered collector must retain its instance and order.',
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
            'The registered panel must retain its instance and order.',
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
