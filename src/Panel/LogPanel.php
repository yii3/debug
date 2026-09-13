<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Data\{FilterPrefix, PageSize, QueryInput};
use PHPForge\Debug\Helper\{EmptyState, LogLevel, Trace};
use PHPForge\Debug\Panel\Log\{LogCellRenderer, LogCounts, LogMessage, LogRow, LogSnapshot};
use PHPForge\Debug\Panel\{PanelIcon, PanelRenderContext, PanelTitle};
use PHPForge\Debug\Toolbar\ToolbarItem;
use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use Yii3\Debug\Search\LogSearch;
use Yii3\Debug\Web\{
    FilterInput,
    FilterRemoval,
    GridColumn,
    GridFooter,
    PageWindow,
    PanelGrid,
    PanelHeading,
    SortState,
    SummaryChip,
};
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Yii\DataView\GridView\GridView;

use function strcasecmp;

/**
 * Presents captured log messages and contributes the total, error, and warning toolbar metrics.
 */
final readonly class LogPanel implements ContextAwarePanelInterface, ToolbarPanelProviderInterface
{
    /**
     * Attributes by which the log entries can be sorted in the grid view.
     */
    private const array SORT_ATTRIBUTES = [
        'time',
        'timeSincePrevious',
        'level',
        'category',
        'message',
    ];

    /**
     * @param Trace $trace Configured renderer of the argument-free source frames shown in the message cells.
     */
    public function __construct(private Trace $trace) {}

    /**
     * Reports whether the capture recorded log messages worth opening the panel for.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return bool `true` when the capture carried data; `false` otherwise.
     */
    public function hasContent(array $payload): bool
    {
        if ($payload === []) {
            return false;
        }

        self::snapshot($payload);

        return true;
    }

    /**
     * Returns the icon key shared with the built-in Logs navigation entry.
     *
     * @return string Shared Debug Core icon key.
     */
    public function icon(): string
    {
        return PanelIcon::LOGS->value;
    }

    /**
     * Returns the identifier associating the panel with its slice of the captured payload.
     *
     * @return string Stable panel identifier.
     */
    public function id(): string
    {
        return 'log';
    }

    /**
     * Returns the panel title shown in the debugger navigation.
     *
     * @return string Human-readable panel name.
     */
    public function name(): string
    {
        return PanelTitle::LOGS->value;
    }

    /**
     * Renders the detail view for a capture opened without page context.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return string Rendered panel markup.
     */
    public function render(array $payload): string
    {
        return $this->renderPanel($payload);
    }

    /**
     * Renders the detail view, letting the page context drive filtering, sorting, and paging.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     * @param PanelRenderContext $context Query parameters and theme of the page being rendered.
     *
     * @return string Rendered panel markup.
     */
    public function renderWithContext(array $payload, PanelRenderContext $context): string
    {
        return $this->renderPanel($payload, $context);
    }

    /**
     * Builds the toolbar metrics: the total message count plus the error and warning counts.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return list<ToolbarItem> Toolbar metrics in display order.
     */
    public function toolbarItems(array $payload): array
    {
        $counts = LogCounts::fromRows(self::snapshot($payload)->entries());

        $items = [ToolbarItem::create((string) $counts->total)->withId('total')];

        if ($counts->hasErrors()) {
            $items[] = ToolbarItem::create((string) $counts->errors)
                ->withLabel('Errors')
                ->withStatus('danger')
                ->withId('errors');
        }

        if ($counts->hasWarnings()) {
            $items[] = ToolbarItem::create((string) $counts->warnings)
                ->withLabel('Warnings')
                ->withStatus('warning')
                ->withId('warnings');
        }

        return $items;
    }

    /**
     * @param array<array-key, mixed> $queryParams
     * @param array<string, string> $filters
     *
     * @return list<GridColumn<LogRow>>
     */
    private function columns(
        PanelRenderContext|null $context,
        array $queryParams,
        array $filters,
    ): array {
        $state = SortState::fromQuery(
            QueryInput::scalar($queryParams, 'sort'),
            self::SORT_ATTRIBUTES,
            'time',
        );

        unset($queryParams['page']);

        $header = static function (string $attribute, string $label) use (
            $context,
            $queryParams,
            $state,
        ): string {
            if ($context === null) {
                return $label;
            }

            $isActive = $state->isActive($attribute);

            $queryParams['sort'] = $state->next($attribute, $attribute === 'timeSincePrevious');

            $link = A::tag()
                ->href($context->panelUrl(queryParams: $queryParams))
                ->content($label);

            return ($isActive ? $link->class($state->direction) : $link)->render();
        };

        $blank = $context === null ? null : '';
        $traceLine = $this->trace->render(...);

        return [
            new GridColumn(
                header: '#',
                content: static fn(LogRow $row): string => (string) $row->id,
                filter: $blank,
                encodeContent: true,
                bodyClass: 'yii-debug-nowrap',
            ),
            new GridColumn(
                header: $header('time', 'Time'),
                content: static fn(LogRow $row): string => LogCellRenderer::renderTimeCell($row),
                filter: $blank,
                encodeContent: true,
                headerClass: 'sort-numerical',
                bodyClass: 'yii-debug-nowrap',
            ),
            new GridColumn(
                header: $header('timeSincePrevious', 'Delta'),
                content: static fn(LogRow $row): string => LogCellRenderer::renderTimeSincePreviousCell($row),
                filter: $blank,
                headerClass: 'sort-numerical',
            ),
            new GridColumn(
                header: $header('level', 'Level'),
                content: static fn(LogRow $row): string => LogCellRenderer::renderLevelCell($row),
                filter: $context === null
                    ? null
                    : FilterInput::select(FilterPrefix::LOG, 'level', 'Level', $filters, self::levelOptions()),
            ),
            new GridColumn(
                header: $header('category', 'Category'),
                content: static fn(LogRow $row): string => LogCellRenderer::renderCategoryCell($row),
                filter: $context === null
                    ? null
                    : FilterInput::text(FilterPrefix::LOG, 'category', 'Category', $filters),
                bodyClass: 'yii-debug-cell-mono yii-debug-cell-fqcn',
            ),
            new GridColumn(
                header: $header('message', 'Message'),
                content: static fn(LogRow $row): string => LogCellRenderer::renderMessageCell($row, $traceLine),
                filter: $context === null
                    ? null
                    : FilterInput::text(FilterPrefix::LOG, 'message', 'Message', $filters),
            ),
        ];
    }

    /**
     * Returns the selectable severities of the level filter, mapped to the label shown for each of them.
     *
     * @return array<int, string>
     */
    private static function levelOptions(): array
    {
        return [
            LogLevel::TRACE => 'Trace',
            LogLevel::INFO => 'Info',
            LogLevel::WARNING => 'Warning',
            LogLevel::ERROR => 'Error',
        ];
    }

    /**
     * Renders the card shown when the request logged no message.
     *
     * @return string Rendered empty-state card.
     */
    private static function renderEmptyState(): string
    {
        return EmptyState::card(
            LogMessage::EMPTY_HEADLINE->value,
            P::tag()->content(LogMessage::EMPTY_EXPLANATION),
        );
    }

    /**
     * @param OffsetPaginator<int, LogRow> $paginator
     * @param array<string, string> $filters
     */
    private function renderGrid(
        OffsetPaginator $paginator,
        PanelRenderContext|null $context = null,
        array $filters = [],
    ): string {
        $queryParams = $context === null
            ? []
            : FilterRemoval::withGroup(
                $context->queryParams,
                FilterPrefix::LOG,
                $filters,
            );

        /** @var GridView<LogRow> $grid */
        $grid = PanelGrid::filterable($paginator, 'yii-debug-log-filters')
            ->bodyRowAttributes(static fn(LogRow $row): array => LogCellRenderer::buildRowOptions($row))
            ->columns(...$this->columns($context, $queryParams, $filters))
            ->urlCreator($context === null ? null : static fn(): string => $context->panelUrl(queryParams: []));

        $footer = GridFooter::renderForPanel(
            $paginator,
            $paginator->getCurrentPageSize(),
            $context,
            $queryParams,
        );

        return Div::tag()
            ->class('yii-debug-grid yii-debug-grid-log')
            ->html($grid->render(), $footer)
            ->render();
    }

    /**
     * @param list<LogRow> $filteredRows
     * @param array<string, string> $filters
     */
    private function renderPaginatedGrid(
        array $filteredRows,
        PanelRenderContext $context,
        array $filters,
    ): string {
        $queryParams = FilterRemoval::withGroup(
            $context->queryParams,
            FilterPrefix::LOG,
            $filters,
        );

        $sortedRows = self::sortRows($filteredRows, QueryInput::scalar($queryParams, 'sort'));

        $paginator = PageWindow::paginate(
            $sortedRows,
            QueryInput::scalar($queryParams, 'per-page'),
            QueryInput::scalar($queryParams, 'page'),
        );

        return $this->renderGrid($paginator, $context, $filters);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderPanel(array $payload, PanelRenderContext|null $context = null): string
    {
        $entries = self::snapshot($payload)->entries();

        $title = PanelHeading::render(PanelTitle::LOG_MESSAGES);

        if ($entries === []) {
            return $title . self::renderEmptyState();
        }

        $search = LogSearch::fromQueryParams($context->queryParams ?? []);

        $queryParams = $context === null
            ? []
            : FilterRemoval::withGroup(
                $context->queryParams,
                FilterPrefix::LOG,
                $search->activeFilters,
            );

        $filteredRows = $search->filter($entries);

        $pageSizeSelector = $context === null ? null : PageSize::selectorFor($queryParams);

        $content = $title . self::renderSummary(
            LogCounts::fromRows($entries),
            $pageSizeSelector,
            $context,
            $queryParams,
        );

        if ($context === null) {
            return $content . $this->renderGrid(PageWindow::single($filteredRows));
        }

        $content .= FilterRemoval::banner(
            $search->activeFilters,
            $context,
            $queryParams,
            FilterPrefix::LOG,
        );

        if ($filteredRows === []) {
            return $content . EmptyState::card(
                LogMessage::NO_MATCH_HEADLINE->value,
                P::tag()->content(LogMessage::NO_MATCH_EXPLANATION),
            );
        }

        return $content . $this->renderPaginatedGrid($filteredRows, $context, $search->activeFilters);
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

        $items = [SummaryChip::render((string) $counts->total, ' messages')];

        if ($counts->hasErrors()) {
            $items[] = SummaryChip::separator();
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
            $items[] = SummaryChip::separator();
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
            $items[] = SummaryChip::separator();
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
            $items[] = SummaryChip::separator();
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
        $value = (string) $count;
        $text = " {$label}";

        if ($context === null) {
            $item = SummaryChip::render(
                $value,
                $text,
            );

            return $level === LogLevel::INFO ? $item : $item->class($class);
        }

        $queryParams[FilterPrefix::LOG] = ['level' => (string) $level];

        return A::tag()
            ->addAriaAttribute('label', "{$count} {$label}; filter log messages by {$levelName} level")
            ->addAttribute('title', "Show only {$levelName} log messages")
            ->class($class)
            ->href($context->panelUrl(queryParams: $queryParams))
            ->html(Strong::tag()->content($value), $text);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function snapshot(array $payload): LogSnapshot
    {
        return LogSnapshot::fromArray(
            $payload,
            '$.panels.log',
        );
    }

    /**
     * @param list<LogRow> $rows
     *
     * @return list<LogRow>
     */
    private static function sortRows(array $rows, string|null $sort): array
    {
        $state = SortState::fromQuery(
            $sort,
            self::SORT_ATTRIBUTES,
            'time',
        );

        return $state->apply(
            $rows,
            static fn(LogRow $left, LogRow $right): int => match ($state->attribute) {
                'time' => $left->time <=> $right->time,
                'timeSincePrevious' => $left->timeSincePrevious <=> $right->timeSincePrevious,
                'level' => $left->level <=> $right->level,
                'category' => strcasecmp($left->category, $right->category),
                default => strcasecmp($left->message, $right->message),
            },
            static fn(LogRow $left, LogRow $right): int => $left->id <=> $right->id,
        );
    }
}
