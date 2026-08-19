<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Search;

use PHPForge\Debug\Panel\Event\EventRow;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Search\EventSearch;

/**
 * Unit tests for {@see EventSearch} covering the static flag and partial event metadata filters.
 */
final class EventSearchTest extends TestCase
{
    public function testFilterAppliesStaticAndMetadataConditions(): void
    {
        $match = new EventRow(1.0, 'afterRequest', 'App\Event\RequestEvent', '1', '');
        $search = EventSearch::fromQueryParams(
            ['Event' => ['isStatic' => '1', 'name' => 'request', 'class' => 'EVENT', 'senderClass' => '']],
        );

        self::assertSame(
            ['isStatic' => '1', 'name' => 'request', 'class' => 'EVENT'],
            $search->activeFilters,
            'Empty sender filters must not remain active.',
        );
        self::assertSame(
            [$match],
            $search->filter([$match, new EventRow(2.0, 'afterRequest', 'App\Event\RequestEvent', '0', 'App')]),
            'Static matching must be exact while name and class remain partial.',
        );
    }
}
