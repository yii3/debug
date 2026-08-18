<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\LogPanel;
use Yii3\Debug\Tests\Support\GridFactory;

/**
 * Unit tests for {@see LogPanel} presenting the shared Logs payload and its count chips.
 */
final class LogPanelTest extends TestCase
{
    public function testMetadataIdentifiesTheLogsPanel(): void
    {
        $panel = new LogPanel(GridFactory::panelGrid());

        self::assertSame('log', $panel->id(), 'Stable ID must pair with the log collector.');
        self::assertSame('logs', $panel->icon(), 'Icon must use the shared logs glyph.');
        self::assertSame('Logs', $panel->name(), 'Label must match the Yii2 panel.');
    }

    public function testRenderShowsEmptyStateWithoutMessages(): void
    {
        $html = (new LogPanel(GridFactory::panelGrid()))->render(['entries' => []]);

        self::assertStringContainsString('No log messages captured', $html, 'Empty state must be shown.');
    }

    public function testRenderShowsSummaryAndMessageGrid(): void
    {
        $html = (new LogPanel(GridFactory::panelGrid()))->render($this->snapshot()->jsonSerialize());

        self::assertStringContainsString('messages', $html, 'Summary must label the message total.');
        self::assertStringContainsString('database went away', $html, 'Message text must be listed.');
        self::assertStringContainsString('app.db', $html, 'Category must be listed.');
    }

    public function testToolbarItemsExposeOnlyTotalWithoutErrorsAndWarnings(): void
    {
        $payload = LogSnapshot::capture(
            [['request started', 0x04, 'application', 1_700_000_000.1, [], 1024]],
        )->jsonSerialize();

        $items = (new LogPanel(GridFactory::panelGrid()))->toolbarItems($payload);

        self::assertCount(1, $items, 'Only the total chip must be emitted.');
        self::assertSame('1', $items[0]->value, 'Total chip must expose the message count.');
    }

    public function testToolbarItemsExposeTotalErrorAndWarningChips(): void
    {
        $items = (new LogPanel(GridFactory::panelGrid()))->toolbarItems($this->snapshot()->jsonSerialize());

        self::assertCount(3, $items, 'Total, error, and warning chips must be emitted.');
        self::assertSame('3', $items[0]->value, 'First chip must expose the total count.');
        self::assertSame('1', $items[1]->value, 'Error chip must expose the error count.');
        self::assertSame('danger', $items[1]->status, 'Error chip must use danger status.');
        self::assertSame('Errors', $items[1]->label, 'Error chip must be labelled.');
        self::assertSame('1', $items[2]->value, 'Warning chip must expose the warning count.');
        self::assertSame('warning', $items[2]->status, 'Warning chip must use warning status.');
    }

    /**
     * Creates a representative log snapshot with one error, one warning, and one info message.
     *
     * @return LogSnapshot Representative snapshot.
     */
    private function snapshot(): LogSnapshot
    {
        return LogSnapshot::capture(
            [
                ['request started', 0x04, 'application', 1_700_000_000.1, [], 1024],
                ['slow query detected', 0x02, 'app.db', 1_700_000_000.2, [], 2048],
                ['database went away', 0x01, 'app.db', 1_700_000_000.3, [], 4096],
            ],
        );
    }
}
