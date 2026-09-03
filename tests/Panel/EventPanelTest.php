<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\Event\{EventRow, EventSnapshot};
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Storage\HydrationException;
use PHPForge\Debug\Toolbar\ToolbarItem;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\EventPanel;
use Yii3\Debug\Web\DebugUrlGenerator;

use function array_map;
use function array_slice;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function str_pad;
use function substr_count;
use function trim;

use const STR_PAD_LEFT;

/**
 * Unit tests for the built-in Events grid, query controls, and toolbar metric.
 */
final class EventPanelTest extends TestCase
{
    public function testContextFreeRenderUsesTheSharedGridAndEscapesCapturedMetadata(): void
    {
        $payload = (
            new EventSnapshot(
                [
                    new EventRow(
                        1_700_000_000.123,
                        '<script>alert("name")</script>',
                        'App\\Event\\<script>alert("class")</script>',
                        '0',
                        'App\\Source\\<script>alert("source")</script>',
                    ),
                ],
            )
        )->jsonSerialize();

        $html = (new EventPanel())->render($payload);

        preg_match_all('~<th[^>]*>\s*([^<]+?)\s*</th>~s', $html, $headerMatches);

        self::assertSame(
            ['Time', 'Event', 'Source'],
            array_map(static fn(string $heading): string => trim($heading), $headerMatches[1]),
            'The Yii3 grid must omit the duplicate Name and invariant Sender and Static columns.',
        );

        self::assertStringContainsString(
            'yii-debug-grid yii-debug-grid-event',
            $html,
            'The shared Events grid variant must be applied.',
        );
        self::assertStringContainsString(
            '<strong>1</strong> events',
            $html,
            'The summary must report the captured total.',
        );
        self::assertStringContainsString(
            '<strong>1</strong> classes',
            $html,
            'The summary must report distinct event classes.',
        );
        self::assertStringContainsString(
            '&lt;script&gt;alert(&quot;class&quot;)&lt;/script&gt;',
            $html,
            'Event classes must be HTML-escaped by the shared renderer.',
        );
        self::assertStringContainsString(
            '&lt;script&gt;alert(&quot;source&quot;)&lt;/script&gt;',
            $html,
            'Source classes must be HTML-escaped by the shared renderer.',
        );
        self::assertStringNotContainsString(
            '<script>',
            $html,
            'Captured metadata must never render executable markup.',
        );
        self::assertStringNotContainsString(
            'name="Event[class]"',
            $html,
            'Context-free rendering must not emit query controls.',
        );
    }

    public function testEveryVisibleColumnSortsAndDefaultOrderIsTimeAscending(): void
    {
        $payload = (
            new EventSnapshot(
                [
                    new EventRow(3.0, 'Zulu', 'App\\AClass', '0', 'App\\MSource'),
                    new EventRow(1.0, 'Mike', 'App\\MClass', '0', 'App\\ZSource'),
                    new EventRow(2.0, 'Alpha', 'App\\ZClass', '0', 'App\\ASource'),
                ],
            )
        )->jsonSerialize();

        foreach (
            [
                [null, 'MClass'],
                ['class', 'AClass'],
                ['senderClass', 'ZClass'],
            ] as [$sort, $firstEvent]
        ) {
            $query = ['per-page' => '1'];

            if ($sort !== null) {
                $query['sort'] = $sort;
            }

            $body = self::tbody((new EventPanel())->renderWithContext($payload, self::context($query)));

            self::assertStringContainsString(
                $firstEvent,
                $body,
                sprintf('The %s ordering must select the expected first row.', $sort ?? 'default Time'),
            );
            self::assertSame(
                1,
                substr_count($body, '<tr>'),
                'A one-row page must render exactly one sorted row.',
            );
        }

        $html = (new EventPanel())->renderWithContext($payload, self::context([]));

        foreach (['sort=-time', 'sort=class', 'sort=senderClass'] as $sortUrl) {
            self::assertStringContainsString(
                $sortUrl,
                $html,
                "The {$sortUrl} column must expose a sortable header link.",
            );
        }

        self::assertStringNotContainsString(
            'sort=name',
            $html,
            'The duplicate Name sort must not be exposed.',
        );
        self::assertStringNotContainsString(
            'sort=isStatic',
            $html,
            'The invariant Static sort must not be exposed.',
        );
    }

    public function testFilterRemovalUrlsPreserveUnrelatedStateAndResetPagination(): void
    {
        $html = (
            new EventPanel())
                ->renderWithContext(
                    self::payload(),
                    self::context(
                        [
                            'Event' => [
                                'name' => 'User',
                                'isStatic' => '0',
                                'unknown' => 'drop-me',
                            ],
                            'sort' => '-class',
                            'per-page' => '25',
                            'page' => '2',
                            'yii_debug_theme' => 'dark',
                            'return' => 'overview',
                            'Other' => ['key' => 'value'],
                        ],
                    ),
                );

        self::assertStringContainsString(
            'href="/debug/view?tag=request-1&amp;panel=event&amp;Event%5BisStatic%5D=0&amp;sort=-class'
                . '&amp;per-page=25&amp;yii_debug_theme=dark&amp;return=overview&amp;Other%5Bkey%5D=value"',
            $html,
            'Removing Name must preserve the other Event filter and unrelated state while resetting page.',
        );
        self::assertStringContainsString(
            'href="/debug/view?tag=request-1&amp;panel=event&amp;sort=-class&amp;per-page=25'
                . '&amp;yii_debug_theme=dark&amp;return=overview&amp;Other%5Bkey%5D=value"',
            $html,
            'Clear All must remove only Event filters while preserving unrelated state and resetting page.',
        );
        self::assertStringNotContainsString(
            'drop-me',
            $html,
            'Unknown Event attributes must not reach controls or URLs.',
        );
        self::assertStringNotContainsString(
            '&amp;page=2',
            $html,
            'Filter-removal and sort links must reset pagination.',
        );
        self::assertStringContainsString(
            '<option value="25" selected>',
            $html,
            'Page size must retain its active value.',
        );
    }

    public function testMalformedAndUnknownFiltersAreIgnored(): void
    {
        $html = (
            new EventPanel())
                ->renderWithContext(
                    self::payload(),
                    self::context(
                        [
                            'Event' => [
                                'name' => ['invalid'],
                                'isStatic' => 'invalid',
                                'unknown' => 'value',
                            ],
                        ],
                    ),
                );

        self::assertStringContainsString(
            '<strong>3</strong> events',
            $html,
            'Malformed and unknown filters must not change the result set.',
        );
        self::assertStringNotContainsString(
            'yii-debug-active-filters',
            $html,
            'Malformed and unknown filters must not create an active-filter banner.',
        );
        self::assertSame(
            3,
            substr_count(self::tbody($html), '<tr>'),
            'Every captured row must remain visible.',
        );
    }

    public function testMalformedPayloadRetainsTheNativeHydrationFailure(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            'Invalid debug snapshot',
        );

        (new EventPanel())
            ->render(['entries' => 'invalid']);
    }

    public function testMetadataVisibilityAndInterfacesIdentifyTheBuiltInPanel(): void
    {
        $panel = new EventPanel();

        self::assertSame(
            'event',
            $panel->id(),
            "Stable panel ID must be 'event'.",
        );
        self::assertSame(
            'Events',
            $panel->name(),
            "Panel name must be 'Events'.",
        );
        self::assertSame(
            'events',
            $panel->icon(),
            "Panel icon must be 'events'.",
        );
        self::assertFalse(
            $panel->hasContent([]),
            'An absent capture must stay hidden.',
        );
        self::assertTrue(
            $panel->hasContent(['entries' => []]),
            'A valid empty capture must remain discoverable so its empty state can render.',
        );
    }

    public function testPaginationDefaultsToFiftySupportsAllAndCapsAtOneThousand(): void
    {
        $rows = [];

        for ($index = 1; $index <= 1_001; $index++) {
            $name = 'Event' . str_pad((string) $index, 4, '0', STR_PAD_LEFT);

            $rows[] = new EventRow((float) $index, $name, 'App\\Event\\' . $name, '0', '');
        }

        $payload = (new EventSnapshot($rows))->jsonSerialize();
        $defaultHtml = (new EventPanel())->renderWithContext($payload, self::context([]));

        self::assertSame(
            50,
            substr_count(self::tbody($defaultHtml), '<tr>'),
            'The default Events page must show fifty rows.',
        );
        self::assertStringContainsString(
            'Showing 1-50 of 1001 items.',
            $defaultHtml,
            'The footer must report the default filtered pagination window.',
        );
        self::assertStringContainsString(
            '<option value="50" selected>',
            $defaultHtml,
            'The default selector must be 50.',
        );

        foreach (['10', '25', '50', '100', 'all'] as $option) {
            self::assertStringContainsString(
                "<option value=\"{$option}\"",
                $defaultHtml,
                "The page-size selector must include {$option}.",
            );
        }

        $allHtml = (new EventPanel())->renderWithContext(
            (new EventSnapshot(array_slice($rows, 0, 51)))->jsonSerialize(),
            self::context(['per-page' => 'all']),
        );

        self::assertSame(
            51,
            substr_count(self::tbody($allHtml), '<tr>'),
            'All must disable pagination.',
        );
        self::assertStringContainsString(
            '<option value="all" selected>',
            $allHtml,
            'All must restore its selector state.',
        );

        $cappedHtml = (new EventPanel())->renderWithContext($payload, self::context(['per-page' => '5000']));

        self::assertSame(
            1_000,
            substr_count(self::tbody($cappedHtml), '<tr>'),
            'Oversized page requests must be capped at one thousand rows.',
        );
        self::assertStringContainsString(
            'Showing 1-1000 of 1001 items.',
            $cappedHtml,
            'The footer must describe the capped pagination window.',
        );
    }

    public function testRenderShowsThePsr14EmptyCaptureState(): void
    {
        $html = (new EventPanel())->render(['entries' => []]);

        self::assertStringContainsString(
            'No events dispatched in this request',
            $html,
            'A valid capture without events must use the PSR-14 empty-state heading.',
        );
        self::assertStringContainsString(
            '$dispatcher-&gt;dispatch(new MyEvent());',
            $html,
            'The empty state must show a safe PSR-14 dispatch example.',
        );
        self::assertStringNotContainsString(
            'wildcard',
            $html,
            'The Yii3 empty state must not describe the Yii2 wildcard listener.',
        );
        self::assertStringNotContainsString(
            'yii-debug-grid-event',
            $html,
            'A truly empty capture must not render a misleading table.',
        );
    }

    public function testRenderWithContextExplainsWhenNoEventsMatch(): void
    {
        $html = (new EventPanel())->renderWithContext(
            self::payload(),
            self::context(['Event' => ['name' => 'missing']]),
        );

        self::assertStringContainsString(
            '<strong>0</strong> events',
            $html,
            'A filtered-empty summary must describe the empty filtered set.',
        );
        self::assertStringContainsString(
            '<strong>0</strong> classes',
            $html,
            'A filtered-empty summary must report no matching classes.',
        );
        self::assertStringContainsString(
            'No events match the active filters',
            $html,
            'A nonempty capture filtered to zero must explain why no events are shown.',
        );
        self::assertStringContainsString(
            '>Clear all</a>',
            $html,
            'A filtered-empty result must provide a reset action.',
        );
        self::assertStringNotContainsString(
            'No events dispatched in this request',
            $html,
            'A filtered-empty result must not claim the capture itself was empty.',
        );
        self::assertStringNotContainsString(
            'yii-debug-grid-event',
            $html,
            'A filtered-empty result must not render a misleading empty table.',
        );
    }

    public function testRenderWithContextFiltersRowsAndScopesTheCompleteSummaryToMatches(): void
    {
        $html = (
            new EventPanel())
                ->renderWithContext(
                    self::payload(),
                    self::context(
                        [
                            'Event' => [
                                'name' => 'userCREATED',
                                'class' => 'app\\event',
                                'senderClass' => 'writer',
                                'isStatic' => '0',
                            ],
                        ],
                    ),
                );

        $body = self::tbody($html);

        self::assertStringContainsString(
            'App\\Event\\UserCreated',
            $body,
            'The matching event must stay visible.',
        );
        self::assertStringNotContainsString(
            'UserDeleted',
            $body,
            'Nonmatching names must be filtered out.',
        );
        self::assertStringNotContainsString(
            'CacheWarmup',
            $body,
            'Nonmatching static events must be filtered out.',
        );
        self::assertStringContainsString(
            '<strong>1</strong> events',
            $html,
            'The event count must describe the complete filtered set.',
        );
        self::assertStringContainsString(
            '<strong>1</strong> classes',
            $html,
            'The distinct-class count must describe the complete filtered set.',
        );
        self::assertStringNotContainsString(
            '<strong>1</strong> static',
            $html,
            'Static events removed by filters must not remain in the summary.',
        );
        self::assertStringContainsString(
            '<span class="yii-debug-active-filters-label">4 filters active</span>',
            $html,
            'Every accepted Event filter must appear in the active-filter banner.',
        );
        self::assertStringContainsString(
            'name="Event[class]"',
            $html,
            'Event must use the Event query group.',
        );
        self::assertStringContainsString(
            'name="Event[senderClass]"',
            $html,
            'Source must use the Event query group.',
        );
        self::assertStringContainsString(
            'aria-label="Filter by Event"',
            $html,
            'The Event filter must be labeled.',
        );
        self::assertStringContainsString(
            'aria-label="Filter by Source"',
            $html,
            'The Source filter must be labeled.',
        );
        self::assertStringNotContainsString(
            'name="Event[name]"',
            $html,
            'The duplicate Name control must stay hidden.',
        );
        self::assertStringNotContainsString(
            'name="Event[isStatic]"',
            $html,
            'The invariant Static control must stay hidden.',
        );
    }

    public function testToolbarItemsExposeOneCounterOnlyWhenEventsExist(): void
    {
        $panel = new EventPanel();

        self::assertSame(
            [],
            $panel->toolbarItems(['entries' => []]),
            'An empty capture must stay out of the toolbar.',
        );
        self::assertSame(
            [
                ['value' => '3', 'status' => 'default', 'id' => 'total'],
            ],
            array_map(
                static fn(ToolbarItem $item): array => $item->jsonSerialize(),
                $panel->toolbarItems(self::payload()),
            ),
            'A nonempty capture must expose exactly one stable Events counter.',
        );
    }

    /**
     * @param array<array-key, mixed> $queryParams
     */
    private static function context(array $queryParams): PanelRenderContext
    {
        return new PanelRenderContext(
            'request-1',
            'event',
            $queryParams,
            'light',
            new DebugUrlGenerator(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(): array
    {
        return (new EventSnapshot(
            [
                new EventRow(
                    1.0,
                    'App\\Event\\UserCreated',
                    'App\\Event\\UserCreated',
                    '0',
                    'App\\Service\\UserWriter',
                ),
                new EventRow(
                    2.0,
                    'App\\Event\\UserDeleted',
                    'App\\Event\\UserDeleted',
                    '0',
                    'App\\Service\\UserWriter',
                ),
                new EventRow(3.0, 'App\\Event\\CacheWarmup', 'App\\Event\\CacheWarmup', '1', ''),
            ],
        ))->jsonSerialize();
    }

    private static function tbody(string $html): string
    {
        preg_match('/<tbody>(.*?)<\/tbody>/s', $html, $matches);

        return $matches[1] ?? '';
    }
}
