<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Web;

use PHPForge\Debug\Storage\RequestSummary;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Web\{GridFooter, HistoryGridRenderer};

use function preg_match_all;
use function substr_count;

/**
 * Tests accessible History controls and stable sorting before pagination.
 */
final class HistoryGridRendererTest extends TestCase
{
    public function testDefaultAndMalformedSortKeepNewestFirstAndEmptyFiltersExplainResults(): void
    {
        foreach ([[], ['sort' => 'unknown'], ['sort' => ['invalid']]] as $query) {
            self::assertSame(
                ['missing', 'long', 'short'],
                self::tags(HistoryGridRenderer::render($this->summaries(), $query, '/debug')),
                'Invalid sort input must safely use newest-first order.',
            );
        }

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
    public function testFiltersAndCurrentPageHaveAccessibleNames(): void
    {
        $html = HistoryGridRenderer::render(
            $this->summaries(),
            ['per-page' => '1', 'page' => '2'],
            '/debug',
        );

        foreach (['ID', 'IP', 'Method', 'AJAX', 'URL'] as $label) {
            self::assertStringContainsString(
                "aria-label=\"Filter by $label\"",
                $html,
                'Every History filter must expose an accessible name.',
            );
        }

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
        self::assertStringNotContainsString(
            '<nav',
            GridFooter::render(1, 0, 1)->render(),
            'A single page must not add empty navigation.',
        );
    }

    public function testNumericSortRunsBeforePaginationAndKeepsUnknownValuesLast(): void
    {
        foreach (['processingTime', 'peakMemory'] as $attribute) {
            $ascending = HistoryGridRenderer::render(
                $this->summaries(),
                ['sort' => $attribute, 'per-page' => 'all'],
                '/debug',
            );
            $descending = HistoryGridRenderer::render(
                $this->summaries(),
                ['sort' => "-{$attribute}", 'per-page' => 'all'],
                '/debug',
            );

            self::assertSame(
                ['short', 'long', 'missing'],
                self::tags($ascending),
                'Ascending metrics must keep unavailable values last.',
            );
            self::assertSame(
                ['long', 'short', 'missing'],
                self::tags($descending),
                'Descending metrics must keep unavailable values last.',
            );
        }

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
            'Sort toggles must retain filters and page size while resetting the page.'
        );
        self::assertStringContainsString(
            'aria-sort="ascending"',
            $page,
            'The active sort direction must be accessible.'
        );
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
