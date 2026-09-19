<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Web;

use Closure;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Tests\Provider\GridFooterProvider;
use Yii3\Debug\Web\{GridFooter, PageWindow};
use Yiisoft\Data\Paginator\OffsetPaginator;

use function array_fill;
use function implode;
use function preg_match_all;
use function substr_count;

/**
 * Unit tests for the item range summary and the bounded page window rendered by {@see GridFooter}.
 *
 * {@see GridFooterProvider} for pagination and item range cases.
 */
final class GridFooterTest extends TestCase
{
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
        self::assertStringContainsString(
            '<li class="yii-debug-pager-item is-active"><a aria-label="Page 13" aria-current="page" '
            . 'class="yii-debug-pager-link" href="/debug?page=13">13</a></li>',
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
            GridFooter::render(self::paginator(500, 10, 3), 10)->render(),
            'A missing URL builder must not add navigation.',
        );
    }

    #[DataProviderExternal(GridFooterProvider::class, 'pageWindows')]
    public function testRendersBoundedPageWindowAroundTheCurrentPage(int $pageCount, int $page, string $expected): void
    {
        self::assertSame(
            $expected,
            self::pages(self::pager($page, $pageCount)),
            'Window must hold ten pages around the current one.',
        );
    }

    public function testRendersUnreachableControlsAsInertSpans(): void
    {
        $first = self::pager(1, 25);

        self::assertStringContainsString(
            '<li class="yii-debug-pager-item is-disabled"><span aria-label="First page" role="link" '
            . 'aria-disabled="true" class="yii-debug-pager-link">⟪</span></li>',
            $first,
            'Backward controls must be inert on the first page.',
        );
        self::assertSame(
            2,
            substr_count($first, '<span aria-label'),
            'Only the backward controls may be inert.',
        );
        self::assertStringContainsString(
            '<a aria-label="Last page" class="yii-debug-pager-link" href="/debug?page=25">⟫</a>',
            $first,
            'Forward controls must stay reachable.',
        );

        $last = self::pager(25, 25);

        self::assertStringContainsString(
            '<li class="yii-debug-pager-item is-disabled"><span aria-label="Next page" role="link" '
            . 'aria-disabled="true" class="yii-debug-pager-link">⟩</span></li>',
            $last,
            'Forward controls must be inert on the last page.',
        );
        self::assertStringNotContainsString(
            '<span aria-label',
            self::pager(13, 25),
            'Every control must stay reachable in the middle of the collection.',
        );
    }

    #[DataProviderExternal(GridFooterProvider::class, 'itemRanges')]
    public function testSummarizesTheVisibleItemRange(
        int $total,
        int $pageSize,
        int $page,
        int $visible,
        string $expected,
    ): void {
        self::assertStringContainsString(
            "<span class=\"summary yii-debug-grid-count\">{$expected}</span>",
            GridFooter::render(self::paginator($total, $pageSize, $page), $visible)->render(),
            'Range must cover the rows on screen.',
        );
    }

    private static function pager(int $page, int $pageCount): string
    {
        return GridFooter::render(self::paginator($pageCount, 1, $page), 1, self::url())->render();
    }

    /**
     * @return string Page numbers in the order they appear, separated by a single space.
     */
    private static function pages(string $html): string
    {
        preg_match_all('#aria-label="Page (\d+)"#', $html, $matches);

        return implode(' ', $matches[1]);
    }

    /**
     * @return OffsetPaginator<int, array{id: int}> Paginator holding one row per item of the collection.
     */
    private static function paginator(int $total, int $pageSize, int $page): OffsetPaginator
    {
        return PageWindow::paginate(array_fill(0, $total, ['id' => 1]), (string) $pageSize, (string) $page);
    }

    /**
     * @return Closure(string): string Builds the URL of a page from its number.
     */
    private static function url(): Closure
    {
        return static fn(string $page): string => "/debug?page={$page}";
    }
}
