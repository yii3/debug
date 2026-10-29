<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Provider;

use Yii3\Debug\Tests\Support\DatabaseFixture;

/**
 * External heading states, sorting cases for Database panel columns, and toolbar chip tooltip states.
 */
final class DbPanelProvider
{
    /**
     * @return array<string, array{array<string, mixed>, array<array-key, mixed>, non-empty-string}>
     */
    public static function headingStates(): array
    {
        return [
            'empty capture' => [
                ['entries' => []],
                [],
                <<<'HTML'
                <h1 class="yii-debug-sr-only">
                Database
                </h1><header class="yii-debug-grid-summary">
                <span><strong>0</strong> queries</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>0.000</strong> ms total</span>
                </header><div class="yii-debug-empty-state">
                <h2>
                No database queries in this request
                </h2><p>
                This request completed without executing SQL through an instrumented DB connection.
                </p><p>
                Configure the development connection with Yii3\Debug\Db\DebugDbProfiler. Rows are shown only when the driver reports them. After a redirect, open the previous request from History.
                </p>
                </div>
                HTML,
            ],
            'no match' => [
                DatabaseFixture::snapshot()->jsonSerialize(),
                ['Db' => ['query' => 'absent']],
                <<<HTML
                <h1 class="yii-debug-sr-only">
                Database
                </h1><header class="yii-debug-grid-summary">
                <span><strong>3</strong> queries</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>6.000</strong> ms total</span><span class="yii-debug-grid-summary-sep">·</span><span class="yii-debug-grid-summary-stat-warn"><strong>2</strong> duplicated</span><label class="yii-debug-grid-pagesize"><span class="yii-debug-grid-pagesize-label">Rows</span><select class="yii-debug-grid-pagesize-select" name="per-page" data-yii-debug-pagesize="true">
                <option value="10">
                10
                </option>
                <option value="25">
                25
                </option>
                <option value="50" selected>
                50
                </option>
                <option value="100">
                100
                </option>
                <option value="all">
                All
                </option>
                </select></label>
                </header><div class="yii-debug-active-filters" role="group" aria-label="Active filters">
                <span class="yii-debug-active-filters-label">1 filter active</span><span class="yii-debug-active-filters-list"><a class="yii-debug-active-filter-pill" href="/inspect/view?tag=capture&amp;panel=db" title="Remove this filter" aria-label="Remove query: absent filter"><span class="yii-debug-active-filter-attr">query</span><span class="yii-debug-active-filter-sep">:</span><span class="yii-debug-active-filter-value">absent</span><span class="yii-debug-active-filter-x" aria-hidden="true">×</span></a></span><a class="yii-debug-active-filters-clear" href="/inspect/view?tag=capture&amp;panel=db" title="Clear all filters and show every row" aria-label="Clear all active filters">Clear all</a>
                </div><div class="yii-debug-empty-state">
                <h2>
                No database queries match these filters
                </h2><p>
                Remove an active filter or clear all filters to see the captured queries.
                </p>
                </div>
                HTML,
            ],
            'grid' => [
                DatabaseFixture::snapshot()->jsonSerialize(),
                [],
                <<<HTML
                <h1 class="yii-debug-sr-only">
                Database
                </h1><header class="yii-debug-grid-summary">
                <span><strong>3</strong> queries</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>6.000</strong> ms total</span><span class="yii-debug-grid-summary-sep">·</span><span class="yii-debug-grid-summary-stat-warn"><strong>2</strong> duplicated</span><label class="yii-debug-grid-pagesize"><span class="yii-debug-grid-pagesize-label">Rows</span><select class="yii-debug-grid-pagesize-select" name="per-page" data-yii-debug-pagesize="true">
                <option value="10">
                10
                </option>
                <option value="25">
                25
                </option>
                <option value="50" selected>
                50
                </option>
                <option value="100">
                100
                </option>
                <option value="all">
                All
                </option>
                </select></label>
                </header><div class="yii-debug-grid yii-debug-grid-db">
                HTML,
            ],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function sorts(): array
    {
        return [
            'default' => ['', 'SELECT alpha'],
            'unknown' => ['invalid', 'SELECT alpha'],
            'time' => ['-seq', 'SELECT gamma'],
            'duration' => ['duration', 'UPDATE beta'],
            'descending duration' => ['-duration', 'SELECT alpha'],
            'type' => ['-type', 'UPDATE beta'],
            'type tie' => ['type', 'SELECT alpha'],
            'query' => ['-query', 'UPDATE beta'],
            'rows' => ['-rows', 'SELECT alpha'],
            'duplicates' => ['duplicate', 'UPDATE beta'],
        ];
    }

    /**
     * @return array<string, array{int|null, int|null, string}>
     */
    public static function toolbarTitles(): array
    {
        return [
            'disabled thresholds' => [null, null, 'Executed 3 database queries.'],
            'critical query count' => [2, null, 'Too many queries, allowed count is 2.'],
            'inclusive caller threshold' => [null, 3, '1 caller is making too many calls.'],
            'both thresholds breached' => [2, 3, "Too many queries, allowed count is 2.\n1 caller is making too many calls."],
        ];
    }
}
