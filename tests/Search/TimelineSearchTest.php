<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Search;

use PHPForge\Debug\Panel\Profile\ProfileRow;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Search\TimelineSearch;

/**
 * Unit tests for {@see TimelineSearch} applying minimum-duration and category filters.
 */
final class TimelineSearchTest extends TestCase
{
    public function testFiltersByMinimumDurationAndPartialCategory(): void
    {
        $search = TimelineSearch::fromQueryParams(
            ['Timeline' => ['duration' => '5', 'category' => 'db\\command']],
        );
        $application = self::row('Yii3\\Application::handle', 100.0);
        $shortQuery = self::row('Yiisoft\\Db\\Command::query', 4.9);
        $visibleQuery = self::row('Yiisoft\\Db\\Command::query', 5.0);

        self::assertSame('5', $search->duration(), 'Submitted duration must round-trip to the filter form.');
        self::assertSame('db\\command', $search->category(), 'Submitted category must round-trip to the filter form.');
        self::assertSame(
            [$visibleQuery],
            $search->filter([$application, $shortQuery, $visibleQuery]),
            'Timeline filters must apply an inclusive duration floor and partial category match.',
        );
    }

    private static function row(string $category, float $duration): ProfileRow
    {
        return new ProfileRow(0.0, $duration, $category, '', 0, 0, 0, 0, []);
    }
}
