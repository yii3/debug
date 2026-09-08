<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Provider;

/**
 * External states for exact Database grid markup regression fixtures.
 */
final class DbPanelMarkupProvider
{
    /**
     * @return array<string, array{string, array<array-key, mixed>|null, bool, bool}>
     */
    public static function states(): array
    {
        return [
            'context-free' => ['context-free', null, false, false],
            'empty' => ['empty', [], false, true],
            'filtered' => ['filtered', ['Db' => ['query' => 'alpha'], 'sort' => '-duration', 'per-page' => '10', 'yii_debug_theme' => 'dark', 'page' => '9'], false, false],
            'no-match' => ['no-match', ['Db' => ['query' => 'absent']], false, false],
            'n-plus-one' => ['n-plus-one', ['sort' => 'seq', 'per-page' => 'all'], false, false],
            'explain' => ['explain', [], true, false],
            'explain-context-free' => ['explain-context-free', null, true, false],
        ];
    }
}
