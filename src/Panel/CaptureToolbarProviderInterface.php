<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Toolbar\ToolbarItem;

/**
 * Defines a toolbar panel whose metrics depend on which capture the toolbar describes, not only on its payload.
 *
 * {@see \Yii3\Debug\ToolbarDataFactory} calls {@see toolbarItemsForCapture()} instead of
 * {@see ToolbarPanelProviderInterface::toolbarItems()}, so a panel can look the capture up in the history, for
 * example to point at the request that preceded a redirect.
 */
interface CaptureToolbarProviderInterface extends ToolbarPanelProviderInterface
{
    /**
     * Returns the toolbar metrics for a captured payload of a known capture.
     *
     * @param string $tag Tag of the capture the toolbar describes.
     * @param array<string, mixed> $payload Serialized panel payload of that capture.
     *
     * @return list<ToolbarItem> Toolbar metrics, or an empty list when the panel should stay hidden.
     */
    public function toolbarItemsForCapture(string $tag, array $payload): array;
}
