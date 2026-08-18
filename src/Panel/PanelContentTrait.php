<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

/**
 * Provides the default payload-presence check for {@see PanelInterface::hasContent()}.
 */
trait PanelContentTrait
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
     * @return bool `true` when the payload is not empty.
     */
    public function hasContent(array $payload): bool
    {
        return $payload !== [];
    }
}
