<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Data\{FilterPrefix, PageSize, QueryInput};
use PHPForge\Debug\Helper\{Dump, EmptyState, LogLevel};
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
    PanelHeading,
    SortState,
    SummaryChip,
};
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Yii\DataView\GridView\GridView;

use function htmlspecialchars;
use function is_int;
use function is_string;
use function strcasecmp;

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
        return PanelIcon::LOGS->value;
    }

    public function id(): string
    {
        return 'log';
    }

    public function name(): string
    {
        return PanelTitle::LOGS->value;
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
    private static function columns(
        PanelRenderContext|null $context,
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
        $traceLine = self::renderTraceLine(...);

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
    private static function renderGrid(
        OffsetPaginator $paginator,
        PanelRenderContext|null $context = null,
        array $filters = [],
    ): string {
        $queryParams = $context === null
            ? []
            : FilterRemoval::withGroup($context->queryParams, FilterPrefix::LOG, $filters);

        /** @var GridView<LogRow> $grid */
        $grid = GridView::widget();

        $grid = $grid
            ->bodyRowAttributes(static fn(LogRow $row): array => LogCellRenderer::buildRowOptions($row))
            ->dataReader($paginator)
            ->layout('{items}')
            ->containerClass('yii-debug-table-wrap')
            ->tableClass('yii-debug-table')
            ->headerCellAttributes(['scope' => 'col'])
            ->filterCellAttributes(['class' => 'yii-debug-filter-cell'])
            ->filterFormId('yii-debug-log-filters')
            ->columns(...self::columns($context, $queryParams, $filters));

        if ($context !== null) {
            $grid = $grid->urlCreator(static fn(): string => $context->panelUrl(queryParams: []));
        }

        $footer = GridFooter::renderForPanel($paginator, $paginator->getCurrentPageSize(), $context, $queryParams);

        return Div::tag()
            ->class('yii-debug-grid yii-debug-grid-log')
            ->html($grid->render(), $footer)
            ->render();
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
        $queryParams = FilterRemoval::withGroup($context->queryParams, FilterPrefix::LOG, $filters);

        $sortedRows = self::sortRows($filteredRows, QueryInput::scalar($queryParams, 'sort'));

        $paginator = PageWindow::paginate(
            $sortedRows,
            QueryInput::scalar($queryParams, 'per-page'),
            QueryInput::scalar($queryParams, 'page'),
        );

        return self::renderGrid($paginator, $context, $filters);
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
            : FilterRemoval::withGroup($context->queryParams, FilterPrefix::LOG, $search->activeFilters);

        $filteredRows = $search->filter($entries);

        $pageSizeSelector = $context === null ? null : PageSize::selectorFor($queryParams);

        $content = $title . self::renderSummary(
            LogCounts::fromRows($entries),
            $pageSizeSelector,
            $context,
            $queryParams,
        );

        if ($context === null) {
            return $content . self::renderGrid(PageWindow::single($filteredRows));
        }

        $content .= FilterRemoval::banner($search->activeFilters, $context, $queryParams, FilterPrefix::LOG);

        if ($filteredRows === []) {
            return $content . EmptyState::card(
                LogMessage::NO_MATCH_HEADLINE->value,
                P::tag()->content(LogMessage::NO_MATCH_EXPLANATION),
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
            $item = SummaryChip::render($value, $text);

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
        $state = SortState::fromQuery($sort, self::SORT_ATTRIBUTES, 'time');

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
