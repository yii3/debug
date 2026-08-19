<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\Event\{EventRow, EventSnapshot};
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\EventPanel;
use Yii3\Debug\Tests\Support\GridFactory;
use Yii3\Debug\Web\DebugUrlGenerator;

use function preg_match;
use function substr_count;

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

    public function testRenderWithContextProvidesTheCompleteFilteredEventGrid(): void
    {
        $html = (new EventPanel(GridFactory::panelGrid()))->renderWithContext(
            $this->snapshot()->jsonSerialize(),
            new PanelRenderContext(
                'request-1',
                'event',
                ['Event' => ['name' => 'MailSent']],
                'light',
                new DebugUrlGenerator(),
            ),
        );

        preg_match('/<tbody>(.*?)<\/tbody>/s', $html, $matches);
        $body = $matches[1] ?? '';

        self::assertSame(0, substr_count($body, 'UserCreated'), 'The unmatched event must be filtered out.');
        self::assertSame(
            3,
            substr_count($body, 'MailSent'),
            'The matching name and two-tone class label must remain visible.',
        );
        self::assertSame(1, substr_count($html, '>Name<'), 'The event-name column must be present once.');
        self::assertSame(1, substr_count($html, '>Class<'), 'The event-class column must be present once.');
        self::assertSame(1, substr_count($html, '>Static<'), 'The static-event column must be present once.');
        self::assertSame(
            1,
            substr_count($html, 'class="yii-debug-active-filters"'),
            'The active-filter banner must render once.',
        );
        self::assertSame(1, substr_count($html, 'yii-debug-grid-footer'), 'The full grid footer must render once.');
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
