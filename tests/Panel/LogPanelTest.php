<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Storage\HydrationException;
use PHPForge\Debug\Toolbar\ToolbarItem;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\LogPanel;
use Yii3\Debug\Web\DebugUrlGenerator;

use function array_map;
use function substr_count;

/**
 * Unit tests for the built-in Logs grid, query controls, and toolbar metrics.
 */
final class LogPanelTest extends TestCase
{
    public function testContextFreeRenderMatchesTheYii2LogGridColumnsAndEscapesCapturedData(): void
    {
        $payload = LogSnapshot::capture(
            [
                [
                    '<script>alert(1)</script>',
                    4,
                    'Yii3\\Application::run',
                    1.0,
                    [['file' => '/tmp/<trace>.php', 'line' => 12]],
                    1024,
                ],
            ],
        )->jsonSerialize();

        $html = (new LogPanel())->render($payload);

        foreach (['#', 'Time', 'Delta', 'Level', 'Category', 'Message'] as $heading) {
            self::assertStringContainsString(
                $heading,
                $html,
                "The context-free grid must render the {$heading} column.",
            );
        }

        self::assertStringContainsString(
            '<span><strong>1</strong> messages</span>',
            $html,
            'The summary must report the unfiltered captured total.',
        );
        self::assertStringContainsString(
            'class="yii-debug-row-info" id="log-1"',
            $html,
            'Rows must retain the shared severity class and stable anchor.',
        );
        self::assertStringContainsString(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            $html,
            'Captured messages must remain inspectable after escaping.',
        );
        self::assertStringContainsString(
            '/tmp/&lt;trace&gt;.php:12',
            $html,
            'Captured trace lines must be escaped before trusted renderer composition.',
        );
        self::assertStringContainsString(
            'href="ide://open?url=file:///tmp/&lt;trace&gt;.php&amp;line=12"',
            $html,
            'Captured source locations must use the same IDE deep links as the Yii2 Logs panel.',
        );
        self::assertStringNotContainsString(
            '<script>alert(1)</script>',
            $html,
            'Captured messages must never render executable HTML.',
        );
        self::assertStringNotContainsString(
            'name="Log[level]"',
            $html,
            'Context-free rendering must not emit query controls.',
        );
    }

    public function testMalformedPayloadRetainsTheNativeHydrationFailure(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            'Invalid debug snapshot',
        );

        (new LogPanel())->render(['entries' => 'invalid']);
    }

    public function testMetadataVisibilityAndInterfacesIdentifyTheBuiltInPanel(): void
    {
        $panel = new LogPanel();

        self::assertSame(
            'log',
            $panel->id(),
            "Stable panel ID must be 'log'.",
        );
        self::assertSame(
            'Logs',
            $panel->name(),
            "Panel name must be 'Logs'.",
        );
        self::assertSame(
            'logs',
            $panel->icon(),
            "Panel icon must be 'logs'.",
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

    public function testRenderShowsTheDedicatedEmptyCaptureState(): void
    {
        self::assertSame(
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Log Messages
            </h1><div class="yii-debug-empty-state">
            <h2>
            No log messages captured
            </h2><p>
            This request did not emit log messages through the debug log target.
            </p>
            </div>
            HTML,
            (new LogPanel())->render(['entries' => []]),
            'A valid capture without messages must render the complete guidance state.',
        );
    }

    public function testRenderWithContextExplainsWhenNoMessagesMatch(): void
    {
        $html = (new LogPanel())->renderWithContext(
            self::payload(),
            self::context(['Log' => ['message' => 'missing'], 'per-page' => '10']),
        );

        self::assertStringContainsString(
            'No log messages match the active filters',
            $html,
            'A nonempty capture filtered to zero must explain why no messages are shown.',
        );
        self::assertStringContainsString(
            '>Clear all</a>',
            $html,
            'A filtered-empty result must provide a direct reset action.',
        );
        self::assertStringNotContainsString(
            'yii-debug-grid-log',
            $html,
            'A filtered-empty result must not render a misleading empty table.',
        );
    }

    public function testRenderWithContextFiltersRowsAndKeepsSummaryCountsUnfiltered(): void
    {
        $html = (new LogPanel())->renderWithContext(
            self::payload(),
            self::context(
                [
                    'Log' => ['level' => '1'],
                    'sort' => '-message',
                    'per-page' => '25',
                    'page' => '2',
                    'yii_debug_theme' => 'dark',
                    'return' => 'overview',
                ],
            ),
        );

        self::assertSame(
            1,
            substr_count($html, 'database went away'),
            'The matching error row must remain visible once.',
        );
        self::assertStringNotContainsString(
            'slow query detected',
            $html,
            'A warning row must be removed by the exact level filter.',
        );
        self::assertStringNotContainsString(
            'request started',
            $html,
            'An info row must be removed by the exact level filter.',
        );
        self::assertStringNotContainsString(
            'framework bootstrap',
            $html,
            'A trace row must be removed by the exact level filter.',
        );
        self::assertStringContainsString(
            '<span><strong>4</strong> messages</span>',
            $html,
            'The message summary must describe the unfiltered capture.',
        );
        self::assertStringContainsString(
            'class="yii-debug-grid-summary-stat-danger"',
            $html,
            'The unfiltered error count must remain visible.',
        );
        self::assertStringContainsString(
            '<strong>1</strong> errors</a>',
            $html,
            'The unfiltered error count must remain visible.',
        );
        self::assertStringContainsString(
            'class="yii-debug-grid-summary-stat-warn"',
            $html,
            'The unfiltered warning count must remain visible.',
        );
        self::assertStringContainsString(
            '<strong>1</strong> warnings</a>',
            $html,
            'The unfiltered warning count must remain visible.',
        );
        self::assertStringContainsString(
            'class="yii-debug-grid-summary-stat-info"',
            $html,
            'The unfiltered info count must remain visible.',
        );
        self::assertStringContainsString('<strong>1</strong> info</a>', $html, 'The info count must remain visible.');
        self::assertStringContainsString(
            'class="yii-debug-grid-summary-stat-trace"',
            $html,
            'The unfiltered trace count must remain visible.',
        );
        self::assertStringContainsString(
            '<strong>1</strong> trace</a>',
            $html,
            'The trace count must remain visible.',
        );
        self::assertStringContainsString(
            'href="/debug/view?tag=request-1&amp;panel=log&amp;Log%5Blevel%5D=2&amp;sort=-message&amp;per-page=25&amp;yii_debug_theme=dark&amp;return=overview"',
            $html,
            'A warning summary link must select that level, reset pagination, and preserve unrelated query state.',
        );
        self::assertStringContainsString(
            'title="Show only warning log messages" aria-label="1 warnings; filter log messages by warning level"',
            $html,
            'Level summary links must explain their filtering action to every user.',
        );
        self::assertStringContainsString(
            'href="/debug/view?tag=request-1&amp;panel=log&amp;Log%5Blevel%5D=8&amp;sort=-message&amp;per-page=25&amp;yii_debug_theme=dark&amp;return=overview"',
            $html,
            'The trace summary link must select that level, reset pagination, and preserve unrelated query state.',
        );
        self::assertStringContainsString(
            'title="Show only trace log messages" aria-label="1 trace; filter log messages by trace level"',
            $html,
            'The trace summary link must explain its filtering action to every user.',
        );
        self::assertStringContainsString(
            '<option value="25" selected>',
            $html,
            'The page-size selector must restore the active size.',
        );
        self::assertSame(
            1,
            substr_count($html, 'class="yii-debug-active-filters"'),
            'The active filter banner must render exactly once.',
        );
        self::assertStringContainsString(
            'href="/debug/view?tag=request-1&amp;panel=log&amp;sort=-message&amp;per-page=25&amp;yii_debug_theme=dark&amp;return=overview"',
            $html,
            'Removing the only filter must preserve sort, page size, and theme while resetting the page.',
        );
        self::assertStringContainsString(
            'name="Log[level]" aria-label="Filter by Level"',
            $html,
            'The level control must use the shared Log query group.',
        );
        self::assertStringContainsString(
            '<option value="1" selected>',
            $html,
            'The level control must restore the exact active value.',
        );
        self::assertStringContainsString(
            'name="Log[category]"',
            $html,
            'The category control must use the shared Log query group.',
        );
        self::assertStringContainsString(
            'name="Log[message]"',
            $html,
            'The message control must use the shared Log query group.',
        );
    }

    public function testRenderWithContextPaginatesSortsAndPreservesFilterStateInLinks(): void
    {
        $payload = LogSnapshot::capture(
            [
                ['Alpha', 4, 'application', 1.0, []],
                ['Charlie', 4, 'application', 2.0, []],
                ['Bravo', 4, 'application', 3.0, []],
            ],
        )->jsonSerialize();

        $html = (new LogPanel())->renderWithContext(
            $payload,
            self::context(
                [
                    'Log' => ['category' => 'app'],
                    'sort' => '-message',
                    'per-page' => '1',
                    'page' => '2',
                    'yii_debug_theme' => 'light',
                ],
            ),
        );

        self::assertStringContainsString(
            'Bravo',
            $html,
            'Descending message sort page two must show the middle row.',
        );
        self::assertStringNotContainsString(
            '>Alpha<',
            $html,
            'The first sorted page must not leak into page two.',
        );
        self::assertStringNotContainsString(
            '>Charlie<',
            $html,
            'The third sorted page must not leak into page two.',
        );
        self::assertStringContainsString(
            'Showing 2-2 of 3 items.',
            $html,
            'The footer must report the filtered pagination window.',
        );
        self::assertStringContainsString(
            'class="yii-debug-pager-item is-active"',
            $html,
            'The effective page must remain marked active.',
        );
        self::assertStringContainsString(
            'Log%5Bcategory%5D=app&amp;sort=-message&amp;per-page=1&amp;page=3&amp;yii_debug_theme=light',
            $html,
            'Pager URLs must preserve filters, sorting, page size, and theme.',
        );
        self::assertStringContainsString(
            'Log%5Bcategory%5D=app&amp;sort=message&amp;per-page=1&amp;yii_debug_theme=light',
            $html,
            'The active descending sort link must toggle ascending and reset pagination.',
        );
        self::assertStringContainsString(
            'Log%5Bcategory%5D=app&amp;sort=-timeSincePrevious&amp;per-page=1&amp;yii_debug_theme=light',
            $html,
            'The elapsed-time sort link must initially select descending order like the Yii2 grid.',
        );
    }

    public function testToolbarItemsMatchTheTotalErrorAndWarningContracts(): void
    {
        $panel = new LogPanel();

        self::assertSame(
            [],
            $panel->toolbarItems(['entries' => []]),
            'An empty capture must not create an empty toolbar panel.',
        );
        self::assertSame(
            [
                ['value' => '4', 'status' => 'default', 'id' => 'total'],
                ['label' => 'Errors', 'value' => '1', 'status' => 'danger', 'id' => 'errors'],
                ['label' => 'Warnings', 'value' => '1', 'status' => 'warning', 'id' => 'warnings'],
            ],
            array_map(
                static fn(ToolbarItem $item): array => $item->jsonSerialize(),
                $panel->toolbarItems(self::payload()),
            ),
            'Toolbar metrics must expose stable IDs and Yii2-compatible labels, counts, and statuses.',
        );
    }

    /**
     * @param array<array-key, mixed> $queryParams
     */
    private static function context(array $queryParams): PanelRenderContext
    {
        return new PanelRenderContext(
            'request-1',
            'log',
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
        return LogSnapshot::capture(
            [
                ['request started', 4, 'application', 1.0, [], 1024],
                ['slow query detected', 2, 'app.db', 2.0, [], 2048],
                ['database went away', 1, 'app.db', 3.0, [], 4096],
                ['framework bootstrap', 8, 'framework', 4.0, [], 8192],
            ],
        )->jsonSerialize();
    }
}
