<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use Psr\Container\ContainerInterface;
use Yii3\Debug\Grid\{GridUrlCreator, PrefixedUrlParameterProvider};
use Yiisoft\Data\Reader\Iterable\IterableDataReader;
use Yiisoft\Yii\DataView\GridView\Column\ColumnInterface;
use Yiisoft\Yii\DataView\GridView\GridView;
use Yiisoft\Yii\DataView\Pagination\OffsetPagination;

/**
 * Renders Yii3 data-view grids with the shared debugger grid styling.
 *
 * Usage example:
 *
 * ```php
 * $html = $grid->render([new DataColumn(header: 'Time', content: $renderer)], $rows);
 * ```
 */
final readonly class PanelGrid
{
    /**
     * @param ContainerInterface $container Container resolving the data-view column renderers.
     */
    public function __construct(private ContainerInterface $container) {}

    /**
     * Creates a bare grid carrying only the items section, ready for panel-specific configuration.
     *
     * Usage example:
     *
     * ```php
     * $html = $grid->create()->dataReader($reader)->columns($column)->render();
     * ```
     *
     * @return GridView<array<array-key, mixed>|object> Preconfigured grid.
     */
    public function create(): GridView
    {
        return (new GridView($this->container))->layout('{items}');
    }

    /**
     * Creates a grid with the full debugger chrome: filter row, sortable headers, footer summary, and pager.
     *
     * The grid navigates through URLs built on top of the current request (sort links, pager, filter values), reads
     * its state from `Prefix[attribute]` query groups, and emits the shared `yii-debug-*` markup contract so the
     * bundled CSS and JavaScript (filter bridge, page-size selector) apply without changes. Server-side filtering
     * stays with the caller — pre-filter the rows before wrapping them in the data reader.
     *
     * Usage example:
     *
     * ```php
     * $html = $grid->full('/debug', $queryParams, 'Log', 'log-filters')
     *     ->dataReader($paginator)
     *     ->columns(...$columns)
     *     ->render();
     * ```
     *
     * @param string $path Path of the current request used as the base for grid navigation URLs.
     * @param array<array-key, mixed> $queryParams Parsed query parameters of the current request.
     * @param string $prefix Filter-group prefix (one of the {@see \Yii3\Debug\Data\FilterPrefix} constants).
     * @param string $filterFormId Identifier of the hidden filter form the filter inputs bind to.
     *
     * @return GridView<array<array-key, mixed>|object> Preconfigured grid.
     */
    public function full(string $path, array $queryParams, string $prefix, string $filterFormId): GridView
    {
        return (new GridView($this->container))
            ->layout(
                "<div class=\"yii-debug-table-wrap\">{items}</div>\n"
                . "<div class=\"yii-debug-grid-footer\">{summary}\n{pager}\n</div>",
            )
            ->containerAttributes(['class' => 'yii-debug-grid'])
            ->tableAttributes(['class' => 'yii-debug-table'])
            ->filterFormId($filterFormId)
            ->filterCellAttributes(['class' => 'yii-debug-filter-cell'])
            ->summaryTemplate('Showing {begin}-{end} of {totalCount} items.')
            ->summaryAttributes(['class' => 'summary yii-debug-grid-count'])
            ->pageSizeParameterName('per-page')
            ->pageSizeConstraint(true)
            ->urlParameterProvider(new PrefixedUrlParameterProvider($queryParams, $prefix))
            ->urlCreator(new GridUrlCreator($path, $queryParams))
            ->sortableHeaderAscAppend('<span class="yii-debug-sort-asc" aria-hidden="true"></span>')
            ->sortableHeaderDescAppend('<span class="yii-debug-sort-desc" aria-hidden="true"></span>')
            ->paginationWidget(
                (new OffsetPagination())
                    ->containerTag(null)
                    ->listTag('ul')
                    ->listAttributes(['class' => 'yii-debug-pager'])
                    ->itemTag('li')
                    ->itemAttributes(['class' => 'yii-debug-pager-item'])
                    ->linkAttributes(['class' => 'yii-debug-pager-link'])
                    ->currentItemClass('is-active')
                    ->disabledItemClass('is-disabled')
                    ->labelFirst(null)
                    ->labelLast(null)
                    ->labelPrevious('«')
                    ->labelNext('»'),
            );
    }

    /**
     * Renders one grid with the shared debugger table classes.
     *
     * @param list<ColumnInterface> $columns Grid columns in display order.
     * @param iterable<array-key, array<array-key, mixed>|object> $rows Typed rows in display order.
     *
     * @return string Grid markup.
     */
    public function render(array $columns, iterable $rows): string
    {
        return $this->create()
            ->dataReader(new IterableDataReader($rows))
            ->columns(...$columns)
            ->containerAttributes(['class' => 'yii-debug-grid'])
            ->tableAttributes(['class' => 'yii-debug-table'])
            ->render();
    }
}
