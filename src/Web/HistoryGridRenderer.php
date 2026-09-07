<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use Closure;
use PHPForge\Debug\Data\{FilterPrefix, PageSize, QueryInput};
use PHPForge\Debug\Panel\PanelTitle;
use PHPForge\Debug\Storage\RequestSummary;
use PHPForge\Debug\View\Grid\ActiveFilterBanner;
use PHPForge\Debug\View\History\{HistoryCellRenderer, HistoryRow, HistoryScale, HistorySummary};
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Form\{InputText, Option, Select};
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Table\{Table, Tbody, Td, Th, Thead, Tr};
use Yii3\Debug\Search\HistorySearch;

use function array_slice;
use function count;
use function http_build_query;
use function is_string;
use function str_starts_with;
use function substr;
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

        $window = new PageWindow(
            count($filteredRows),
            $perPageRaw,
            QueryInput::scalar($queryParams, 'page'),
        );

        $visibleRows = array_slice($filteredRows, $window->offset, $window->limit);

        $summary = HistorySummary::fromManifest($summaries);
        $scale = HistoryScale::fromModels($visibleRows);

        $bucketUrls = [];

        foreach ($summary->statusBuckets as $bucket) {
            $bucketUrls[$bucket->label] = self::url(
                $routePrefix,
                $queryParams,
                [
                    'Debug' => [
                        'statusCode' => (string) $bucket->sampleCode],
                    'page' => null,
                ],
            );
        }

        return H1::tag()
            ->class('yii-debug-sr-only')
            ->content(PanelTitle::REQUEST_HISTORY)
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
                $window->offset,
                count($filteredRows),
                $window->page,
                $window->pageCount,
            );
    }

    private static function filterLabel(string $attribute): string
    {
        return 'Filter by ' . match ($attribute) {
            'tag' => 'ID',
            'ip' => 'IP',
            'method' => 'Method',
            'ajax' => 'AJAX',
            'url' => 'URL',
            default => $attribute,
        };
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
                Td::tag()
                    ->class('yii-debug-col-id')
                    ->html(
                        self::textFilter(
                            'tag',
                            $filters,
                            'yii-debug-input yii-debug-col-id-input',
                        ),
                    ),
                Td::tag(),
                Td::tag(),
                Td::tag(),
                Td::tag()
                    ->class('yii-debug-col-ip')
                    ->html(self::textFilter('ip', $filters)),
                Td::tag()
                    ->html(
                        self::selectFilter(
                            'method',
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
                    ),
                Td::tag()
                    ->html(
                        self::selectFilter(
                            'ajax',
                            $filters,
                            ['0' => 'No', '1' => 'Yes'],
                        ),
                    ),
                Td::tag()
                    ->html(
                        self::textFilter(
                            'url',
                            $filters,
                        ),
                    ),
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
                    Td::tag()
                        ->class('yii-debug-col-num')
                        ->content((string) ($offset + $index + 1)),
                    Td::tag()
                        ->class('yii-debug-col-id')
                        ->html(
                            HistoryCellRenderer::renderTagCell(
                                $row,
                                "{$routePrefix}/view?tag=" . rawurlencode($row->tag) . '&panel=auto',
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

        if ($bodyRows === []) {
            $bodyRows[] = Tr::tag()->html(
                Td::tag()
                    ->colspan(9)
                    ->class('yii-debug-muted')
                    ->content($filters === [] ? 'No requests have been captured.' : 'No requests match the current filters.'),
            );
        }

        $table = Div::tag()
            ->class('yii-debug-table-wrap')
            ->html(
                Table::tag()
                    ->class('yii-debug-table')
                    ->html(
                        Thead::tag()
                            ->html(
                                self::renderHeaderRow($routePrefix, $queryParams),
                                self::renderFilterRow($filters),
                            ),
                        Tbody::tag()->html(...$bodyRows),
                    ),
            );

        $footer = GridFooter::render(
            $totalRows,
            $offset,
            count($rows),
            $page,
            $pageCount,
            static fn(int $number): string => self::url($routePrefix, $queryParams, ['page' => $number]),
        );

        return Div::tag()
            ->class('yii-debug-grid yii-debug-grid-history')
            ->html($table, $footer)
            ->render();
    }

    /**
     * @param array<array-key, mixed> $queryParams
     */
    private static function renderHeaderRow(string $routePrefix, array $queryParams): Tr
    {
        [$activeAttribute, $direction] = self::sortState(QueryInput::scalar($queryParams, 'sort'));

        $cells = [
            Th::tag()
                ->scope('col')
                ->class('yii-debug-col-num')
                ->content('#'),
        ];

        foreach (self::HEADERS as $attribute => $label) {
            $cell = Th::tag()->scope('col');

            $class = match ($attribute) {
                'tag' => 'yii-debug-col-id',
                'ip' => 'yii-debug-col-ip',
                default => null,
            };

            $active = $attribute === $activeAttribute;
            $nextSort = $active && $direction === 'asc' ? "-{$attribute}" : $attribute;

            $link = A::tag()
                ->href(self::url($routePrefix, $queryParams, ['sort' => $nextSort, 'page' => null]))
                ->content($label);

            if ($active) {
                $cell = $cell->addAriaAttribute('sort', $direction === 'asc' ? 'ascending' : 'descending');
                $link = $link->class($direction);
            }

            $cell = $cell->html($link);

            $cells[] = $class === null ? $cell : $cell->class($class);
        }

        return Tr::tag()->html(...$cells);
    }

    /**
     * @param array<string, string> $filters
     * @param array<array-key, string> $options
     */
    private static function selectFilter(string $attribute, array $filters, array $options): Select
    {
        $select = Select::tag()
            ->class('yii-debug-select')
            ->addAriaAttribute('label', self::filterLabel($attribute))
            ->name('Debug[' . $attribute . ']')
            ->value($filters[$attribute] ?? '')
            ->option(Option::tag()->value('')->content(''));

        foreach ($options as $value => $label) {
            $select = $select->option(Option::tag()->value((string) $value)->content($label));
        }

        return $select;
    }

    /**
     * @param list<HistoryRow> $rows
     *
     * @return list<HistoryRow>
     */
    private static function sortRows(array $rows, string|null $sort): array
    {
        [$attribute, $direction] = self::sortState($sort);

        usort(
            $rows,
            static function (HistoryRow $left, HistoryRow $right) use ($attribute, $direction): int {
                $a = self::sortValue($left, $attribute);
                $b = self::sortValue($right, $attribute);

                if ($a === null || $b === null) {
                    return ($a === null) <=> ($b === null);
                }

                return $direction === 'asc' ? $a <=> $b : $b <=> $a;
            }
        );

        return $rows;
    }

    /**
     * @return array{string, 'asc'|'desc'}
     */
    private static function sortState(string|null $sort): array
    {
        $sort ??= '';

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        $attribute = $direction === 'desc' ? substr($sort, 1) : $sort;

        return isset(self::HEADERS[$attribute]) ? [$attribute, $direction] : ['time', 'desc'];
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
     * @param array<string, string> $filters
     */
    private static function textFilter(string $attribute, array $filters, string $class = 'yii-debug-input'): InputText
    {
        return InputText::tag()
            ->class($class)
            ->addAriaAttribute('label', self::filterLabel($attribute))
            ->name("Debug[{$attribute}]")
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

        return $queryParams === []
            ? $routePrefix
            : "{$routePrefix}?" . http_build_query($queryParams);
    }
}
