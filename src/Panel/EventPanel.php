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
use Yii3\Debug\View\ViewMessage as AdapterMessage;
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
use Yiisoft\Html\Html;
use Yiisoft\Html\Tag\Tr;
use Yiisoft\Yii\DataView\GridView\GridView;

use function count;
use function strcasecmp;

/**
 * Presents dispatched PSR-14 events and contributes their total to the debug toolbar.
 */
final readonly class EventPanel implements ContextAwarePanelInterface, ToolbarPanelProviderInterface
{
    /**
     * Defines the sortable attributes for the event grid.
     */
    private const array SORT_ATTRIBUTES = ['time', 'class', 'senderClass'];

    /**
     * Reports whether the capture recorded dispatched events worth opening the panel for.
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
     * Returns the icon key shared with the built-in Events navigation entry.
     *
     * @return string Shared Debug Core icon key.
     */
    public function icon(): string
    {
        return PanelIcon::EVENTS->value;
    }

    /**
     * Returns the identifier associating the panel with its slice of the captured payload.
     *
     * @return string Stable panel identifier.
     */
    public function id(): string
    {
        return 'event';
    }

    /**
     * Returns the panel title shown in the debugger navigation.
     *
     * @return string Human-readable panel name.
     */
    public function name(): string
    {
        return PanelTitle::EVENTS->value;
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
     * Builds the toolbar metric: the number of events the request dispatched.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return list<ToolbarItem> Toolbar metrics, or an empty list when the capture dispatched no event.
     */
    public function toolbarItems(array $payload): array
    {
        $total = count(self::snapshot($payload)->entries());

        return $total === 0
            ? []
            : [ToolbarItem::create((string) $total)->withId('total')];
    }

    /**
     * Builds the grid columns for the captured events.
     *
     * @param EventSequence $sequence Ordering of every captured event, so row numbers survive pagination.
     * @param PanelRenderContext|null $context State of the debugger request, or `null` when the panel renders
     * standalone.
     * @param array<array-key, mixed> $queryParams Raw query parameters of the debugger request.
     * @param array<string, string> $filters Active filter values keyed by attribute.
     *
     * @return list<GridColumn<EventRow>> Columns in display order.
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
                header: EventMessage::NUMBER->value,
                content: static fn(EventRow $row): string => (string) $sequence->index($row),
                filter: $context === null ? null : '',
                encodeContent: true,
                class: 'yii-debug-col-num',
            ),
            new GridColumn(
                header: $header('time', EventMessage::TIME->value),
                content: static fn(EventRow $row): string => EventInspectorRenderer::renderTimeCell($row, $sequence),
                filter: $context === null ? null : '',
                headerClass: 'sort-numerical',
                bodyClass: 'yii-debug-event-time-cell',
            ),
            new GridColumn(
                header: $header('class', EventMessage::EVENT->value),
                content: static fn(EventRow $row): string => EventInspectorRenderer::renderEventCell($row, $sequence),
                filter: $context === null
                    ? null
                    : FilterInput::text(FilterPrefix::EVENT, 'class', EventMessage::EVENT->value, $filters),
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

    /**
     * Renders the card shown when the request dispatched no event at all.
     *
     * @return string Rendered empty-state card.
     */
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
     * Renders the events grid for the visible page.
     *
     * @param OffsetPaginator<int, EventRow> $paginator Paginator clamped to the visible page.
     * @param list<EventRow> $allRows Every captured event, numbering the rows.
     * @param PanelRenderContext|null $context State of the debugger request, or `null` when the panel renders
     * standalone.
     * @param array<string, string> $filters Active filter values keyed by attribute.
     *
     * @return string Rendered grid.
     */
    private static function renderGrid(
        OffsetPaginator $paginator,
        array $allRows,
        PanelRenderContext|null $context = null,
        array $filters = [],
    ): string {
        $sequence = new EventSequence($allRows);
        $queryParams = $context === null
            ? []
            : FilterRemoval::withGroup(
                $context->queryParams,
                FilterPrefix::EVENT,
                $filters,
            );

        /** @var GridView<EventRow> $grid */
        $grid = PanelGrid::filterable($paginator, 'yii-debug-event-filters')
            ->afterRow(
                static fn(EventRow $row): Tr => Html::tr(['class' => 'yii-debug-event-detail-row'])
                    ->cells(
                        Html::td(
                            EventInspectorRenderer::renderDetailCell($row, $sequence),
                            ['colspan' => 4],
                        )->encode(false),
                    ),
            )
            ->columns(...self::columns($sequence, $context, $queryParams, $filters))
            ->urlCreator($context === null ? null : static fn(): string => $context->panelUrl(queryParams: []));

        $table = $grid->render();

        $footer = GridFooter::renderForPanel($paginator, $paginator->getCurrentPageSize(), $context, $queryParams);

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
                    AdapterMessage::EVENT_CAPTURE_SCOPE->value,
                ),
                $table,
                $footer,
            )
            ->render();
    }

    /**
     * Paginates the filtered events and renders the resulting page.
     *
     * @param list<EventRow> $filteredRows Events matching the active filters.
     * @param list<EventRow> $allRows Every captured event, numbering the rows.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     * @param array<string, string> $filters Active filter values keyed by attribute.
     *
     * @return string Rendered grid.
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
     * Renders the complete Events panel for a capture.
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

        $title = PanelHeading::render(PanelTitle::EVENTS);

        if ($entries === []) {
            return $title . self::renderEmptyCaptureState();
        }

        $search = EventSearch::fromQueryParams($context->queryParams ?? []);

        $queryParams = $context === null
            ? []
            : FilterRemoval::withGroup(
                $context->queryParams,
                FilterPrefix::EVENT,
                $search->activeFilters,
            );

        $filteredRows = $search->filter($entries);

        $pageSizeSelector = $context === null ? null : PageSize::selectorFor($queryParams);

        $content = $title . self::renderSummary($filteredRows, $pageSizeSelector);

        if ($context === null) {
            return $content . self::renderGrid(PageWindow::single($filteredRows), $entries);
        }

        $content .= FilterRemoval::banner(
            $search->activeFilters,
            $context,
            $queryParams,
            FilterPrefix::EVENT,
        );

        if ($filteredRows === []) {
            return $content . EmptyState::card(
                EventMessage::NO_MATCH_HEADLINE->value,
                P::tag()->content(EventMessage::NO_MATCH_EXPLANATION),
            );
        }

        return $content . self::renderPaginatedGrid($filteredRows, $entries, $context, $search->activeFilters);
    }

    /**
     * Renders the grid heading with the event total and the page-size selector.
     *
     * @param list<EventRow> $rows Events matching the active filters, before pagination.
     * @param string|null $pageSizeSelector Rendered page-size selector, or `null` to omit it.
     *
     * @return string Rendered heading.
     */
    private static function renderSummary(array $rows, string|null $pageSizeSelector): string
    {
        $items = [
            SummaryChip::render((string) count($rows), EventMessage::EVENTS_SUFFIX->value),
            SummaryChip::separator(),
            SummaryChip::render((string) EventRow::distinctClassCount($rows), EventMessage::CLASSES_SUFFIX->value),
        ];

        $staticCount = EventRow::staticCount($rows);

        if ($staticCount > 0) {
            $items[] = SummaryChip::separator();
            $items[] = SummaryChip::render((string) $staticCount, EventMessage::STATIC_SUFFIX->value);
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
     * Decodes the captured payload into its typed snapshot.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return EventSnapshot Typed snapshot decoded from the payload.
     */
    private static function snapshot(array $payload): EventSnapshot
    {
        return EventSnapshot::fromArray($payload, '$.panels.event');
    }

    /**
     * Orders the captured events by the submitted sort expression.
     *
     * @param list<EventRow> $rows Captured events to order.
     * @param string|null $sort Submitted sort expression, or `null` to keep capture order.
     *
     * @return list<EventRow> Events in display order.
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
