<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Search;

use PHPForge\Debug\Panel\Log\LogRow;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Search\LogSearch;

/**
 * Unit tests for {@see LogSearch} covering grouped query input and exact/partial log filtering.
 */
final class LogSearchTest extends TestCase
{
    public function testFilterAppliesEveryActiveLogCondition(): void
    {
        $match = self::row(1, 'app.db', 'Connection failed');
        $search = LogSearch::fromQueryParams(
            ['Log' => ['level' => '1', 'category' => 'DB', 'message' => 'failed', 'empty' => '']],
        );

        self::assertSame(
            ['level' => '1', 'category' => 'DB', 'message' => 'failed'],
            $search->activeFilters,
            'Only non-empty scalar values must remain active.',
        );
        self::assertSame(
            [$match],
            $search->filter([$match, self::row(2, 'app.db', 'Connection failed')]),
            'Level must match exactly while category and message match partially.',
        );
    }

    private static function row(int $level, string $category, string $message): LogRow
    {
        return new LogRow(1, $message, $level, $category, 1.0, 1.0, 0.0, null, null, 0, []);
    }
}
