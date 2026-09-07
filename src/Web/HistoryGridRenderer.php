<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use PHPForge\Debug\Data\{FilterPrefix, PageSize, QueryInput};
use PHPForge\Debug\Panel\PanelTitle;
use PHPForge\Debug\Storage\RequestSummary;
use PHPForge\Debug\View\Grid\ActiveFilterBanner;
use PHPForge\Debug\View\History\{HistoryCellRenderer, HistoryRow, HistoryScale, HistorySummary};
use Stringable;
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Palpable\A;
use Yii3\Debug\Search\HistorySearch;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Yii\DataView\GridView\Column\Base\DataContext;
use Yiisoft\Yii\DataView\GridView\GridView;

use function array_filter;
use function array_keys;
use function count;
use function http_build_query;
use function iterator_to_array;
use function usort;

/**
 * Renders the filterable, paginated request history grid with Debug Core primitives.
 */
final class HistoryGridRenderer
{
    private const array HEADERS = [
        'tag' => 'ID',
        'time' => 'Time',
        'processingTime' => 'Duration',
        'peakMemory' => 'Memory',
        'ip' => 'IP',
        'method' => 'Method',
        'ajax' => 'Ajax',
        'url' => 'URL',
    ];

    /**
     * @param array<string, RequestSummary> $summaries
     * @param array<array-key, mixed> $queryParams
     */
    public static function render(array $summaries, array $queryParams, string $routePrefix): string
    {
        $search = HistorySearch::fromQueryParams($queryParams);

        $rows = [];

        foreach ($summaries as $requestSummary) {
            $rows[] = HistoryRow::fromSummary($requestSummary);
        }

        $filteredRows = self::sortRows($search->filter($rows), QueryInput::scalar($queryParams, 'sort'));

        $perPageRaw = QueryInput::scalar($queryParams, 'per-page');
        $paginator = PageWindow::paginate($filteredRows, $perPageRaw, QueryInput::scalar($queryParams, 'page'));
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
            . HistoryComparisonRenderer::renderHistoryForm($summaries, $routePrefix)
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
                $routePrefix,
                $queryParams,
            );
    }

    /**
     * @param array<string, string> $filters
     * @param array<array-key, mixed> $queryParams
     *
     * @return list<GridColumn<HistoryRow>>
     */
    private static function columns(
        HistoryScale $scale,
        array $filters,
        string $routePrefix,
        array $queryParams,
        int $offset,
    ): array {
        $state = SortState::fromQuery(
            QueryInput::scalar($queryParams, 'sort'),
            array_keys(self::HEADERS),
            'time',
            'desc',
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

        foreach (self::HEADERS as $attribute => $label) {
            $class = match ($attribute) {
                'tag' => 'yii-debug-col-id',
                'ip' => 'yii-debug-col-ip',
                default => null,
            };

            $active = $state->isActive($attribute);
            $nextSort = $state->next($attribute);

            $link = A::tag()
                ->href(self::url($routePrefix, $queryParams, ['sort' => $nextSort, 'page' => null]))
                ->class($active ? $state->direction : null)
                ->content($label);

            $columns[] = new GridColumn(
                header: $link->render(),
                content: static fn(HistoryRow $row): string => self::renderCell($row, $attribute, $scale, $routePrefix),
                filter: self::filter($attribute, $filters),
                encodeContent: $attribute === 'ip' || $attribute === 'ajax',
                class: $class,
                headerAttributes: $active ? ['aria-sort' => $state->ariaSort()] : [],
            );
        }

        return $columns;
    }

    /**
     * @param array<string, string> $filters
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
            'ip' => FilterInput::text(FilterPrefix::DEBUG, 'ip', 'IP', $filters),
            'url' => FilterInput::text(FilterPrefix::DEBUG, 'url', 'URL', $filters),
            'method' => FilterInput::select(
                FilterPrefix::DEBUG,
                'method',
                'Method',
                $filters,
                [
                    'GET' => 'GET',
                    'POST' => 'POST',
                    'PUT' => 'PUT',
                    'PATCH' => 'PATCH',
                    'DELETE' => 'DELETE',
                    'HEAD' => 'HEAD',
                    'OPTIONS' => 'OPTIONS',
                    'COMMAND' => 'COMMAND',
                ],
            ),
            'ajax' => FilterInput::select(FilterPrefix::DEBUG, 'ajax', 'AJAX', $filters, ['0' => 'No', '1' => 'Yes']),
            default => '',
        };
    }

    /**
     * @param key-of<self::HEADERS> $attribute
     */
    private static function renderCell(
        HistoryRow $row,
        string $attribute,
        HistoryScale $scale,
        string $routePrefix,
    ): string {
        return match ($attribute) {
            'tag' => HistoryCellRenderer::renderTagCell(
                $row,
                "{$routePrefix}/view?tag=" . rawurlencode($row->tag) . '&panel=auto',
            ),
            'time' => HistoryCellRenderer::renderTimeCell($row),
            'processingTime' => HistoryCellRenderer::renderDurationCell($row, $scale->maxProcessingTime),
            'peakMemory' => HistoryCellRenderer::renderMemoryCell($row, $scale->maxPeakMemory),
            'ip' => $row->ip,
            'method' => HistoryCellRenderer::renderMethodCell($row),
            'ajax' => HistoryCellRenderer::renderAjaxCell($row),
            'url' => HistoryCellRenderer::renderUrlCell($row),
        };
    }

    /**
     * @param OffsetPaginator<int, HistoryRow> $paginator
     * @param array<string, string> $filters
     * @param array<array-key, mixed> $queryParams
     */
    private static function renderGrid(
        OffsetPaginator $paginator,
        array $filters,
        string $routePrefix,
        array $queryParams,
    ): string {
        $rows = iterator_to_array($paginator->read(), false);

        /** @var GridView<HistoryRow> $grid */
        $grid = GridView::widget();

        $table = $grid
            ->bodyRowAttributes(
                static fn(HistoryRow $row): array => array_filter(
                    HistoryCellRenderer::buildRowAttributes($row, $row->statusCode >= 400),
                    static fn(mixed $value): bool => $value !== '',
                ),
            )
            ->dataReader($paginator)
            ->layout('{items}')
            ->containerClass('yii-debug-table-wrap')
            ->tableClass('yii-debug-table')
            ->headerCellAttributes(['scope' => 'col'])
            ->filterCellAttributes(['class' => 'yii-debug-filter-cell'])
            ->filterFormId('yii-debug-history-filters')
            ->urlCreator(static fn(): string => $routePrefix)
            ->noResultsCellAttributes(['class' => 'yii-debug-muted'])
            ->noResultsText($filters === [] ? 'No requests have been captured.' : 'No requests match the current filters.')
            ->columns(...self::columns(
                HistoryScale::fromModels($rows),
                $filters,
                $routePrefix,
                $queryParams,
                $paginator->getOffset(),
            ))
            ->render();

        $footer = GridFooter::render(
            $paginator->getTotalItems(),
            $paginator->getOffset(),
            count($rows),
            $paginator->getCurrentPage(),
            $paginator->getTotalPages(),
            static fn(int $number): string => self::url($routePrefix, $queryParams, ['page' => $number]),
        );

        return Div::tag()
            ->class('yii-debug-grid yii-debug-grid-history')
            ->html($table, $footer)
            ->render();
    }

    /**
     * @param list<HistoryRow> $rows
     *
     * @return list<HistoryRow>
     */
    private static function sortRows(array $rows, string|null $sort): array
    {
        $state = SortState::fromQuery($sort, array_keys(self::HEADERS), 'time', 'desc');

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

    private static function sortValue(HistoryRow $row, string $attribute): string|float|int|bool|null
    {
        return match ($attribute) {
            'tag' => $row->tag,
            'processingTime' => $row->processingTime,
            'peakMemory' => $row->peakMemory,
            'ip' => $row->ip,
            'method' => $row->method,
            'ajax' => $row->ajax,
            'url' => $row->url,
            default => $row->time,
        };
    }

    /**
     * @param array<array-key, mixed> $queryParams
     * @param array<array-key, mixed> $changes
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
