<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Provider;

use PHPForge\Debug\Panel\Event\{EventRow, EventSnapshot};
use Yii3\Debug\Tests\Panel\EventPanelTest;

/**
 * Provides panel states and query variations for the unified event table in {@see EventPanelTest}.
 */
final class EventPanelProvider
{
    /**
     * @return iterable<string, array{string|null, string}>
     */
    public static function columnSorts(): iterable
    {
        yield 'default time' => [null, 'Mike'];
        yield 'event class' => ['class', 'Zulu'];
        yield 'source' => ['senderClass', 'Alpha'];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>, non-empty-string}>
     */
    public static function headingStates(): iterable
    {
        $payload = (
            new EventSnapshot(
                [
                    new EventRow(
                        1.0,
                        'App\\Event\\UserCreated',
                        'App\\Event\\UserCreated',
                        '0',
                        'App\\Service\\UserWriter',
                    ),
                    new EventRow(
                        2.0,
                        'App\\Event\\UserDeleted',
                        'App\\Event\\UserDeleted',
                        '0',
                        'App\\Service\\UserWriter',
                    ),
                    new EventRow(3.0, 'App\\Event\\CacheWarmup', 'App\\Event\\CacheWarmup', '1', ''),
                ],
            )
        )->jsonSerialize();

        yield 'grid follows the summary' => [
            $payload,
            [],
            <<<HTML
            <h1 class="yii-debug-sr-only">
            Events
            </h1><header class="yii-debug-grid-summary">
            <span><strong>3</strong> events</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>3</strong> classes</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>1</strong> static</span><label class="yii-debug-grid-pagesize"><span class="yii-debug-grid-pagesize-label">Rows</span><select class="yii-debug-grid-pagesize-select" name="per-page" data-yii-debug-pagesize="true">
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
            </header><div class="yii-debug-grid yii-debug-grid-event">
            HTML,
        ];
        yield 'no-match card follows the summary and the filter banner' => [
            $payload,
            ['Event' => ['name' => 'missing']],
            <<<HTML
            <h1 class="yii-debug-sr-only">
            Events
            </h1><header class="yii-debug-grid-summary">
            <span><strong>0</strong> events</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>0</strong> classes</span><label class="yii-debug-grid-pagesize"><span class="yii-debug-grid-pagesize-label">Rows</span><select class="yii-debug-grid-pagesize-select" name="per-page" data-yii-debug-pagesize="true">
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
            <span class="yii-debug-active-filters-label">1 filter active</span><span class="yii-debug-active-filters-list"><a class="yii-debug-active-filter-pill" href="/debug/view?tag=request-1&amp;panel=event" title="Remove this filter" aria-label="Remove name: missing filter"><span class="yii-debug-active-filter-attr">name</span><span class="yii-debug-active-filter-sep">:</span><span class="yii-debug-active-filter-value">missing</span><span class="yii-debug-active-filter-x" aria-hidden="true">×</span></a></span><a class="yii-debug-active-filters-clear" href="/debug/view?tag=request-1&amp;panel=event" title="Clear all filters and show every row" aria-label="Clear all active filters">Clear all</a>
            </div><div class="yii-debug-empty-state">
            <h2>
            No events match the active filters
            </h2><p>
            Adjust or clear the filters to show the dispatched events.
            </p>
            </div>
            HTML,
        ];
        yield 'empty capture state follows the heading' => [
            ['entries' => []],
            [],
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Events
            </h1><div class="yii-debug-empty-state">
            <h2>
            No events dispatched in this request
            </h2><p>
            The Events panel records PSR-14 objects sent through the configured debug dispatcher decorator, so this request completed without dispatching any.
            </p><p>
            Dispatch an application event to populate this view:
            </p><pre class="yii-debug-empty-state-code">
            $dispatcher-&gt;dispatch(new MyEvent());
            </pre>
            </div>
            HTML,
        ];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, int, string, string}>
     */
    public static function queryControls(): iterable
    {
        yield 'descending order' => [
            ['sort' => '-time', 'per-page' => '1'],
            3,
            '+500.000 ms',
            '+375.000 ms gap',
        ];
        yield 'filtered observation' => [
            ['Event' => ['name' => 'third'], 'per-page' => '1'],
            3,
            '+500.000 ms',
            '+375.000 ms gap',
        ];
        yield 'second page' => [
            ['sort' => 'time', 'page' => '2', 'per-page' => '1'],
            2,
            '+125.000 ms',
            '+125.000 ms gap',
        ];
    }
}
