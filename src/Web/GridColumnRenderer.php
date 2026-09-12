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
    public function makeFilter(ColumnInterface $column, MakeFilterContext $context): FilterInterface|null
    {
        return null;
    }

    public function renderBody(ColumnInterface $column, Cell $cell, DataContext $context): Cell
    {
        return $cell
            ->addClass($column->class)
            ->addClass($column->bodyClass)
            ->content(($column->content)($context->data, $context))
            ->encode($column->encodeContent);
    }

    public function renderColumn(ColumnInterface $column, Cell $cell, GlobalContext $context): Cell
    {
        return $cell->addClass($column->class);
    }

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

    public function renderFooter(ColumnInterface $column, Cell $cell, GlobalContext $context): Cell
    {
        return $cell;
    }

    public function renderHeader(ColumnInterface $column, Cell $cell, GlobalContext $context): Cell
    {
        return $cell
            ->addAttributes($column->headerAttributes)
            ->addClass($column->class)
            ->addClass($column->headerClass)
            ->content($column->header)
            ->encode(false);
    }
}
