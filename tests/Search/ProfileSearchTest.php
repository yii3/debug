<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Search;

use PHPForge\Debug\Panel\Profile\ProfileRow;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Search\ProfileSearch;

/**
 * Unit tests for Yii2-compatible category and info profiling filters.
 */
final class ProfileSearchTest extends TestCase
{
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

    private static function row(string $category, string $info): ProfileRow
    {
        return new ProfileRow(0.0, 1.0, $category, $info, 0, 0, 0, 0, []);
    }
}
