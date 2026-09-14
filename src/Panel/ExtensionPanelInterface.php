<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

/**
 * Defines a stateless presenter for an optional debug panel.
 */
interface ExtensionPanelInterface
{
    /**
     * Returns whether a captured payload contains activity worth exposing in the sidebar.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return bool `true` when the capture holds activity worth listing; `false` otherwise.
     */
    public function hasContent(array $payload): bool;

    /**
     * Returns the shared Debug Core icon key.
     *
     * @return string Icon key for the toolbar chip.
     */
    public function icon(): string;

    /**
     * Returns the stable panel identifier used in captured payloads and debug URLs.
     *
     * @return string Stable panel ID.
     */
    public function id(): string;

    /**
     * Returns the human-readable panel name.
     *
     * @return string Panel display name.
     */
    public function name(): string;

    /**
     * Renders the detail content for a captured payload.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return string Rendered detail content.
     */
    public function render(array $payload): string;
}
