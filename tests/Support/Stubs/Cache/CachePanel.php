<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs\Cache;

use PHPForge\Debug\{ColumnStyle, Panel, PanelView};

use function array_is_list;
use function count;
use function is_array;

/**
 * Presents the operations {@see CacheCollector} captured for one request.
 */
final class CachePanel extends Panel
{
    protected const string ICON = 'db';
    protected const string ID = 'cache';
    protected const string TITLE = 'Cache';

    public function present(array $data): PanelView
    {
        $operations = $data['operations'] ?? null;
        $rows = is_array($operations) && array_is_list($operations) ? $operations : [];
        $count = count($rows);

        $view = PanelView::create()
            ->summary($count === 1 ? ' operation' : ' operations', $count)
            ->toolbar('Cache', $count);

        return $rows === []
            ? $view->emptyState('No cache operations', 'The cache was observed, but nothing happened.')
            : $view->table(
                ['Operation', 'Key', 'Result'],
                $rows,
                collapsible: true,
                styles: [1 => ColumnStyle::IDENTIFIER],
            );
    }
}
