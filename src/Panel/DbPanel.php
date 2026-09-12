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
use Yii3\Debug\Db\DbExplain;
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
use function strcasecmp;

/**
 * Presents the shared Database grid with Yii3 navigation, filters, page-scoped N+1 groups, and optional query plans.
 */
final class DbPanel implements ContextAwarePanelInterface, ToolbarPanelProviderInterface
{
    /**
     * Guidance shown in the empty state, describing how to instrument a Yii3 connection so queries are captured.
     */
    private const string CAPTURE_GUIDANCE = 'Configure the development connection with '
        . 'Yii3\\Debug\\Db\\DebugDbProfiler. Rows are shown only when the driver reports them. '
        . 'After a redirect, open the previous request from History.';
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
        return PanelIcon::DATABASE->value;
    }

    public function id(): string
    {
        return 'db';
    }

    public function name(): string
    {
        return PanelTitle::DATABASE->value;
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

    public function withThresholds(int|null $criticalQueryThreshold, int|null $excessiveCallerThreshold): self
    {
        $new = clone $this;
        $new->criticalQueryThreshold = $criticalQueryThreshold;
        $new->excessiveCallerThreshold = $excessiveCallerThreshold;

        return $new;
    }

    /**
     * @param array<int, NPlusOneFinding> $findings
     * @param array<array-key, mixed> $queryParams
     * @param array<string, string> $filters
     * @return list<GridColumn<QueryRow>>
     */
    private function columns(
        DbSummary $summary,
        array $findings,
        PanelRenderContext|null $context,
        array $queryParams,
        array $filters,
    ): array {
        $state = SortState::fromQuery(
            QueryInput::scalar($queryParams, 'sort'),
            self::SORT_ATTRIBUTES,
            'seq',
        );

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
                'type' => FilterInput::select(
                    FilterPrefix::DB,
                    'type',
                    $label,
                    $filters,
                    $summary->types,
                ),
                'query' => FilterInput::text(
                    FilterPrefix::DB,
                    'query',
                    $label,
                    $filters,
                ),
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
     * @param OffsetPaginator<int, QueryRow> $paginator
     * @param array<string, string> $filters
     * @param array<array-key, mixed> $queryParams
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
     * @param array<string, mixed> $payload
     */
    private function renderPanel(array $payload, PanelRenderContext|null $context = null): string
    {
        $entries = self::snapshot($payload)->entries();

        $summary = new DbSummary($entries);

        $search = DbSearch::fromQueryParams($context->queryParams ?? []);

        $queryParams = $context === null
            ? []
            : FilterRemoval::withGroup(
                $context->queryParams,
                FilterPrefix::DB,
                $search->activeFilters,
            );
        $content = PanelHeading::render(PanelTitle::DATABASE)
            . DbSummaryRenderer::render(
                $summary,
                $context !== null && $entries !== [] ? PageSize::selectorFor($queryParams) : null,
            );

        if ($entries === []) {
            return $content . EmptyState::card(
                DbMessage::EMPTY_HEADLINE->value,
                P::tag()->content(DbMessage::EMPTY_EXPLANATION),
                P::tag()->content(self::CAPTURE_GUIDANCE),
            );
        }

        $filtered = $search->filter($entries);

        if ($context !== null) {
            $content .= FilterRemoval::banner(
                $search->activeFilters,
                $context,
                $queryParams,
                FilterPrefix::DB,
            );
        }

        if ($filtered === []) {
            return $content . EmptyState::card(
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
     * @param array<string, mixed> $payload
     */
    private static function snapshot(array $payload): DbSnapshot
    {
        return DbSnapshot::fromArray(
            $payload,
            '$.panels.db',
        );
    }
}
