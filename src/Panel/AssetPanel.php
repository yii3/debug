<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Helper\EmptyState;
use PHPForge\Debug\Panel\Asset\{AssetBundleNormalizer, AssetSectionRenderer, AssetSnapshot};
use PHPForge\Debug\Toolbar\ToolbarItem;
use UIAwesome\Html\Flow\P;

use function count;
use function is_array;

/**
 * Presents the shared Asset Bundles panel payload and contributes the bundle-count toolbar chip.
 */
final readonly class AssetPanel implements PanelInterface
{
    use PanelContentTrait;

    public function icon(): string
    {
        return 'asset';
    }

    public function id(): string
    {
        return 'asset';
    }

    public function name(): string
    {
        return 'Asset Bundles';
    }

    public function render(array $payload): string
    {
        $snapshot = AssetSnapshot::fromArray($payload, 'panels.asset');
        $summary = (new AssetBundleNormalizer())->normalize($snapshot->bundles());
        $header = AssetSectionRenderer::renderHeader($summary);

        if ($summary->isEmpty()) {
            return $header
                . EmptyState::card(
                    'No asset bundles registered',
                    P::tag()->content('This request did not register bundles on the Yii3 asset manager.'),
                );
        }

        return $header . AssetSectionRenderer::renderInventory($summary);
    }

    public function toolbarItems(array $payload): array
    {
        $bundles = $payload['bundles'] ?? null;

        if (!is_array($bundles) || $bundles === []) {
            return [];
        }

        return [
            new ToolbarItem(
                value: (string) count($bundles),
                status: 'info',
                title: 'Number of asset bundles loaded',
            ),
        ];
    }
}
