<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use Closure;
use PHPForge\Debug\Data\{FilterPrefix, PageSize, QueryInput};
use PHPForge\Debug\Helper\{EmptyState, Format};
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Panel\Profile\{ProfileCellRenderer, ProfileRow, ProfilingSnapshot};
use PHPForge\Debug\Toolbar\ToolbarItem;
use PHPForge\Debug\View\Grid\ActiveFilterBanner;
use UIAwesome\Html\Flow\{Div, P, Pre};
use UIAwesome\Html\Form\InputText;
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\List\{Li, Ul};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Code, Span, Strong};
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Table\{Table, Tbody, Td, Th, Thead, Tr};
use Yii3\Debug\Search\ProfileSearch;

use function array_slice;
use function ceil;
use function count;
use function in_array;
use function is_string;
use function max;
use function min;
use function number_format;
use function str_starts_with;
use function strcasecmp;
use function substr;
use function usort;

/**
 * Presents captured profile blocks and contributes the processing-time and peak-memory toolbar metrics.
 */
final readonly class ProfilingPanel implements
    ContextAwarePanelInterface,
    ToolbarPanelProviderInterface,
    ToolbarTitleProviderInterface
{
    private const array SORT_ATTRIBUTES = ['seq', 'duration', 'category', 'info'];

    public function hasContent(array $payload): bool
    {
        self::snapshot($payload);

        return true;
    }

    public function icon(): string
    {
        return 'profiling';
    }

    public function id(): string
    {
        return 'profiling';
    }

    public function name(): string
    {
        return 'Profiling';
    }

    public function render(array $payload): string
    {
        return $this->renderPanel($payload);
    }

    public function renderWithContext(array $payload, PanelRenderContext $context): string
    {
        return $this->renderPanel($payload, $context);
    }

    public function toolbarItems(array $payload): array
    {
        $snapshot = self::snapshot($payload);

        return [
            new ToolbarItem(value: self::formatTime($snapshot->time), title: 'Total processing time'),
            new ToolbarItem(value: Format::bytesToMb($snapshot->memory, 3), title: 'Peak memory'),
        ];
    }

    public function toolbarTitle(): string
    {
        return '';
    }

    /**
     * @return Closure(list<string>): string
     */
    private static function filterRemovalUrl(PanelRenderContext $context): Closure
    {
        return static function (array $without) use ($context): string {
            $params = $context->queryParams;
            $filters = QueryInput::group($params, FilterPrefix::PROFILE);

            foreach ($without as $attribute) {
                if (is_string($attribute)) {
                    unset($filters[$attribute]);
                }
            }

            if ($filters === []) {
                unset($params[FilterPrefix::PROFILE]);
            } else {
                $params[FilterPrefix::PROFILE] = $filters;
            }

            unset($params['page']);

            return $context->panelUrl(queryParams: $params);
        };
    }

    /**
     * Formats a duration in seconds as a millisecond readout.
     */
    private static function formatTime(float $seconds): string
    {
        return number_format($seconds * 1000) . ' ms';
    }

    private static function renderEmptyState(): string
    {
        return EmptyState::card(
            'No profile blocks captured',
            P::tag()
                ->html(
                    'This request did not produce any ',
                    Code::tag()->content('ProfilerInterface::begin()'),
                    ' / ',
                    Code::tag()->content('ProfilerInterface::end()'),
                    ' blocks, so the timing table is empty.',
                ),
            P::tag()->content('To populate this view, wrap interesting sections of code with profile markers:'),
            Pre::tag()
                ->class('yii-debug-empty-state-code')
                ->content(
                    "\$profiler->begin('my-token');\n// …work…\n\$profiler->end('my-token');",
                ),
            P::tag()->content('Database queries are profiled automatically when the DB collector is configured.'),
        );
    }

    /**
     * @param array<string, string> $filters
     */
    private static function renderFilterRow(array $filters): Tr
    {
        return Tr::tag()
            ->class('filters')
            ->html(
                Td::tag(),
                Td::tag(),
                Td::tag()->html(self::textFilter('category', $filters)),
                Td::tag()->html(self::textFilter('info', $filters)),
            );
    }

    /**
     * @param list<ProfileRow> $rows
     * @param array<string, string> $filters
     */
    private static function renderGrid(
        array $rows,
        int $totalRows,
        int $offset,
        float $maxDuration,
        PanelRenderContext|null $context = null,
        array $filters = [],
        int $page = 1,
        int $pageCount = 1,
    ): string {
        $bodyRows = [];

        foreach ($rows as $row) {
            $bodyRows[] = Tr::tag()
                ->html(
                    Td::tag()
                        ->class('yii-debug-cell-mono yii-debug-nowrap')
                        ->html(ProfileCellRenderer::renderTimeCell($row)),
                    Td::tag()
                        ->class('yii-debug-cell-numeric')
                        ->html(ProfileCellRenderer::renderDurationCell($row, $maxDuration)),
                    Td::tag()
                        ->class('yii-debug-cell-mono yii-debug-cell-fqcn')
                        ->html(ProfileCellRenderer::renderCategoryCell($row)),
                    Td::tag()->html(ProfileCellRenderer::renderInfoCell($row)),
                );
        }

        $headerRows = [self::renderHeaderRow($context)];

        if ($context !== null) {
            $headerRows[] = self::renderFilterRow($filters);
        }

        $table = Div::tag()
            ->class('yii-debug-table-wrap')
            ->html(
                Table::tag()
                    ->class('yii-debug-table')
                    ->html(
                        Thead::tag()->html(...$headerRows),
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
                $context === null ? '' : self::renderPager($context, $page, $pageCount),
            );

        return Div::tag()
            ->class('yii-debug-grid yii-debug-grid-profile')
            ->html($table, $footer)
            ->render();
    }

    private static function renderHeaderRow(PanelRenderContext|null $context): Tr
    {
        $headers = [
            'seq' => 'Time',
            'duration' => 'Duration',
            'category' => 'Category',
            'info' => 'Info',
        ];

        $cells = [];

        foreach ($headers as $attribute => $label) {
            $cell = Th::tag()->scope('col');

            $cells[] = $context === null
                ? $cell->content($label)
                : $cell->html(self::renderSortLink($context, $attribute, $label));
        }

        return Tr::tag()->html(...$cells);
    }

    private static function renderPager(PanelRenderContext $context, int $page, int $pageCount): Ul|string
    {
        if ($pageCount <= 1) {
            return '';
        }

        $items = [];

        for ($number = 1; $number <= $pageCount; $number++) {
            $params = $context->queryParams;
            $params['page'] = $number;

            $link = A::tag()
                ->class('yii-debug-pager-link')
                ->href($context->panelUrl(queryParams: $params))
                ->content((string) $number);
            $item = Li::tag()
                ->class('yii-debug-pager-item')
                ->html($link);

            $items[] = $number === $page ? $item->class('is-active') : $item;
        }

        return Ul::tag()->class('yii-debug-pager')->html(...$items);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderPanel(array $payload, PanelRenderContext|null $context = null): string
    {
        $snapshot = self::snapshot($payload);

        $entries = $snapshot->entries();

        $queryParams = $context === null ? [] : $context->queryParams;

        $search = ProfileSearch::fromQueryParams($queryParams);

        $filteredRows = $search->filter($entries);

        $content = H1::tag()
            ->class('yii-debug-sr-only')
            ->content('Performance Profiling')
            ->render()
            . self::renderSummary(
                count($filteredRows),
                count($entries),
                $snapshot,
                $context === null || $filteredRows === []
                    ? null
                    : PageSize::selectorHtml(
                        PageSize::current(QueryInput::scalar($queryParams, 'per-page')),
                    ),
            );

        if ($entries === []) {
            return $content . self::renderEmptyState();
        }

        if ($context === null) {
            return $content . self::renderGrid(
                $filteredRows,
                count($filteredRows),
                0,
                ProfileRow::maxDuration($entries),
            );
        }

        $filterBanner = ActiveFilterBanner::render(
            $search->activeFilters,
            self::filterRemovalUrl($context),
        );

        if ($filteredRows === []) {
            return $content
                . $filterBanner
                . EmptyState::card(
                    'No profile blocks match the active filters',
                    P::tag()
                        ->content('Adjust or clear the filters to show the captured profile blocks.'),
                );
        }

        $sortedRows = self::sortRows($filteredRows, QueryInput::scalar($queryParams, 'sort'));

        $pageSize = PageSize::resolve(QueryInput::scalar($queryParams, 'per-page'));

        $effectivePageSize = $pageSize ?? max(1, count($sortedRows));

        $pageCount = max(
            1,
            (int) ceil(count($sortedRows) / $effectivePageSize),
        );
        $page = min(
            $pageCount,
            max(1, (int) (QueryInput::scalar($queryParams, 'page') ?? '1')),
        );

        $offset = ($page - 1) * $effectivePageSize;

        $visibleRows = array_slice($sortedRows, $offset, $effectivePageSize);

        return $content
            . $filterBanner
            . self::renderGrid(
                $visibleRows,
                count($filteredRows),
                $offset,
                ProfileRow::maxDuration($entries),
                $context,
                $search->activeFilters,
                $page,
                $pageCount,
            );
    }

    private static function renderSortLink(PanelRenderContext $context, string $attribute, string $label): A
    {
        [$activeAttribute, $direction] = self::sortState(QueryInput::scalar($context->queryParams, 'sort'));

        $isActive = $activeAttribute === $attribute;
        $nextSort = $isActive && $direction === 'asc' ? "-{$attribute}" : $attribute;
        $params = $context->queryParams;
        $params['sort'] = $nextSort;

        unset($params['page']);

        $link = A::tag()
            ->href($context->panelUrl(queryParams: $params))
            ->content($label);

        return $isActive ? $link->class($direction) : $link;
    }

    private static function renderSummary(
        int $filteredCount,
        int $totalCount,
        ProfilingSnapshot $snapshot,
        string|null $pageSizeSelector,
    ): string {
        $items = [
            Span::tag()
                ->html(
                    Strong::tag()->content((string) $filteredCount),
                    " of {$totalCount} profile block" . ($totalCount === 1 ? '' : 's'),
                ),
            Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·'),
            Span::tag()
                ->html(
                    Strong::tag()->content(self::formatTime($snapshot->time)),
                    ' total',
                ),
            Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·'),
            Span::tag()
                ->html(
                    Strong::tag()->content(Format::bytesToMb($snapshot->memory, 3)),
                    ' peak',
                ),
        ];

        if ($pageSizeSelector !== null) {
            $items[] = $pageSizeSelector;
        }

        return Header::tag()
            ->class('yii-debug-grid-summary')
            ->html(...$items)
            ->render();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function snapshot(array $payload): ProfilingSnapshot
    {
        return ProfilingSnapshot::fromArray($payload, '$.panels.profiling');
    }

    /**
     * @param list<ProfileRow> $rows
     *
     * @return list<ProfileRow>
     */
    private static function sortRows(array $rows, string|null $sort): array
    {
        [$attribute, $direction] = self::sortState($sort);

        usort(
            $rows,
            static function (ProfileRow $left, ProfileRow $right) use ($attribute, $direction): int {
                $result = match ($attribute) {
                    'seq' => $left->seq <=> $right->seq,
                    'duration' => $left->duration <=> $right->duration,
                    'category' => strcasecmp($left->category, $right->category),
                    default => strcasecmp($left->info, $right->info),
                };

                if ($result !== 0) {
                    return $direction === 'desc' ? -$result : $result;
                }

                return $left->seq <=> $right->seq;
            },
        );

        return $rows;
    }

    /**
     * @return array{string, 'asc'|'desc'}
     */
    private static function sortState(string|null $sort): array
    {
        if ($sort === null || $sort === '') {
            return ['duration', 'desc'];
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        $attribute = $direction === 'desc' ? substr($sort, 1) : $sort;

        return in_array($attribute, self::SORT_ATTRIBUTES, true)
            ? [$attribute, $direction]
            : ['duration', 'desc'];
    }

    /**
     * @param array<string, string> $filters
     */
    private static function textFilter(string $attribute, array $filters): InputText
    {
        return InputText::tag()
            ->class('yii-debug-input')
            ->name(FilterPrefix::PROFILE . "[{$attribute}]")
            ->value($filters[$attribute] ?? '');
    }
}
