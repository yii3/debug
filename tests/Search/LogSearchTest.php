<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Search;

use PHPForge\Debug\Panel\Log\LogRow;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Search\LogSearch;

/**
 * Unit tests for the Yii2-compatible Log level, category, and message filters.
 */
final class LogSearchTest extends TestCase
{
    public function testFiltersLevelExactlyAndTextFieldsAsCaseInsensitiveSubstrings(): void
    {
        $match = self::row(1, 'app.database', 'Connection FAILED');
        $wrongLevel = self::row(10, 'app.database', 'Connection failed');
        $wrongCategory = self::row(1, 'app.http', 'Connection failed');
        $wrongMessage = self::row(1, 'app.database', 'Connection opened');

        $search = LogSearch::fromQueryParams(
            [
                'Log' => [
                    'level' => '1',
                    'category' => 'DATABASE',
                    'message' => 'failed',
                ],
            ],
        );

        self::assertSame(
            ['level' => '1', 'category' => 'DATABASE', 'message' => 'failed'],
            $search->activeFilters,
            'Log filters must use the canonical grouped query vocabulary.',
        );
        self::assertSame(
            '1',
            $search->level(),
            'Level must round-trip to the filter row.',
        );
        self::assertSame(
            'DATABASE',
            $search->category(),
            'Category must round-trip to the filter row.',
        );
        self::assertSame(
            'failed',
            $search->message(),
            'Message must round-trip to the filter row.',
        );
        self::assertSame(
            [$match],
            $search->filter([$match, $wrongLevel, $wrongCategory, $wrongMessage]),
            'All active filters must apply together with Yii2-compatible matching semantics.',
        );
    }

    public function testIgnoresALogGroupThatIsNotAnArray(): void
    {
        $row = self::row(4, 'application', 'request started');

        $search = LogSearch::fromQueryParams(['Log' => 'application']);

        self::assertSame(
            [],
            $search->activeFilters,
            'A malformed group must normalize to no active filters.',
        );
        self::assertSame(
            [$row],
            $search->filter([$row]),
            'A malformed group must leave all rows visible.',
        );
    }

    public function testIgnoresMalformedEmptyAndUnknownFilterValues(): void
    {
        $rows = [
            self::row(1, 'application', 'first'),
            self::row(2, 'database', 'second'),
        ];

        $search = LogSearch::fromQueryParams(
            [
                'Log' => [
                    'level' => [],
                    'category' => '',
                    'message' => ['second'],
                    'unknown' => 'value',
                ],
            ],
        );

        self::assertSame(
            [],
            $search->activeFilters,
            'Only supported non-empty scalar Log attributes may become active filters.',
        );
        self::assertSame(
            '',
            $search->level(),
            'An inactive level must render as empty.',
        );
        self::assertSame(
            '',
            $search->category(),
            'An inactive category must render as empty.',
        );
        self::assertSame(
            '',
            $search->message(),
            'An inactive message must render as empty.',
        );
        self::assertSame(
            $rows,
            $search->filter($rows),
            'Malformed or unsupported filter values must not hide captured rows.',
        );
    }

    private static function row(int $level, string $category, string $message): LogRow
    {
        return new LogRow(1, $message, $level, $category, 1.0, 1.0, 0.0, null, null, 0, []);
    }
}
