<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Provider;

/**
 * Filter navigation, sorting, and pagination cases for {@see \Yii3\Debug\Tests\Panel\LogPanelTest}.
 */
final class LogPanelProvider
{
    /**
     * @return iterable<string, array{array<array-key, mixed>, list<string>}>
     */
    public static function filterRemoval(): iterable
    {
        yield 'each removal starts from the complete normalized group' => [
            [
                'message' => 'slow query',
                'category' => 'app.db',
                'level' => 2,
            ],
            [
                'Log%5Bcategory%5D=app.db&Log%5Bmessage%5D=slow%20query&',
                'Log%5Blevel%5D=2&Log%5Bmessage%5D=slow%20query&',
                'Log%5Blevel%5D=2&Log%5Bcategory%5D=app.db&',
                '',
            ],
        ];
        yield 'malformed and unknown filters do not return in removal links' => [
            [
                'message' => 'query',
                'level' => ['invalid'],
                'category' => 'app.db',
                'unknown' => 'ignored',
                0 => 'ignored',
            ],
            [
                'Log%5Bmessage%5D=query&',
                'Log%5Bcategory%5D=app.db&',
                '',
            ],
        ];
        yield 'numeric zero remains an active filter' => [
            [
                'level' => 0,
                'category' => '',
                'message' => false,
            ],
            ['', ''],
        ];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<int>, string}>
     */
    public static function pages(): iterable
    {
        yield 'All ignores requested page' => [
            ['per-page' => 'ALL', 'page' => '99'],
            [1, 2, 3, 4],
            'Showing 1-4 of 4 items.',
        ];
        yield 'combined filters precede pagination' => [
            ['Log' => ['category' => 'app.db', 'message' => 'query'], 'per-page' => '1', 'page' => '99'],
            [2],
            'Showing 1-1 of 1 items.',
        ];
        yield 'invalid size uses default' => [
            ['per-page' => ['1'], 'page' => 'invalid'],
            [1, 2, 3, 4],
            'Showing 1-4 of 4 items.',
        ];
        yield 'last page is clamped' => [
            ['per-page' => '3', 'page' => '99'],
            [4],
            'Showing 4-4 of 4 items.',
        ];
        yield 'negative page is clamped' => [
            ['per-page' => '3', 'page' => '-2'],
            [1, 2, 3],
            'Showing 1-3 of 4 items.',
        ];
    }

    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function sorting(): iterable
    {
        yield 'case-insensitive message ties use IDs' => ['-message', [1, 2, 3, 4]];
        yield 'invalid sort uses time ascending' => ['invalid', [1, 2, 3, 4]];
        yield 'numeric level ascending' => ['level', [1, 4, 2, 3]];
        yield 'numeric level descending with stable IDs' => ['-level', [3, 2, 4, 1]];
        yield 'numeric time ascending' => ['time', [1, 2, 3, 4]];
        yield 'numeric time descending with stable IDs' => ['-time', [4, 2, 3, 1]];
    }
}
