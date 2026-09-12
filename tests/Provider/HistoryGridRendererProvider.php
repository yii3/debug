<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Provider;

/**
 * Sorting cases for {@see \Yii3\Debug\Tests\Web\HistoryGridRendererTest}.
 */
final class HistoryGridRendererProvider
{
    /**
     * Provides the `sort` query values that name no sortable attribute.
     *
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function malformedSortQueries(): iterable
    {
        yield 'absent sort' => [[]];
        yield 'non-scalar value' => [['sort' => ['invalid']]];
        yield 'unknown attribute' => [['sort' => 'unknown']];
    }

    /**
     * Provides the attributes whose captures carry a metric that one capture never reported.
     *
     * @return iterable<string, array{string}>
     */
    public static function numericSortAttributes(): iterable
    {
        yield 'peakMemory' => ['peakMemory'];
        yield 'processingTime' => ['processingTime'];
    }

    /**
     * Provides the attributes compared as text, boolean, or address rather than as a metric.
     *
     * @return iterable<string, array{string}>
     */
    public static function textualSortAttributes(): iterable
    {
        yield 'ajax' => ['ajax'];
        yield 'ip' => ['ip'];
        yield 'method' => ['method'];
        yield 'tag' => ['tag'];
        yield 'url' => ['url'];
    }
}
