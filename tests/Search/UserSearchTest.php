<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Search;

use PHPUnit\Framework\TestCase;
use Yii3\Debug\Search\UserSearch;

/**
 * Unit tests for {@see UserSearch} covering exact and partial switch-grid filters.
 */
final class UserSearchTest extends TestCase
{
    public function testFilterAppliesTheCompleteUserVocabularyAndIgnoresTheTabMarker(): void
    {
        $rows = [
            [
                'id' => '1',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'status' => '10',
                'created_at' => '1700000000',
                'updated_at' => '1700000001',
            ],
            [
                'id' => '2',
                'username' => 'editor',
                'email' => 'editor@example.com',
                'status' => '9',
                'created_at' => '1800000000',
                'updated_at' => '1800000001',
            ],
        ];

        $search = UserSearch::fromQueryParams(
            [
                'User' => [
                    '_active' => 'switch',
                    'username' => 'DIT',
                    'email' => 'EDITOR@',
                    'status' => '9',
                    'created_at' => '1800',
                    'updated_at' => '000001',
                ],
            ],
        );

        self::assertSame(
            [$rows[1]],
            $search->filter($rows),
            'Every active filter must match while the internal tab marker remains outside the search vocabulary.',
        );
        self::assertSame(
            [
                'username' => 'DIT',
                'email' => 'EDITOR@',
                'status' => '9',
                'created_at' => '1800',
                'updated_at' => '000001',
            ],
            $search->activeFilters,
            'The active-filter banner must never expose the internal tab marker.',
        );
    }

    public function testFilterMatchesIdentityIdExactly(): void
    {
        $rows = [
            ['id' => '1', 'username' => '', 'email' => '', 'status' => '', 'created_at' => '', 'updated_at' => ''],
            ['id' => '10', 'username' => '', 'email' => '', 'status' => '', 'created_at' => '', 'updated_at' => ''],
        ];

        self::assertSame(
            [$rows[0]],
            UserSearch::fromQueryParams(['User' => ['id' => '1']])->filter($rows),
            'Identity IDs must use exact matching like the Yii2 integer filter.',
        );
    }
}
