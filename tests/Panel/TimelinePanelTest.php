<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\PanelRenderContext;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\TimelinePanel;
use Yii3\Debug\Web\DebugUrlGenerator;

/**
 * Unit tests for {@see TimelinePanel} composing sibling Profiling data into the shared Timeline chart.
 */
final class TimelinePanelTest extends TestCase
{
    public function testMetadataIdentifiesTheTimelinePanel(): void
    {
        $panel = new TimelinePanel();

        self::assertSame('timeline', $panel->id(), 'Stable ID must pair with the Timeline collector.');
        self::assertSame('timeline', $panel->icon(), 'Icon must use the shared Timeline glyph.');
        self::assertSame('Timeline', $panel->name(), 'Label must match the Yii2 panel.');
        self::assertSame([], $panel->toolbarItems([]), 'Timeline must not duplicate Profiling toolbar metrics.');
    }

    public function testRenderWithContextBuildsNestedTimelineAndMemoryGraph(): void
    {
        $html = (new TimelinePanel())->renderWithContext(
            self::timelinePayload(),
            self::context(),
        );

        self::assertStringContainsString('<strong>100</strong> ms total', $html, 'Profiling duration must drive the summary.');
        self::assertStringContainsString('<strong>2</strong> spans', $html, 'Summary must count visible spans.');
        self::assertStringContainsString('yii-debug-tl-row-app', $html, 'Application root must use the app variant.');
        self::assertStringContainsString('yii-debug-tl-row-db', $html, 'DB query must use the database variant.');
        self::assertStringContainsString("style='--depth: 1;'", $html, 'Nested DB query must retain its profiler depth.');
        self::assertStringContainsString('yii-debug-tl-memory-gradient', $html, 'Memory samples must render the shared SVG.');
        self::assertStringContainsString('name="Timeline[duration]"', $html, 'Duration filter must use the shared prefix.');
        self::assertStringContainsString('value="request-1"', $html, 'Filter form must retain the snapshot tag.');
    }

    public function testRenderWithContextShowsProfilingHintWhenFiltersRemoveEverySpan(): void
    {
        $context = new PanelRenderContext(
            'request-1',
            'timeline',
            ['Timeline' => ['category' => 'missing']],
            'light',
            new DebugUrlGenerator(),
            ['profiling' => self::profilingPayload()],
        );
        $html = (new TimelinePanel())->renderWithContext(self::timelinePayload(), $context);

        self::assertStringContainsString('<strong>0</strong> spans', $html, 'Filtered summary must count visible spans.');
        self::assertStringContainsString('No spans matched your filter.', $html, 'Empty filters must show guidance.');
        self::assertStringContainsString(
            '/debug/view?tag=request-1&amp;panel=profiling',
            $html,
            'Empty hint must link back to the Profiling panel for the same snapshot.',
        );
    }

    private static function context(): PanelRenderContext
    {
        return new PanelRenderContext(
            'request-1',
            'timeline',
            [],
            'light',
            new DebugUrlGenerator(),
            ['profiling' => self::profilingPayload()],
        );
    }

    /**
     * @return array<string, mixed> Representative populated Profiling payload.
     */
    private static function profilingPayload(): array
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
            'samples' => [
                ['time' => 1_000.0, 'memory' => 1_048_576],
                ['time' => 1_100.0, 'memory' => 2_097_152],
            ],
        ];
    }

    /**
     * @return array<string, mixed> Representative Timeline payload.
     */
    private static function timelinePayload(): array
    {
        return ['start' => 1.0, 'end' => 1.1, 'memory' => 2_097_152];
    }
}
