<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Search;

use PHPForge\Debug\Panel\Profile\ProfileRow;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Search\ProfileSearch;

/**
 * Unit tests for {@see ProfileSearch} applying Yii2-compatible category and info filters.
 */
final class ProfileSearchTest extends TestCase
{
    public function testFiltersCategoryAndInfoAsCaseInsensitiveSubstrings(): void
    {
        $search = ProfileSearch::fromQueryParams(
            ['Profile' => ['category' => 'db\\command', 'info' => 'select']],
        );
        $application = self::row('Yii3\\Application::handle', 'GET /');
        $select = self::row('Yiisoft\\Db\\Command::query', 'SELECT 1');
        $update = self::row('Yiisoft\\Db\\Command::query', 'UPDATE user');

        self::assertSame(
            [$select],
            $search->filter([$application, $select, $update]),
            'Both active partial filters must be applied together.',
        );
    }

    private static function row(string $category, string $info): ProfileRow
    {
        return new ProfileRow(0.0, 1.0, $category, $info, 0, 0, 0, 0, []);
    }
}
