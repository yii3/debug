<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Helper\Trace;
use PHPForge\Debug\Panel\Db\{DbSnapshot, QueryRow};
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Storage\HydrationException;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Db\DbExplain;
use Yii3\Debug\Panel\DbPanel;
use Yii3\Debug\Tests\Provider\DbPanelProvider;
use Yii3\Debug\Tests\Support\DatabaseFixture;
use Yii3\Debug\Web\DebugUrlGenerator;

/**
 * Unit tests for Database grid parity, filters, sorts, pagination, N+1 markers, and toolbar metrics.
 */
#[Group('db')]
final class DbPanelTest extends TestCase
{
    #[DataProviderExternal(DbPanelProvider::class, 'toolbarTitles')]
    public function testCountChipTitleReportsExecutedQueriesOrActiveWarnings(
        int|null $criticalQueryThreshold,
        int|null $excessiveCallerThreshold,
        string $expected,
    ): void {
        $items = (new DbPanel(new DbExplain(), new DebugUrlGenerator(), Trace::create()))
            ->withThresholds($criticalQueryThreshold, $excessiveCallerThreshold)
            ->toolbarItems(DatabaseFixture::snapshot()->jsonSerialize());
        self::assertSame($expected, $items[0]->title ?? null, 'Tooltip must match the active threshold state.');
        self::assertSame('Total query time', $items[1]->title ?? null, 'Duration chip must keep the shared tooltip.');
    }

    public function testExplainLinksUseConfiguredPrefixAndStoredSequenceOnly(): void
    {
        $panel = new DbPanel(
            new DbExplain(DatabaseFixture::connection()),
            new DebugUrlGenerator('/inspect'),
            Trace::create(),
        );
        $html = $panel->renderWithContext(DatabaseFixture::snapshot()->jsonSerialize(), self::context([]));
        self::assertStringContainsString('/inspect/db-explain?tag=capture&amp;seq=0', $html, 'Plans must use the configured prefix.');
        self::assertStringContainsString('Explain all', $html, 'Supported drivers must expose the shared batch control.');
        self::assertStringNotContainsString('sql=', $html, 'Plan URLs must never carry client-supplied SQL.');
    }

    public function testFiltersPreserveNavigationAndExposeRecoveryWhenNoRowsMatch(): void
    {
        $panel = new DbPanel(new DbExplain(), new DebugUrlGenerator(), Trace::create());
        $query = ['Db' => ['type' => 'select', 'query' => 'alpha', 'unknown' => 'ignored'], 'sort' => '-duration',
            'per-page' => '20', 'page' => '999', 'yii_debug_theme' => 'dark'];
        $html = $panel->renderWithContext(DatabaseFixture::snapshot()->jsonSerialize(), self::context($query));
        self::assertStringContainsString('name="Db[type]"', $html, 'SQL verbs must use the shared select control.');
        self::assertStringContainsString('name="Db[query]"', $html, 'SQL text must use the shared text control.');
        self::assertStringContainsString('alpha', $html, 'Matching SQL must remain visible.');
        self::assertStringNotContainsString('UPDATE beta', strip_tags($html), 'Combined filters must exclude other rows.');
        self::assertStringContainsString('yii_debug_theme=dark', $html, 'Filter and sort links must preserve the theme.');
        self::assertStringNotContainsString('unknown', $html, 'Unknown filter keys must not survive normalized URLs.');
        self::assertStringNotContainsString('page=999', $html, 'Sort and filter navigation must reset stale page numbers.');
        $html = $panel->renderWithContext(DatabaseFixture::snapshot()->jsonSerialize(), self::context(['Db' => ['query' => 'absent']]));
        self::assertStringContainsString('No database queries match', $html, 'No-match states must differ from empty captures.');
        self::assertStringContainsString('Clear all', $html, 'No-match states must retain filter recovery.');
    }
    public function testMetadataEmptyCaptureAndContextFreeGrid(): void
    {
        $panel = new DbPanel(new DbExplain(), new DebugUrlGenerator(), Trace::create());
        self::assertSame('db', $panel->id(), 'Database must use its canonical panel ID.');
        self::assertSame('Database', $panel->name(), 'The shared panel title must be preserved.');
        self::assertSame('db', $panel->icon(), 'The shared Database icon must be reused.');
        self::assertFalse($panel->hasContent([]), 'Missing captures must not be advertised.');
        self::assertTrue($panel->hasContent(['entries' => []]), 'Empty valid captures must remain inspectable.');
        self::assertSame([], $panel->toolbarItems(['entries' => []]), 'Empty Database toolbars must omit query metrics.');
        self::assertStringContainsString('No database queries in this request', $panel->render(['entries' => []]), 'Empty captures must show guidance.');
        $html = $panel->render(DatabaseFixture::snapshot()->jsonSerialize());
        self::assertStringContainsString('yii-debug-grid yii-debug-grid-db', $html, 'Database must use the shared grid CSS.');
        self::assertStringContainsString('<strong>3</strong> queries', $html, 'Summary must report request-wide query totals.');
        self::assertStringNotContainsString('name="Db[', $html, 'Context-free grids must omit query controls.');
        foreach (['Type', 'Time', 'Duration', 'Rows', 'Dup', 'Query'] as $heading) {
            self::assertStringContainsString($heading, $html, 'The grid must retain every Yii2 Database column.');
        }
        $items = $panel->toolbarItems(DatabaseFixture::snapshot()->jsonSerialize());
        self::assertSame('3', $items[0]->value ?? null, 'Toolbar count must include all queries.');
        self::assertSame('6 ms', $items[1]->value ?? null, 'Toolbar duration must use milliseconds.');
        self::assertSame('info', $items[0]->status ?? null, 'Disabled thresholds must not warn.');
        self::assertSame('warning', $panel->withThresholds(2, null)->toolbarItems(DatabaseFixture::snapshot()->jsonSerialize())[0]->status ?? null, 'Critical query counts must warn.');
        self::assertSame('info', $panel->withThresholds(3, null)->toolbarItems(DatabaseFixture::snapshot()->jsonSerialize())[0]->status ?? null, 'The query threshold must be exclusive.');
        self::assertSame('warning', $panel->withThresholds(null, 3)->toolbarItems(DatabaseFixture::snapshot()->jsonSerialize())[0]->status ?? null, 'The caller threshold must be inclusive.');
    }

    public function testNPlusOneMarkersArePageScopedAndSqlAndTracesAreEscaped(): void
    {
        $rows = [];
        for ($i = 0; $i < 4; $i++) {
            $rows[] = QueryRow::create('SELECT <script>' . $i, 1.0, 1000.0 + $i)
                ->withTrace([['file' => '/app/<script>.php', 'line' => 5]])->withSequence($i);
        }
        $payload = DbSnapshot::capture($rows)->jsonSerialize();
        $panel = new DbPanel(new DbExplain(), new DebugUrlGenerator(), Trace::create());
        $html = $panel->renderWithContext($payload, self::context(['per-page' => '3']));
        self::assertStringContainsString('Potential N+1 queries on this page', $html, 'N+1 summaries must state their scope.');
        self::assertStringContainsString('data-yii-debug-n1-group', $html, 'Matching rows must expose existing JS markers.');
        self::assertStringNotContainsString('<script>', $html, 'Captured SQL and traces must not become executable markup.');
        self::assertStringNotContainsString('Potential N+1 queries', $panel->renderWithContext($payload, self::context(['per-page' => '3', 'page' => '2'])), 'N+1 groups must not include rows outside the current page.');
    }
    public function testThresholdCopiesAndLargeDurationsRetainToolbarPrecision(): void
    {
        $panel = new DbPanel(new DbExplain(), new DebugUrlGenerator(), Trace::create());
        $copy = $panel->withThresholds(0, 1);
        self::assertNotSame($panel, $copy, 'Threshold configuration must return a copy.');
        $payload = (new DbSnapshot([QueryRow::create('SELECT 1', 10000.0, 0.0)]))->jsonSerialize();
        self::assertSame('10,000 ms', $panel->toolbarItems($payload)[1]->value ?? null, 'Millisecond totals must not be rescaled.');
        self::assertSame('info', $panel->toolbarItems($payload)[0]->status ?? null, 'The original panel must keep disabled thresholds.');
    }

    public function testThrowHydrationExceptionWhenContentChecksReceiveInvalidPayloads(): void
    {
        $this->expectException(HydrationException::class);
        (new DbPanel(new DbExplain(), new DebugUrlGenerator(), Trace::create()))->hasContent(['entries' => 'invalid']);
    }

    public function testThrowHydrationExceptionWhenDatabasePayloadIsMalformed(): void
    {
        $this->expectException(HydrationException::class);
        (new DbPanel(new DbExplain(), new DebugUrlGenerator(), Trace::create()))->render(['entries' => 'invalid']);
    }

    #[DataProviderExternal(DbPanelProvider::class, 'sorts')]
    public function testVisibleColumnsSortBeforePagination(string $sort, string $first): void
    {
        $html = (new DbPanel(new DbExplain(), new DebugUrlGenerator(), Trace::create()))->renderWithContext(
            DatabaseFixture::snapshot()->jsonSerialize(),
            self::context(['sort' => $sort, 'per-page' => '1']),
        );
        preg_match('~<div class="yii-debug-db-sql">(.*?)</div>~s', $html, $match);
        self::assertSame($first, trim(strip_tags($match[1] ?? '')), 'The sorted first query must be the only visible row.');
        self::assertStringContainsString('aria-sort=', $html, 'The active column must announce its sort direction.');
    }

    /**
     * @param array<array-key, mixed> $query
     */
    private static function context(array $query): PanelRenderContext
    {
        return new PanelRenderContext('capture', 'db', $query, 'light', new DebugUrlGenerator('/inspect'));
    }
}
