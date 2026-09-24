<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\Asset\{AssetBundleRow, AssetSnapshot, ViteManifest};
use PHPForge\Debug\Storage\HydrationException;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\AssetPanel;

/**
 * Unit tests for {@see AssetPanel} adapting the PHPForge Asset Bundles panel to the Yii3 debugger.
 */
final class AssetPanelTest extends TestCase
{
    public function testHasContentRejectsCaptureWithoutBundlesAndWithoutVite(): void
    {
        self::assertFalse(
            (new AssetPanel())->hasContent((new AssetSnapshot([], null))->jsonSerialize()),
            'A capture describing nothing must stay hidden.',
        );
    }

    public function testHasContentReportsCapturedBundle(): void
    {
        $payload = (new AssetSnapshot(
            [new AssetBundleRow('App\\Asset\\AppAsset', '@app/assets', '/public/assets', '/assets', [], [], [])],
            null,
        ))->jsonSerialize();

        self::assertTrue(
            (new AssetPanel())->hasContent($payload),
            'One bundle must be enough to list the panel.',
        );
    }

    public function testHasContentReportsCapturedViteSection(): void
    {
        $payload = (new AssetSnapshot(
            [],
            new ViteManifest('/build', true, 'http://127.0.0.1:5173', '', []),
        ))->jsonSerialize();

        self::assertTrue(
            (new AssetPanel())->hasContent($payload),
            'A Vite section must be enough to list the panel.',
        );
    }

    public function testHasContentReportsMalformedNonEmptyCapture(): void
    {
        self::assertTrue(
            (new AssetPanel())->hasContent(['bundles' => 'broken', 'vite' => null]),
            'A malformed non-empty capture must stay listed.',
        );
    }

    public function testPanelIdentityMatchesAssetCollector(): void
    {
        $panel = new AssetPanel();

        self::assertSame(
            'asset',
            $panel->id(),
            'Panel ID must match the collector ID.',
        );
        self::assertSame(
            'Asset Bundles',
            $panel->name(),
            'Panel title must match the PHPForge portable panel.',
        );
        self::assertSame(
            'asset',
            $panel->icon(),
            'Icon must match the portable panel declaration.',
        );
    }

    public function testThrowHydrationExceptionWhenToolbarItemsReceiveMalformedPayload(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '\$.asset.bundles': expected a list.",
        );

        (new AssetPanel())->toolbarItems(['bundles' => 'broken', 'vite' => null]);
    }
}
