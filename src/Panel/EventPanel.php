<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Data\{FilterPrefix, PageSize, QueryInput};
use PHPForge\Debug\Helper\EmptyState;
use PHPForge\Debug\Panel\Event\{
    EventCellRenderer,
    EventInspectorRenderer,
    EventMessage,
    EventRow,
    EventSequence,
    EventSnapshot,
};
use PHPForge\Debug\Panel\{PanelIcon, PanelRenderContext, PanelTitle};
use PHPForge\Debug\Toolbar\ToolbarItem;
use UIAwesome\Html\Flow\{Div, P, Pre};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Root\Header;
use Yii3\Debug\Search\EventSearch;
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
use Yiisoft\Html\Html;
use Yiisoft\Html\Tag\Tr;
use Yiisoft\Yii\DataView\GridView\GridView;

use function count;
use function iterator_to_array;
use function strcasecmp;

/**
 * Presents dispatched PSR-14 events and contributes their total to the debug toolbar.
 */
final readonly class EventPanel implements ContextAwarePanelInterface, ToolbarPanelProviderInterface
{
    private const array SORT_ATTRIBUTES = ['time', 'class', 'senderClass'];

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
        return PanelIcon::EVENTS->value;
    }

    public function id(): string
    {
        return 'event';
    }

    public function name(): string
    {
        return PanelTitle::EVENTS->value;
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
        $total = count(self::snapshot($payload)->entries());

        return $total === 0
            ? []
            : [new ToolbarItem(value: (string) $total, id: 'total')];
    }

    /**
     * @param array<array-key, mixed> $queryParams
     * @param array<string, string> $filters
     *
     * @return list<GridColumn<EventRow>>
     */
    private static function columns(
        EventSequence $sequence,
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

            $queryParams['sort'] = $state->next($attribute);

            $link = A::tag()
                ->href($context->panelUrl(queryParams: $queryParams))
                ->content($label);

            return ($isActive ? $link->class($state->direction) : $link)->render();
        };

        return [
            new GridColumn(
                header: '#',
                content: static fn(EventRow $row): string => (string) $sequence->index($row),
                filter: $context === null ? null : '',
                encodeContent: true,
                class: 'yii-debug-col-num',
            ),
            new GridColumn(
                header: $header('time', 'Time'),
                content: static fn(EventRow $row): string => EventInspectorRenderer::renderTimeCell($row, $sequence),
                filter: $context === null ? null : '',
                headerClass: 'sort-numerical',
                bodyClass: 'yii-debug-event-time-cell',
            ),
            new GridColumn(
                header: $header('class', 'Event'),
                content: static fn(EventRow $row): string => EventInspectorRenderer::renderEventCell($row, $sequence),
                filter: $context === null
                    ? null
                    : FilterInput::text(FilterPrefix::EVENT, 'class', 'Event', $filters),
                bodyClass: 'yii-debug-event-cell',
            ),
            new GridColumn(
                header: $header('senderClass', 'Source'),
                content: static fn(EventRow $row): string => EventCellRenderer::renderSenderCell($row),
                filter: $context === null
                    ? null
                    : FilterInput::text(FilterPrefix::EVENT, 'senderClass', 'Source', $filters),
                bodyClass: 'yii-debug-cell-mono yii-debug-cell-fqcn',
            ),
        ];
    }

    private static function renderEmptyCaptureState(): string
    {
        return EmptyState::card(
            EventMessage::EMPTY_HEADLINE->value,
            P::tag()->content(EventMessage::EMPTY_EXPLANATION),
            P::tag()->content(EventMessage::EMPTY_CALL_TO_ACTION),
            Pre::tag()
                ->class('yii-debug-empty-state-code')
                ->content(EventMessage::EMPTY_EXAMPLE),
        );
    }

    /**
     * @param OffsetPaginator<int, EventRow> $paginator
     * @param list<EventRow> $allRows
     * @param array<string, string> $filters
     */
    private static function renderGrid(
        OffsetPaginator $paginator,
        array $allRows,
        PanelRenderContext|null $context = null,
        array $filters = [],
    ): string {
        $sequence = new EventSequence($allRows);
        $rows = iterator_to_array($paginator->read(), false);
        $queryParams = $context === null
            ? []
            : FilterRemoval::withGroup($context->queryParams, FilterPrefix::EVENT, $filters);

        /** @var GridView<EventRow> $grid */
        $grid = GridView::widget();

        $grid = $grid
            ->afterRow(
                static fn(EventRow $row): Tr => Html::tr(['class' => 'yii-debug-event-detail-row'])
                    ->cells(
                        Html::td(
                            EventInspectorRenderer::renderDetailCell($row, $sequence),
                            ['colspan' => 4],
                        )->encode(false),
                    ),
            )
            ->dataReader($paginator)
            ->layout('{items}')
            ->containerClass('yii-debug-table-wrap')
            ->tableClass('yii-debug-table')
            ->headerCellAttributes(['scope' => 'col'])
            ->filterCellAttributes(['class' => 'yii-debug-filter-cell'])
            ->filterFormId('yii-debug-event-filters')
            ->columns(...self::columns($sequence, $context, $queryParams, $filters));

        if ($context !== null) {
            $grid = $grid->urlCreator(static fn(): string => $context->panelUrl(queryParams: []));
        }

        $table = $grid->render();

        $footer = GridFooter::renderForPanel($paginator, count($rows), $context, $queryParams);

        return Div::tag()
            ->class('yii-debug-grid yii-debug-grid-event')
            ->html(
                EventInspectorRenderer::renderControls(
                    $allRows,
                    $context === null
                        ? null
                        : static function (
                            string $attribute,
                            string $value,
                        ) use ($context, $queryParams, $filters): string {
                            $params = $queryParams;

                            unset($params['page']);

                            $params[FilterPrefix::EVENT] = [...$filters, $attribute => $value];

                            return $context->panelUrl(queryParams: $params);
                        },
                    'class',
                    'Observed through the decorated PSR-14 dispatcher. Direct calls to other dispatchers are not captured.',
                ),
                $table,
                $footer,
            )
            ->render();
    }

    /**
     * @param list<EventRow> $filteredRows
     * @param list<EventRow> $allRows
     * @param array<string, string> $filters
     */
    private static function renderPaginatedGrid(
        array $filteredRows,
        array $allRows,
        PanelRenderContext $context,
        array $filters,
    ): string {
        $queryParams = FilterRemoval::withGroup($context->queryParams, FilterPrefix::EVENT, $filters);

        $sortedRows = self::sortRows($filteredRows, QueryInput::scalar($queryParams, 'sort'));

        $paginator = PageWindow::paginate(
            $sortedRows,
            QueryInput::scalar($queryParams, 'per-page'),
            QueryInput::scalar($queryParams, 'page'),
        );

        return self::renderGrid($paginator, $allRows, $context, $filters);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderPanel(array $payload, PanelRenderContext|null $context = null): string
    {
        $entries = self::snapshot($payload)->entries();

        $title = PanelHeading::render(PanelTitle::EVENTS);

        if ($entries === []) {
            return $title . self::renderEmptyCaptureState();
        }

        $search = EventSearch::fromQueryParams($context->queryParams ?? []);

        $queryParams = $context === null
            ? []
            : FilterRemoval::withGroup($context->queryParams, FilterPrefix::EVENT, $search->activeFilters);

        $filteredRows = $search->filter($entries);

        $pageSizeSelector = $context === null ? null : PageSize::selectorFor($queryParams);

        $content = $title . self::renderSummary($filteredRows, $pageSizeSelector);

        if ($context === null) {
            return $content . self::renderGrid(PageWindow::single($filteredRows), $entries);
        }

        $content .= FilterRemoval::banner($search->activeFilters, $context, $queryParams, FilterPrefix::EVENT);

        if ($filteredRows === []) {
            return $content . EmptyState::card(
                EventMessage::NO_MATCH_HEADLINE->value,
                P::tag()->content(EventMessage::NO_MATCH_EXPLANATION),
            );
        }

        return $content . self::renderPaginatedGrid($filteredRows, $entries, $context, $search->activeFilters);
    }

    /**
     * @param list<EventRow> $rows
     */
    private static function renderSummary(array $rows, string|null $pageSizeSelector): string
    {
        $items = [
            SummaryChip::render((string) count($rows), ' events'),
            SummaryChip::separator(),
            SummaryChip::render((string) EventRow::distinctClassCount($rows), ' classes'),
        ];

        $staticCount = EventRow::staticCount($rows);

        if ($staticCount > 0) {
            $items[] = SummaryChip::separator();
            $items[] = SummaryChip::render((string) $staticCount, ' static');
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
     * @param array<string, mixed> $payload
     */
    private static function snapshot(array $payload): EventSnapshot
    {
        return EventSnapshot::fromArray($payload, '$.panels.event');
    }

    /**
     * @param list<EventRow> $rows
     *
     * @return list<EventRow>
     */
    private static function sortRows(array $rows, string|null $sort): array
    {
        $state = SortState::fromQuery($sort, self::SORT_ATTRIBUTES, 'time');

        return $state->apply(
            $rows,
            static fn(EventRow $left, EventRow $right): int => match ($state->attribute) {
                'time' => $left->time <=> $right->time,
                'class' => strcasecmp($left->class, $right->class),
                default => strcasecmp($left->senderClass, $right->senderClass),
            },
        );
    }
}
