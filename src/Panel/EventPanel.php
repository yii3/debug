<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Helper\EmptyState;
use PHPForge\Debug\Panel\Event\{EventCellRenderer, EventRow, EventSnapshot};
use PHPForge\Debug\Toolbar\ToolbarItem;
use UIAwesome\Html\Flow\P;
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use Yiisoft\Yii\DataView\GridView\Column\DataColumn;

use function count;
use function is_array;

/**
 * Presents the shared Events panel payload and contributes the event-count toolbar chip.
 */
final readonly class EventPanel implements PanelInterface
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
        $entries = EventSnapshot::fromArray($payload, 'panels.event')->entries();
        $title = H1::tag()->class('yii-debug-sr-only')->content('Events')->render();

        if ($entries === []) {
            return $title
                . EmptyState::card(
                    'No events captured',
                    P::tag()->content(
                        'This request did not dispatch events through the debug dispatcher decorator.',
                    ),
                );
        }

        $header = Header::tag()
            ->class('yii-debug-grid-summary')
            ->html(
                Span::tag()->html(Strong::tag()->content((string) count($entries)), ' events'),
                Span::tag()->class('yii-debug-grid-summary-sep')->content('·'),
                Span::tag()->html(
                    Strong::tag()->content((string) EventRow::distinctClassCount($entries)),
                    ' classes',
                ),
            );
        $columns = [
            new DataColumn(
                header: 'Time',
                content: static fn(EventRow $row): string => EventCellRenderer::renderTimeCell($row),
                encodeContent: false,
                withSorting: false,
                bodyClass: 'yii-debug-cell-num',
            ),
            new DataColumn(
                header: 'Event',
                content: static fn(EventRow $row): string => EventCellRenderer::renderClassCell($row),
                encodeContent: false,
                withSorting: false,
            ),
            new DataColumn(
                header: 'Sender',
                content: static fn(EventRow $row): string => EventCellRenderer::renderSenderCell($row),
                encodeContent: false,
                withSorting: false,
            ),
        ];

        return $title . $header->render() . $this->grid->render($columns, $entries);
    }

    public function toolbarItems(array $payload): array
    {
        $entries = $payload['entries'] ?? null;

        if (!is_array($entries) || $entries === []) {
            return [];
        }

        return [new ToolbarItem(value: (string) count($entries))];
    }
}
