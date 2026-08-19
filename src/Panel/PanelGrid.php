<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Data\{PageSize, QueryInput};
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\View\Grid\ActiveFilterBanner;
use Psr\Container\ContainerInterface;
use Yii3\Debug\Grid\{GridUrlCreator, PrefixedUrlParameterProvider};
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Data\Reader\Iterable\IterableDataReader;
use Yiisoft\Data\Reader\Sort;
use Yiisoft\Yii\DataView\GridView\Column\ColumnInterface;
use Yiisoft\Yii\DataView\GridView\GridView;
use Yiisoft\Yii\DataView\Pagination\OffsetPagination;

use function count;
use function is_array;
use function max;

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
     * Renders removable active-filter pills with panel-aware URLs.
     *
     * @param array<string, string> $activeFilters Active filter values.
     */
    public function activeFilterBanner(
        PanelRenderContext $context,
        string $prefix,
        array $activeFilters,
    ): string {
        return ActiveFilterBanner::render(
            $activeFilters,
            static function (array $without) use ($context, $prefix): string {
                $params = $context->queryParams;
                $group = is_array($params[$prefix] ?? null) ? $params[$prefix] : [];

                foreach ($without as $attribute) {
                    unset($group[$attribute]);
                }

                if ($group === []) {
                    unset($params[$prefix]);
                } else {
                    $params[$prefix] = $group;
                }

                unset($params['page']);

                return $context->panelUrl(queryParams: $params);
            },
        );
    }

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
     * @param string $prefix Filter-group prefix (one of the {@see \PHPForge\Debug\Data\FilterPrefix} constants).
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
     * Creates the full grid chrome for a captured panel while retaining its tag and panel route parameters.
     *
     * @return GridView<array<array-key, mixed>|object> Preconfigured grid.
     */
    public function fullForContext(
        PanelRenderContext $context,
        string $prefix,
        string $filterFormId,
    ): GridView {
        return $this->full(
            $context->panelUrl(queryParams: []),
            $context->queryParams,
            $prefix,
            $filterFormId,
        );
    }

    /**
     * Renders the shared page-size selector in its current request state.
     *
     * @param array<array-key, mixed> $queryParams Parsed query parameters.
     */
    public function pageSizeSelector(array $queryParams): string
    {
        $raw = QueryInput::scalar($queryParams, 'per-page');

        return PageSize::selectorHtml(PageSize::current($raw));
    }

    /**
     * Wraps pre-filtered panel rows in the shared sortable paginator and resolves the `per-page` contract.
     *
     * @param array<array-key, array<array-key, mixed>|object> $rows Pre-filtered rows.
     * @param array<array-key, mixed> $queryParams Parsed query parameters.
     *
     * @return OffsetPaginator<array-key, array<array-key, mixed>|object>
     */
    public function paginator(array $rows, array $queryParams, Sort $sort): OffsetPaginator
    {
        $pageSize = PageSize::resolve(QueryInput::scalar($queryParams, 'per-page'));

        $effectiveSize = $pageSize ?? max(1, count($rows));

        $reader = (new IterableDataReader($rows))->withSort($sort);

        return (new OffsetPaginator($reader))->withPageSize($effectiveSize);
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
