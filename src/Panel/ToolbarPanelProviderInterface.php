<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Toolbar\ToolbarItem;

/**
 * Defines an extension panel that can contribute captured metrics to the debug toolbar.
 */
interface ToolbarPanelProviderInterface extends ExtensionPanelInterface
{
    /**
     * Returns the toolbar metrics for a captured payload.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return list<ToolbarItem> Toolbar metrics, or an empty list when the panel should stay hidden.
     */
    public function toolbarItems(array $payload): array;
}
