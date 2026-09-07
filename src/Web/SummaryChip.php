<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use UIAwesome\Html\Phrasing\{Span, Strong};

/**
 * Builds the metric chips and separators composing the summary header of a debug grid.
 */
final class SummaryChip
{
    /**
     * Renders a summary chip with a strong value followed by its label.
     *
     * @param string $value Metric highlighted at the start of the chip.
     * @param string $label Unit the metric is expressed in, including the space separating it from the value.
     */
    public static function render(string $value, string $label): Span
    {
        return Span::tag()->html(Strong::tag()->content($value), $label);
    }

    /**
     * Renders the separator between summary chips.
     */
    public static function separator(): Span
    {
        return Span::tag()
            ->class('yii-debug-grid-summary-sep')
            ->content('·');
    }
}
