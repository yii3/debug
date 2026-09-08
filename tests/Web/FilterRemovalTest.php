<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Web;

use PHPForge\Debug\Data\FilterPrefix;
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Search\ProfileSearch;
use Yii3\Debug\Web\{DebugUrlGenerator, FilterRemoval};

use function preg_match;

/**
 * Unit tests for the active-filter banner links built by {@see FilterRemoval}.
 */
final class FilterRemovalTest extends TestCase
{
    public function testClearAllRemovesSubmittedAndActiveFilterKeys(): void
    {
        $queryParams = [
            FilterPrefix::PROFILE => ['duration' => 'invalid', 'category' => 'app.db'],
            'sort' => '-duration',
        ];
        $search = ProfileSearch::fromQueryParams($queryParams);

        self::assertSame(
            ['category' => 'app.db'],
            $search->activeFilters,
            'A rejected bound must stay out of the active set.',
        );

        $html = FilterRemoval::banner(
            $search->activeFilters,
            new PanelRenderContext('capture', 'profiling', $queryParams, 'light', new DebugUrlGenerator('/inspect')),
            $queryParams,
            FilterPrefix::PROFILE,
        );

        self::assertStringContainsString(
            'Profile%5Bduration%5D=invalid',
            self::href($html, 'yii-debug-active-filter-pill'),
            'A single removal must keep the unrelated slot.',
        );

        $clearAll = self::href($html, 'yii-debug-active-filters-clear');

        self::assertStringNotContainsString('Profile%5Bduration%5D', $clearAll, 'The rejected slot must be dropped.');
        self::assertStringNotContainsString('Profile%5Bcategory%5D', $clearAll, 'The active slot must be dropped.');
        self::assertStringContainsString('sort=-duration', $clearAll, 'Unrelated navigation must be preserved.');
    }

    private static function href(string $html, string $class): string
    {
        preg_match('~class="' . $class . '" href="([^"]*)"~', $html, $matches);

        return $matches[1] ?? '';
    }
}
