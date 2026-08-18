<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Toolbar\ToolbarItem;

/**
 * Defines Yii3 presentation metadata, toolbar contribution, and rendering for a stored collector payload.
 */
interface PanelInterface
{
    /**
     * Returns whether the stored payload carries content worth surfacing in the sidebar navigation.
     *
     * Usage example:
     *
     * ```php
     * $visible = $panel->hasContent($payload);
     * ```
     *
     * @param array<string, mixed> $payload Decoded collector payload.
     *
     * @return bool `true` when the panel view has content for this payload.
     */
    public function hasContent(array $payload): bool;

    /**
     * Returns the shared icon name or `null` to use the JSON fallback icon.
     *
     * Usage example:
     *
     * ```php
     * $icon = $panel->icon();
     * ```
     *
     * @return string|null Shared icon name.
     */
    public function icon(): string|null;
    /**
     * Returns the stable collector ID presented by this panel.
     *
     * Usage example:
     *
     * ```php
     * $id = $panel->id();
     * ```
     *
     * @return string Stable panel ID.
     */
    public function id(): string;

    /**
     * Returns the navigation label for this panel.
     *
     * Usage example:
     *
     * ```php
     * $name = $panel->name();
     * ```
     *
     * @return string Panel label.
     */
    public function name(): string;

    /**
     * Renders a decoded collector payload as trusted application HTML.
     *
     * Usage example:
     *
     * ```php
     * $html = $panel->render($payload);
     * ```
     *
     * @param array<string, mixed> $payload Decoded collector payload.
     *
     * @return string Panel detail markup.
     */
    public function render(array $payload): string;

    /**
     * Returns the toolbar metric chips for a decoded collector payload.
     *
     * Usage example:
     *
     * ```php
     * $items = $panel->toolbarItems($payload);
     * ```
     *
     * @param array<string, mixed> $payload Decoded collector payload.
     *
     * @return list<ToolbarItem> Metric chips; empty to omit the panel from the toolbar.
     */
    public function toolbarItems(array $payload): array;
}
