<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use Throwable;

/**
 * Applies the fail-open content policy shared by the toolbar chips and the sidebar navigation.
 *
 * A panel that throws while deciding is treated as present, so its chip or navigation entry stays discoverable and the
 * render step can expose the failure.
 */
final class PanelContent
{
    /**
     * Returns whether a captured payload holds content worth exposing.
     *
     * @param ExtensionPanelInterface $panel Panel presenting the capture.
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return bool `true` when the panel reports content or fails to decide; `false` otherwise.
     */
    public static function isPresent(ExtensionPanelInterface $panel, array $payload): bool
    {
        try {
            return $panel->hasContent($payload);
        } catch (Throwable) {
            // Keep malformed captured panels discoverable so the failure can be exposed downstream.
            return true;
        }
    }
}
