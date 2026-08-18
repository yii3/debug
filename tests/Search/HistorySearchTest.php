<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Search;

use PHPForge\Debug\Storage\RequestSummary;
use PHPForge\Debug\View\History\HistoryRow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Search\HistorySearch;

use function array_map;

/**
 * Unit tests for {@see HistorySearch} covering the `Debug[attribute]` group parsing and the history filter semantics.
 *
 * @since 0.1
 */
#[Group('search')]
final class HistorySearchTest extends TestCase
{
    public function testFilterAppliesLeadingComparisonOperatorsOnCounters(): void
    {
        $search = HistorySearch::fromQueryParams(['Debug' => ['sqlCount' => '>5']]);

        $rows = $search->filter(self::rows([['sqlCount' => 2], ['sqlCount' => 9]]));

        self::assertCount(1, $rows, 'Only rows above the numeric bound must remain.');
        self::assertSame(9, $rows[0]->sqlCount, 'The row above the bound must survive.');
    }

    public function testFilterMatchesAjaxAgainstTheBooleanFlag(): void
    {
        $rows = self::rows([['ajax' => true], ['ajax' => false]]);

        $yes = HistorySearch::fromQueryParams(['Debug' => ['ajax' => '1']])->filter($rows);
        $no = HistorySearch::fromQueryParams(['Debug' => ['ajax' => '0']])->filter($rows);

        self::assertCount(1, $yes, "The '1' filter must keep only AJAX rows.");
        self::assertTrue($yes[0]->ajax, 'Surviving row must be AJAX.');
        self::assertCount(1, $no, "The '0' filter must keep only non-AJAX rows.");
        self::assertFalse($no[0]->ajax, 'Surviving row must be non-AJAX.');
    }

    public function testFilterMatchesSubstringsOnTagIpAndUrl(): void
    {
        $search = HistorySearch::fromQueryParams(['Debug' => ['url' => 'admin']]);

        $rows = $search->filter(
            self::rows([['url' => 'https://example.test/admin/users'], ['url' => 'https://example.test/']]),
        );

        self::assertCount(1, $rows, 'URL filters must match as substrings.');
    }

    public function testFilterReturnsAllRowsWithoutActiveFilters(): void
    {
        $search = HistorySearch::fromQueryParams([]);
        $rows = self::rows([['tag' => 'a'], ['tag' => 'b']]);

        self::assertSame($rows, $search->filter($rows), 'No active filters must keep every row.');
        self::assertSame([], $search->activeFilters, 'No query group must yield no active filters.');
    }

    public function testIsCodeCriticalFlagsConfiguredHttpStatusCodes(): void
    {
        $search = HistorySearch::fromQueryParams([]);

        self::assertTrue($search->isCodeCritical(500), 'Server errors must be flagged as critical.');
        self::assertTrue($search->isCodeCritical(404), 'Not-found responses must be flagged as critical.');
        self::assertFalse($search->isCodeCritical(200), 'Successful responses must not be flagged as critical.');
    }

    /**
     * @param list<array<string, mixed>> $overridesList
     *
     * @return list<HistoryRow>
     */
    private static function rows(array $overridesList): array
    {
        return array_map(
            static fn(array $overrides): HistoryRow => HistoryRow::fromSummary(
                RequestSummary::fromArray(
                    [
                        'tag' => 'tag-1',
                        'url' => 'https://example.test/',
                        'ajax' => false,
                        'method' => 'GET',
                        'ip' => '127.0.0.1',
                        'time' => 1_700_000_000.0,
                        'statusCode' => 200,
                        'sqlCount' => 0,
                        'excessiveCallersCount' => 0,
                        'mailCount' => 0,
                        'mailFiles' => [],
                        'processingTime' => null,
                        'peakMemory' => null,
                        ...$overrides,
                    ],
                ),
            ),
            $overridesList,
        );
    }
}
