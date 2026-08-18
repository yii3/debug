<?php

declare(strict_types=1);

namespace Yii3\Debug\Data;

use UIAwesome\Html\Form\{Option, Select};
use UIAwesome\Html\Phrasing\{Label, Span};

use function is_numeric;
use function min;
use function strcasecmp;

/**
 * Resolves the `per-page` grid page size and renders the shared page-size selector.
 *
 * Centralizes the page-size semantics both adapters honor: the default of `50` rows, the hard cap of `1000`, and the
 * literal `all` (case-insensitive) that disables pagination. The selector markup carries the `data-yii-debug-pagesize`
 * hook the shared JavaScript rewires into a `per-page` query-parameter reload.
 *
 * Usage example:
 * ```php
 * $size = \PHPForge\Debug\Data\PageSize::resolve($request->getQueryParams()['per-page'] ?? null);
 * ```
 */
final class PageSize
{
    /**
     * Default page size applied when no `per-page` parameter is supplied or the value is invalid.
     */
    public const int DEFAULT = 50;

    /**
     * Hard cap on the number of rows per page.
     */
    public const int MAX = 1000;

    /**
     * Selector options in display order; the literal `all` disables pagination.
     */
    public const array OPTIONS = ['10', '25', '50', '100', 'all'];

    /**
     * Returns the raw `per-page` value for the selector's selected state, falling back to the default.
     *
     * @param string|null $raw Raw `per-page` query-parameter value, or `null` when absent.
     * @param int $default Page size used when no value is supplied.
     */
    public static function current(string|null $raw, int $default = self::DEFAULT): string
    {
        return $raw ?? (string) $default;
    }

    /**
     * Resolves the raw `per-page` value into an effective page size.
     *
     * @param string|null $raw Raw `per-page` query-parameter value, or `null` when absent.
     * @param positive-int $default Page size used when no value is supplied or the value is invalid.
     *
     * @return positive-int|null Effective page size capped at {@see MAX}, or `null` when `all` disables pagination.
     */
    public static function resolve(string|null $raw, int $default = self::DEFAULT): int|null
    {
        if ($raw !== null && strcasecmp($raw, 'all') === 0) {
            return null;
        }

        $size = $raw !== null && is_numeric($raw) ? (int) $raw : $default;

        if ($size <= 0) {
            $size = $default;
        }

        return min($size, self::MAX);
    }

    /**
     * Renders the inline page-size selector shown in the grid summary header.
     *
     * Usage example:
     * ```php
     * $html = \PHPForge\Debug\Data\PageSize::selectorHtml('50');
     * ```
     *
     * @param string $current Currently selected raw value (one of {@see OPTIONS} for a highlighted option).
     */
    public static function selectorHtml(string $current): string
    {
        $select = Select::tag()
            ->addDataAttribute('yii-debug-pagesize', true)
            ->class('yii-debug-grid-pagesize-select')
            ->name('per-page');

        foreach (self::OPTIONS as $row) {
            $select = $select->option(
                Option::tag()
                    ->value($row)
                    ->content($row === 'all' ? 'All' : $row)
                    ->selected($row === $current),
            );
        }

        return Label::tag()
            ->class('yii-debug-grid-pagesize')
            ->html(
                Span::tag()
                    ->class('yii-debug-grid-pagesize-label')
                    ->content('Rows'),
                $select,
            )
            ->render();
    }
}
