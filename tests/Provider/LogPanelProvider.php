<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Provider;

/**
 * Filter navigation, heading order, sorting, and pagination cases for {@see \Yii3\Debug\Tests\Panel\LogPanelTest}.
 */
final class LogPanelProvider
{
    /**
     * @return iterable<string, array{array<array-key, mixed>, list<string>}>
     */
    public static function filterRemoval(): iterable
    {
        yield 'each removal starts from the complete normalized group' => [
            [
                'message' => 'slow query',
                'category' => 'app.db',
                'level' => 2,
            ],
            [
                'Log%5Bcategory%5D=app.db&Log%5Bmessage%5D=slow%20query&',
                'Log%5Blevel%5D=2&Log%5Bmessage%5D=slow%20query&',
                'Log%5Blevel%5D=2&Log%5Bcategory%5D=app.db&',
                '',
            ],
        ];
        yield 'malformed and unknown filters do not return in removal links' => [
            [
                'message' => 'query',
                'level' => ['invalid'],
                'category' => 'app.db',
                'unknown' => 'ignored',
                0 => 'ignored',
            ],
            [
                'Log%5Bmessage%5D=query&',
                'Log%5Bcategory%5D=app.db&',
                '',
            ],
        ];
        yield 'numeric zero remains an active filter' => [
            [
                'level' => 0,
                'category' => '',
                'message' => false,
            ],
            ['', ''],
        ];
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, non-empty-string}>
     */
    public static function headingStates(): iterable
    {
        yield 'grid follows the summary' => [
            [],
            <<<HTML
            <h1 class="yii-debug-sr-only">
            Log Messages
            </h1><header class="yii-debug-grid-summary">
            <span><strong>4</strong> messages</span><span class="yii-debug-grid-summary-sep">·</span><a class="yii-debug-grid-summary-stat-danger" href="/debug/view?tag=request-1&amp;panel=log&amp;Log%5Blevel%5D=1" title="Show only error log messages" aria-label="1 errors; filter log messages by error level"><strong>1</strong> errors</a><span class="yii-debug-grid-summary-sep">·</span><a class="yii-debug-grid-summary-stat-warn" href="/debug/view?tag=request-1&amp;panel=log&amp;Log%5Blevel%5D=2" title="Show only warning log messages" aria-label="1 warnings; filter log messages by warning level"><strong>1</strong> warnings</a><span class="yii-debug-grid-summary-sep">·</span><a class="yii-debug-grid-summary-stat-info" href="/debug/view?tag=request-1&amp;panel=log&amp;Log%5Blevel%5D=4" title="Show only info log messages" aria-label="1 info; filter log messages by info level"><strong>1</strong> info</a><span class="yii-debug-grid-summary-sep">·</span><a class="yii-debug-grid-summary-stat-trace" href="/debug/view?tag=request-1&amp;panel=log&amp;Log%5Blevel%5D=8" title="Show only trace log messages" aria-label="1 trace; filter log messages by trace level"><strong>1</strong> trace</a><label class="yii-debug-grid-pagesize"><span class="yii-debug-grid-pagesize-label">Rows</span><select class="yii-debug-grid-pagesize-select" name="per-page" data-yii-debug-pagesize="true">
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
            </header><div class="yii-debug-grid yii-debug-grid-log">
            HTML,
        ];
        yield 'no-match card follows the summary and the filter banner' => [
            ['Log' => ['message' => 'missing']],
            <<<HTML
            <h1 class="yii-debug-sr-only">
            Log Messages
            </h1><header class="yii-debug-grid-summary">
            <span><strong>4</strong> messages</span><span class="yii-debug-grid-summary-sep">·</span><a class="yii-debug-grid-summary-stat-danger" href="/debug/view?tag=request-1&amp;panel=log&amp;Log%5Blevel%5D=1" title="Show only error log messages" aria-label="1 errors; filter log messages by error level"><strong>1</strong> errors</a><span class="yii-debug-grid-summary-sep">·</span><a class="yii-debug-grid-summary-stat-warn" href="/debug/view?tag=request-1&amp;panel=log&amp;Log%5Blevel%5D=2" title="Show only warning log messages" aria-label="1 warnings; filter log messages by warning level"><strong>1</strong> warnings</a><span class="yii-debug-grid-summary-sep">·</span><a class="yii-debug-grid-summary-stat-info" href="/debug/view?tag=request-1&amp;panel=log&amp;Log%5Blevel%5D=4" title="Show only info log messages" aria-label="1 info; filter log messages by info level"><strong>1</strong> info</a><span class="yii-debug-grid-summary-sep">·</span><a class="yii-debug-grid-summary-stat-trace" href="/debug/view?tag=request-1&amp;panel=log&amp;Log%5Blevel%5D=8" title="Show only trace log messages" aria-label="1 trace; filter log messages by trace level"><strong>1</strong> trace</a><label class="yii-debug-grid-pagesize"><span class="yii-debug-grid-pagesize-label">Rows</span><select class="yii-debug-grid-pagesize-select" name="per-page" data-yii-debug-pagesize="true">
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
            <span class="yii-debug-active-filters-label">1 filter active</span><span class="yii-debug-active-filters-list"><a class="yii-debug-active-filter-pill" href="/debug/view?tag=request-1&amp;panel=log" title="Remove this filter" aria-label="Remove message: missing filter"><span class="yii-debug-active-filter-attr">message</span><span class="yii-debug-active-filter-sep">:</span><span class="yii-debug-active-filter-value">missing</span><span class="yii-debug-active-filter-x" aria-hidden="true">×</span></a></span><a class="yii-debug-active-filters-clear" href="/debug/view?tag=request-1&amp;panel=log" title="Clear all filters and show every row" aria-label="Clear all active filters">Clear all</a>
            </div><div class="yii-debug-empty-state">
            <h2>
            No log messages match the active filters
            </h2><p>
            Adjust or clear the filters to show the captured messages.
            </p>
            </div>
            HTML,
        ];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<int>, string}>
     */
    public static function pages(): iterable
    {
        yield 'All ignores requested page' => [
            ['per-page' => 'ALL', 'page' => '99'],
            [1, 2, 3, 4],
            'Showing 1-4 of 4 items.',
        ];
        yield 'combined filters precede pagination' => [
            ['Log' => ['category' => 'app.db', 'message' => 'query'], 'per-page' => '1', 'page' => '99'],
            [2],
            'Showing 1-1 of 1 item.',
        ];
        yield 'invalid size uses default' => [
            ['per-page' => ['1'], 'page' => 'invalid'],
            [1, 2, 3, 4],
            'Showing 1-4 of 4 items.',
        ];
        yield 'last page is clamped' => [
            ['per-page' => '3', 'page' => '99'],
            [4],
            'Showing 4-4 of 4 items.',
        ];
        yield 'negative page is clamped' => [
            ['per-page' => '3', 'page' => '-2'],
            [1, 2, 3],
            'Showing 1-3 of 4 items.',
        ];
    }

    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function sorting(): iterable
    {
        yield 'case-insensitive message ties use IDs' => ['-message', [1, 2, 3, 4]];
        yield 'invalid sort uses time ascending' => ['invalid', [1, 2, 3, 4]];
        yield 'numeric level ascending' => ['level', [1, 4, 2, 3]];
        yield 'numeric level descending with stable IDs' => ['-level', [3, 2, 4, 1]];
        yield 'numeric time ascending' => ['time', [1, 2, 3, 4]];
        yield 'numeric time descending with stable IDs' => ['-time', [4, 2, 3, 1]];
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function summaryLinks(): iterable
    {
        $navigation = [
            'sort' => '-time',
            'per-page' => 'all',
            'page' => '9',
            'yii_debug_theme' => 'dark',
            'return' => 'overview & details',
            'Other' => ['key' => 'value'],
        ];

        $query = 'sort=-time&per-page=all&yii_debug_theme=dark&return=overview%20%26%20details&Other%5Bkey%5D=value';

        yield 'replace all accepted filters and discard unknown values' => [
            ['Log' => ['message' => 'missing', 'category' => 'app.db', 'level' => 2, 'unknown' => 'ignored']]
            + $navigation,
            "Log%5Blevel%5D={level}&{$query}",
        ];
        yield 'empty normalized group is appended after navigation' => [
            ['Log' => ['level' => ['invalid'], 'category' => '', 'message' => false]] + $navigation,
            "{$query}&Log%5Blevel%5D={level}",
        ];
        yield 'malformed group is appended after navigation' => [
            ['Log' => 'invalid'] + $navigation,
            "{$query}&Log%5Blevel%5D={level}",
        ];
        yield 'absent group is appended after navigation' => [
            $navigation,
            "{$query}&Log%5Blevel%5D={level}",
        ];
    }
}
