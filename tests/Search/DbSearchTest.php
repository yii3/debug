<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Search;

use PHPForge\Debug\Panel\Db\QueryRow;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Search\DbSearch;

/**
 * Unit tests for {@see DbSearch} covering the Yii2-compatible partial type and query filters.
 */
final class DbSearchTest extends TestCase
{
    public function testFilterMatchesTypeAndSqlTextCaseInsensitively(): void
    {
        $match = self::row('SELECT', 'SELECT * FROM users');
        $search = DbSearch::fromQueryParams(['Db' => ['type' => 'sel', 'query' => 'USERS']]);

        self::assertSame(
            [$match],
            $search->filter([$match, self::row('INSERT', 'INSERT INTO logs VALUES (1)')]),
            'Both database filters must apply as case-insensitive substrings.',
        );
    }

    private static function row(string $type, string $query): QueryRow
    {
        return new QueryRow($type, $query, 1.0, [], 'hash', 1.0, 0, 1, null);
    }
}
