<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Panel\Asset\AssetPanel as PortableAssetPanel;

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
}
