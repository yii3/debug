<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Search;

use PHPForge\Debug\Storage\RequestSummary;
use PHPForge\Debug\View\History\HistoryRow;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Search\HistorySearch;

use function array_map;

/**
 * Unit tests for {@see HistorySearch} request-history filtering.
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
}
