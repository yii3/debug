<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Provider;

/**
 * Data provider for {@see \Yii3\Debug\Tests\Web\PageWindowTest} test cases.
 */
final class PageWindowProvider
{
    /**
     * @return iterable<string, array{int, string|null, string|null, int, int, int, int}>
     */
    public static function windows(): iterable
    {
        yield 'single row with all page size' => [1, 'all', null, 1, 1, 1, 0];
        yield 'second page of a partial last page' => [25, '10', '2', 10, 3, 2, 10];
    }
}
