<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use Yiisoft\Data\Reader\FilterInterface;
use Yiisoft\Yii\DataView\GridView\Column\Base\{Cell, DataContext, FilterContext, GlobalContext, MakeFilterContext};
use Yiisoft\Yii\DataView\GridView\Column\{ColumnInterface, FilterableColumnRendererInterface};

/**
 * Renders {@see GridColumn} cells, applying the column classes and the pre-built content to the header, filter, and
 * body cells, and encoding the body content only when the column requests it.
 *
 * GridView resolves this renderer from {@see GridColumn::getRenderer()}, so callers never instantiate it.
 *
 * @implements FilterableColumnRendererInterface<GridColumn<array<array-key, mixed>|object>>
 */
final class GridColumnRenderer implements FilterableColumnRendererInterface
{
    /**
     * Declines to build a reader filter: the panels filter their rows before the grid renders.
     *
     * @param GridColumn<array<array-key, mixed>|object> $column Column being rendered.
     * @param MakeFilterContext $context Filter state supplied by GridView.
     *
     * @return FilterInterface|null Always `null`.
     */
    public function makeFilter(ColumnInterface $column, MakeFilterContext $context): FilterInterface|null
    {
        return null;
    }

    /**
     * Applies the column classes and the pre-built content to a body cell, encoding it only on request.
     *
     * @param GridColumn<array<array-key, mixed>|object> $column Column being rendered.
     * @param Cell $cell Cell to decorate.
     * @param DataContext $context Row state supplied by GridView.
     *
     * @return Cell Decorated body cell.
     */
    public function renderBody(ColumnInterface $column, Cell $cell, DataContext $context): Cell
    {
        return $cell
            ->addClass($column->class)
            ->addClass($column->bodyClass)
            ->content(($column->content)($context->data, $context))
            ->encode($column->encodeContent);
    }

    /**
     * Applies the column classes to the column-level cell.
     *
     * @param GridColumn<array<array-key, mixed>|object> $column Column being rendered.
     * @param Cell $cell Cell to decorate.
     * @param GlobalContext $context Grid state supplied by GridView.
     *
     * @return Cell Decorated column cell.
     */
    public function renderColumn(ColumnInterface $column, Cell $cell, GlobalContext $context): Cell
    {
        return $cell->addClass($column->class);
    }

    /**
     * Renders the filter cell, omitting it when the column declares no filter content.
     *
     * @param GridColumn<array<array-key, mixed>|object> $column Column being rendered.
     * @param Cell $cell Cell to decorate.
     * @param FilterContext $context Filter state supplied by GridView.
     *
     * @return Cell|null Decorated filter cell, or `null` when the column has no filter.
     */
    public function renderFilter(ColumnInterface $column, Cell $cell, FilterContext $context): Cell|null
    {
        if ($column->filter === null) {
            return null;
        }

        return $cell
            ->addClass($column->class)
            ->content($column->filter)
            ->encode(false);
    }

    /**
     * Returns the footer cell unchanged; the grids in this package render no footer content per column.
     *
     * @param GridColumn<array<array-key, mixed>|object> $column Column being rendered.
     * @param Cell $cell Cell to decorate.
     * @param GlobalContext $context Grid state supplied by GridView.
     *
     * @return Cell Decorated footer cell.
     */
    public function renderFooter(ColumnInterface $column, Cell $cell, GlobalContext $context): Cell
    {
        return $cell;
    }

    /**
     * Applies the column classes and the pre-built header content to the header cell.
     *
     * @param GridColumn<array<array-key, mixed>|object> $column Column being rendered.
     * @param Cell $cell Cell to decorate.
     * @param GlobalContext $context Grid state supplied by GridView.
     *
     * @return Cell Decorated header cell.
     */
    public function renderHeader(ColumnInterface $column, Cell $cell, GlobalContext $context): Cell
    {
        $header = $column->header;

        return $cell
            ->addAttributes($header instanceof SortHeader ? $header->attributes : [])
            ->addClass($column->class)
            ->addClass($column->headerClass)
            ->content((string) $header)
            ->encode(false);
    }
}
