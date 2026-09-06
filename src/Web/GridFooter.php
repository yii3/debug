<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use Closure;
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\List\{Li, Ul};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;

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
                    ->href($pageUrl($number))
                    ->content((string) $number);
                $item = Li::tag()->class('yii-debug-pager-item')->html($link);

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
                    : Ul::tag()
                        ->class('yii-debug-pager')
                        ->html(...$items),
            );
    }
}
