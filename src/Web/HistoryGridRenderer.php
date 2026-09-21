<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use PHPForge\Debug\Data\{FilterPrefix, PageSize, QueryInput};
use PHPForge\Debug\Panel\PanelTitle;
use PHPForge\Debug\Storage\RequestSummary;
use PHPForge\Debug\View\Grid\ActiveFilterBanner;
use PHPForge\Debug\View\History\{HistoryCellRenderer, HistoryRow, HistoryScale, HistorySummary};
use PHPForge\Debug\View\ViewMessage;
use Stringable;
use UIAwesome\Html\Flow\Div;
use Yii3\Debug\Panel\DbPanel;
use Yii3\Debug\Search\HistorySearch;
use Yii3\Debug\View\ViewMessage as AdapterMessage;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Yii\DataView\GridView\Column\Base\DataContext;
use Yiisoft\Yii\DataView\GridView\GridView;

use function array_filter;
use function array_keys;
use function http_build_query;
use function iterator_to_array;
use function usort;

/**
 * Renders the filterable, paginated request history grid with Debug Core primitives.
 */
final class HistoryGridRenderer
{
    /**
     * Column headings keyed by the attribute each column renders.
     */
    private const array HEADERS = [
        'tag' => ViewMessage::ID->value,
        'time' => ViewMessage::TIME->value,
        'processingTime' => ViewMessage::DURATION->value,
        'peakMemory' => ViewMessage::MEMORY->value,
        'ip' => ViewMessage::IP->value,
        'sqlCount' => ViewMessage::QUERY->value,
        'method' => ViewMessage::METHOD->value,
        'ajax' => 'Ajax',
        'url' => ViewMessage::URL->value,
    ];

    /**
     * Renders the history grid, including the Query column only when the Database panel is registered.
     *
     * @param array<string, RequestSummary> $summaries Manifest entries in capture order.
     * @param array<array-key, mixed> $queryParams Request query parameters driving filtering, sorting, and paging.
     * @param string $routePrefix Base route used to generate debugger URLs.
     * @param DbPanel|null $dbPanel Registered Database panel supplying the critical-query threshold, or `null`.
     *
     * @return string Rendered history grid.
     */
    public static function render(
        array $summaries,
        array $queryParams,
        string $routePrefix,
        DbPanel|null $dbPanel = null,
    ): string {
        $search = HistorySearch::fromQueryParams($queryParams);

        $rows = [];

        foreach ($summaries as $requestSummary) {
            $rows[] = HistoryRow::fromSummary($requestSummary);
        }

        $state = SortState::fromQuery(
            QueryInput::scalar($queryParams, 'sort'),
            array_keys(self::headers($dbPanel)),
            'time',
            'desc',
        );

        $filteredRows = self::sortRows($search->filter($rows), $state);

        $perPageRaw = QueryInput::scalar(
            $queryParams,
            'per-page',
        );
        $paginator = PageWindow::paginate(
            $filteredRows,
            $perPageRaw,
            QueryInput::scalar($queryParams, 'page'),
        );
        $summary = HistorySummary::fromManifest($summaries);

        $bucketUrls = [];

        foreach ($summary->statusBuckets as $bucket) {
            $bucketUrls[$bucket->label] = self::url(
                $routePrefix,
                $queryParams,
                [
                    FilterPrefix::DEBUG => ['statusCode' => (string) $bucket->sampleCode],
                    'page' => null,
                ],
            );
        }

        return PanelHeading::render(PanelTitle::REQUEST_HISTORY)
            . HistoryCellRenderer::renderSummary(
                $summary,
                $bucketUrls,
                PageSize::selectorFor($queryParams),
            )
            . HistoryComparisonRenderer::renderHistoryForm(
                $summaries,
                $routePrefix,
            )
            . ActiveFilterBanner::render(
                $search->activeFilters,
                static fn(array $without): string => self::url(
                    $routePrefix,
                    FilterRemoval::queryParams($queryParams, FilterPrefix::DEBUG, $without),
                    [],
                ),
            )
            . self::renderGrid(
                $paginator,
                $search->activeFilters,
                $state,
                $routePrefix,
                $queryParams,
                $dbPanel,
            );
    }

    /**
     * Builds the grid columns, including the Query column only when the Database panel is registered.
     *
     * @param HistoryScale $scale Page maxima scaling the duration and memory gauges.
     * @param array<string, string> $filters Active filter values keyed by attribute.
     * @param SortState $state Sort state of the visible page.
     * @param string $routePrefix Base route used to generate debugger URLs.
     * @param array<array-key, mixed> $queryParams Request query parameters driving filtering, sorting, and paging.
     * @param int $offset Index of the first row on the visible page, numbering the rows.
     * @param DbPanel|null $dbPanel Registered Database panel supplying the critical-query threshold, or `null`.
     *
     * @return list<GridColumn<HistoryRow>> Columns in display order.
     */
    private static function columns(
        HistoryScale $scale,
        array $filters,
        SortState $state,
        string $routePrefix,
        array $queryParams,
        int $offset,
        DbPanel|null $dbPanel,
    ): array {
        $headers = self::headers($dbPanel);

        $url = static fn(string $sort): string => self::url(
            $routePrefix,
            $queryParams,
            ['sort' => $sort, 'page' => null],
        );

        $columns = [
            new GridColumn(
                header: '#',
                content: static fn(HistoryRow $row, DataContext $context): string => (string) (
                    $offset + $context->index + 1
                ),
                filter: '',
                class: 'yii-debug-col-num',
            ),
        ];

        foreach ($headers as $attribute => $label) {
            $class = match ($attribute) {
                'tag' => 'yii-debug-col-id',
                'ip' => 'yii-debug-col-ip',
                'sqlCount' => 'yii-debug-col-num',
                default => null,
            };

            $columns[] = new GridColumn(
                header: $state->header($attribute, $label, $url),
                content: static fn(HistoryRow $row): string => self::renderCell(
                    $row,
                    $attribute,
                    $scale,
                    $routePrefix,
                    $dbPanel,
                ),
                filter: self::filter($attribute, $filters),
                encodeContent: $attribute === 'ip' || $attribute === 'ajax',
                class: $class,
            );
        }

        return $columns;
    }

    /**
     * Renders the filter input of one column, carrying its submitted value.
     *
     * @param string $attribute Attribute the column filters on.
     * @param array<string, string> $filters Active filter values keyed by attribute.
     *
     * @return string|Stringable Rendered filter input.
     */
    private static function filter(string $attribute, array $filters): string|Stringable
    {
        return match ($attribute) {
            'tag' => FilterInput::text(
                FilterPrefix::DEBUG,
                'tag',
                'ID',
                $filters,
                'yii-debug-input yii-debug-col-id-input',
            ),
            'ip' => FilterInput::text(
                FilterPrefix::DEBUG,
                'ip',
                'IP',
                $filters,
            ),
            'sqlCount' => FilterInput::text(
                FilterPrefix::DEBUG,
                'sqlCount',
                'Query count',
                $filters,
            ),
            'url' => FilterInput::text(
                FilterPrefix::DEBUG,
                'url',
                ViewMessage::URL->value,
                $filters,
            ),
            'method' => FilterInput::select(
                FilterPrefix::DEBUG,
                'method',
                ViewMessage::METHOD->value,
                $filters,
                [
                    'GET' => 'GET',
                    'POST' => 'POST',
                    'PUT' => 'PUT',
                    'PATCH' => 'PATCH',
                    'DELETE' => 'DELETE',
                    'HEAD' => 'HEAD',
                    'OPTIONS' => 'OPTIONS',
                    'COMMAND' => ViewMessage::COMMAND->value,
                ],
            ),
            'ajax' => FilterInput::select(
                FilterPrefix::DEBUG,
                'ajax',
                ViewMessage::AJAX->value,
                $filters,
                ['0' => 'No', '1' => 'Yes'],
            ),
            default => '',
        };
    }

    /**
     * Returns the header map for the rendered columns, dropping Query when no Database panel is registered.
     *
     * @param DbPanel|null $dbPanel Registered Database panel, or `null`.
     *
     * @return array<key-of<self::HEADERS>, string> Sortable attribute mapped to its column label, in display order.
     */
    private static function headers(DbPanel|null $dbPanel): array
    {
        $headers = self::HEADERS;

        if ($dbPanel === null) {
            unset($headers['sqlCount']);
        }

        return $headers;
    }

    /**
     * Renders one body cell, dispatching on the attribute the column shows.
     *
     * @param HistoryRow $row Capture the row describes.
     * @param key-of<self::HEADERS> $attribute Attribute the column renders.
     * @param HistoryScale $scale Page maxima scaling the duration and memory gauges.
     * @param string $routePrefix Base route used to generate debugger URLs.
     * @param DbPanel|null $dbPanel Registered Database panel supplying the critical-query threshold, or `null`.
     *
     * @return string Rendered cell.
     */
    private static function renderCell(
        HistoryRow $row,
        string $attribute,
        HistoryScale $scale,
        string $routePrefix,
        DbPanel|null $dbPanel,
    ): string {
        return match ($attribute) {
            'tag' => HistoryCellRenderer::renderTagCell(
                $row,
                "{$routePrefix}/view?tag=" . rawurlencode($row->tag) . '&panel=auto',
            ),
            'time' => HistoryCellRenderer::renderTimeCell($row),
            'processingTime' => HistoryCellRenderer::renderDurationCell(
                $row,
                $scale->maxProcessingTime,
            ),
            'peakMemory' => HistoryCellRenderer::renderMemoryCell(
                $row,
                $scale->maxPeakMemory,
            ),
            'ip' => $row->ip,
            'sqlCount' => HistoryCellRenderer::renderSqlCountCell(
                $row,
                "{$routePrefix}/view?tag=" . rawurlencode($row->tag) . '&panel=db',
                $dbPanel?->isQueryCountCritical($row->sqlCount) ?? false,
                $dbPanel?->criticalQueryThreshold() ?? 0,
            ),
            'method' => HistoryCellRenderer::renderMethodCell($row),
            'ajax' => HistoryCellRenderer::renderAjaxCell($row),
            'url' => HistoryCellRenderer::renderUrlCell($row),
        };
    }

    /**
     * Renders the grid for the visible page.
     *
     * @param OffsetPaginator<int, HistoryRow> $paginator Paginator clamped to the visible page.
     * @param array<string, string> $filters Active filter values keyed by attribute.
     * @param SortState $state Sort state of the visible page.
     * @param string $routePrefix Base route used to generate debugger URLs.
     * @param array<array-key, mixed> $queryParams Request query parameters driving filtering, sorting, and paging.
     * @param DbPanel|null $dbPanel Registered Database panel supplying the critical-query threshold, or `null`.
     *
     * @return string Rendered grid.
     */
    private static function renderGrid(
        OffsetPaginator $paginator,
        array $filters,
        SortState $state,
        string $routePrefix,
        array $queryParams,
        DbPanel|null $dbPanel,
    ): string {
        $rows = iterator_to_array($paginator->read(), false);

        /** @var GridView<HistoryRow> $grid */
        $grid = PanelGrid::filterable(
            $paginator,
            'yii-debug-history-filters',
        );

        $table = $grid
            ->bodyRowAttributes(
                static fn(HistoryRow $row): array => array_filter(
                    HistoryCellRenderer::buildRowAttributes($row, $row->statusCode >= 400),
                    static fn(mixed $value): bool => $value !== '',
                ),
            )
            ->urlCreator(static fn(): string => $routePrefix)
            ->noResultsCellAttributes(['class' => 'yii-debug-muted'])
            ->noResultsText(
                $filters === []
                    ? AdapterMessage::HISTORY_EMPTY->value
                    : AdapterMessage::HISTORY_NO_MATCH->value,
            )
            ->columns(...self::columns(
                HistoryScale::fromModels($rows),
                $filters,
                $state,
                $routePrefix,
                $queryParams,
                $paginator->getOffset(),
                $dbPanel,
            ))
            ->render();

        $footer = GridFooter::render(
            $paginator,
            static fn(string $page): string => self::url($routePrefix, $queryParams, ['page' => $page]),
        );

        return Div::tag()
            ->class('yii-debug-grid yii-debug-grid-history')
            ->html($table, $footer)
            ->render();
    }

    /**
     * Orders the captures by the submitted sort expression.
     *
     * @param list<HistoryRow> $rows Captures to order.
     * @param SortState $state Sort state of the visible page.
     *
     * @return list<HistoryRow> Captures in display order.
     */
    private static function sortRows(array $rows, SortState $state): array
    {
        usort(
            $rows,
            static function (HistoryRow $left, HistoryRow $right) use ($state): int {
                $a = self::sortValue($left, $state->attribute);
                $b = self::sortValue($right, $state->attribute);

                if ($a === null || $b === null) {
                    return ($a === null) <=> ($b === null);
                }

                return $state->direction === 'asc' ? $a <=> $b : $b <=> $a;
            }
        );

        return $rows;
    }

    /**
     * Reads the comparable value one attribute sorts on.
     *
     * @param HistoryRow $row Capture the value is read from.
     * @param string $attribute Attribute being sorted on.
     *
     * @return string|float|int|bool|null Comparable value, or `null` when the attribute is unknown.
     */
    private static function sortValue(HistoryRow $row, string $attribute): string|float|int|bool|null
    {
        return match ($attribute) {
            'tag' => $row->tag,
            'processingTime' => $row->processingTime,
            'peakMemory' => $row->peakMemory,
            'ip' => $row->ip,
            'sqlCount' => $row->sqlCount,
            'method' => $row->method,
            'ajax' => $row->ajax,
            'url' => $row->url,
            default => $row->time,
        };
    }

    /**
     * Builds a history URL from the current query parameters, applying the given changes.
     *
     * @param string $routePrefix Base route used to generate debugger URLs.
     * @param array<array-key, mixed> $queryParams Request query parameters driving filtering, sorting, and paging.
     * @param array<array-key, mixed> $changes Parameters to set; a `null` value removes the parameter.
     *
     * @return string History URL carrying the merged parameters.
     */
    private static function url(string $routePrefix, array $queryParams, array $changes): string
    {
        foreach ($changes as $name => $value) {
            if ($value === null) {
                unset($queryParams[$name]);
            } else {
                $queryParams[$name] = $value;
            }
        }

        return $queryParams === []
            ? $routePrefix
            : "{$routePrefix}?" . http_build_query($queryParams);
    }
}
