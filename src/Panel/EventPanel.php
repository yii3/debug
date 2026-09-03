<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use Closure;
use PHPForge\Debug\Data\{FilterPrefix, PageSize, QueryInput};
use PHPForge\Debug\Helper\EmptyState;
use PHPForge\Debug\Panel\Event\{EventCellRenderer, EventRow, EventSnapshot};
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Toolbar\ToolbarItem;
use PHPForge\Debug\View\Grid\ActiveFilterBanner;
use UIAwesome\Html\Flow\{Div, P, Pre};
use UIAwesome\Html\Form\InputText;
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\List\{Li, Ul};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Table\{Table, Tbody, Td, Th, Thead, Tr};
use Yii3\Debug\Search\EventSearch;

use function array_slice;
use function ceil;
use function count;
use function in_array;
use function is_string;
use function max;
use function min;
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
     * @return Closure(list<string>): string
     */
    private static function filterRemovalUrl(PanelRenderContext $context): Closure
    {
        return static function (array $without) use ($context): string {
            $params = self::queryParams($context);

            $filters = EventSearch::fromQueryParams($params)->activeFilters;

            foreach ($without as $attribute) {
                if (is_string($attribute)) {
                    unset($filters[$attribute]);
                }
            }

            if ($filters === []) {
                unset($params[FilterPrefix::EVENT]);
            } else {
                $params[FilterPrefix::EVENT] = $filters;
            }

            unset($params['page']);

            return $context->panelUrl(queryParams: $params);
        };
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function queryParams(PanelRenderContext $context): array
    {
        $params = $context->queryParams;
        $filters = EventSearch::fromQueryParams($params)->activeFilters;

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
                Td::tag()->html(self::textFilter('class', 'Event', $filters)),
                Td::tag()->html(self::textFilter('senderClass', 'Source', $filters)),
            );
    }

    /**
     * @param list<EventRow> $rows
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

        foreach ($rows as $row) {
            $bodyRows[] = Tr::tag()
                ->html(
                    Td::tag()
                        ->class('yii-debug-cell-mono yii-debug-nowrap')
                        ->content(EventCellRenderer::renderTimeCell($row)),
                    Td::tag()
                        ->class('yii-debug-cell-mono yii-debug-cell-fqcn')
                        ->html(EventCellRenderer::renderClassCell($row)),
                    Td::tag()
                        ->class('yii-debug-cell-mono yii-debug-cell-fqcn')
                        ->html(EventCellRenderer::renderSenderCell($row)),
                );
        }

        $headerRows = [self::renderHeaderRow($context)];

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

        $begin = $totalRows === 0 ? 0 : $offset + 1;

        $end = min($offset + count($rows), $totalRows);

        $footer = Div::tag()
            ->class('yii-debug-grid-footer')
            ->html(
                Span::tag()
                    ->class('summary yii-debug-grid-count')
                    ->content("Showing {$begin}-{$end} of {$totalRows} items."),
                $context === null ? '' : self::renderPager($context, $page, $pageCount),
            );

        return Div::tag()
            ->class('yii-debug-grid yii-debug-grid-event')
            ->html($table, $footer)
            ->render();
    }

    private static function renderHeaderRow(PanelRenderContext|null $context): Tr
    {
        $cells = [];
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

            $cells[] = $context === null
                ? $cell->content($label)
                : $cell->html(self::renderSortLink($context, $attribute, $label));
        }

        return Tr::tag()->html(...$cells);
    }

    private static function renderPager(PanelRenderContext $context, int $page, int $pageCount): Ul|string
    {
        if ($pageCount <= 1) {
            return '';
        }

        $items = [];

        for ($number = 1; $number <= $pageCount; $number++) {
            $params = self::queryParams($context);
            $params['page'] = $number;

            $link = A::tag()
                ->class('yii-debug-pager-link')
                ->href($context->panelUrl(queryParams: $params))
                ->content((string) $number);
            $item = Li::tag()
                ->class('yii-debug-pager-item')
                ->html($link);

            $items[] = $number === $page ? $item->class('is-active') : $item;
        }

        return Ul::tag()
            ->class('yii-debug-pager')
            ->html(...$items);
    }

    /**
     * @param list<EventRow> $filteredRows
     * @param array<string, string> $filters
     */
    private static function renderPaginatedGrid(
        array $filteredRows,
        PanelRenderContext $context,
        array $filters,
    ): string {
        $queryParams = self::queryParams($context);
        $sortedRows = self::sortRows($filteredRows, QueryInput::scalar($queryParams, 'sort'));

        $pageSize = PageSize::resolve(QueryInput::scalar($queryParams, 'per-page'));

        $effectivePageSize = $pageSize ?? max(1, count($sortedRows));

        $pageCount = max(
            1,
            (int) ceil(count($sortedRows) / $effectivePageSize),
        );
        $page = min(
            $pageCount,
            max(1, (int) (QueryInput::scalar($queryParams, 'page') ?? '1')),
        );

        $offset = ($page - 1) * $effectivePageSize;

        $visibleRows = array_slice($sortedRows, $offset, $effectivePageSize);

        return self::renderGrid(
            $visibleRows,
            count($filteredRows),
            $offset,
            $context,
            $filters,
            $page,
            $pageCount,
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

        $queryParams = $context === null ? [] : self::queryParams($context);

        $search = EventSearch::fromQueryParams($queryParams);

        $filteredRows = $search->filter($entries);

        $pageSizeSelector = $context === null
            ? null
            : PageSize::selectorHtml(
                PageSize::current(QueryInput::scalar($queryParams, 'per-page')),
            );
        $content = $title . self::renderSummary($filteredRows, $pageSizeSelector);

        if ($context === null) {
            return $content . self::renderGrid($filteredRows, count($filteredRows), 0);
        }

        $content .= ActiveFilterBanner::render(
            $search->activeFilters,
            self::filterRemovalUrl($context),
        );

        if ($filteredRows === []) {
            return $content . EmptyState::card(
                'No events match the active filters',
                P::tag()->content('Adjust or clear the filters to show the dispatched events.'),
            );
        }

        return $content . self::renderPaginatedGrid($filteredRows, $context, $search->activeFilters);
    }

    private static function renderSortLink(PanelRenderContext $context, string $attribute, string $label): A
    {
        $queryParams = self::queryParams($context);
        [$activeAttribute, $direction] = self::sortState(QueryInput::scalar($queryParams, 'sort'));

        $isActive = $activeAttribute === $attribute;
        $params = $queryParams;
        $params['sort'] = $isActive && $direction === 'asc' ? "-{$attribute}" : $attribute;

        unset($params['page']);

        $link = A::tag()
            ->href($context->panelUrl(queryParams: $params))
            ->content($label);

        return $isActive ? $link->class($direction) : $link;
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
