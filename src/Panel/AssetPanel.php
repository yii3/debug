<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Panel\Asset\{AssetPanel as PortableAssetPanel, AssetSnapshot};
use PHPForge\Debug\Storage\HydrationException;

/**
 * Adapts the framework-neutral PHPForge Asset Bundles panel to the Yii3 debugger.
 */
final class AssetPanel extends ProviderPanel
{
    /**
     * Binds the adapter to the shared Asset Bundles presentation.
     */
    public function __construct()
    {
        parent::__construct(new PortableAssetPanel());
    }

    /**
     * Reports whether the capture registered an asset bundle or a Vite build, the two things the panel describes.
     *
     * A capture holding neither has nothing to show, so the navigation entry and the toolbar chip stay hidden instead
     * of announcing a count of `0`. A malformed capture still throws, keeping the failure visible to the host.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @throws HydrationException when the payload does not match the snapshot schema.
     *
     * @return bool `true` when a bundle or the Vite section was captured; `false` otherwise.
     */
    public function hasContent(array $payload): bool
    {
        $snapshot = AssetSnapshot::fromArray($payload, '$.asset');

        return $snapshot->bundles() !== [] || $snapshot->vite() !== null;
    }
}
