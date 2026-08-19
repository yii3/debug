<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\PanelRenderContext;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\ProfilingPanel;
use Yii3\Debug\Tests\Support\GridFactory;
use Yii3\Debug\Web\DebugUrlGenerator;

/**
 * Unit tests for {@see ProfilingPanel} presenting the shared Profiling payload and its toolbar metrics.
 */
final class ProfilingPanelTest extends TestCase
{
    public function testMetadataIdentifiesTheProfilingPanel(): void
    {
        $panel = new ProfilingPanel();

        self::assertSame('profiling', $panel->id(), 'Stable ID must pair with the profiling collector.');
        self::assertSame('profiling', $panel->icon(), 'Icon must use the shared profiling glyph.');
        self::assertSame('Profiling', $panel->name(), 'Label must match the Yii2 panel.');
    }

    public function testRenderShowsSummaryStripAndEmptyStateWithoutProfileBlocks(): void
    {
        $payload = ['memory' => 2_097_152, 'time' => 0.0125, 'entries' => [], 'samples' => []];

        $html = (new ProfilingPanel())->render($payload);

        self::assertStringContainsString('13 ms', $html, 'Summary must render the total time.');
        self::assertStringContainsString('2.000 MB', $html, 'Summary must render the peak memory.');
        self::assertStringContainsString('No profile blocks captured', $html, 'Empty state must be shown.');
    }

    public function testRenderWithContextProvidesTheCompleteFilteredGridContract(): void
    {
        $html = (new ProfilingPanel(GridFactory::panelGrid()))->renderWithContext(
            self::payload(),
            new PanelRenderContext(
                'request-1',
                'profiling',
                ['Profile' => ['category' => 'Db\\Command'], 'per-page' => '25'],
                'light',
                new DebugUrlGenerator(),
            ),
        );

        self::assertStringContainsString('Open timeline', $html, 'Summary must link the same spans to Timeline.');
        self::assertStringContainsString(
            '/debug/view?tag=request-1&amp;panel=timeline',
            $html,
            'Timeline link must retain the active snapshot tag.',
        );
        self::assertStringContainsString('name="Profile[category]"', $html, 'Category filter must use the shared prefix.');
        self::assertStringContainsString('name="Profile[info]"', $html, 'Info filter must use the shared prefix.');
        self::assertStringContainsString('SELECT', $html, 'Matching SQL profile info must remain visible.');
        self::assertStringNotContainsString('GET /', $html, 'Non-matching application block must be filtered out.');
        self::assertStringContainsString('yii-debug-indent', $html, 'Nested profile rows must retain their indentation.');
        self::assertStringContainsString('class="yii-debug-active-filters"', $html, 'Active filters must render once.');
        self::assertStringContainsString('option value="25" selected', $html, 'Page-size state must round-trip.');
    }

    public function testToolbarItemsExposeTimeAndMemoryChips(): void
    {
        $items = (new ProfilingPanel())->toolbarItems(
            ['memory' => 2_097_152, 'time' => 1.2345, 'entries' => [], 'samples' => []],
        );

        self::assertCount(2, $items, 'Both metrics must be emitted.');
        self::assertSame('1,235 ms', $items[0]->value, 'Time chip must use thousands separators.');
        self::assertSame('Total processing time', $items[0]->title, 'Time chip must carry its tooltip.');
        self::assertSame('2.000 MB', $items[1]->value, 'Memory chip must render megabytes.');
        self::assertSame('Peak memory', $items[1]->title, 'Memory chip must carry its tooltip.');
    }

    public function testToolbarItemsStayEmptyForIncompletePayload(): void
    {
        $panel = new ProfilingPanel();

        self::assertSame([], $panel->toolbarItems([]), 'Missing metrics must not emit chips.');
        self::assertSame([], $panel->toolbarItems(['time' => 'fast', 'memory' => 1]), 'Mistyped time must be refused.');
        self::assertSame([], $panel->toolbarItems(['time' => 0.1, 'memory' => '1M']), 'Mistyped memory must be refused.');
    }

    /**
     * @return array<string, mixed> Representative populated Profiling payload.
     */
    private static function payload(): array
    {
        return [
            'memory' => 2_097_152,
            'time' => 0.1,
            'entries' => [
                [
                    'timestamp' => 1_000.0,
                    'duration' => 100.0,
                    'category' => 'Yii3\\Application::handle',
                    'info' => 'GET /',
                    'level' => 0,
                    'seq' => 0,
                    'memory' => 1_048_576,
                    'memoryDiff' => 0,
                    'trace' => [],
                ],
                [
                    'timestamp' => 1_025.0,
                    'duration' => 10.0,
                    'category' => 'Yiisoft\\Db\\Command::query',
                    'info' => 'SELECT 1',
                    'level' => 1,
                    'seq' => 1,
                    'memory' => 1_572_864,
                    'memoryDiff' => 524_288,
                    'trace' => [],
                ],
            ],
            'samples' => [],
        ];
    }
}
