<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Web;

use PHPForge\Debug\Helper\Trace;
use PHPForge\Debug\Storage\RequestSummary;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Db\DbExplain;
use Yii3\Debug\Panel\DbPanel;
use Yii3\Debug\Tests\Provider\HistoryGridRendererProvider;
use Yii3\Debug\Web\{DebugUrlGenerator, HistoryGridRenderer};

use function preg_match_all;
use function substr_count;

/**
 * Tests accessible History controls and stable sorting before pagination.
 *
 * {@see HistoryGridRendererProvider} for sort attribute and malformed query cases.
 */
final class HistoryGridRendererTest extends TestCase
{
    public function testFiltersAndCurrentPageHaveAccessibleNames(): void
    {
        $html = HistoryGridRenderer::render(
            $this->summaries(),
            ['per-page' => '1', 'page' => '2'],
            '/debug',
        );

        preg_match_all('/aria-label="Filter by ([^"]+)"/', $html, $matches);

        self::assertSame(
            ['ID', 'IP', 'Method', 'AJAX', 'URL'],
            $matches[1],
            'Every History filter must expose an accessible name.',
        );
        self::assertStringContainsString(
            '<nav aria-label="Pagination">',
            $html,
            'Paging must expose a navigation landmark.',
        );
        self::assertSame(
            1,
            substr_count($html, 'aria-current="page"'),
            'Only the current page may be announced as current.',
        );
        self::assertMatchesRegularExpression(
            '/<a[^>]*aria-label="Page 2"[^>]*aria-current="page"/',
            $html,
            'The second page must be announced as current.',
        );
    }

    /**
     * @param array<string, mixed> $query Query naming no sortable attribute.
     */
    #[DataProviderExternal(HistoryGridRendererProvider::class, 'malformedSortQueries')]
    public function testMalformedSortKeepsNewestFirstOrder(array $query): void
    {
        self::assertSame(
            ['missing', 'long', 'short'],
            self::tags(HistoryGridRenderer::render($this->summaries(), $query, '/debug')),
            'Invalid sort input must safely use newest-first order.',
        );
    }

    /**
     * @param string $attribute Metric the captures are ordered by.
     */
    #[DataProviderExternal(HistoryGridRendererProvider::class, 'numericSortAttributes')]
    public function testNumericAttributesKeepUnreportedMetricsLast(string $attribute): void
    {
        $summaries = $this->summaries();

        self::assertSame(
            ['short', 'long', 'missing'],
            self::tags(HistoryGridRenderer::render($summaries, ['sort' => $attribute, 'per-page' => 'all'], '/debug')),
            'Ascending metrics must keep unavailable values last.',
        );
        self::assertSame(
            ['long', 'short', 'missing'],
            self::tags(
                HistoryGridRenderer::render($summaries, ['sort' => "-{$attribute}", 'per-page' => 'all'], '/debug'),
            ),
            'Descending metrics must keep unavailable values last.',
        );
    }

    public function testQueryColumnAppearsOnlyWhenTheDatabasePanelIsRegistered(): void
    {
        $summaries = $this->databaseSummaries();

        $without = HistoryGridRenderer::render(
            $summaries,
            ['per-page' => 'all'],
            '/debug',
        );

        self::assertStringNotContainsString(
            '>Query</a>',
            $without,
            'A debugger without the Database panel must not offer the column.',
        );
        self::assertStringNotContainsString(
            'panel=db',
            $without,
            'A debugger without the Database panel must not link to it.',
        );

        $with = HistoryGridRenderer::render(
            $summaries,
            ['per-page' => 'all'],
            '/debug',
            $this->dbPanel(),
        );

        self::assertStringContainsString(
            '<th scope="col" class="yii-debug-col-num"><a href="/debug?per-page=all&amp;sort=sqlCount">Query</a></th>',
            $with,
            'The column must be sortable from its header.',
        );
        self::assertStringContainsString(
            '<a href="/debug/view?tag=calm&amp;panel=db" title="Executed 3 database queries.">3</a>',
            $with,
            'Each count must deep-link to the Database panel of its capture.',
        );
        self::assertStringContainsString(
            'name="Debug[sqlCount]" type="text" aria-label="Filter by Query count"',
            $with,
            'The column must expose a named filter.',
        );
    }

    public function testQueryColumnFlagsCriticalCountsAndExcessiveCallers(): void
    {
        $html = HistoryGridRenderer::render(
            $this->databaseSummaries(),
            ['per-page' => 'all'],
            '/debug',
            $this->dbPanel(),
        );

        self::assertStringContainsString(
            '<a href="/debug/view?tag=busy&amp;panel=db" title="Executed 12 database queries.">12 '
            . '<span title="Too many queries. Allowed count is 10',
            $html,
            'A count above the threshold must carry the warning.',
        );
        self::assertStringContainsString(
            '2 callers are making too many calls.">⚠</span></a>',
            $html,
            'Excessive callers must stack under the query warning.',
        );
        self::assertStringNotContainsString(
            'Executed 3 database queries.">3 <span',
            $html,
            'A count below the threshold must stay unflagged.',
        );
    }

    public function testQueryCountFiltersAndSortsTheCapturedRequests(): void
    {
        $summaries = $this->databaseSummaries();
        $dbPanel = $this->dbPanel();

        self::assertSame(
            ['busy'],
            self::tags(
                HistoryGridRenderer::render(
                    $summaries,
                    ['per-page' => 'all', 'Debug' => ['sqlCount' => '>5']],
                    '/debug',
                    $dbPanel,
                ),
            ),
            'A numeric comparison must narrow the captures.',
        );
        self::assertSame(
            ['calm', 'busy'],
            self::tags(
                HistoryGridRenderer::render(
                    $summaries,
                    ['per-page' => 'all', 'sort' => 'sqlCount'],
                    '/debug',
                    $dbPanel,
                ),
            ),
            'Ascending order must lead with the lower count.',
        );
        self::assertSame(
            ['busy', 'calm'],
            self::tags(
                HistoryGridRenderer::render(
                    $summaries,
                    ['per-page' => 'all', 'sort' => '-sqlCount'],
                    '/debug',
                    $dbPanel,
                ),
            ),
            'Descending order must lead with the higher count.',
        );
    }

    public function testSortRunsBeforePaginationAndPreservesFilterStateInLinks(): void
    {
        $page = HistoryGridRenderer::render(
            $this->summaries(),
            [
                'sort' => 'processingTime',
                'per-page' => '1',
                'page' => '2',
                'Debug' => ['method' => 'GET'],
            ],
            '/developer/debug',
        );

        self::assertSame(
            ['long'],
            self::tags($page),
            'Pagination must slice sorted rows, not sort a page independently.',
        );
        self::assertStringContainsString(
            'sort=-processingTime&amp;per-page=1&amp;Debug%5Bmethod%5D=GET',
            $page,
            'Sort toggles must retain filters and page size while resetting the page.',
        );
        self::assertStringContainsString(
            'aria-sort="ascending"',
            $page,
            'The active sort direction must be accessible.',
        );
    }

    /**
     * @param string $attribute Attribute the captures are ordered by.
     */
    #[DataProviderExternal(HistoryGridRendererProvider::class, 'textualSortAttributes')]
    public function testTextualAttributesOrderTheCapturedRequests(string $attribute): void
    {
        $summaries = $this->comparableSummaries();

        self::assertSame(
            ['alpha', 'beta'],
            self::tags(HistoryGridRenderer::render($summaries, ['sort' => $attribute], '/debug')),
            'Ascending order must lead with the lower value.',
        );
        self::assertSame(
            ['beta', 'alpha'],
            self::tags(HistoryGridRenderer::render($summaries, ['sort' => "-{$attribute}"], '/debug')),
            'Descending order must lead with the higher value.',
        );
    }

    public function testUnmatchedFiltersExplainTheEmptyResult(): void
    {
        $html = HistoryGridRenderer::render(
            $this->summaries(),
            ['Debug' => ['method' => 'POST']],
            '/debug',
        );

        self::assertSame(
            [],
            self::tags($html),
            'Unmatched filters must not display unrelated captures.',
        );
        self::assertStringContainsString(
            'No requests match the current filters.',
            $html,
            'An empty result must be explained.',
        );
    }

    /**
     * Returns two captures whose tag, IP, method, AJAX flag, and URL all order `alpha` before `beta`.
     *
     * @return array<string, RequestSummary>
     */
    private function comparableSummaries(): array
    {
        return [
            'beta' => RequestSummary::create('beta')
                ->withRequest('/zulu', 'POST', '10.0.0.2', 1.0, true),
            'alpha' => RequestSummary::create('alpha')
                ->withRequest('/alpha', 'GET', '10.0.0.1', 2.0),
        ];
    }

    /**
     * Returns two captures whose query counts straddle the critical threshold.
     *
     * @return array<string, RequestSummary>
     */
    private function databaseSummaries(): array
    {
        return [
            'busy' => RequestSummary::create('busy')
                ->withRequest('/busy', 'GET', '', 2.0)
                ->withDatabase(12, 2),
            'calm' => RequestSummary::create('calm')
                ->withRequest('/calm', 'GET', '', 1.0)
                ->withDatabase(3),
        ];
    }

    private function dbPanel(): DbPanel
    {
        return (new DbPanel(new DbExplain(), new DebugUrlGenerator(), Trace::create()))->withThresholds(10, 5);
    }

    /**
     * @return array<string, RequestSummary>
     */
    private function summaries(): array
    {
        return [
            'missing' => RequestSummary::create('missing')
                ->withRequest('/missing', 'GET', '', 3.0),
            'long' => RequestSummary::create('long')
                ->withRequest('/long', 'GET', '', 2.0)
                ->withProfiling(0.2, 9_000_000),
            'short' => RequestSummary::create('short')
                ->withRequest('/short', 'GET', '', 1.0)
                ->withProfiling(0.1, 1_000_000),
        ];
    }

    /**
     * @return list<string>
     */
    private static function tags(string $html): array
    {
        preg_match_all('/data-yii-debug-tag="([^"]+)"/', $html, $matches);

        return $matches[1];
    }
}
