<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Data\{FilterPrefix, PageSize, QueryInput};
use PHPForge\Debug\Helper\EmptyState;
use PHPForge\Debug\Panel\Event\{EventCellRenderer, EventInspectorRenderer, EventRow, EventSequence, EventSnapshot};
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Toolbar\ToolbarItem;
use PHPForge\Debug\View\Grid\ActiveFilterBanner;
use UIAwesome\Html\Flow\{Div, P, Pre};
use UIAwesome\Html\Form\InputText;
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Table\{Table, Tbody, Td, Th, Thead, Tr};
use Yii3\Debug\Search\EventSearch;
use Yii3\Debug\Web\{FilterRemoval, GridFooter, PageWindow};

use function array_replace;
use function array_slice;
use function count;
use function in_array;
use function str_starts_with;
use function strcasecmp;
use function substr;
use function usort;

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
        return 'events';
    }

    public function id(): string
    {
        return 'event';
    }

    public function name(): string
    {
        return 'Events';
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
     * @param array<string, string> $filters
     *
     * @return array<array-key, mixed>
     */
    private static function queryParams(PanelRenderContext $context, array $filters): array
    {
        $params = $context->queryParams;

        if ($filters === []) {
            unset($params[FilterPrefix::EVENT]);
        } else {
            $params[FilterPrefix::EVENT] = $filters;
        }

        return $params;
    }

    private static function renderEmptyCaptureState(): string
    {
        return EmptyState::card(
            'No events dispatched in this request',
            P::tag()->content(
                'The Events panel records PSR-14 objects sent through the configured debug dispatcher decorator, '
                . 'so this request completed without dispatching any.',
            ),
            P::tag()->content('Dispatch an application event to populate this view:'),
            Pre::tag()
                ->class('yii-debug-empty-state-code')
                ->content('$dispatcher->dispatch(new MyEvent());'),
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
                Td::tag()->html(self::textFilter('class', 'Event', $filters)),
                Td::tag()->html(self::textFilter('senderClass', 'Source', $filters)),
            );
    }

    /**
     * @param list<EventRow> $rows
     * @param list<EventRow> $allRows
     * @param array<string, string> $filters
     */
    private static function renderGrid(
        array $rows,
        array $allRows,
        int $totalRows,
        int $offset,
        PanelRenderContext|null $context = null,
        array $filters = [],
        int $page = 1,
        int $pageCount = 1,
    ): string {
        $sequence = new EventSequence($allRows);
        $bodyRows = [];

        foreach ($rows as $row) {
            $bodyRows[] = Tr::tag()
                ->html(
                    Td::tag()
                        ->class('yii-debug-col-num')
                        ->content((string) $sequence->index($row)),
                    Td::tag()
                        ->class('yii-debug-event-time-cell')
                        ->html(EventInspectorRenderer::renderTimeCell($row, $sequence)),
                    Td::tag()
                        ->class('yii-debug-event-cell')
                        ->html(EventInspectorRenderer::renderEventCell($row, $sequence)),
                    Td::tag()
                        ->class('yii-debug-cell-mono yii-debug-cell-fqcn')
                        ->html(EventCellRenderer::renderSenderCell($row)),
                );
            $bodyRows[] = EventInspectorRenderer::renderDetailRow($row, $sequence, 4);
        }

        $queryParams = $context === null ? [] : self::queryParams($context, $filters);

        $headerRows = [
            self::renderHeaderRow($context, $queryParams),
        ];

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
     * @param array<array-key, mixed> $queryParams
     */
    private static function renderHeaderRow(PanelRenderContext|null $context, array $queryParams): Tr
    {
        [$activeAttribute, $direction] = self::sortState(QueryInput::scalar($queryParams, 'sort'));

        unset($queryParams['page']);

        $cells = [
            Th::tag()
                ->scope('col')
                ->class('yii-debug-col-num')
                ->content('#'),
        ];

        $headers = [
            'time' => 'Time',
            'class' => 'Event',
            'senderClass' => 'Source',
        ];

        foreach ($headers as $attribute => $label) {
            $cell = Th::tag()->scope('col');

            if ($attribute === 'time') {
                $cell = $cell->class('sort-numerical');
            }

            if ($context === null) {
                $cells[] = $cell->content($label);

                continue;
            }

            $isActive = $activeAttribute === $attribute;

            $queryParams['sort'] = $isActive && $direction === 'asc' ? "-{$attribute}" : $attribute;

            $link = A::tag()
                ->href($context->panelUrl(queryParams: $queryParams))
                ->content($label);

            $cells[] = $cell->html($isActive ? $link->class($direction) : $link);
        }

        return Tr::tag()->html(...$cells);
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
            $allRows,
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
            ->content('Events')
            ->render();

        if ($entries === []) {
            return $title . self::renderEmptyCaptureState();
        }

        $search = EventSearch::fromQueryParams($context->queryParams ?? []);

        $queryParams = $context === null ? [] : self::queryParams($context, $search->activeFilters);

        $filteredRows = $search->filter($entries);

        $pageSizeSelector = $context === null
            ? null
            : PageSize::selectorHtml(
                PageSize::current(QueryInput::scalar($queryParams, 'per-page')),
            );
        $content = $title . self::renderSummary($filteredRows, $pageSizeSelector);

        if ($context === null) {
            return $content . self::renderGrid($filteredRows, $entries, count($filteredRows), 0);
        }

        $content .= ActiveFilterBanner::render(
            $search->activeFilters,
            static fn(array $without): string => $context->panelUrl(
                queryParams: FilterRemoval::queryParams($queryParams, FilterPrefix::EVENT, $without),
            ),
        );

        if ($filteredRows === []) {
            return $content . EmptyState::card(
                'No events match the active filters',
                P::tag()->content('Adjust or clear the filters to show the dispatched events.'),
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
            Span::tag()
                ->html(
                    Strong::tag()->content((string) count($rows)),
                    ' events',
                ),
            Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·'),
            Span::tag()
                ->html(
                    Strong::tag()->content((string) EventRow::distinctClassCount($rows)),
                    ' classes',
                ),
        ];

        $staticCount = EventRow::staticCount($rows);

        if ($staticCount > 0) {
            $items[] = Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·');
            $items[] = Span::tag()
                ->html(
                    Strong::tag()->content((string) $staticCount),
                    ' static',
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
        [$attribute, $direction] = self::sortState($sort);

        usort(
            $rows,
            static function (EventRow $left, EventRow $right) use ($attribute, $direction): int {
                $result = match ($attribute) {
                    'time' => $left->time <=> $right->time,
                    'class' => strcasecmp($left->class, $right->class),
                    default => strcasecmp($left->senderClass, $right->senderClass),
                };

                return $direction === 'desc' ? -$result : $result;
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
    private static function textFilter(string $attribute, string $label, array $filters): InputText
    {
        return InputText::tag()
            ->addAriaAttribute('label', 'Filter by ' . $label)
            ->class('yii-debug-input')
            ->name(FilterPrefix::EVENT . "[{$attribute}]")
            ->value($filters[$attribute] ?? '');
    }
}
