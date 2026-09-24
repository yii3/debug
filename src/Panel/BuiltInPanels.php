<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use function in_array;

/**
 * Classifies panel IDs as built-in, keeping the sidebar navigation and the toolbar chips in the same order.
 */
final class BuiltInPanels
{
    /**
     * Built-in panel IDs, in display order.
     *
     * This is the only place the order is written: {@see BuiltInPanelList::fromMap()} orders the built-in panels from
     * it for both the sidebar and the toolbar, and a newly ported built-in is appended here and nowhere else.
     */
    public const array IDS = [
        'request',
        'log',
        'event',
        'profiling',
        'db',
        'asset',
    ];

    /**
     * Returns whether a panel ID belongs to the built-in navigation.
     *
     * @param string $id Panel ID to classify.
     *
     * @return bool `true` when the ID is built-in; `false` when the panel is an extension.
     */
    public static function isBuiltIn(string $id): bool
    {
        return in_array($id, self::IDS, true);
    }
}
