<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use UIAwesome\Html\Form\{InputText, Option, Select};

/**
 * Builds the labelled filter controls shown in the filter row of a debug grid.
 */
final class FilterInput
{
    /**
     * Returns the filter dropdown offering the allowed values of the attribute, preceded by an empty option.
     *
     * @param string $prefix Filter group the attribute belongs to.
     * @param string $attribute Attribute the control filters on.
     * @param string $label Attribute name announced to assistive technology.
     * @param array<string, string> $filters Active filters of the group, keyed by attribute.
     * @param array<array-key, string> $options Selectable values mapped to the label shown for each of them.
     * @param string $class Class list applied to the control.
     */
    public static function select(
        string $prefix,
        string $attribute,
        string $label,
        array $filters,
        array $options,
        string $class = 'yii-debug-select',
    ): Select {
        $select = Select::tag()
            ->class($class)
            ->addAriaAttribute('label', "Filter by {$label}")
            ->name("{$prefix}[{$attribute}]")
            ->value($filters[$attribute] ?? '')
            ->option(Option::tag()->value('')->content(''));

        foreach ($options as $value => $optionLabel) {
            $select = $select->option(
                Option::tag()
                    ->value((string) $value)
                    ->content($optionLabel),
            );
        }

        return $select;
    }

    /**
     * Returns the free-text filter box carrying the value the attribute is currently filtered by.
     *
     * @param string $prefix Filter group the attribute belongs to.
     * @param string $attribute Attribute the control filters on.
     * @param string $label Attribute name announced to assistive technology.
     * @param array<string, string> $filters Active filters of the group, keyed by attribute.
     * @param string $class Class list applied to the control.
     */
    public static function text(
        string $prefix,
        string $attribute,
        string $label,
        array $filters,
        string $class = 'yii-debug-input',
    ): InputText {
        return InputText::tag()
            ->class($class)
            ->addAriaAttribute('label', "Filter by {$label}")
            ->name("{$prefix}[{$attribute}]")
            ->value($filters[$attribute] ?? '');
    }
}
