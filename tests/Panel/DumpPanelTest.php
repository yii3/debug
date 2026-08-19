<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\Dump\{DumpRow, DumpSnapshot};
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\DumpPanel;
use Yii3\Debug\Tests\Support\GridFactory;
use Yii3\Debug\Web\DebugUrlGenerator;

/**
 * Unit tests for {@see DumpPanel} presenting shared dump cards and Yii2-compatible filters.
 */
final class DumpPanelTest extends TestCase
{
    public function testMetadataAndToolbarMatchTheDumpContract(): void
    {
        $panel = new DumpPanel(GridFactory::panelGrid());
        $payload = $this->snapshot()->jsonSerialize();
        $items = $panel->toolbarItems($payload);
        $item = $items[0] ?? self::fail('Expected the dump toolbar item.');

        self::assertSame('dump', $panel->id(), 'Stable ID must pair with the dump collector.');
        self::assertSame('dump', $panel->icon(), 'Icon must use the shared dump glyph.');
        self::assertSame('Dump', $panel->name(), 'Label must match Yii2.');
        self::assertSame('2', $item->value, 'Toolbar must expose the dump count.');
        self::assertSame([], $panel->toolbarItems(['entries' => []]), 'Empty dumps must omit the toolbar chip.');
    }

    public function testRenderShowsEmptyStateAndFilteredCardGrid(): void
    {
        $panel = new DumpPanel(GridFactory::panelGrid());

        self::assertStringContainsString('No variables dumped', $panel->render(['entries' => []]), 'Empty state must explain the capture API.');

        $html = $panel->renderWithContext(
            $this->snapshot()->jsonSerialize(),
            new PanelRenderContext(
                'request-1',
                'dump',
                ['Log' => ['category' => 'demo']],
                'light',
                new DebugUrlGenerator(),
            ),
        );

        self::assertStringContainsString('first value', $html, 'Matching dump card must remain visible.');
        self::assertStringNotContainsString('second value', $html, 'Unmatched category must be filtered out.');
        self::assertStringContainsString('yii-debug-active-filters', $html, 'Active filter banner must render.');
    }

    private function snapshot(): DumpSnapshot
    {
        return new DumpSnapshot(
            [
                new DumpRow('first value', 1, 'demo', 1_700_000_000_000.0, []),
                new DumpRow('second value', 1, 'other', 1_700_000_001_000.0, []),
            ],
        );
    }
}
