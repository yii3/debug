<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Data\{FilterPrefix, PageSize, QueryInput};
use PHPForge\Debug\Helper\{Dump, EmptyState, LogLevel};
use PHPForge\Debug\Panel\Log\{LogCellRenderer, LogCounts, LogRow, LogSnapshot};
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Toolbar\ToolbarItem;
use PHPForge\Debug\View\Grid\ActiveFilterBanner;
use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Form\{InputText, Option, Select};
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Table\{Table, Tbody, Td, Th, Thead, Tr};
use Yii3\Debug\Search\LogSearch;
use Yii3\Debug\Web\{FilterRemoval, GridFooter, PageWindow};

use function array_replace;
use function array_slice;
use function count;
use function htmlspecialchars;
use function in_array;
use function is_int;
use function is_string;
use function str_starts_with;
use function strcasecmp;
use function substr;
use function ucfirst;
use function usort;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Presents captured log messages and contributes the total, error, and warning toolbar metrics.
 */
final readonly class LogPanel implements ContextAwarePanelInterface, ToolbarPanelProviderInterface
{
    private const array SORT_ATTRIBUTES = ['time', 'timeSincePrevious', 'level', 'category', 'message'];

    public function hasContent(array $payload): bool
    {
        if ($payload === []) {
            return false;
        }

        self::snapshot($payload);

        return true;
    }

    public function icon(): string
    {
        return 'logs';
    }

    public function id(): string
    {
        return 'log';
    }

    public function name(): string
    {
        return 'Logs';
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
        $counts = LogCounts::fromRows(self::snapshot($payload)->entries());

        $items = [new ToolbarItem(value: (string) $counts->total, id: 'total')];

        if ($counts->hasErrors()) {
            $items[] = new ToolbarItem(
                value: (string) $counts->errors,
                label: 'Errors',
                status: 'danger',
                id: 'errors',
            );
        }

        if ($counts->hasWarnings()) {
            $items[] = new ToolbarItem(
                value: (string) $counts->warnings,
                label: 'Warnings',
                status: 'warning',
                id: 'warnings',
            );
        }

        return $items;
    }

    /**
     * @param array<string, string> $filters
     */
    private static function levelFilter(array $filters): Select
    {
        $select = Select::tag()
            ->addAriaAttribute('label', 'Filter by Level')
            ->class('yii-debug-select')
            ->name(FilterPrefix::LOG . '[level]')
            ->value($filters['level'] ?? '')
            ->option(Option::tag()->value('')->content(''));

        foreach (
            [
                LogLevel::TRACE => 'Trace',
                LogLevel::INFO => 'Info',
                LogLevel::WARNING => 'Warning',
                LogLevel::ERROR => 'Error',
            ] as $value => $label
        ) {
            $select = $select->option(
                Option::tag()
                    ->value((string) $value)
                    ->content($label),
            );
        }

        return $select;
    }

    /**
     * @param array<string, string> $filters
     *
     * @return array<array-key, mixed>
     */
    private static function queryParams(PanelRenderContext $context, array $filters): array
    {
        $params = $context->queryParams;

        if ($filters === []) {
            unset($params[FilterPrefix::LOG]);
        } else {
            $params[FilterPrefix::LOG] = $filters;
        }

        return $params;
    }

    private static function renderEmptyState(): string
    {
        return EmptyState::card(
            'No log messages captured',
            P::tag()->content('This request did not emit log messages through the debug log target.'),
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
                Td::tag(),
                Td::tag()->html(self::levelFilter($filters)),
                Td::tag()->html(self::textFilter('category', $filters)),
                Td::tag()->html(self::textFilter('message', $filters)),
            );
    }

    /**
     * @param list<LogRow> $rows
     * @param array<string, string> $filters
     */
    private static function renderGrid(
        array $rows,
        int $totalRows,
        int $offset,
        PanelRenderContext|null $context = null,
        array $filters = [],
        int $page = 1,
        int $pageCount = 1,
    ): string {
        $bodyRows = [];
        $traceLine = self::renderTraceLine(...);

        foreach ($rows as $row) {
            $bodyRows[] = Tr::tag()
                ->attributes(LogCellRenderer::buildRowOptions($row))
                ->html(
                    Td::tag()
                        ->class('yii-debug-nowrap')
                        ->content((string) $row->id),
                    Td::tag()
                        ->class('yii-debug-nowrap')
                        ->content(LogCellRenderer::renderTimeCell($row)),
                    Td::tag()->html(LogCellRenderer::renderTimeSincePreviousCell($row)),
                    Td::tag()->html(LogCellRenderer::renderLevelCell($row)),
                    Td::tag()
                        ->class('yii-debug-cell-mono yii-debug-cell-fqcn')
                        ->html(LogCellRenderer::renderCategoryCell($row)),
                    Td::tag()->html(LogCellRenderer::renderMessageCell($row, $traceLine)),
                );
        }

        $queryParams = $context === null ? [] : self::queryParams($context, $filters);

        $headerRows = [self::renderHeaderRow($context, $queryParams)];

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

        $footer = GridFooter::render(
            $totalRows,
            $offset,
            count($rows),
            $page,
            $pageCount,
            $context === null ? null : static fn(int $number): string => $context->panelUrl(
                queryParams: array_replace($queryParams, ['page' => $number]),
            ),
        );

        return Div::tag()
            ->class('yii-debug-grid yii-debug-grid-log')
            ->html($table, $footer)
            ->render();
    }

    /**
     * @param array<array-key, mixed> $queryParams
     */
    private static function renderHeaderRow(PanelRenderContext|null $context, array $queryParams): Tr
    {
        [$activeAttribute, $direction] = self::sortState(QueryInput::scalar($queryParams, 'sort'));

        unset($queryParams['page']);

        $cells = [
            Th::tag()
                ->scope('col')
                ->content('#'),
        ];

        $headers = [
            'time' => 'Time',
            'timeSincePrevious' => 'Delta',
            'level' => 'Level',
            'category' => 'Category',
            'message' => 'Message',
        ];

        foreach ($headers as $attribute => $label) {
            $cell = Th::tag()->scope('col');

            if ($attribute === 'time' || $attribute === 'timeSincePrevious') {
                $cell = $cell->class('sort-numerical');
            }

            if ($context === null) {
                $cells[] = $cell->content($label);

                continue;
            }

            $isActive = $activeAttribute === $attribute;

            $queryParams['sort'] = match (true) {
                $isActive && $direction === 'asc' => "-{$attribute}",
                !$isActive && $attribute === 'timeSincePrevious' => "-{$attribute}",
                default => $attribute,
            };

            $link = A::tag()
                ->href($context->panelUrl(queryParams: $queryParams))
                ->content($label);

            $cells[] = $cell->html($isActive ? $link->class($direction) : $link);
        }

        return Tr::tag()->html(...$cells);
    }

    /**
     * @param list<LogRow> $filteredRows
     * @param array<string, string> $filters
     */
    private static function renderPaginatedGrid(
        array $filteredRows,
        PanelRenderContext $context,
        array $filters,
    ): string {
        $queryParams = self::queryParams($context, $filters);
        $sortedRows = self::sortRows($filteredRows, QueryInput::scalar($queryParams, 'sort'));

        $window = new PageWindow(
            count($sortedRows),
            QueryInput::scalar($queryParams, 'per-page'),
            QueryInput::scalar($queryParams, 'page'),
        );

        $visibleRows = array_slice($sortedRows, $window->offset, $window->limit);

        return self::renderGrid(
            $visibleRows,
            count($filteredRows),
            $window->offset,
            $context,
            $filters,
            $window->page,
            $window->pageCount,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderPanel(array $payload, PanelRenderContext|null $context = null): string
    {
        $entries = self::snapshot($payload)->entries();

        $title = H1::tag()
            ->class('yii-debug-sr-only')
            ->content('Log Messages')
            ->render();

        if ($entries === []) {
            return $title . self::renderEmptyState();
        }

        $search = LogSearch::fromQueryParams($context->queryParams ?? []);

        $queryParams = $context === null ? [] : self::queryParams($context, $search->activeFilters);

        $filteredRows = $search->filter($entries);

        $pageSizeSelector = $context === null
            ? null
            : PageSize::selectorHtml(
                PageSize::current(QueryInput::scalar($queryParams, 'per-page')),
            );
        $content = $title . self::renderSummary(
            LogCounts::fromRows($entries),
            $pageSizeSelector,
            $context,
            $queryParams,
        );

        if ($context === null) {
            return $content . self::renderGrid($filteredRows, count($filteredRows), 0);
        }

        $content .= ActiveFilterBanner::render(
            $search->activeFilters,
            static fn(array $without): string => $context->panelUrl(
                queryParams: FilterRemoval::queryParams($queryParams, FilterPrefix::LOG, $without),
            ),
        );

        if ($filteredRows === []) {
            return $content . EmptyState::card(
                'No log messages match the active filters',
                P::tag()->content('Adjust or clear the filters to show the captured messages.'),
            );
        }

        return $content . self::renderPaginatedGrid($filteredRows, $context, $search->activeFilters);
    }

    /**
     * @param array<array-key, mixed> $queryParams
     */
    private static function renderSummary(
        LogCounts $counts,
        string|null $pageSizeSelector,
        PanelRenderContext|null $context,
        array $queryParams,
    ): string {
        unset($queryParams['page']);

        $items = [
            Span::tag()
                ->html(
                    Strong::tag()->content((string) $counts->total),
                    ' messages',
                ),
        ];

        if ($counts->hasErrors()) {
            $items[] = Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·');
            $items[] = self::renderSummaryLevel(
                $counts->errors,
                'errors',
                'error',
                LogLevel::ERROR,
                'yii-debug-grid-summary-stat-danger',
                $context,
                $queryParams,
            );
        }

        if ($counts->hasWarnings()) {
            $items[] = Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·');
            $items[] = self::renderSummaryLevel(
                $counts->warnings,
                'warnings',
                'warning',
                LogLevel::WARNING,
                'yii-debug-grid-summary-stat-warn',
                $context,
                $queryParams,
            );
        }

        if ($counts->hasInfo()) {
            $items[] = Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·');
            $items[] = self::renderSummaryLevel(
                $counts->info,
                'info',
                'info',
                LogLevel::INFO,
                'yii-debug-grid-summary-stat-info',
                $context,
                $queryParams,
            );
        }

        if ($counts->hasTrace()) {
            $items[] = Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·');
            $items[] = self::renderSummaryLevel(
                $counts->trace,
                'trace',
                'trace',
                LogLevel::TRACE,
                'yii-debug-grid-summary-stat-trace',
                $context,
                $queryParams,
            );
        }

        if ($pageSizeSelector !== null) {
            $items[] = $pageSizeSelector;
        }

        return Header::tag()
            ->class('yii-debug-grid-summary')
            ->html(...$items)
            ->render();
    }

    /**
     * @param array<array-key, mixed> $queryParams
     */
    private static function renderSummaryLevel(
        int $count,
        string $label,
        string $levelName,
        int $level,
        string $class,
        PanelRenderContext|null $context,
        array $queryParams,
    ): A|Span {
        $content = [
            Strong::tag()->content((string) $count),
            " {$label}",
        ];

        if ($context === null) {
            $item = Span::tag()->html(...$content);

            return $level === LogLevel::INFO ? $item : $item->class($class);
        }

        $queryParams[FilterPrefix::LOG] = ['level' => (string) $level];

        return A::tag()
            ->addAriaAttribute('label', "{$count} {$label}; filter log messages by {$levelName} level")
            ->addAttribute('title', "Show only {$levelName} log messages")
            ->class($class)
            ->href($context->panelUrl(queryParams: $queryParams))
            ->html(...$content);
    }

    /**
     * @param array<string, mixed> $frame
     */
    private static function renderTraceLine(array $frame): string
    {
        $file = $frame['file'] ?? '';
        $line = $frame['line'] ?? '';

        if (!is_string($file) || !is_int($line)) {
            return htmlspecialchars(Dump::asString($frame), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return A::tag()
            ->href("ide://open?url=file://{$file}&line={$line}")
            ->content("{$file}:{$line}")
            ->render();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function snapshot(array $payload): LogSnapshot
    {
        return LogSnapshot::fromArray($payload, '$.panels.log');
    }

    /**
     * @param list<LogRow> $rows
     *
     * @return list<LogRow>
     */
    private static function sortRows(array $rows, string|null $sort): array
    {
        [$attribute, $direction] = self::sortState($sort);

        usort(
            $rows,
            static function (LogRow $left, LogRow $right) use ($attribute, $direction): int {
                $result = match ($attribute) {
                    'time' => $left->time <=> $right->time,
                    'timeSincePrevious' => $left->timeSincePrevious <=> $right->timeSincePrevious,
                    'level' => $left->level <=> $right->level,
                    'category' => strcasecmp($left->category, $right->category),
                    default => strcasecmp($left->message, $right->message),
                };

                if ($result !== 0) {
                    return $direction === 'desc' ? -$result : $result;
                }

                return $left->id <=> $right->id;
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
            return ['time', 'asc'];
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        $attribute = $direction === 'desc' ? substr($sort, 1) : $sort;

        return in_array($attribute, self::SORT_ATTRIBUTES, true)
            ? [$attribute, $direction]
            : ['time', 'asc'];
    }

    /**
     * @param array<string, string> $filters
     */
    private static function textFilter(string $attribute, array $filters): InputText
    {
        return InputText::tag()
            ->addAriaAttribute('label', 'Filter by ' . ucfirst($attribute))
            ->class('yii-debug-input')
            ->name(FilterPrefix::LOG . "[{$attribute}]")
            ->value($filters[$attribute] ?? '');
    }
}
