<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use Stringable;

/**
 * Represents the header cell of a sortable column, pairing the rendered sort link with the announced order.
 */
final readonly class SortHeader implements Stringable
{
    /**
     * @param string $link Rendered link requesting the order of the next click.
     * @param array<string, string> $attributes Header cell attributes announcing the active order, empty while the
     * rows are ordered by another attribute.
     */
    public function __construct(public string $link, public array $attributes = []) {}

    /**
     * Returns the rendered sort link of the header cell.
     *
     * @return string Rendered link.
     */
    public function __toString(): string
    {
        return $this->link;
    }
}
