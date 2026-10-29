<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Search;

use PHPForge\Debug\Storage\RequestSummary;
use PHPForge\Debug\View\History\HistoryRow;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Search\HistorySearch;
use Yii3\Debug\Tests\Provider\HistorySearchProvider;

use function array_map;

/**
 * Unit tests for {@see HistorySearch} request-history filtering.
 *
 * {@see HistorySearchProvider} for test case data providers.
 */
final class HistorySearchTest extends TestCase
{
    public function testFilterMatchesAjaxAndNonAjaxRequests(): void
    {
        $rows = [
            HistoryRow::fromSummary(
                RequestSummary::create('regular')
                    ->withRequest('https://example.test/regular', 'GET', '127.0.0.1', 1.0),
            ),
            HistoryRow::fromSummary(
                RequestSummary::create('ajax')
                    ->withRequest('https://example.test/ajax', 'GET', '127.0.0.1', 2.0, true),
            ),
        ];

        $ajaxRows = HistorySearch::fromQueryParams(['Debug' => ['ajax' => '1']])->filter($rows);
        $regularRows = HistorySearch::fromQueryParams(['Debug' => ['ajax' => '0']])->filter($rows);

        self::assertSame(
            ['ajax'],
            array_map(static fn(HistoryRow $row): string => $row->tag, $ajaxRows),
            'The AJAX filter must retain only AJAX requests.',
        );
        self::assertSame(
            ['regular'],
            array_map(static fn(HistoryRow $row): string => $row->tag, $regularRows),
            'The non-AJAX filter must retain only regular requests.',
        );
    }

    /**
     * @param array<string, string> $filters Submitted `Debug` filter values.
     * @param list<string> $expectedTags Tags of the captures expected to remain, in order.
     */
    #[DataProviderExternal(HistorySearchProvider::class, 'fieldFilters')]
    public function testFilterMatchesTheSubmittedFieldValue(array $filters, array $expectedTags): void
    {
        self::assertSame(
            $expectedTags,
            $this->tags($filters),
            'Only captures matching the submitted value must remain.',
        );
    }

    /**
     * Filters two distinct captures with the given `Debug` group and returns the tags left, in order.
     *
     * @param array<string, string> $filters Submitted `Debug` filter values.
     *
     * @return list<string> Tags of the captures matching the filters.
     */
    private function tags(array $filters): array
    {
        $rows = [
            HistoryRow::fromSummary(
                RequestSummary::create('aa11')
                    ->withRequest('https://example.test/users/list', 'GET', '10.0.0.1', 1.0)
                    ->withResponse(200),
            ),
            HistoryRow::fromSummary(
                RequestSummary::create('bb22')
                    ->withRequest('https://example.test/posts', 'POST', '192.168.1.5', 2.0)
                    ->withResponse(404),
            ),
        ];

        return array_map(
            static fn(HistoryRow $row): string => $row->tag,
            HistorySearch::fromQueryParams(['Debug' => $filters])->filter($rows),
        );
    }
}
