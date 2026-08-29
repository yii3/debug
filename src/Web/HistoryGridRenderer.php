<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use Closure;
use PHPForge\Debug\Data\{FilterPrefix, PageSize, QueryInput};
use PHPForge\Debug\Storage\RequestSummary;
use PHPForge\Debug\View\Grid\ActiveFilterBanner;
use PHPForge\Debug\View\History\{HistoryCellRenderer, HistoryRow, HistoryScale, HistorySummary};
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Form\{InputText, Option, Select};
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\List\{Li, Ul};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Table\{Table, Tbody, Td, Th, Thead, Tr};
use Yii3\Debug\Search\HistorySearch;

use function array_slice;
use function ceil;
use function count;
use function http_build_query;
use function is_string;
use function max;
use function min;

/**
 * Renders the filterable, paginated request history grid with Debug Core primitives.
 */
final class HistoryGridRenderer
{
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
        $filteredRows = $search->filter($rows);

        $perPageRaw = QueryInput::scalar($queryParams, 'per-page');
        $resolvedPageSize = PageSize::resolve($perPageRaw);

        $pageSize = $resolvedPageSize ?? max(1, count($filteredRows));
        $pageCount = max(1, (int) ceil(count($filteredRows) / $pageSize));
        $page = min($pageCount, max(1, (int) (QueryInput::scalar($queryParams, 'page') ?? '1')));
        $offset = ($page - 1) * $pageSize;
        $visibleRows = array_slice($filteredRows, $offset, $pageSize);

        $summary = HistorySummary::fromManifest($summaries);
        $scale = HistoryScale::fromModels($visibleRows);

        $bucketUrls = [];

        foreach ($summary->statusBuckets as $bucket) {
            $bucketUrls[$bucket->label] = self::url(
                $routePrefix,
                $queryParams,
                ['Debug' => ['statusCode' => (string) $bucket->sampleCode], 'page' => null],
            );
        }

        return H1::tag()
            ->class('yii-debug-sr-only')
            ->content('Request history')
            ->render()
            . HistoryCellRenderer::renderSummary(
                $summary,
                $bucketUrls,
                PageSize::selectorHtml(PageSize::current($perPageRaw)),
            )
            . HistoryComparisonRenderer::renderHistoryForm($summaries, $routePrefix)
            . ActiveFilterBanner::render(
                $search->activeFilters,
                self::filterRemovalUrl($routePrefix, $queryParams),
            )
            . self::renderGrid(
                $visibleRows,
                $scale,
                $search->activeFilters,
                $routePrefix,
                $queryParams,
                $offset,
                count($filteredRows),
                $page,
                $pageCount,
            );
    }

    /**
     * @param array<array-key, mixed> $queryParams
     *
     * @return Closure(list<string>): string
     */
    private static function filterRemovalUrl(string $routePrefix, array $queryParams): Closure
    {
        return static function (array $without) use ($queryParams, $routePrefix): string {
            $filters = QueryInput::group($queryParams, FilterPrefix::DEBUG);

            foreach ($without as $attribute) {
                if (is_string($attribute)) {
                    unset($filters[$attribute]);
                }
            }

            $changes = [FilterPrefix::DEBUG => $filters === [] ? null : $filters, 'page' => null];

            return self::url($routePrefix, $queryParams, $changes);
        };
    }

    /**
     * @param array<string, string> $filters
     */
    private static function renderFilterRow(array $filters): Tr
    {
        return Tr::tag()
            ->class('filters')
            ->html(
                Td::tag()->class('yii-debug-col-num'),
                Td::tag()->class('yii-debug-col-id')->html(
                    self::textFilter('tag', $filters, 'yii-debug-input yii-debug-col-id-input'),
                ),
                Td::tag(),
                Td::tag(),
                Td::tag(),
                Td::tag()->class('yii-debug-col-ip')->html(self::textFilter('ip', $filters)),
                Td::tag()->html(
                    self::selectFilter(
                        'method',
                        $filters,
                        ['GET' => 'GET', 'POST' => 'POST', 'PUT' => 'PUT', 'PATCH' => 'PATCH', 'DELETE' => 'DELETE'],
                    ),
                ),
                Td::tag()->html(self::selectFilter('ajax', $filters, ['0' => 'No', '1' => 'Yes'])),
                Td::tag()->html(self::textFilter('url', $filters)),
            );
    }

    /**
     * @param list<HistoryRow> $rows
     * @param array<string, string> $filters
     * @param array<array-key, mixed> $queryParams
     */
    private static function renderGrid(
        array $rows,
        HistoryScale $scale,
        array $filters,
        string $routePrefix,
        array $queryParams,
        int $offset,
        int $totalRows,
        int $page,
        int $pageCount,
    ): string {
        $bodyRows = [];

        foreach ($rows as $index => $row) {
            $attributes = HistoryCellRenderer::buildRowAttributes($row, $row->statusCode >= 400);
            $bodyRows[] = Tr::tag()
                ->attributes($attributes)
                ->html(
                    Td::tag()->class('yii-debug-col-num')->content((string) ($offset + $index + 1)),
                    Td::tag()->class('yii-debug-col-id')->html(
                        HistoryCellRenderer::renderTagCell(
                            $row,
                            $routePrefix . '/view?tag=' . rawurlencode($row->tag) . '&panel=config',
                        ),
                    ),
                    Td::tag()->html(HistoryCellRenderer::renderTimeCell($row)),
                    Td::tag()->html(HistoryCellRenderer::renderDurationCell($row, $scale->maxProcessingTime)),
                    Td::tag()->html(HistoryCellRenderer::renderMemoryCell($row, $scale->maxPeakMemory)),
                    Td::tag()->class('yii-debug-col-ip')->content($row->ip),
                    Td::tag()->html(HistoryCellRenderer::renderMethodCell($row)),
                    Td::tag()->content(HistoryCellRenderer::renderAjaxCell($row)),
                    Td::tag()->html(HistoryCellRenderer::renderUrlCell($row)),
                );
        }

        $table = Div::tag()
            ->class('yii-debug-table-wrap')
            ->html(
                Table::tag()
                    ->class('yii-debug-table')
                    ->html(
                        Thead::tag()->html(
                            self::renderHeaderRow(),
                            self::renderFilterRow($filters),
                        ),
                        Tbody::tag()->html(...$bodyRows),
                    ),
            );

        $begin = $totalRows === 0 ? 0 : $offset + 1;
        $end = min($offset + count($rows), $totalRows);
        $footer = Div::tag()
            ->class('yii-debug-grid-footer')
            ->html(
                Span::tag()
                    ->class('summary yii-debug-grid-count')
                    ->content("Showing {$begin}-{$end} of {$totalRows} items."),
                self::renderPager($routePrefix, $queryParams, $page, $pageCount),
            );

        return Div::tag()
            ->class('yii-debug-grid yii-debug-grid-history')
            ->html($table, $footer)
            ->render();
    }

    private static function renderHeaderRow(): Tr
    {
        $headers = ['#', 'ID', 'Time', 'Duration', 'Memory', 'IP', 'Method', 'Ajax', 'URL'];
        $cells = [];

        foreach ($headers as $header) {
            $class = match ($header) {
                '#' => 'yii-debug-col-num',
                'ID' => 'yii-debug-col-id',
                'IP' => 'yii-debug-col-ip',
                default => null,
            };
            $cell = Th::tag()->scope('col')->content($header);
            $cells[] = $class === null ? $cell : $cell->class($class);
        }

        return Tr::tag()->html(...$cells);
    }

    /**
     * @param array<array-key, mixed> $queryParams
     */
    private static function renderPager(string $routePrefix, array $queryParams, int $page, int $pageCount): Ul|string
    {
        if ($pageCount <= 1) {
            return '';
        }

        $items = [];

        for ($number = 1; $number <= $pageCount; $number++) {
            $link = A::tag()
                ->class('yii-debug-pager-link')
                ->href(self::url($routePrefix, $queryParams, ['page' => $number]))
                ->content((string) $number);
            $item = Li::tag()->class('yii-debug-pager-item')->html($link);
            $items[] = $number === $page ? $item->class('is-active') : $item;
        }

        return Ul::tag()->class('yii-debug-pager')->html(...$items);
    }

    /**
     * @param array<string, string> $filters
     * @param array<array-key, string> $options
     */
    private static function selectFilter(string $attribute, array $filters, array $options): Select
    {
        $select = Select::tag()
            ->class('yii-debug-select')
            ->name('Debug[' . $attribute . ']')
            ->value($filters[$attribute] ?? '')
            ->option(Option::tag()->value('')->content(''));

        foreach ($options as $value => $label) {
            $select = $select->option(Option::tag()->value((string) $value)->content($label));
        }

        return $select;
    }

    /**
     * @param array<string, string> $filters
     */
    private static function textFilter(string $attribute, array $filters, string $class = 'yii-debug-input'): InputText
    {
        return InputText::tag()
            ->class($class)
            ->name('Debug[' . $attribute . ']')
            ->value($filters[$attribute] ?? '');
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

        return $queryParams === [] ? $routePrefix : $routePrefix . '?' . http_build_query($queryParams);
    }
}
