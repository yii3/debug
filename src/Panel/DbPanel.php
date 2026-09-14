<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Data\{FilterPrefix, PageSize, QueryInput};
use PHPForge\Debug\Helper\{EmptyState, Trace};
use PHPForge\Debug\Panel\Db\{
    DbMessage,
    DbQueryRenderer,
    DbSnapshot,
    DbSummary,
    DbSummaryRenderer,
    NPlusOneDetector,
    NPlusOneFinding,
    QueryRow,
};
use PHPForge\Debug\Panel\{PanelIcon, PanelRenderContext, PanelTitle};
use PHPForge\Debug\Toolbar\ToolbarItem;
use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Form\Button;
use UIAwesome\Html\Palpable\A;
use Yii3\Debug\Db\{DbExplain, DebugDbProfiler};
use Yii3\Debug\Search\DbSearch;
use Yii3\Debug\Web\{
    DebugUrlGenerator,
    FilterInput,
    FilterRemoval,
    GridColumn,
    GridFooter,
    PageWindow,
    PanelGrid,
    PanelHeading,
    SortState
};
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Yii\DataView\GridView\GridView;

use function iterator_to_array;
use function sprintf;
use function strcasecmp;

/**
 * Presents the shared Database grid with Yii3 navigation, filters, page-scoped N+1 groups, and optional query plans.
 */
final class DbPanel implements ContextAwarePanelInterface, ToolbarPanelProviderInterface
{
    /**
     * Defines the sortable attributes for the database grid.
     */
    private const array SORT_ATTRIBUTES = [
        'type',
        'seq',
        'duration',
        'rows',
        'duplicate',
        'query',
    ];
    /**
     * Defines the duration threshold that marks a query as critical.
     */
    private int|null $criticalQueryThreshold = null;
    /**
     * Defines the caller threshold that marks a query group as excessive.
     */
    private int|null $excessiveCallerThreshold = null;

    /**
     * @param DbExplain $explain Query-plan runner of the development connection.
     * @param DebugUrlGenerator $urls Adapter-owned URL generator backing the query-plan links.
     * @param Trace $trace Configured renderer of the argument-free source frames shown beside each query.
     */
    public function __construct(
        private readonly DbExplain $explain,
        private readonly DebugUrlGenerator $urls,
        private readonly Trace $trace,
    ) {}

    /**
     * Returns the query count above which a request is critical, or `0` when the check is disabled.
     *
     * @return int Threshold in statements; `0` when the check is disabled.
     */
    public function criticalQueryThreshold(): int
    {
        return $this->criticalQueryThreshold ?? 0;
    }

    /**
     * Reports whether the capture recorded database statements worth opening the panel for.
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
     * Returns the icon key shared with the built-in Database navigation entry.
     *
     * @return string Shared Debug Core icon key.
     */
    public function icon(): string
    {
        return PanelIcon::DATABASE->value;
    }

    /**
     * Returns the identifier associating the panel with its slice of the captured payload.
     *
     * @return string Stable panel identifier.
     */
    public function id(): string
    {
        return 'db';
    }

    /**
     * Returns whether the given query count exceeds the configured critical threshold.
     *
     * @param int $count Statements executed during the captured request.
     *
     * @return bool `true` when the count exceeds the threshold; `false` otherwise.
     */
    public function isQueryCountCritical(int $count): bool
    {
        return $this->criticalQueryThreshold !== null && $count > $this->criticalQueryThreshold;
    }

    /**
     * Returns the panel title shown in the debugger navigation.
     *
     * @return string Human-readable panel name.
     */
    public function name(): string
    {
        return PanelTitle::DATABASE->value;
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
     * Builds the toolbar metric: the executed statement count, flagged when a threshold is exceeded.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return list<ToolbarItem> Toolbar metrics, or an empty list when the capture executed no statement.
     */
    public function toolbarItems(array $payload): array
    {
        $summary = new DbSummary(self::snapshot($payload)->entries());

        if ($summary->count === 0) {
            return [];
        }

        $warning = $summary->hasWarning($this->criticalQueryThreshold, $this->excessiveCallerThreshold);

        return [
            ToolbarItem::create((string) $summary->count)
                ->withId('total')
                ->withStatus($warning ? 'warning' : 'info')
                ->withTitle(
                    DbSummaryRenderer::toolbarTitle(
                        $summary,
                        $this->criticalQueryThreshold,
                        $this->excessiveCallerThreshold,
                    ),
                ),
        ];
    }

    /**
     * Returns a new instance with the thresholds that mark a capture as worth attention.
     *
     * @param int|null $criticalQueryThreshold Statement count above which the capture is critical, or `null` to
     * disable the check.
     * @param int|null $excessiveCallerThreshold Statements per call site that flag it, or `null` to disable the
     * check.
     *
     * @return self New instance carrying the requested thresholds.
     */
    public function withThresholds(int|null $criticalQueryThreshold, int|null $excessiveCallerThreshold): self
    {
        $new = clone $this;
        $new->criticalQueryThreshold = $criticalQueryThreshold;
        $new->excessiveCallerThreshold = $excessiveCallerThreshold;

        return $new;
    }

    /**
     * Builds the grid columns for the captured statements.
     *
     * @param DbSummary $summary Query metrics of the capture.
     * @param array<int, NPlusOneFinding> $findings N+1 groups detected on the visible page.
     * @param PanelRenderContext|null $context State of the debugger request, or `null` when the panel renders
     * standalone.
     * @param array<array-key, mixed> $queryParams Raw query parameters of the debugger request.
     * @param array<string, string> $filters Active filter values keyed by attribute.
     *
     * @return list<GridColumn<QueryRow>> Columns in display order.
     */
    private function columns(
        DbSummary $summary,
        array $findings,
        PanelRenderContext|null $context,
        array $queryParams,
        array $filters,
    ): array {
        $state = SortState::fromQuery(QueryInput::scalar($queryParams, 'sort'), self::SORT_ATTRIBUTES, 'seq');

        unset($queryParams['page']);

        $columns = [];
        $tag = $context->tag ?? '';

        foreach (self::SORT_ATTRIBUTES as $attribute) {
            $label = (match ($attribute) {
                'type' => DbMessage::TYPE,
                'seq' => DbMessage::TIME,
                'duration' => DbMessage::DURATION,
                'rows' => DbMessage::ROWS,
                'duplicate' => DbMessage::DUPLICATE,
                'query' => DbMessage::QUERY,
            })->value;

            $header = $label;

            if ($context !== null) {
                $link = A::tag()
                    ->href($context->panelUrl(queryParams: [...$queryParams, 'sort' => $state->next($attribute)]))
                    ->content($label);
                $header = ($state->isActive($attribute) ? $link->class($state->direction) : $link)->render();
            }

            $filter = match ($attribute) {
                'type' => FilterInput::select(FilterPrefix::DB, 'type', $label, $filters, $summary->types),
                'query' => FilterInput::text(FilterPrefix::DB, 'query', $label, $filters),
                default => '',
            };

            $columns[] = new GridColumn(
                header: $header,
                content: fn(QueryRow $row): string => match ($attribute) {
                    'type' => DbQueryRenderer::renderTypeCell($row),
                    'seq' => DbQueryRenderer::renderTimeCell($row),
                    'duration' => DbQueryRenderer::renderDurationCell($row),
                    'rows' => DbQueryRenderer::renderRowsCell($row),
                    'duplicate' => (string) $row->getDuplicate(),
                    default => DbQueryRenderer::renderQueryCell(
                        $row,
                        $this->trace->render(...),
                        $context !== null && $this->explain->available(),
                        fn(int $seq): string => $this->urls->dbExplain($tag, $seq),
                        $findings[$row->getSequence()] ?? null,
                    ),
                },
                filter: $context === null ? null : $filter,
                headerClass: $attribute === 'type' || $attribute === 'query' ? null : 'sort-numerical',
                bodyClass: $attribute === 'query' ? null : 'yii-debug-cell-mono yii-debug-nowrap',
                headerAttributes: $state->isActive($attribute) ? ['aria-sort' => $state->ariaSort()] : [],
            );
        }

        return $columns;
    }

    /**
     * Renders the statements grid for the visible page.
     *
     * @param OffsetPaginator<int, QueryRow> $paginator Paginator clamped to the visible page.
     * @param DbSummary $summary Query metrics of the capture.
     * @param PanelRenderContext|null $context State of the debugger request, or `null` when the panel renders
     * standalone.
     * @param array<string, string> $filters Active filter values keyed by attribute.
     * @param array<array-key, mixed> $queryParams Raw query parameters of the debugger request.
     *
     * @return string Rendered grid.
     */
    private function renderGrid(
        OffsetPaginator $paginator,
        DbSummary $summary,
        PanelRenderContext|null $context,
        array $filters,
        array $queryParams,
    ): string {
        $findings = NPlusOneDetector::detect(iterator_to_array($paginator->read(), false));
        $bySequence = NPlusOneDetector::bySequence($findings);

        /** @var GridView<QueryRow> $grid */
        $grid = PanelGrid::filterable($paginator, 'yii-debug-db-filters')
            ->columns(...$this->columns($summary, $bySequence, $context, $queryParams, $filters))
            ->urlCreator($context === null ? null : static fn(): string => $context->panelUrl(queryParams: []));

        $explainAll = $context !== null && $this->explain->available()
            ? Div::tag()->class('yii-debug-db-explain-all')->html(
                Button::tag()
                    ->addAriaAttribute('expanded', 'false')
                    ->class('yii-debug-btn yii-debug-btn-ghost yii-debug-btn-sm yii-debug-db-explain-all-toggle')
                    ->content(DbMessage::EXPLAIN_ALL)
                    ->type('button'),
            )->render()
            : '';

        return DbQueryRenderer::renderNPlusOneSummary($findings, DbMessage::PAGE_SCOPE->value)
            . Div::tag()
                ->class('yii-debug-grid yii-debug-grid-db')
                ->html(
                    $grid->render(),
                    GridFooter::renderForPanel(
                        $paginator,
                        $paginator->getCurrentPageSize(),
                        $context,
                        $queryParams,
                    ),
                )
                ->render()
            . $explainAll;
    }

    /**
     * Renders the complete Database panel for a capture.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     * @param PanelRenderContext|null $context State of the debugger request, or `null` when the panel renders
     * standalone.
     *
     * @return string Rendered detail content.
     */
    private function renderPanel(array $payload, PanelRenderContext|null $context = null): string
    {
        $entries = self::snapshot($payload)->entries();

        $summary = new DbSummary($entries);

        $search = DbSearch::fromQueryParams($context->queryParams ?? []);

        $queryParams = $context === null
            ? []
            : FilterRemoval::withGroup($context->queryParams, FilterPrefix::DB, $search->activeFilters);
        $content = PanelHeading::render(PanelTitle::DATABASE)
            . DbSummaryRenderer::render(
                $summary,
                $context !== null && $entries !== [] ? PageSize::selectorFor($queryParams) : null,
            );

        if ($entries === []) {
            return $content . EmptyState::card(
                DbMessage::EMPTY_HEADLINE->value,
                P::tag()->content(DbMessage::EMPTY_EXPLANATION),
                P::tag()->content(sprintf(DbMessage::CAPTURE_GUIDANCE->value, DebugDbProfiler::class)),
            );
        }

        $filtered = $search->filter($entries);

        if ($context !== null) {
            $content .= FilterRemoval::banner($search->activeFilters, $context, $queryParams, FilterPrefix::DB);
        }

        if ($filtered === []) {
            return $content
                . EmptyState::card(
                    DbMessage::NO_MATCH_HEADLINE->value,
                    P::tag()->content(DbMessage::NO_MATCH_EXPLANATION),
                );
        }

        $state = SortState::fromQuery(
            QueryInput::scalar($queryParams, 'sort'),
            self::SORT_ATTRIBUTES,
            'seq',
        );

        $sorted = $state->apply(
            $filtered,
            static fn(QueryRow $a, QueryRow $b): int => match ($state->attribute) {
                'type' => strcasecmp($a->getType(), $b->getType()),
                'query' => strcasecmp($a->getQuery(), $b->getQuery()),
                'duration' => $a->getDuration() <=> $b->getDuration(),
                'rows' => $a->getRows() <=> $b->getRows(),
                'duplicate' => $a->getDuplicate() <=> $b->getDuplicate(),
                default => $a->getSequence() <=> $b->getSequence(),
            },
            static fn(QueryRow $a, QueryRow $b): int => $a->getSequence() <=> $b->getSequence(),
        );

        $paginator = $context === null
            ? PageWindow::single($sorted)
            : PageWindow::paginate(
                $sorted,
                QueryInput::scalar($queryParams, 'per-page'),
                QueryInput::scalar($queryParams, 'page'),
            );

        return $content . $this->renderGrid($paginator, $summary, $context, $search->activeFilters, $queryParams);
    }

    /**
     * Decodes the captured payload into its typed snapshot.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return DbSnapshot Typed snapshot decoded from the payload.
     */
    private static function snapshot(array $payload): DbSnapshot
    {
        return DbSnapshot::fromArray($payload, '$.panels.db');
    }
}
