<?php

declare(strict_types=1);

namespace Yii3\Debug\Grid;

use Yiisoft\Html\Html;
use Yiisoft\Yii\DataView\Filter\Widget\{Context, FilterWidget};

/**
 * Renders a grid filter dropdown named `Prefix[attribute]` so the shared JavaScript filter bridge applies it.
 *
 * A leading empty option clears the filter; the option matching the current value renders selected.
 *
 * Usage example:
 *
 * ```php
 * $column = new DataColumn(property: 'method', filter: new PrefixedDropdownFilter('Debug', ['get' => 'GET']));
 * ```
 */
final class PrefixedDropdownFilter extends FilterWidget
{
    /**
     * @param string $prefix Filter-group prefix (for example, `Debug`).
     * @param array<array-key, string> $options Value-to-label map rendered as the dropdown options.
     * @param array<string, mixed> $selectAttributes Extra attributes merged into the rendered select.
     */
    public function __construct(
        private readonly string $prefix,
        private readonly array $options,
        private readonly array $selectAttributes = [],
    ) {}

    public function renderFilter(Context $context): string
    {
        $options = ['' => ''];

        foreach ($this->options as $value => $label) {
            $options[(string) $value] = $label;
        }

        return Html::select()
            ->name("{$this->prefix}[{$context->property}]")
            ->optionsData($options)
            ->value($context->value ?? '')
            ->addAttributes(['class' => 'yii-debug-select', 'form' => $context->formId, ...$this->selectAttributes])
            ->render();
    }
}
