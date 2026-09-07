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
use function intdiv;
use function max;
use function min;
use function range;

/**
 * Renders the shared item count and numbered links without owning panel navigation.
 */
final class GridFooter
{
    /**
     * Number of consecutive page links rendered around the current page.
     */
    private const int WINDOW = 10;

    /**
     * Renders the item count summary followed by a bounded window of page links around the current page, adding first
     * and last page links, separated by an ellipsis, whenever the window leaves them out.
     *
     * @param (Closure(int): string)|null $pageUrl Omit for context-free rendering without navigation.
     */
    public static function render(
        int $total,
        int $offset,
        int $visible,
        int $page = 1,
        // Read only behind the `> 1` guard, so a default of `0` renders the same markup as `1`.
        // @infection-ignore-all
        int $pageCount = 1,
        Closure|null $pageUrl = null,
    ): Div {
        $begin = $total === 0 ? 0 : $offset + 1;

        $end = min($offset + $visible, $total);

        $items = [];

        if ($pageUrl !== null && $pageCount > 1) {
            $lastPage = min($pageCount, max(self::WINDOW, $page + intdiv(self::WINDOW, 2) - 1));

            $firstPage = $lastPage - min(self::WINDOW, $pageCount) + 1;

            if ($firstPage > 1) {
                $items[] = self::pageLink(1, $page, $pageUrl);

                if ($firstPage > 2) {
                    $items[] = self::ellipsis();
                }
            }

            foreach (range($firstPage, $lastPage) as $number) {
                $items[] = self::pageLink($number, $page, $pageUrl);
            }

            if ($lastPage < $pageCount) {
                if ($lastPage < $pageCount - 1) {
                    $items[] = self::ellipsis();
                }

                $items[] = self::pageLink($pageCount, $page, $pageUrl);
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

    /**
     * Returns the hidden, inert item marking the pages the window leaves out.
     */
    private static function ellipsis(): Li
    {
        return Li::tag()
            ->class('yii-debug-pager-item')
            ->class('is-disabled')
            ->addAriaAttribute('hidden', 'true')
            ->html(Span::tag()->class('yii-debug-pager-link')->content('…'));
    }

    /**
     * Returns the item linking to the given page, marked as current when it matches the active one.
     *
     * @param Closure(int): string $pageUrl Builds the target URL of the page.
     */
    private static function pageLink(int $number, int $page, Closure $pageUrl): Li
    {
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

        return $number === $page ? $item->class('is-active') : $item;
    }
}
