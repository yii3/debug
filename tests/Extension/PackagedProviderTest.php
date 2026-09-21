<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Extension;

use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Inertia\Debug\{InertiaCollector, InertiaPanel};
use PHPForge\Inertia\Event\ProtocolResultCreated;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Extension\PackagedProvider;

/**
 * Unit tests for {@see PackagedProvider} describing one optional provider package.
 */
final class PackagedProviderTest extends TestCase
{
    public function testDefinitionDefaultsToNull(): void
    {
        $provider = new PackagedProvider(
            'inertia',
            InertiaCollector::class,
            InertiaPanel::class,
            ProtocolResultCreated::class,
        );

        self::assertNull(
            $provider->definition,
            'An autowired collector needs no definition.',
        );
    }

    public function testInstalledReportsFalseForAnAbsentCollectorClass(): void
    {
        $provider = new PackagedProvider(
            'ghost',
            'Acme\\Ghost\\Debug\\GhostCollector',
            'Acme\\Ghost\\Debug\\GhostPanel',
            'Acme\\Ghost\\Event\\GhostResolved',
        );

        self::assertFalse(
            $provider->installed(),
            'An unloadable collector means an absent package.',
        );
    }

    public function testInstalledReportsTrueForALoadableCollectorClass(): void
    {
        $provider = new PackagedProvider(
            'inertia',
            InertiaCollector::class,
            InertiaPanel::class,
            ProtocolResultCreated::class,
        );

        self::assertTrue(
            $provider->installed(),
            'A loadable collector means an installed package.',
        );
    }

    public function testPropertiesExposeTheDeclaredRegistration(): void
    {
        $definition = static fn(CapturePolicy $capturePolicy): InertiaCollector => new InertiaCollector(
            $capturePolicy->redact(...),
        );

        $provider = new PackagedProvider(
            'inertia',
            InertiaCollector::class,
            InertiaPanel::class,
            ProtocolResultCreated::class,
            $definition,
        );

        self::assertSame(
            'inertia',
            $provider->id,
            'Stable ID must round-trip.',
        );
        self::assertSame(
            InertiaCollector::class,
            $provider->collector,
            'Collector class must round-trip.',
        );
        self::assertSame(
            InertiaPanel::class,
            $provider->panel,
            'Panel class must round-trip.',
        );
        self::assertSame(
            ProtocolResultCreated::class,
            $provider->event,
            'Event class must round-trip.',
        );
        self::assertSame(
            $definition,
            $provider->definition,
            'Definition must round-trip.',
        );
    }
}
