<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use Closure;
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\View\Grid\GridCount;
use UIAwesome\Html\Flow\Div;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Yii\DataView\Pagination\{OffsetPagination, PaginationContext};

use function array_replace;

/**
 * Renders the shared item count and the DataView pager without owning panel navigation.
 */
final class GridFooter
{
    /**
     * Renders the item count summary followed by the page controls of the collection.
     *
     * The pager renders nothing while the rows fit on a single page, and a control leading nowhere renders as an
     * inert `span` instead of a link.
     *
     * @template TKey of array-key
     * @template TValue of array|object
     *
     * @param OffsetPaginator<TKey, TValue> $paginator Paginator backing the grid.
     * @param (Closure(string): string)|null $pageUrl Builds the URL of a page from its number, or `null` to omit the
     * page controls.
     *
     * @return Div Rendered footer.
     */
    public static function render(OffsetPaginator $paginator, Closure|null $pageUrl = null): Div
    {
        $total = $paginator->getTotalItems();
        $offset = $paginator->getOffset();

        return Div::tag()
            ->class('yii-debug-grid-footer')
            ->html(
                GridCount::render(
                    $total === 0 ? 0 : $offset + 1,
                    $offset + $paginator->getCurrentPageSize(),
                    $total,
                ),
                $pageUrl === null ? '' : self::pager($paginator, $pageUrl),
            );
    }

    /**
     * Renders the footer of a panel grid, deriving the counters and page links from the paginator.
     *
     * @template TKey of array-key
     * @template TValue of array|object
     *
     * @param OffsetPaginator<TKey, TValue> $paginator Paginator backing the grid.
     * @param PanelRenderContext|null $context State of the debugger request, or `null` to omit the page links.
     * @param array<array-key, mixed> $queryParams Query parameters the page links are built from.
     *
     * @return Div Rendered footer.
     */
    public static function renderForPanel(
        OffsetPaginator $paginator,
        PanelRenderContext|null $context,
        array $queryParams,
    ): Div {
        return self::render(
            $paginator,
            $context === null ? null : static fn(string $page): string => $context->panelUrl(
                queryParams: array_replace($queryParams, ['page' => $page]),
            ),
        );
    }

    /**
     * Renders the page controls of the collection with the shared pager markup.
     *
     * @template TKey of array-key
     * @template TValue of array|object
     *
     * @param OffsetPaginator<TKey, TValue> $paginator Paginator backing the grid.
     * @param Closure(string): string $pageUrl Builds the URL of a page from its number.
     *
     * @return string Rendered pager, empty while the rows fit on a single page.
     */
    private static function pager(OffsetPaginator $paginator, Closure $pageUrl): string
    {
        return OffsetPagination::create(
            $paginator,
            $pageUrl(PaginationContext::URL_PLACEHOLDER),
            $pageUrl('1'),
            accessibility: true,
        )
            ->currentItemClass('is-active')
            ->disabledItemClass('is-disabled')
            ->itemAttributes(['class' => 'yii-debug-pager-item'])
            ->itemTag('li')
            ->linkClass('yii-debug-pager-link')
            ->listAttributes(['class' => 'yii-debug-pager'])
            ->listTag('ul')
            ->render();
    }
}
