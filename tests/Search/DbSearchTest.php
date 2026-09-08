<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Search;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Search\DbSearch;
use Yii3\Debug\Tests\Support\DatabaseFixture;

/**
 * Unit tests for normalized Database filter input and Yii2-compatible partial matching.
 */
#[Group('db')]
final class DbSearchTest extends TestCase
{
    public function testFiltersNormalizeMalformedValuesAndCombineCaseInsensitiveSubstrings(): void
    {
        foreach ([[], ['Db' => 'invalid'], ['Db' => ['type' => [], 'query' => [], 'unused' => 'x']]] as $query) {
            self::assertSame([], DbSearch::fromQueryParams($query)->activeFilters, 'Malformed filter input must be ignored.');
        }
        $search = DbSearch::fromQueryParams(['Db' => ['type' => 'sel', 'query' => 'GAM']]);
        self::assertSame(['type' => 'sel', 'query' => 'GAM'], $search->activeFilters, 'Only known filters may be retained.');
        self::assertEquals([DatabaseFixture::snapshot()->entries()[2] ?? null], $search->filter(DatabaseFixture::snapshot()->entries()), 'SQL type and text filters must combine.');
    }
    public function testTypeFilterWorksWithoutATextFilter(): void
    {
        $rows = DatabaseFixture::snapshot()->entries();
        self::assertSame([$rows[1] ?? null], DbSearch::fromQueryParams(['Db' => ['type' => 'upd']])->filter($rows), 'Type filters must work independently.');
    }
}
