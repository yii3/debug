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
final class DbPanel implements ToolbarPanelProviderInterface
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
     * The capture is listed whenever it carries a payload; the detail page and the toolbar chip surface a malformed
     * one when they hydrate it.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return bool `true` when the capture carried data; `false` otherwise.
     */
    public function hasContent(array $payload): bool
    {
        return $payload !== [];
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
     * @param SortState $state Sort state of the visible page.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     * @param array<array-key, mixed> $queryParams Raw query parameters of the debugger request.
     * @param array<string, string> $filters Active filter values keyed by attribute.
     *
     * @return list<GridColumn<QueryRow>> Columns in display order.
     */
    private function columns(
        DbSummary $summary,
        array $findings,
        SortState $state,
        PanelRenderContext $context,
        array $queryParams,
        array $filters,
    ): array {
        unset($queryParams['page']);

        $columns = [];
        $tag = $context->tag;

        $url = SortState::panelUrl($context, $queryParams);

        foreach (self::SORT_ATTRIBUTES as $attribute) {
            $label = (match ($attribute) {
                'type' => DbMessage::TYPE,
                'seq' => DbMessage::TIME,
                'duration' => DbMessage::DURATION,
                'rows' => DbMessage::ROWS,
                'duplicate' => DbMessage::DUPLICATE,
                'query' => DbMessage::QUERY,
            })->value;

            $filter = match ($attribute) {
                'type' => FilterInput::select(FilterPrefix::DB, 'type', $label, $filters, $summary->types),
                'query' => FilterInput::text(FilterPrefix::DB, 'query', $label, $filters),
                default => '',
            };

            $columns[] = new GridColumn(
                header: $state->header($attribute, $label, $url),
                content: fn(QueryRow $row): string => match ($attribute) {
                    'type' => DbQueryRenderer::renderTypeCell($row),
                    'seq' => DbQueryRenderer::renderTimeCell($row),
                    'duration' => DbQueryRenderer::renderDurationCell($row),
                    'rows' => DbQueryRenderer::renderRowsCell($row),
                    'duplicate' => (string) $row->getDuplicate(),
                    default => DbQueryRenderer::renderQueryCell(
                        $row,
                        $this->trace->render(...),
                        $this->explain->available(),
                        fn(int $seq): string => $this->urls->dbExplain($tag, $seq),
                        $findings[$row->getSequence()] ?? null,
                    ),
                },
                filter: $filter,
                headerClass: $attribute === 'type' || $attribute === 'query' ? null : 'sort-numerical',
                bodyClass: $attribute === 'query' ? null : 'yii-debug-cell-mono yii-debug-nowrap',
            );
        }

        return $columns;
    }

    /**
     * Renders the statements grid for the visible page.
     *
     * @param OffsetPaginator<int, QueryRow> $paginator Paginator clamped to the visible page.
     * @param DbSummary $summary Query metrics of the capture.
     * @param SortState $state Sort state of the visible page.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     * @param array<string, string> $filters Active filter values keyed by attribute.
     * @param array<array-key, mixed> $queryParams Raw query parameters of the debugger request.
     *
     * @return string Rendered grid.
     */
    private function renderGrid(
        OffsetPaginator $paginator,
        DbSummary $summary,
        SortState $state,
        PanelRenderContext $context,
        array $filters,
        array $queryParams,
    ): string {
        $findings = NPlusOneDetector::detect(iterator_to_array($paginator->read(), false));
        $bySequence = NPlusOneDetector::bySequence($findings);

        /** @var GridView<QueryRow> $grid */
        $grid = PanelGrid::filterable($paginator, 'yii-debug-db-filters')
            ->columns(...$this->columns($summary, $bySequence, $state, $context, $queryParams, $filters))
            ->urlCreator(static fn(): string => $context->panelUrl(queryParams: []));

        $explainAll = $this->explain->available()
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
                    GridFooter::renderForPanel($paginator, $context, $queryParams),
                )
                ->render()
            . $explainAll;
    }

    /**
     * Renders the complete Database panel for a capture.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     *
     * @return string Rendered detail content.
     */
    private function renderPanel(array $payload, PanelRenderContext $context): string
    {
        $entries = self::snapshot($payload)->entries();

        $summary = new DbSummary($entries);

        $search = DbSearch::fromQueryParams($context->queryParams);
        $queryParams = FilterRemoval::withGroup($context->queryParams, FilterPrefix::DB, $search->activeFilters);
        $content = PanelHeading::render(PanelTitle::DATABASE)
            . DbSummaryRenderer::render(
                $summary,
                $entries === [] ? null : PageSize::selectorFor($queryParams),
            );

        if ($entries === []) {
            return $content . EmptyState::card(
                DbMessage::EMPTY_HEADLINE->value,
                P::tag()->content(DbMessage::EMPTY_EXPLANATION),
                P::tag()->content(sprintf(DbMessage::CAPTURE_GUIDANCE->value, DebugDbProfiler::class)),
            );
        }

        $filtered = $search->filter($entries);

        $content .= FilterRemoval::banner($search->activeFilters, $context, $queryParams, FilterPrefix::DB);

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

        $paginator = PageWindow::paginate(
            $sorted,
            QueryInput::scalar($queryParams, 'per-page'),
            QueryInput::scalar($queryParams, 'page'),
        );

        return $content . $this->renderGrid(
            $paginator,
            $summary,
            $state,
            $context,
            $search->activeFilters,
            $queryParams,
        );
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
