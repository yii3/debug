<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Extension;

use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Inertia\Debug\{InertiaCollector, InertiaPanel};
use PHPForge\Inertia\Event\ProtocolResultCreated;
use PHPForge\Vite\Debug\{ViteCollector, VitePanel};
use PHPForge\Vite\Event\AssetsResolved;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Extension\{PackagedProvider, ProviderCatalog};

use function array_keys;

/**
 * Unit tests for {@see ProviderCatalog} wiring every optional provider whose package is installed.
 */
final class ProviderCatalogTest extends TestCase
{
    public function testAbsentProviderIsAbsentFromEveryRegistration(): void
    {
        $catalog = new ProviderCatalog(self::inertia(), self::ghost());

        self::assertSame(
            ['inertia' => InertiaCollector::class],
            $catalog->collectors(),
            'Absent package must contribute no collector.',
        );
        self::assertSame(
            ['inertia' => InertiaPanel::class],
            $catalog->panels(),
            'Absent package must contribute no panel.',
        );
        self::assertSame(
            [InertiaCollector::class],
            array_keys($catalog->definitions()),
            'Absent package must contribute no definition.',
        );
        self::assertSame(
            [ProtocolResultCreated::class => [InertiaCollector::class]],
            $catalog->listeners(),
            'Absent package must contribute no listener.',
        );
    }

    public function testDefinitionsSkipAnInstalledProviderTheContainerAutowires(): void
    {
        $catalog = new ProviderCatalog(
            new PackagedProvider('vite', ViteCollector::class, VitePanel::class, AssetsResolved::class),
        );

        self::assertSame(
            [],
            $catalog->definitions(),
            'A `null` definition must stay out of the container.',
        );
    }

    public function testEmptyCatalogRegistersNothing(): void
    {
        $catalog = new ProviderCatalog();

        self::assertSame(
            [],
            $catalog->collectors(),
            'No provider means no collector.',
        );
        self::assertSame(
            [],
            $catalog->panels(),
            'No provider means no panel.',
        );
        self::assertSame(
            [],
            $catalog->definitions(),
            'No provider means no definition.',
        );
        self::assertSame(
            [],
            $catalog->listeners(),
            'No provider means no listener.',
        );
    }

    public function testPackagedBuildsTheInertiaCollectorFromTheHostCapturePolicy(): void
    {
        $definitions = ProviderCatalog::packaged()->definitions();

        self::assertArrayHasKey(
            InertiaCollector::class,
            $definitions,
            'Inertia provider must declare a definition.',
        );

        $collector = $definitions[InertiaCollector::class](new CapturePolicy());

        self::assertInstanceOf(
            InertiaCollector::class,
            $collector,
            'Definition must build the packaged collector.',
        );
    }

    public function testPackagedRegistersInertiaThenVite(): void
    {
        $catalog = ProviderCatalog::packaged();

        self::assertSame(
            ['inertia' => InertiaCollector::class, 'vite' => ViteCollector::class],
            $catalog->collectors(),
            'Order: Inertia first, Vite second.',
        );
        self::assertSame(
            ['inertia' => InertiaPanel::class, 'vite' => VitePanel::class],
            $catalog->panels(),
            'Order: Inertia first, Vite second.',
        );
        self::assertSame(
            [
                ProtocolResultCreated::class => [InertiaCollector::class],
                AssetsResolved::class => [ViteCollector::class],
            ],
            $catalog->listeners(),
            'Each provider must route its own event to its own collector.',
        );
        self::assertSame(
            [InertiaCollector::class],
            array_keys($catalog->definitions()),
            'Only the Inertia collector needs a definition.',
        );
    }

    /**
     * @return PackagedProvider Provider whose package is not installed.
     */
    private static function ghost(): PackagedProvider
    {
        return new PackagedProvider(
            'ghost',
            'Acme\\Ghost\\Debug\\GhostCollector',
            'Acme\\Ghost\\Debug\\GhostPanel',
            'Acme\\Ghost\\Event\\GhostResolved',
            static fn(CapturePolicy $capturePolicy): InertiaCollector => new InertiaCollector(
                $capturePolicy->redact(...),
            ),
        );
    }

    /**
     * @return PackagedProvider Packaged Inertia provider, whose package the development dependencies install.
     */
    private static function inertia(): PackagedProvider
    {
        return new PackagedProvider(
            'inertia',
            InertiaCollector::class,
            InertiaPanel::class,
            ProtocolResultCreated::class,
            static fn(CapturePolicy $capturePolicy): InertiaCollector => new InertiaCollector(
                $capturePolicy->redact(...),
                $capturePolicy->redactUrl(...),
            ),
        );
    }
}
