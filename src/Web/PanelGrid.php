<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use Yiisoft\Data\Reader\ReadableDataInterface;
use Yiisoft\Yii\DataView\GridView\GridView;

/**
 * Builds the DataView grid with the layout, table, and empty-cell conventions shared by every debug panel.
 *
 * The grid creates no application URLs: the debugger serves its pages from its own middleware, outside the router, so
 * an application widget theme binding `GridView` to a router-aware URL creator must not reach the debug panels. A
 * panel that needs real page links applies its own `urlCreator()` afterwards.
 */
final class PanelGrid
{
    /**
     * Returns the grid rendering only its items, keeping the column classes on body cells that resolve to no content
     * and letting DataView add the accessibility attributes of the table.
     *
     * @template TRow of array|object
     *
     * @param ReadableDataInterface<array-key, TRow> $dataReader Rows the grid renders.
     *
     * @return GridView<TRow> Grid carrying the shared panel configuration.
     */
    public static function create(ReadableDataInterface $dataReader): GridView
    {
        /** @var GridView<TRow> $grid */
        $grid = GridView::widget();

        return $grid
            ->accessibility()
            ->containerClass('yii-debug-table-wrap')
            ->dataReader($dataReader)
            ->emptyCell('')
            ->keepColumnAttributesInEmptyCell()
            ->layout('{items}')
            ->tableClass('yii-debug-table')
            ->urlCreator(null);
    }

    /**
     * Returns the grid of {@see create()} with the filter row wired to the hidden form that submits the controls.
     *
     * @template TRow of array|object
     *
     * @param ReadableDataInterface<array-key, TRow> $dataReader Rows the grid renders.
     * @param string $formId Identifier of the hidden form the filter controls submit.
     *
     * @return GridView<TRow> Grid carrying the shared panel and filter row configuration.
     */
    public static function filterable(ReadableDataInterface $dataReader, string $formId): GridView
    {
        return self::create($dataReader)
            ->filterFormId($formId)
            ->filterRowAttributes(['class' => 'filters']);
    }
}
