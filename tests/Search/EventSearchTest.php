<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Search;

use PHPForge\Debug\Panel\Event\EventRow;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Search\EventSearch;

/**
 * Unit tests for the Events filter vocabulary and matching rules.
 */
final class EventSearchTest extends TestCase
{
    public function testFilterAppliesCaseInsensitiveSubstringAndExactStaticConditions(): void
    {
        $match = self::row(
            name: 'App\\Event\\UserCreated',
            class: 'App\\Event\\UserCreated',
            senderClass: 'App\\Service\\UserWriter',
            isStatic: '0',
        );

        $rows = [
            $match,
            self::row(
                name: 'App\\Event\\UserDeleted',
                class: 'App\\Event\\UserDeleted',
                senderClass: 'App\\Service\\UserWriter',
                isStatic: '0',
            ),
            self::row(
                name: 'App\\Event\\UserCreated',
                class: 'App\\Event\\UserCreated',
                senderClass: 'App\\Service\\UserWriter',
                isStatic: '1',
            ),
        ];

        $search = EventSearch::fromQueryParams(
            [
                'Event' => [
                    'name' => 'userCREATED',
                    'class' => 'event\\user',
                    'senderClass' => 'SERVICE\\user',
                    'isStatic' => '0',
                ],
            ],
        );

        self::assertSame(
            $rows,
            EventSearch::fromQueryParams([])->filter($rows),
            'No active filters must preserve every row and dispatch order.',
        );
        self::assertSame(
            [$match],
            $search->filter($rows),
            'Text filters must be case-insensitive substrings and Static must match exactly.',
        );
    }
    public function testFromQueryParamsAcceptsOnlyKnownWellFormedFilters(): void
    {
        $search = EventSearch::fromQueryParams(
            [
                'Event' => [
                    'name' => 'created',
                    'class' => 42,
                    'senderClass' => ['invalid'],
                    'isStatic' => 'yes',
                    'unknown' => 'ignored',
                ],
            ],
        );

        self::assertSame(
            ['name' => 'created', 'class' => '42'],
            $search->activeFilters,
            'Unknown, nonscalar, empty, and invalid boolean filters must be ignored.',
        );
        self::assertSame(
            'created',
            $search->name(),
            'The name accessor must expose its normalized filter.',
        );
        self::assertSame(
            '42',
            $search->class(),
            'Numeric scalar filters must use the shared string normalization.',
        );
        self::assertSame(
            '',
            $search->senderClass(),
            'Malformed sender filters must remain inactive.',
        );
        self::assertSame(
            '',
            $search->isStatic(),
            'Static filters must accept only the Yes/No wire values.',
        );
    }

    public function testFromQueryParamsIgnoresMalformedGroupsAndEmptyValues(): void
    {
        self::assertSame(
            [],
            EventSearch::fromQueryParams(['Event' => 'invalid'])->activeFilters,
            'A non-array Event group must not activate filters.',
        );
        self::assertSame(
            [],
            EventSearch::fromQueryParams(
                ['Event' => ['name' => '', 'class' => [], 'senderClass' => '', 'isStatic' => null]],
            )->activeFilters,
            'Empty and nonscalar values must not activate filters.',
        );
    }

    private static function row(
        string $name,
        string $class,
        string $senderClass,
        string $isStatic,
    ): EventRow {
        return new EventRow(1.0, $name, $class, $isStatic, $senderClass);
    }
}
