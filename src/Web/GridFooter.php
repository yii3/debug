<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use Closure;
use PHPForge\Debug\Panel\PanelRenderContext;
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\List\{Li, Ul};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Sectioning\Nav;
use Yiisoft\Data\Paginator\OffsetPaginator;

use function array_replace;
use function min;

/**
 * Renders the shared item count and numbered links without owning panel navigation.
 */
final class GridFooter
{
    /**
     * @param (Closure(int): string)|null $pageUrl Omit for context-free rendering without navigation.
     */
    public static function render(
        int $total,
        int $offset,
        int $visible,
        int $page = 1,
        int $pageCount = 1,
        Closure|null $pageUrl = null,
    ): Div {
        $begin = $total === 0 ? 0 : $offset + 1;

        $end = min($offset + $visible, $total);

        $items = [];

        if ($pageUrl !== null && $pageCount > 1) {
            for ($number = 1; $number <= $pageCount; $number++) {
                $link = A::tag()
                    ->class('yii-debug-pager-link')
                    ->addAriaAttribute('label', 'Page ' . $number)
                    ->href($pageUrl($number))
                    ->content((string) $number);

                if ($number === $page) {
                    $link = $link->addAriaAttribute('current', 'page');
                }

                $item = Li::tag()
                    ->class('yii-debug-pager-item')
                    ->html($link);

                $items[] = $number === $page ? $item->class('is-active') : $item;
            }
        }

        return Div::tag()
            ->class('yii-debug-grid-footer')
            ->html(
                Span::tag()
                    ->class('summary yii-debug-grid-count')
                    ->content("Showing {$begin}-{$end} of {$total} items."),
                $items === []
                    ? ''
                    : Nav::tag()
                        ->addAriaAttribute('label', 'Pagination')
                        ->html(Ul::tag()->class('yii-debug-pager')->html(...$items)),
            );
    }

    /**
     * Renders the footer of a panel grid, deriving the counters and page links from the paginator.
     *
     * @template TKey of array-key
     * @template TValue of array|object
     *
     * @param OffsetPaginator<TKey, TValue> $paginator Paginator backing the grid.
     * @param int $visible Number of rows rendered on the current page.
     * @param PanelRenderContext|null $context Omit for context-free rendering without navigation.
     * @param array<array-key, mixed> $queryParams Query parameters the page links are built from.
     */
    public static function renderForPanel(
        OffsetPaginator $paginator,
        int $visible,
        PanelRenderContext|null $context,
        array $queryParams,
    ): Div {
        return self::render(
            $paginator->getTotalItems(),
            $paginator->getOffset(),
            $visible,
            $paginator->getCurrentPage(),
            $paginator->getTotalPages(),
            $context === null ? null : static fn(int $number): string => $context->panelUrl(
                queryParams: array_replace($queryParams, ['page' => $number]),
            ),
        );
    }
}
