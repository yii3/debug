<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Provider;

/**
 * Data provider for {@see \Yii3\Debug\Tests\Search\HistorySearchTest} test cases.
 */
final class HistorySearchProvider
{
    /**
     * @return iterable<string, array{array<string, string>, list<string>}>
     */
    public static function fieldFilters(): iterable
    {
        yield 'IP substring' => [['ip' => '168.1'], ['bb22']];
        yield 'status code exact match' => [['statusCode' => '404'], ['bb22']];
        yield 'tag case-insensitive substring' => [['tag' => 'A1'], ['aa11']];
        yield 'URL case-insensitive substring' => [['url' => 'USERS'], ['aa11']];
    }
}
