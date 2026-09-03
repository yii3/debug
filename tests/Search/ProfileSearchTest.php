<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Search;

use PHPForge\Debug\Panel\Profile\ProfileRow;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Search\ProfileSearch;

/**
 * Unit tests for the shared Profiling duration, category, and info filters.
 */
final class ProfileSearchTest extends TestCase
{
    public function testFiltersByInclusiveDurationCategoryAndInfo(): void
    {
        $search = ProfileSearch::fromQueryParams(
            [
                'Profile' => [
                    'duration' => '5',
                    'category' => 'db\\command',
                    'info' => 'select',
                ],
            ],
        );

        $application = self::row('Yii3\\Application::handle', 'GET /', 100.0);
        $shortQuery = self::row('Yiisoft\\Db\\Command::query', 'SELECT short', 4.9);
        $visibleQuery = self::row('Yiisoft\\Db\\Command::query', 'SELECT visible', 5.0);

        self::assertSame(
            ['duration' => '5', 'category' => 'db\\command', 'info' => 'select'],
            $search->activeFilters,
            'The unified filter state must use the canonical Profile query group.',
        );
        self::assertSame(
            '5',
            $search->duration(),
            'Duration must round-trip to the shared filter form.',
        );
        self::assertSame(
            'db\\command',
            $search->category(),
            'Category must round-trip to the shared filter form.',
        );
        self::assertSame(
            'select',
            $search->info(),
            'Info must round-trip to the shared filter form.',
        );
        self::assertSame(
            [$visibleQuery],
            $search->filter([$application, $shortQuery, $visibleQuery]),
            'All shared filters must apply to the same profile rows with an inclusive duration boundary.',
        );
    }

    public function testFiltersCategoryAndInfoAsCaseInsensitiveSubstrings(): void
    {
        $search = ProfileSearch::fromQueryParams(
            [
                'Profile' => [
                    'category' => 'db\\command',
                    'info' => 'select',
                ],
            ],
        );

        $application = self::row('Yii3\\Application::handle', 'GET /');
        $select = self::row('Yiisoft\\Db\\Command::query', 'SELECT 1');
        $update = self::row('Yiisoft\\Db\\Command::query', 'UPDATE user');

        self::assertSame(
            ['category' => 'db\\command', 'info' => 'select'],
            $search->activeFilters,
            'Normalized profile filters must remain available to the grid banner.',
        );
        self::assertSame(
            [$select],
            $search->filter([$application, $select, $update]),
            'Both active partial filters must be applied together.',
        );
    }

    public function testIgnoresAProfileGroupThatIsNotAnArray(): void
    {
        $row = self::row('Application', 'request');

        $search = ProfileSearch::fromQueryParams(['Profile' => 'request']);

        self::assertSame(
            [],
            $search->activeFilters,
            'A malformed filter group must normalize to no filters.',
        );
        self::assertSame(
            [$row],
            $search->filter([$row]),
            'A malformed group must not hide captured rows.',
        );
    }

    public function testIgnoresMissingEmptyAndNonScalarFilterValues(): void
    {
        $rows = [
            self::row('Application', 'first'),
            self::row('Database', 'second'),
        ];

        $search = ProfileSearch::fromQueryParams(
            [
                'Profile' => [
                    'category' => '',
                    'info' => ['second'],
                ],
            ],
        );

        self::assertSame(
            [],
            $search->activeFilters,
            'Inactive filter values must not render as active pills.',
        );
        self::assertSame(
            $rows,
            $search->filter($rows),
            'Inactive values must not remove profile rows.',
        );
    }

    public function testInvalidMinimumDurationsAreIgnoredWithoutDiscardingValidFilters(): void
    {
        $application = self::row('Application', 'request', 1.0);
        $database = self::row('Database', 'query', 1.0);

        foreach (['not-a-number', '-0.1', '1e9999'] as $duration) {
            $search = ProfileSearch::fromQueryParams(
                ['Profile' => ['duration' => $duration, 'category' => 'database']],
            );

            self::assertSame(
                ['category' => 'database'],
                $search->activeFilters,
                "Invalid minimum duration {$duration} must be omitted without discarding valid filters.",
            );
            self::assertSame(
                '',
                $search->duration(),
                "Invalid minimum duration {$duration} must not round-trip to the numeric input.",
            );
            self::assertSame(
                [$database],
                $search->filter([$application, $database]),
                "Invalid minimum duration {$duration} must leave the valid category filter active.",
            );
        }
    }

    private static function row(string $category, string $info, float $duration = 1.0): ProfileRow
    {
        return new ProfileRow(0.0, $duration, $category, $info, 0, 0, 0, 0, []);
    }
}
