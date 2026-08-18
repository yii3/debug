<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\Asset\{AssetBundleRow, AssetSnapshot};
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\AssetPanel;

/**
 * Unit tests for {@see AssetPanel} presenting the shared Asset Bundles payload and its count chip.
 */
final class AssetPanelTest extends TestCase
{
    public function testMetadataIdentifiesTheAssetBundlesPanel(): void
    {
        $panel = new AssetPanel();

        self::assertSame('asset', $panel->id(), 'Stable ID must pair with the asset collector.');
        self::assertSame('asset', $panel->icon(), 'Icon must use the shared asset glyph.');
        self::assertSame('Asset Bundles', $panel->name(), 'Label must match the Yii2 panel.');
    }

    public function testRenderShowsEmptyStateWithoutBundles(): void
    {
        $html = (new AssetPanel())->render(['bundles' => [], 'vite' => null]);

        self::assertStringContainsString('No asset bundles registered', $html, 'Empty state must be shown.');
    }

    public function testRenderShowsSummaryAndBundleCards(): void
    {
        $html = (new AssetPanel())->render($this->snapshot()->jsonSerialize());

        self::assertStringContainsString('MainAsset', $html, 'Bundle card must name the bundle.');
        self::assertStringContainsString('css/site.css', $html, 'Bundle card must list CSS files.');
    }

    public function testToolbarItemsExposeBundleCountChip(): void
    {
        $items = (new AssetPanel())->toolbarItems($this->snapshot()->jsonSerialize());

        self::assertCount(1, $items, 'Exactly one count chip must be emitted.');
        self::assertSame('1', $items[0]->value, 'Chip value must expose the bundle count.');
        self::assertSame('info', $items[0]->status, 'Chip must use the info status.');
    }

    public function testToolbarItemsStayEmptyWithoutBundles(): void
    {
        $items = (new AssetPanel())->toolbarItems(['bundles' => [], 'vite' => null]);

        self::assertSame([], $items, 'Zero bundles must omit the chip.');
    }

    /**
     * Creates a representative asset snapshot.
     *
     * @return AssetSnapshot Representative snapshot.
     */
    private function snapshot(): AssetSnapshot
    {
        return new AssetSnapshot(
            [
                new AssetBundleRow(
                    name: 'App\Asset\MainAsset',
                    sourcePath: '@resources/assets',
                    basePath: '@public/assets',
                    baseUrl: '/assets',
                    css: ['css/site.css'],
                    js: ['js/site.js'],
                    depends: [],
                ),
            ],
            null,
        );
    }
}
