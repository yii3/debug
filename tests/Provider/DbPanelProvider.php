<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Provider;

/**
 * External sorting cases for Database panel columns and toolbar chip tooltip states.
 */
final class DbPanelProvider
{
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
