<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\Event\{EventRow, EventSnapshot};
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\EventPanel;
use Yii3\Debug\Tests\Support\GridFactory;

/**
 * Unit tests for {@see EventPanel} presenting the shared Events payload and its count chip.
 */
final class EventPanelTest extends TestCase
{
    public function testMetadataIdentifiesTheEventsPanel(): void
    {
        $panel = new EventPanel(GridFactory::panelGrid());

        self::assertSame('event', $panel->id(), 'Stable ID must pair with the event collector.');
        self::assertSame('events', $panel->icon(), 'Icon must use the shared events glyph.');
        self::assertSame('Events', $panel->name(), 'Label must match the Yii2 panel.');
    }

    public function testRenderShowsEmptyStateWithoutEvents(): void
    {
        $html = (new EventPanel(GridFactory::panelGrid()))->render(['entries' => []]);

        self::assertStringContainsString('No events captured', $html, 'Empty state must be shown.');
    }

    public function testRenderShowsSummaryAndEventTable(): void
    {
        $payload = $this->snapshot()->jsonSerialize();

        $html = (new EventPanel(GridFactory::panelGrid()))->render($payload);

        self::assertStringContainsString('2', $html, 'Summary must render the event count.');
        self::assertStringContainsString('App\Event\UserCreated', $html, 'Event class must be listed.');
    }

    public function testToolbarItemsExposeEventCountChip(): void
    {
        $items = (new EventPanel(GridFactory::panelGrid()))->toolbarItems($this->snapshot()->jsonSerialize());

        self::assertCount(1, $items, 'Exactly one count chip must be emitted.');
        self::assertSame('2', $items[0]->value, 'Chip value must expose the event count.');
    }

    public function testToolbarItemsStayEmptyWithoutEvents(): void
    {
        self::assertSame([], (new EventPanel(GridFactory::panelGrid()))->toolbarItems(['entries' => []]), 'Zero events must omit the chip.');
    }

    /**
     * Creates a representative event snapshot.
     *
     * @return EventSnapshot Representative snapshot.
     */
    private function snapshot(): EventSnapshot
    {
        return new EventSnapshot(
            [
                new EventRow(
                    time: 1_700_000_000.1,
                    name: 'App\Event\UserCreated',
                    class: 'App\Event\UserCreated',
                    isStatic: '0',
                    senderClass: '',
                ),
                new EventRow(
                    time: 1_700_000_000.2,
                    name: 'App\Event\MailSent',
                    class: 'App\Event\MailSent',
                    isStatic: '0',
                    senderClass: '',
                ),
            ],
        );
    }
}
