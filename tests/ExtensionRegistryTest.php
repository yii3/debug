<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use InvalidArgumentException;
use PHPForge\Debug\Helper\Trace;
use PHPForge\Debug\Registration\PanelOverride;
use PHPForge\Inertia\Debug\InertiaPanel;
use PHPForge\Vite\Debug\VitePanel;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Collector\{DbCollector, EventCollector, LogCollector, ProfilingCollector, RequestCollector};
use Yii3\Debug\Db\DbExplain;
use Yii3\Debug\ExtensionRegistry;
use Yii3\Debug\Log\DebugLogTarget;
use Yii3\Debug\Panel\{
    DbPanel,
    EventPanel,
    ExtensionPanelInterface,
    LogPanel,
    ProfilingPanel,
    ProviderPanel,
    RequestPanel,
};
use Yii3\Debug\Tests\Support\Stubs\Cache\{CacheCollector, CachePanel};
use Yii3\Debug\Tests\Support\Stubs\{ContainerStub, ExtensionCollectorStub};
use Yii3\Debug\Web\DebugUrlGenerator;

/**
 * Unit tests for explicit debug-extension registration through {@see ExtensionRegistry}.
 */
final class ExtensionRegistryTest extends TestCase
{
    public function testAppendedRegistrationsKeepPreviouslyRegisteredEntries(): void
    {
        $firstCollector = new ExtensionCollectorStub();
        $secondCollector = new DbCollector();
        $firstPanel = new ProviderPanel(new InertiaPanel());
        $secondPanel = new ProviderPanel(new VitePanel());

        $registry = ExtensionRegistry::create(collectors: [$firstCollector], panels: [$firstPanel])
            ->withCollector($secondCollector)
            ->withPanel($secondPanel);

        self::assertSame(
            [$firstCollector, $secondCollector],
            $registry->collectors(),
            'Order: already registered collector first, appended one last.',
        );
        self::assertSame(
            [$firstPanel, $secondPanel],
            $registry->panels(),
            'Order: already registered panel first, appended one last.',
        );
    }

    public function testBuiltInCollectorDisabledByConfigurationIsAbsentFromComposition(): void
    {
        $builtIn = new RequestCollector();
        $laterBuiltIn = new DbCollector();

        $registry = ExtensionRegistry::fromParams(
            ['request' => ['class' => RequestCollector::class, 'enabled' => false]],
            [],
            new ContainerStub(),
        );

        self::assertSame(
            ['request'],
            $registry->disabled(),
            'Disabled ID must be reported.',
        );
        self::assertSame(
            [],
            $registry->collectorsWithBuiltIn($builtIn),
            'Disabled built-in must not come back.',
        );
        self::assertSame(
            [$laterBuiltIn],
            $registry->collectorsWithBuiltIns([$builtIn, $laterBuiltIn]),
            'Only the disabled built-in must be dropped.',
        );
    }

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
                $logPanelOverride,
                $eventPanelOverride,
                $profilingPanelOverride,
                $panel,
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
                $requestPanelOverride,
                $logPanelOverride,
                $eventPanelOverride,
                $profilingPanelOverride,
                $panel,
            ],
            $registry->panels(),
            'Composition must not mutate the panel registry.',
        );
    }

    public function testBuiltInPanelDisabledByConfigurationIsAbsentFromComposition(): void
    {
        $builtIn = new RequestPanel();
        $laterBuiltIn = new EventPanel();

        $registry = ExtensionRegistry::fromParams(
            [],
            ['request' => ['class' => RequestPanel::class, 'enabled' => false]],
            new ContainerStub(),
        );

        self::assertSame(
            ['request'],
            $registry->disabled(),
            'Disabled ID must be reported.',
        );
        self::assertSame(
            [],
            $registry->panelsWithBuiltIn($builtIn),
            'Disabled built-in must not come back.',
        );
        self::assertSame(
            [$laterBuiltIn],
            $registry->panelsWithBuiltIns([$builtIn, $laterBuiltIn]),
            'Only the disabled built-in must be dropped.',
        );
    }

    public function testConfiguredOverrideRenamesPortablePanelAndLeadsByPosition(): void
    {
        $registry = ExtensionRegistry::create(
            panels: ['inertia' => new InertiaPanel(), 'vite' => new VitePanel()],
            overrides: [
                'vite' => PanelOverride::fromArray(['title' => 'Vite assets', 'icon' => 'asset', 'position' => 1]),
            ],
        );

        $panels = $registry->panels();

        self::assertCount(
            2,
            $panels,
            'Both registrations must survive an override.',
        );
        self::assertSame(
            'vite',
            $panels[0]->id(),
            'Order: positioned entry first.',
        );
        self::assertSame(
            'Vite assets',
            $panels[0]->name(),
            'Title override must beat the provider default.',
        );
        self::assertSame(
            'asset',
            $panels[0]->icon(),
            'Icon override must beat the provider default.',
        );
        self::assertSame(
            'inertia',
            $panels[1]->id(),
            'Entry without a position must follow.',
        );
        self::assertSame(
            'Inertia',
            $panels[1]->name(),
            'Provider default must survive an unrelated override.',
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
            [$profilingPanel, $inertiaPanel],
            $registry->panels(),
            'Order: built-in IDs first, extensions last.',
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

    public function testDisabledIdMissingFromTheBuiltInsLeavesCompositionUntouched(): void
    {
        $builtInCollector = new RequestCollector();
        $builtInPanel = new RequestPanel();

        $registry = ExtensionRegistry::fromParams(
            ['cache' => ['class' => CacheCollector::class, 'enabled' => false]],
            ['cache' => ['class' => CachePanel::class, 'enabled' => false]],
            new ContainerStub(),
        );

        self::assertSame(
            ['cache'],
            $registry->disabled(),
            'Disabled ID must be reported.',
        );
        self::assertSame(
            [$builtInCollector],
            $registry->collectorsWithBuiltIns([$builtInCollector]),
            'Unmatched disabled ID must keep the built-in collector.',
        );
        self::assertSame(
            [$builtInPanel],
            $registry->panelsWithBuiltIns([$builtInPanel]),
            'Unmatched disabled ID must keep the built-in panel.',
        );
    }

    public function testDisabledIdsAreReportedSortedWithoutDuplicates(): void
    {
        $registry = ExtensionRegistry::create(
            panels: ['inertia' => new InertiaPanel()],
            overrides: ['inertia' => PanelOverride::fromArray(['enabled' => false])],
            disabled: ['vite', 'ghost', 'vite'],
        );

        self::assertSame(
            [],
            $registry->panels(),
            'A disabled entry must not stay listed.',
        );
        self::assertSame(
            ['ghost', 'inertia', 'vite'],
            $registry->disabled(),
            'Disabled IDs must be unique and sorted.',
        );
    }

    public function testFromParamsAppliesArrayEntryOptions(): void
    {
        $collector = new CacheCollector();
        $panel = new CachePanel();

        $registry = ExtensionRegistry::fromParams(
            ['cache' => ['class' => CacheCollector::class, 'enabled' => true]],
            ['cache' => ['class' => CachePanel::class, 'title' => 'Cache operations', 'icon' => 'asset']],
            new ContainerStub([CacheCollector::class => $collector, CachePanel::class => $panel]),
        );

        $panels = $registry->panels();

        self::assertSame(
            [$collector],
            $registry->collectors(),
            'An `enabled` entry must stay registered.',
        );
        self::assertCount(
            1,
            $panels,
            'One configured entry must produce one panel.',
        );
        self::assertSame(
            'cache',
            $panels[0]->id(),
            'Stable ID must come from the configuration key.',
        );
        self::assertSame(
            'Cache operations',
            $panels[0]->name(),
            'Title option must beat the provider default.',
        );
        self::assertSame(
            'asset',
            $panels[0]->icon(),
            'Icon option must beat the provider default.',
        );
        self::assertSame(
            [],
            $registry->disabled(),
            'No ID may be reported disabled.',
        );
    }

    public function testFromParamsRecordsDisabledEntriesWithoutResolvingTheirClass(): void
    {
        $collector = new CacheCollector();
        $panel = new CachePanel();

        $registry = ExtensionRegistry::fromParams(
            [
                'ghost' => ['class' => 'Acme\\Missing\\GhostCollector', 'enabled' => false],
                'cache' => CacheCollector::class,
            ],
            [
                'phantom' => ['class' => 'Acme\\Missing\\PhantomPanel', 'enabled' => false],
                'cache' => CachePanel::class,
            ],
            new ContainerStub([CacheCollector::class => $collector, CachePanel::class => $panel]),
        );

        self::assertSame(
            [$collector],
            $registry->collectors(),
            'Entries after a disabled one must still register.',
        );
        self::assertCount(
            1,
            $registry->panels(),
            'Entries after a disabled one must still register.',
        );
        self::assertSame(
            ['ghost', 'phantom'],
            $registry->disabled(),
            'Disabled IDs must be unique and sorted.',
        );
    }

    public function testFromParamsReportsDisabledIdsAsStrings(): void
    {
        $registry = ExtensionRegistry::fromParams(
            [0 => ['class' => 'Acme\\Missing\\GhostCollector', 'enabled' => false]],
            [1 => ['class' => 'Acme\\Missing\\PhantomPanel', 'enabled' => false]],
            new ContainerStub(),
        );

        self::assertSame(
            ['0', '1'],
            $registry->disabled(),
            'Numeric keys must be reported as `string` IDs.',
        );
    }

    public function testFromParamsResolvesClassStringEntriesThroughTheContainer(): void
    {
        $collector = new CacheCollector();
        $panel = new CachePanel();

        $registry = ExtensionRegistry::fromParams(
            ['cache' => CacheCollector::class],
            ['cache' => CachePanel::class],
            new ContainerStub([CacheCollector::class => $collector, CachePanel::class => $panel]),
        );

        $panels = $registry->panels();

        self::assertSame(
            [$collector],
            $registry->collectors(),
            'Container instance must be registered as is.',
        );
        self::assertCount(
            1,
            $panels,
            'One configured entry must produce one panel.',
        );
        self::assertSame(
            'cache',
            $panels[0]->id(),
            'Stable ID must come from the configuration key.',
        );
        self::assertSame(
            'Cache',
            $panels[0]->name(),
            'Provider default title must survive a bare class string.',
        );
        self::assertSame(
            [],
            $registry->disabled(),
            'No ID may be reported disabled.',
        );
    }

    public function testRegisteredEntryWinsOverADisabledBuiltInOfTheSameId(): void
    {
        $builtIn = new ProviderPanel(new CachePanel());

        $registry = ExtensionRegistry::fromParams(
            ['cache' => ['class' => CacheCollector::class, 'enabled' => false]],
            ['cache' => CachePanel::class],
            new ContainerStub([CachePanel::class => new CachePanel()]),
        );

        $panels = $registry->panels();

        self::assertSame(
            ['cache'],
            $registry->disabled(),
            'Collector entry must report its ID disabled.',
        );
        self::assertCount(
            1,
            $panels,
            'An enabled panel entry must survive a disabled collector of the same ID.',
        );
        self::assertSame(
            $panels,
            $registry->panelsWithBuiltIns([$builtIn]),
            'Registered panel must win over the disabled built-in.',
        );
    }

    public function testRegistrationIsExplicitOrderedAndImmutable(): void
    {
        $collector = new ExtensionCollectorStub();
        $panel = new ProviderPanel(new InertiaPanel());
        $empty = new ExtensionRegistry();

        $withCollector = $empty->withCollector($collector);
        $complete = $withCollector->withPanel($panel);

        $fromKeyedIterables = new ExtensionRegistry(['extension' => $collector], ['inertia' => $panel]);

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

    public function testThrowInvalidArgumentExceptionForArrayEntryWithoutAClassKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Debug registration "cache" must be a class string or an array declaring a "class" string.',
        );

        ExtensionRegistry::fromParams(['cache' => ['enabled' => true]], [], new ContainerStub());
    }

    public function testThrowInvalidArgumentExceptionForCollectorKeyNotMatchingItsId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Debug collector registered as "wrong" must match its ID "extension".');

        ExtensionRegistry::create(collectors: ['wrong' => new ExtensionCollectorStub()]);
    }

    public function testThrowInvalidArgumentExceptionForEntryThatIsNeitherStringNorArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Debug registration "cache" must be a class string or an array declaring a "class" string.',
        );

        ExtensionRegistry::fromParams(['cache' => 42], [], new ContainerStub());
    }

    public function testThrowInvalidArgumentExceptionForNonBooleanCollectorEnabled(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Debug collector option "enabled" for "cache" must be a boolean.',
        );

        ExtensionRegistry::fromParams(
            ['cache' => ['class' => CacheCollector::class, 'enabled' => 'yes']],
            [],
            new ContainerStub(),
        );
    }

    public function testThrowInvalidArgumentExceptionForPanelKeyNotMatchingItsId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Debug panel registered as "wrong" must match its ID "vite".',
        );

        ExtensionRegistry::create(panels: ['wrong' => new VitePanel()]);
    }

    public function testThrowInvalidArgumentExceptionForUnknownCollectorOption(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Unknown debug collector option "colour" for "cache". Available option: enabled.',
        );

        ExtensionRegistry::fromParams(
            ['cache' => ['class' => CacheCollector::class, 'colour' => 'red']],
            [],
            new ContainerStub(),
        );
    }

    public function testThrowInvalidArgumentExceptionWhenRenamingPanelRenderingItsOwnPresentation(): void
    {
        $panel = self::createStub(ExtensionPanelInterface::class);

        $panel
            ->method('icon')
            ->willReturn('db');
        $panel
            ->method('id')
            ->willReturn('acme');
        $panel
            ->method('name')
            ->willReturn('Acme');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Panel acme renders its own title and icon',
        );

        ExtensionRegistry::create(
            panels: ['acme' => $panel],
            overrides: ['acme' => PanelOverride::fromArray(['title' => 'Renamed'])],
        );
    }
}
