<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Helper\EmptyState;
use PHPForge\Debug\Panel\Asset\{AssetBundleNormalizer, AssetCardRenderer, AssetSnapshot};
use PHPForge\Debug\Toolbar\ToolbarItem;
use UIAwesome\Html\Flow\P;
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;

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
        $title = H1::tag()->class('yii-debug-sr-only')->content('Asset Bundles')->render();

        if ($summary->isEmpty()) {
            return $title
                . EmptyState::card(
                    'No asset bundles registered',
                    P::tag()->content('This request did not register bundles on the Yii3 asset manager.'),
                );
        }

        $header = Header::tag()
            ->class('yii-debug-grid-summary')
            ->html(
                Span::tag()->html(Strong::tag()->content((string) $summary->totalBundles), ' bundles'),
                Span::tag()->class('yii-debug-grid-summary-sep')->content('·'),
                Span::tag()->html(Strong::tag()->content((string) $summary->totalCss), ' css'),
                Span::tag()->class('yii-debug-grid-summary-sep')->content('·'),
                Span::tag()->html(Strong::tag()->content((string) $summary->totalJs), ' js'),
            );
        $cards = '';

        foreach ($summary->bundles as $bundle) {
            $cards .= AssetCardRenderer::renderCard($bundle, $summary)->render();
        }

        return $title . $header->render() . $cards;
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
