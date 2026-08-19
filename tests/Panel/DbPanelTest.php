<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\Db\{DbSnapshot, QueryRow};
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\DbPanel;
use Yii3\Debug\Tests\Support\GridFactory;
use Yii3\Debug\Web\DebugUrlGenerator;

use function preg_match;
use function substr_count;

/**
 * Unit tests for {@see DbPanel} presenting the shared Database payload and its query chips.
 */
final class DbPanelTest extends TestCase
{
    public function testMetadataIdentifiesTheDatabasePanel(): void
    {
        $panel = new DbPanel(GridFactory::panelGrid());

        self::assertSame('db', $panel->id(), 'Stable ID must pair with the db collector.');
        self::assertSame('db', $panel->icon(), 'Icon must use the shared db glyph.');
        self::assertSame('Database', $panel->name(), 'Label must match the Yii2 panel.');
    }

    public function testRenderShowsEmptyStateWithoutQueries(): void
    {
        $html = (new DbPanel(GridFactory::panelGrid()))->render(['entries' => []]);

        self::assertStringContainsString('No database queries captured', $html, 'Empty state must be shown.');
    }

    public function testRenderShowsSummaryAndQueryGrid(): void
    {
        $html = (new DbPanel(GridFactory::panelGrid()))->render($this->snapshot()->jsonSerialize());

        self::assertStringContainsString('queries', $html, 'Summary must label the query total.');
        self::assertStringContainsString('SELECT', $html, 'Query type must be listed.');
        self::assertStringContainsString('users', $html, 'Query text must be listed.');
    }

    public function testRenderWithContextProvidesFiltersColumnsPaginationAndExplain(): void
    {
        $html = (new DbPanel(GridFactory::panelGrid()))->renderWithContext(
            $this->snapshot()->jsonSerialize(),
            new PanelRenderContext(
                'request-1',
                'db',
                ['Db' => ['type' => 'SELECT']],
                'light',
                new DebugUrlGenerator(),
            ),
        );

        preg_match('/<tbody>(.*?)<\/tbody>/s', $html, $matches);
        $body = $matches[1] ?? '';

        self::assertSame(1, substr_count($body, 'users'), 'Only the SELECT query must remain visible.');
        self::assertSame(0, substr_count($body, 'logs'), 'The INSERT query must be filtered out.');
        self::assertSame(
            2,
            substr_count($html, '>Rows<'),
            'The rows column and page-size label must both be present.',
        );
        self::assertSame(1, substr_count($html, '>Dup<'), 'The duplicate column must be present once.');
        self::assertSame(1, substr_count($html, 'Toggle EXPLAIN output'), 'The supported query must expose EXPLAIN.');
        self::assertSame(
            1,
            substr_count($html, '/debug/db-explain?tag=request-1&amp;seq=0'),
            'The EXPLAIN toggle must use the adapter-owned action URL.',
        );
        self::assertSame(1, substr_count($html, 'yii-debug-grid-footer'), 'The full grid footer must render once.');
    }

    public function testToolbarItemsExposeQueryCountAndTotalTimeChips(): void
    {
        $items = (new DbPanel(GridFactory::panelGrid()))->toolbarItems($this->snapshot()->jsonSerialize());

        self::assertCount(2, $items, 'Count and total-time chips must be emitted.');
        self::assertSame('2', $items[0]->value, 'Count chip must expose the query count.');
        self::assertSame('info', $items[0]->status, 'Count chip must use the info status.');
        self::assertSame('Executed 2 database queries.', $items[0]->title, 'Count chip must describe the total.');
        self::assertSame('8 ms', $items[1]->value, 'Time chip must sum the query durations.');
        self::assertSame('Total query time', $items[1]->title, 'Time chip must carry its tooltip.');
    }

    public function testToolbarItemsStayEmptyWithoutQueries(): void
    {
        $items = (new DbPanel(GridFactory::panelGrid()))->toolbarItems(['entries' => []]);

        self::assertSame([], $items, 'Zero queries must omit the chips.');
    }

    /**
     * Creates a representative database snapshot with two queries.
     *
     * @return DbSnapshot Representative snapshot.
     */
    private function snapshot(): DbSnapshot
    {
        return new DbSnapshot(
            [
                new QueryRow(
                    type: 'SELECT',
                    query: 'SELECT * FROM users',
                    duration: 5.0,
                    trace: [],
                    traceHash: 'abc',
                    timestamp: 1_700_000_000_000.0,
                    seq: 0,
                    duplicate: 1,
                    rows: 10,
                ),
                new QueryRow(
                    type: 'INSERT',
                    query: 'INSERT INTO logs VALUES (1)',
                    duration: 3.0,
                    trace: [],
                    traceHash: 'def',
                    timestamp: 1_700_000_000_100.0,
                    seq: 1,
                    duplicate: 1,
                    rows: 1,
                ),
            ],
        );
    }
}
