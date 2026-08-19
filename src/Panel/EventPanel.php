<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Data\FilterPrefix;
use PHPForge\Debug\Helper\EmptyState;
use PHPForge\Debug\Panel\Event\{EventCellRenderer, EventRow, EventSnapshot};
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Toolbar\ToolbarItem;
use UIAwesome\Html\Flow\P;
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use Yii3\Debug\Grid\{PrefixedDropdownFilter, PrefixedTextFilter};
use Yii3\Debug\Search\EventSearch;
use Yiisoft\Data\Reader\Sort;
use Yiisoft\Yii\DataView\GridView\Column\DataColumn;

use function count;
use function is_array;

/**
 * Presents the shared Events panel payload and contributes the event-count toolbar chip.
 */
final readonly class EventPanel implements ContextAwarePanelInterface
{
    use PanelContentTrait;

    /**
     * @param PanelGrid $grid Shared debugger grid renderer.
     */
    public function __construct(private PanelGrid $grid) {}

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
        $entries = $payload['entries'] ?? null;

        if (!is_array($entries) || $entries === []) {
            return [];
        }

        return [new ToolbarItem(value: (string) count($entries))];
    }

    /**
     * Renders the context-free fallback or the complete panel grid used by snapshot pages.
     *
     * @param array<string, mixed> $payload Decoded Events payload.
     */
    private function renderPanel(array $payload, PanelRenderContext|null $context = null): string
    {
        $entries = EventSnapshot::fromArray($payload, 'panels.event')->entries();

        $title = H1::tag()
            ->class('yii-debug-sr-only')
            ->content('Events')
            ->render();

        if ($entries === []) {
            return $title
                . EmptyState::card(
                    'No events captured',
                    P::tag()->content(
                        'This request did not dispatch events through the debug dispatcher decorator.',
                    ),
                );
        }

        $queryParams = $context === null ? [] : $context->queryParams;

        $search = EventSearch::fromQueryParams($queryParams);

        $filtered = $search->filter($entries);

        $summaryItems = [
            Span::tag()
                ->html(
                    Strong::tag()->content((string) count($filtered)),
                    ' events',
                ),
            Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·'),
            Span::tag()
                ->html(
                    Strong::tag()->content((string) EventRow::distinctClassCount($entries)),
                    ' classes',
                ),
        ];
        $staticCount = EventRow::staticCount($entries);

        if ($staticCount > 0) {

            $summaryItems[] = Span::tag()
                ->class('yii-debug-grid-summary-sep')->content('·');
            $summaryItems[] = Span::tag()
                ->html(
                    Strong::tag()->content((string) $staticCount),
                    ' static',
                );
        }

        if ($context !== null) {
            $summaryItems[] = $this->grid->pageSizeSelector($context->queryParams);
        }

        $header = Header::tag()
            ->class('yii-debug-grid-summary')
            ->html(...$summaryItems);

        $full = $context !== null;

        $columns = [
            new DataColumn(
                property: 'time',
                header: 'Time',
                content: static fn(EventRow $row): string => EventCellRenderer::renderTimeCell($row),
                encodeContent: false,
                withSorting: $full,
                bodyClass: 'yii-debug-cell-mono yii-debug-nowrap',
            ),
            new DataColumn(
                property: 'name',
                header: 'Name',
                withSorting: $full,
                filter: $full
                    ? new PrefixedTextFilter(FilterPrefix::EVENT, ['aria-label' => 'Filter by Name'])
                    : false,
                filterEmpty: $full ? static fn(): bool => true : null,
                bodyClass: 'yii-debug-cell-mono',
            ),
            new DataColumn(
                property: 'class',
                header: 'Class',
                content: static fn(EventRow $row): string => EventCellRenderer::renderClassCell($row),
                encodeContent: false,
                withSorting: $full,
                filter: $full
                    ? new PrefixedTextFilter(FilterPrefix::EVENT, ['aria-label' => 'Filter by Class'])
                    : false,
                filterEmpty: $full ? static fn(): bool => true : null,
                bodyClass: 'yii-debug-cell-mono',
            ),
            new DataColumn(
                property: 'senderClass',
                header: 'Sender',
                content: static fn(EventRow $row): string => EventCellRenderer::renderSenderCell($row),
                encodeContent: false,
                withSorting: $full,
                filter: $full
                    ? new PrefixedTextFilter(FilterPrefix::EVENT, ['aria-label' => 'Filter by Sender'])
                    : false,
                filterEmpty: $full ? static fn(): bool => true : null,
                bodyClass: 'yii-debug-cell-mono',
            ),
            new DataColumn(
                property: 'isStatic',
                header: 'Static',
                content: static fn(EventRow $row): string => EventCellRenderer::renderStaticCell($row),
                encodeContent: false,
                withSorting: $full,
                filter: $full
                    ? new PrefixedDropdownFilter(
                        FilterPrefix::EVENT,
                        ['1' => 'Yes', '0' => 'No'],
                        ['aria-label' => 'Filter by Static'],
                    )
                    : false,
                filterEmpty: $full ? static fn(): bool => true : null,
                bodyClass: 'yii-debug-cell-pill',
            ),
        ];

        if ($context === null) {
            return $title . $header->render() . $this->grid->render($columns, $filtered);
        }

        $grid = $this->grid
            ->fullForContext($context, FilterPrefix::EVENT, 'yii-debug-event-filters')
            ->containerAttributes(['class' => 'yii-debug-grid yii-debug-grid-event'])
            ->dataReader(
                $this->grid->paginator(
                    $filtered,
                    $context->queryParams,
                    Sort::only(['time', 'name', 'class', 'senderClass', 'isStatic'])
                        ->withoutDefaultSorting()
                        ->withOrder(['time' => 'asc']),
                ),
            )
            ->columns(...$columns)
            ->render();

        return $title
            . $header->render()
            . $this->grid->activeFilterBanner($context, FilterPrefix::EVENT, $search->activeFilters)
            . $grid;
    }
}
