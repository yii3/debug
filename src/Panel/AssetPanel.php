<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

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
        parent::__construct(new \PHPForge\Debug\Panel\Asset\AssetPanel());
    }

    /**
     * Reports whether the capture registered an asset bundle or a Vite build, the two things the panel describes.
     *
     * A capture holding neither has nothing to show, so the navigation entry and the toolbar chip stay hidden instead
     * of announcing a count of `0`. The answer comes from the raw payload, so a malformed capture carrying a non-empty
     * `bundles` or `vite` value stays listed and the detail page and the toolbar chip expose the hydration failure
     * instead of hiding it.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return bool `true` when a bundle or the Vite section was captured; `false` otherwise.
     */
    public function hasContent(array $payload): bool
    {
        return ($payload['bundles'] ?? []) !== [] || ($payload['vite'] ?? null) !== null;
    }
}
