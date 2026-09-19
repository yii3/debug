<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use Closure;
use UIAwesome\Html\Palpable\A;

use function in_array;
use function str_starts_with;
use function substr;
use function usort;

/**
 * Represents the active sort attribute and direction parsed from the `sort` query value.
 */
final readonly class SortState
{
    /**
     * @param string $attribute Attribute the rows are ordered by.
     * @param 'asc'|'desc' $direction Order applied to the attribute.
     */
    private function __construct(public string $attribute, public string $direction) {}

    /**
     * Sorts rows with the natural ascending comparator, inverted for `desc`, then breaks ties in a fixed order.
     *
     * @template TRow
     *
     * @param list<TRow> $rows Rows to order.
     * @param Closure(TRow, TRow): int $compare Ascending comparator for the active attribute.
     * @param (Closure(TRow, TRow): int)|null $tieBreak Direction-independent comparator applied to rows the main
     * comparator considers equal, or `null` to keep their relative input order.
     *
     * @return list<TRow> Ordered rows.
     */
    public function apply(array $rows, Closure $compare, Closure|null $tieBreak = null): array
    {
        $descending = $this->direction === 'desc';

        usort(
            $rows,
            /**
             * @param TRow $left
             * @param TRow $right
             */
            static function (mixed $left, mixed $right) use ($compare, $descending, $tieBreak): int {
                $result = $compare($left, $right);

                if ($result !== 0) {
                    return $descending ? -$result : $result;
                }

                return $tieBreak === null ? 0 : $tieBreak($left, $right);
            },
        );

        return $rows;
    }

    /**
     * Parses the query value, keeping it only when the attribute is sortable; otherwise applies the default.
     *
     * @param string|null $sort Raw `sort` query value, optionally prefixed with `-` to request a descending order.
     * @param list<string> $attributes Sortable attribute names.
     * @param string $defaultAttribute Attribute used when the query value names no sortable attribute.
     * @param 'asc'|'desc' $defaultDirection Direction paired with the default attribute.
     *
     * @return self Sort state for the visible page.
     */
    public static function fromQuery(
        string|null $sort,
        array $attributes,
        string $defaultAttribute,
        string $defaultDirection = 'asc',
    ): self {
        $sort ??= '';

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        $attribute = $direction === 'desc' ? substr($sort, 1) : $sort;

        return in_array($attribute, $attributes, true)
            ? new self($attribute, $direction)
            : new self($defaultAttribute, $defaultDirection);
    }

    /**
     * Builds the header cell of a sortable column, linking to the order the next click on it requests.
     *
     * @param string $attribute Attribute backing the header cell.
     * @param string $label Visible column label.
     * @param Closure(string): string $url Builds the panel URL requesting a `sort` query value.
     * @param bool $descendingFirst Whether the first click on an inactive attribute requests a descending order.
     *
     * @return SortHeader Header cell announcing the active order.
     */
    public function header(string $attribute, string $label, Closure $url, bool $descendingFirst = false): SortHeader
    {
        $link = A::tag()
            ->href($url($this->next($attribute, $descendingFirst)))
            ->content($label);

        if ($this->isActive($attribute) === false) {
            return new SortHeader($link->render());
        }

        return new SortHeader(
            $link->class($this->direction)->render(),
            ['aria-sort' => $this->direction === 'asc' ? 'ascending' : 'descending'],
        );
    }

    /**
     * Determines whether the rows are currently ordered by the attribute.
     *
     * @param string $attribute Attribute backing a header cell.
     *
     * @return bool `true` when the rows are ordered by that attribute; `false` otherwise.
     */
    private function isActive(string $attribute): bool
    {
        return $this->attribute === $attribute;
    }

    /**
     * Returns the `sort` query value that a header link for the attribute must produce.
     *
     * @param string $attribute Attribute backing the header link.
     * @param bool $descendingFirst Whether the first click on an inactive attribute requests a descending order.
     *
     * @return string Value for the `sort` query parameter.
     */
    private function next(string $attribute, bool $descendingFirst): string
    {
        if ($this->isActive($attribute)) {
            return $this->direction === 'asc' ? "-{$attribute}" : $attribute;
        }

        return $descendingFirst ? "-{$attribute}" : $attribute;
    }
}
