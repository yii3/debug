<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Provider;

/**
 * Pagination and item range cases for {@see \Yii3\Debug\Tests\Web\GridFooterTest}.
 */
final class GridFooterProvider
{
    /**
     * Provides the collections whose visible range starts, ends, or stops on an uneven page.
     *
     * @return iterable<string, array{int, int, int, string}> Total rows, page size, current page, and the expected
     * summary.
     */
    public static function itemRanges(): iterable
    {
        yield 'empty collection' => [0, 10, 1, 'Showing 0-0 of 0 items.'];
        yield 'last page' => [23, 10, 3, 'Showing 21-23 of 23 items.'];
        yield 'single row' => [1, 10, 1, 'Showing 1-1 of 1 item.'];
        yield 'full page' => [100, 10, 1, 'Showing 1-10 of 100 items.'];
    }

    /**
     * Provides the page counts and current pages that move, clamp, or shrink the window of page links.
     *
     * @return iterable<string, array{int, int, string}> Total pages, current page, and the expected page numbers.
     */
    public static function pageWindows(): iterable
    {
        yield '25 pages on page 1' => [25, 1, '1 2 3 4 5 6 7 8 9 10'];
        yield '25 pages on page 6' => [25, 6, '1 2 3 4 5 6 7 8 9 10'];
        yield '25 pages on page 7' => [25, 7, '2 3 4 5 6 7 8 9 10 11'];
        yield '25 pages on page 13' => [25, 13, '8 9 10 11 12 13 14 15 16 17'];
        yield '25 pages on page 25' => [25, 25, '16 17 18 19 20 21 22 23 24 25'];
        yield '11 pages on page 1' => [11, 1, '1 2 3 4 5 6 7 8 9 10'];
        yield '10 pages on page 7' => [10, 7, '1 2 3 4 5 6 7 8 9 10'];
        yield '3 pages on page 2' => [3, 2, '1 2 3'];
        yield '2 pages on page 2' => [2, 2, '1 2'];
        yield '2000 pages on page 1000' => [2000, 1000, '995 996 997 998 999 1000 1001 1002 1003 1004'];
    }
}
