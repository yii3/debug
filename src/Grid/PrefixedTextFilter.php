<?php

declare(strict_types=1);

namespace Yii3\Debug\Grid;

use Yiisoft\Html\Html;
use Yiisoft\Yii\DataView\Filter\Widget\{Context, FilterWidget};

/**
 * Renders a grid filter text input named `Prefix[attribute]` so the shared JavaScript filter bridge applies it.
 *
 * Usage example:
 *
 * ```php
 * $column = new DataColumn(property: 'url', filter: new PrefixedTextFilter('Debug'));
 * ```
 */
final class PrefixedTextFilter extends FilterWidget
{
    /**
     * @param string $prefix Filter-group prefix (for example, `Debug`).
     * @param array<string, mixed> $inputAttributes Extra attributes merged into the rendered input.
     */
    public function __construct(
        private readonly string $prefix,
        private readonly array $inputAttributes = [],
    ) {}

    public function renderFilter(Context $context): string
    {
        return Html::textInput(
            "{$this->prefix}[{$context->property}]",
            $context->value,
            ['class' => 'yii-debug-input', 'form' => $context->formId, ...$this->inputAttributes],
        )->render();
    }
}
