<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\ProfilingPanel;

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
}
