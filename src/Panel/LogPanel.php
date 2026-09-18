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
use UIAwesome\Html\Phrasing\Strong;
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

use function sprintf;
use function strcasecmp;

/**
 * Presents captured log messages and contributes the total, error, and warning toolbar metrics.
 */
final readonly class LogPanel implements ToolbarPanelProviderInterface
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
     * Renders the detail view, letting the page context drive filtering, sorting, and paging.
     *
     * @param PanelRenderInput $input Payload, request context, and request summary of the page being rendered.
     *
     * @return string Rendered panel markup.
     */
    public function render(PanelRenderInput $input): string
    {
        return $this->renderPanel($input->payload, $input->context);
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
                ->withLabel(LogMessage::TOOLBAR_ERRORS->value)
                ->withStatus('danger')
                ->withId('errors');
        }

        if ($counts->hasWarnings()) {
            $items[] = ToolbarItem::create((string) $counts->warnings)
                ->withLabel(LogMessage::TOOLBAR_WARNINGS->value)
                ->withStatus('warning')
                ->withId('warnings');
        }

        return $items;
    }

    /**
     * Builds the grid columns for the captured messages.
     *
     * @param PanelRenderContext $context State of the debugger request being rendered.
     * @param array<array-key, mixed> $queryParams Raw query parameters of the debugger request.
     * @param array<string, string> $filters Active filter values keyed by attribute.
     *
     * @return list<GridColumn<LogRow>> Columns in display order.
     */
    private function columns(
        PanelRenderContext $context,
        array $queryParams,
        array $filters,
    ): array {
        $state = SortState::fromQuery(QueryInput::scalar($queryParams, 'sort'), self::SORT_ATTRIBUTES, 'time');

        unset($queryParams['page']);

        $header = static function (string $attribute, string $label) use (
            $context,
            $queryParams,
            $state,
        ): string {
            $isActive = $state->isActive($attribute);

            $queryParams['sort'] = $state->next($attribute, $attribute === 'timeSincePrevious');

            $link = A::tag()
                ->href($context->panelUrl(queryParams: $queryParams))
                ->content($label);

            return ($isActive ? $link->class($state->direction) : $link)->render();
        };

        $traceLine = $this->trace->render(...);

        return [
            new GridColumn(
                header: LogMessage::NUMBER->value,
                content: static fn(LogRow $row): string => (string) $row->id,
                filter: '',
                encodeContent: true,
                bodyClass: 'yii-debug-nowrap',
            ),
            new GridColumn(
                header: $header('time', LogMessage::TIME->value),
                content: static fn(LogRow $row): string => LogCellRenderer::renderTimeCell($row),
                filter: '',
                encodeContent: true,
                headerClass: 'sort-numerical',
                bodyClass: 'yii-debug-nowrap',
            ),
            new GridColumn(
                header: $header('timeSincePrevious', LogMessage::DELTA->value),
                content: static fn(LogRow $row): string => LogCellRenderer::renderTimeSincePreviousCell($row),
                filter: '',
                headerClass: 'sort-numerical',
            ),
            new GridColumn(
                header: $header('level', LogMessage::LEVEL->value),
                content: static fn(LogRow $row): string => LogCellRenderer::renderLevelCell($row),
                filter: FilterInput::select(
                    FilterPrefix::LOG,
                    'level',
                    LogMessage::LEVEL->value,
                    $filters,
                    self::levelOptions(),
                ),
            ),
            new GridColumn(
                header: $header('category', LogMessage::CATEGORY->value),
                content: static fn(LogRow $row): string => LogCellRenderer::renderCategoryCell($row),
                filter: FilterInput::text(FilterPrefix::LOG, 'category', LogMessage::CATEGORY->value, $filters),
                bodyClass: 'yii-debug-cell-mono yii-debug-cell-fqcn',
            ),
            new GridColumn(
                header: $header('message', LogMessage::MESSAGE->value),
                content: static fn(LogRow $row): string => LogCellRenderer::renderMessageCell($row, $traceLine),
                filter: FilterInput::text(FilterPrefix::LOG, 'message', LogMessage::MESSAGE->value, $filters),
            ),
        ];
    }

    /**
     * Returns the selectable severities of the level filter, mapped to the label shown for each of them.
     *
     * @return array<int, string> Level labels keyed by their numeric level.
     */
    private static function levelOptions(): array
    {
        return [
            LogLevel::TRACE => LogMessage::FILTER_TRACE->value,
            LogLevel::INFO => LogMessage::FILTER_INFO->value,
            LogLevel::WARNING => LogMessage::FILTER_WARNING->value,
            LogLevel::ERROR => LogMessage::FILTER_ERROR->value,
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
     * Renders the messages grid for the visible page.
     *
     * @param OffsetPaginator<int, LogRow> $paginator Paginator clamped to the visible page.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     * @param array<string, string> $filters Active filter values keyed by attribute.
     *
     * @return string Rendered grid.
     */
    private function renderGrid(
        OffsetPaginator $paginator,
        PanelRenderContext $context,
        array $filters,
    ): string {
        $queryParams = FilterRemoval::withGroup($context->queryParams, FilterPrefix::LOG, $filters);

        /** @var GridView<LogRow> $grid */
        $grid = PanelGrid::filterable($paginator, 'yii-debug-log-filters')
            ->bodyRowAttributes(static fn(LogRow $row): array => LogCellRenderer::buildRowOptions($row))
            ->columns(...$this->columns($context, $queryParams, $filters))
            ->urlCreator(static fn(): string => $context->panelUrl(queryParams: []));

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
     * Paginates the filtered messages and renders the resulting page.
     *
     * @param list<LogRow> $filteredRows Messages matching the active filters.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     * @param array<string, string> $filters Active filter values keyed by attribute.
     *
     * @return string Rendered grid.
     */
    private function renderPaginatedGrid(
        array $filteredRows,
        PanelRenderContext $context,
        array $filters,
    ): string {
        $queryParams = FilterRemoval::withGroup($context->queryParams, FilterPrefix::LOG, $filters);

        $sortedRows = self::sortRows($filteredRows, QueryInput::scalar($queryParams, 'sort'));

        $paginator = PageWindow::paginate(
            $sortedRows,
            QueryInput::scalar($queryParams, 'per-page'),
            QueryInput::scalar($queryParams, 'page'),
        );

        return $this->renderGrid($paginator, $context, $filters);
    }

    /**
     * Renders the complete Logs panel for a capture.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     *
     * @return string Rendered detail content.
     */
    private function renderPanel(array $payload, PanelRenderContext $context): string
    {
        $entries = self::snapshot($payload)->entries();

        $title = PanelHeading::render(PanelTitle::LOG_MESSAGES);

        if ($entries === []) {
            return $title . self::renderEmptyState();
        }

        $search = LogSearch::fromQueryParams($context->queryParams);

        $queryParams = FilterRemoval::withGroup($context->queryParams, FilterPrefix::LOG, $search->activeFilters);

        $filteredRows = $search->filter($entries);

        $content = $title . self::renderSummary(
            LogCounts::fromRows($entries),
            PageSize::selectorFor($queryParams),
            $context,
            $queryParams,
        );

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
     * Renders the grid heading with the per-level counters and the page-size selector.
     *
     * @param LogCounts $counts Message counts by level.
     * @param string $pageSizeSelector Rendered page-size selector.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     * @param array<array-key, mixed> $queryParams Raw query parameters of the debugger request.
     *
     * @return string Rendered heading.
     */
    private static function renderSummary(
        LogCounts $counts,
        string $pageSizeSelector,
        PanelRenderContext $context,
        array $queryParams,
    ): string {
        unset($queryParams['page']);

        $items = [SummaryChip::render((string) $counts->total, LogMessage::MESSAGES_SUFFIX->value)];

        if ($counts->hasErrors()) {
            $items[] = SummaryChip::separator();
            $items[] = self::renderSummaryLevel(
                $counts->errors,
                LogMessage::LEVEL_ERRORS->value,
                LogMessage::LEVEL_ERROR->value,
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
                LogMessage::LEVEL_WARNINGS->value,
                LogMessage::LEVEL_WARNING->value,
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
                LogMessage::LEVEL_INFO->value,
                LogMessage::LEVEL_INFO->value,
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
                LogMessage::LEVEL_TRACE->value,
                LogMessage::LEVEL_TRACE->value,
                LogLevel::TRACE,
                'yii-debug-grid-summary-stat-trace',
                $context,
                $queryParams,
            );
        }

        $items[] = $pageSizeSelector;

        return Header::tag()
            ->class('yii-debug-grid-summary')
            ->html(...$items)
            ->render();
    }

    /**
     * Renders one level counter, linking it to the grid filtered on that level.
     *
     * @param int $count Messages recorded at that level.
     * @param string $label Visible counter label.
     * @param string $levelName PSR-3 level name used in the filter link.
     * @param int $level Numeric level used in the filter link.
     * @param string $class CSS modifier of the counter.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     * @param array<array-key, mixed> $queryParams Raw query parameters of the debugger request.
     *
     * @return A Linked counter.
     */
    private static function renderSummaryLevel(
        int $count,
        string $label,
        string $levelName,
        int $level,
        string $class,
        PanelRenderContext $context,
        array $queryParams,
    ): A {
        $value = (string) $count;
        $text = " {$label}";

        $queryParams[FilterPrefix::LOG] = ['level' => (string) $level];

        return A::tag()
            ->addAriaAttribute('label', sprintf(LogMessage::CHIP_ARIA->value, $count, $label, $levelName))
            ->addAttribute('title', sprintf(LogMessage::CHIP_TITLE->value, $levelName))
            ->class($class)
            ->href($context->panelUrl(queryParams: $queryParams))
            ->html(Strong::tag()->content($value), $text);
    }

    /**
     * Decodes the captured payload into its typed snapshot.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return LogSnapshot Typed snapshot decoded from the payload.
     */
    private static function snapshot(array $payload): LogSnapshot
    {
        return LogSnapshot::fromArray($payload, '$.panels.log');
    }

    /**
     * Orders the captured messages by the submitted sort expression.
     *
     * @param list<LogRow> $rows Messages to order.
     * @param string|null $sort Submitted sort expression, or `null` to keep capture order.
     *
     * @return list<LogRow> Messages in display order.
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
