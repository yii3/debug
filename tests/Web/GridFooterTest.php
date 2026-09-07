<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Web;

use Closure;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Web\GridFooter;

use function implode;
use function preg_match_all;
use function substr_count;

/**
 * Unit tests for the bounded page window and item range summary rendered by {@see GridFooter}.
 */
final class GridFooterTest extends TestCase
{
    public function testDefaultsToASinglePageOnTheFirstPage(): void
    {
        $html = GridFooter::render(100, 0, 10, pageCount: 25, pageUrl: self::url())->render();

        self::assertSame(
            '1 2 3 4 5 6 7 8 9 10 … 25',
            self::labels($html),
            'The window must open on the first page.',
        );
        self::assertMatchesRegularExpression(
            '#<li class="yii-debug-pager-item is-active">\s*<a class="yii-debug-pager-link" '
            . 'href="/debug\?page=1" aria-label="Page 1" aria-current="page">1</a>#',
            $html,
            'Page 1 must be the current one.',
        );
        self::assertStringNotContainsString(
            '<nav',
            GridFooter::render(100, 0, 10, pageUrl: self::url())->render(),
            'A defaulted page count must not add navigation.',
        );
    }

    public function testLinksFirstAndLastPagesWithLabelsAndGeneratedUrls(): void
    {
        $html = self::pager(13, 25);

        self::assertStringContainsString(
            '<a class="yii-debug-pager-link" href="/debug?page=1" aria-label="Page 1">1</a>',
            $html,
            'Edge link must address the first page.',
        );
        self::assertStringContainsString(
            '<a class="yii-debug-pager-link" href="/debug?page=25" aria-label="Page 25">25</a>',
            $html,
            'Edge link must address the last page.',
        );
        self::assertSame(
            14,
            substr_count($html, '<li class="yii-debug-pager-item'),
            'Ten window links, two gaps, and two edges make fourteen items.',
        );
    }

    public function testMarksOnlyTheCurrentPageAsCurrent(): void
    {
        $html = self::pager(13, 25);

        self::assertSame(
            1,
            substr_count($html, 'aria-current="page"'),
            'Only one link may be announced as current.',
        );
        self::assertSame(
            1,
            substr_count($html, 'is-active'),
            'Only one item may be highlighted.',
        );
        self::assertMatchesRegularExpression(
            '#<li class="yii-debug-pager-item is-active">\s*<a class="yii-debug-pager-link" '
            . 'href="/debug\?page=13" aria-label="Page 13" aria-current="page">13</a>#',
            $html,
            'Current markers must sit on page 13.',
        );
    }

    public function testOmitsNavigationWithoutPageUrlOrAdditionalPages(): void
    {
        self::assertStringNotContainsString(
            '<nav',
            self::pager(1, 1),
            'A single page must not add navigation.',
        );
        self::assertStringNotContainsString(
            '<nav',
            GridFooter::render(500, 0, 10, 3, 50)->render(),
            'A missing URL builder must not add navigation.',
        );
    }

    public function testRendersBoundedPageWindowWithFirstAndLastPageLinks(): void
    {
        $cases = [
            '25 pages on page 1' => [25, 1, '1 2 3 4 5 6 7 8 9 10 … 25'],
            '25 pages on page 6' => [25, 6, '1 2 3 4 5 6 7 8 9 10 … 25'],
            '25 pages on page 7' => [25, 7, '1 2 3 4 5 6 7 8 9 10 11 … 25'],
            '25 pages on page 8' => [25, 8, '1 … 3 4 5 6 7 8 9 10 11 12 … 25'],
            '25 pages on page 13' => [25, 13, '1 … 8 9 10 11 12 13 14 15 16 17 … 25'],
            '25 pages on page 25' => [25, 25, '1 … 16 17 18 19 20 21 22 23 24 25'],
            '11 pages on page 1' => [11, 1, '1 2 3 4 5 6 7 8 9 10 11'],
            '12 pages on page 1' => [12, 1, '1 2 3 4 5 6 7 8 9 10 … 12'],
            '10 pages on page 1' => [10, 1, '1 2 3 4 5 6 7 8 9 10'],
            '10 pages on page 7' => [10, 7, '1 2 3 4 5 6 7 8 9 10'],
            '10 pages on page 10' => [10, 10, '1 2 3 4 5 6 7 8 9 10'],
            '3 pages on page 2' => [3, 2, '1 2 3'],
            '2 pages on page 2' => [2, 2, '1 2'],
            '2000 pages on page 1000' => [2000, 1000, '1 … 995 996 997 998 999 1000 1001 1002 1003 1004 … 2000'],
        ];

        foreach ($cases as $label => [$pageCount, $page, $expected]) {
            self::assertSame(
                $expected,
                self::labels(self::pager($page, $pageCount)),
                "Window mismatch for {$label}.",
            );
        }
    }

    public function testSeparatesSkippedPagesWithInertEllipsisItems(): void
    {
        $html = self::pager(13, 25);

        self::assertSame(
            2,
            substr_count($html, '<li class="yii-debug-pager-item is-disabled" aria-hidden="true">'),
            'Both gaps must be inert and hidden.',
        );
        self::assertSame(
            2,
            substr_count($html, '<span class="yii-debug-pager-link">…</span>'),
            'Gaps must carry a raw ellipsis character.',
        );
        self::assertStringNotContainsString(
            '&hellip;',
            $html,
            'The ellipsis must not be encoded as an entity.',
        );
        self::assertStringNotContainsString(
            'is-disabled',
            self::pager(1, 11),
            'A window reaching every page must not add a gap.',
        );
    }

    public function testSummarizesTheVisibleItemRange(): void
    {
        self::assertStringContainsString(
            '<span class="summary yii-debug-grid-count">Showing 0-0 of 0 items.</span>',
            GridFooter::render(0, 0, 0)->render(),
            'An empty grid must start the range at zero.',
        );
        self::assertStringContainsString(
            '<span class="summary yii-debug-grid-count">Showing 21-23 of 23 items.</span>',
            GridFooter::render(23, 20, 3)->render(),
            'The last page must be offset by one item.',
        );
        self::assertStringContainsString(
            '<span class="summary yii-debug-grid-count">Showing 21-23 of 23 items.</span>',
            GridFooter::render(23, 20, 5)->render(),
            'A partially filled page must stop at the total.',
        );
        self::assertStringContainsString(
            'Showing 1-10 of 100 items.',
            self::pager(1, 10),
            'A full page must span the requested size.',
        );
    }

    /**
     * @return string Page numbers and gaps in the order they appear, separated by a single space.
     */
    private static function labels(string $html): string
    {
        preg_match_all('#<li[^>]*>\s*<(?:a|span)[^>]*>([^<]*)</#u', $html, $matches);

        return implode(' ', $matches[1]);
    }

    private static function pager(int $page, int $pageCount): string
    {
        return GridFooter::render(100, 0, 10, $page, $pageCount, self::url())->render();
    }

    /**
     * @return Closure(int): string
     */
    private static function url(): Closure
    {
        return static fn(int $number): string => "/debug?page={$number}";
    }
}
