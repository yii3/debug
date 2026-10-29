<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Web;

use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Tests\Provider\PageWindowProvider;
use Yii3\Debug\Web\PageWindow;

/**
 * Unit tests for the page size, clamped page, and row offset resolved by {@see PageWindow}.
 *
 * {@see PageWindowProvider} for test case data providers.
 */
final class PageWindowTest extends TestCase
{
    #[DataProviderExternal(PageWindowProvider::class, 'windows')]
    public function testResolvesLimitPageCountPageAndOffset(
        int $total,
        string|null $perPage,
        string|null $page,
        int $expectedLimit,
        int $expectedPageCount,
        int $expectedPage,
        int $expectedOffset,
    ): void {
        $window = new PageWindow($total, $perPage, $page);

        self::assertSame(
            $expectedLimit,
            $window->limit,
            'Limit must follow the resolved page size.',
        );
        self::assertSame(
            $expectedPageCount,
            $window->pageCount,
            'Page count must round the last partial page up.',
        );
        self::assertSame(
            $expectedPage,
            $window->page,
            'Page must follow the requested page.',
        );
        self::assertSame(
            $expectedOffset,
            $window->offset,
            'Offset must skip the rows of the previous pages.',
        );
    }
}
