<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Grid;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Grid\{PrefixedDropdownFilter, PrefixedTextFilter};
use Yiisoft\Yii\DataView\Filter\Widget\Context;

/**
 * Unit tests for {@see PrefixedTextFilter} and {@see PrefixedDropdownFilter} covering the `Prefix[attribute]` input
 * naming contract consumed by the shared JavaScript filter bridge.
 *
 * @since 0.1
 */
#[Group('grid')]
final class PrefixedFiltersTest extends TestCase
{
    public function testDropdownRendersPrefixedNameEmptyOptionAndSelection(): void
    {
        $filter = new PrefixedDropdownFilter('Debug', ['get' => 'GET', 'post' => 'POST']);

        $html = $filter->withContext(new Context('method', 'post', 'filters-form'))->render();

        self::assertStringContainsString('name="Debug[method]"', $html, 'Select must carry the prefixed name.');
        self::assertStringContainsString('form="filters-form"', $html, 'Select must bind to the filter form.');
        self::assertStringContainsString('<option value></option>', $html, 'Empty option must clear the filter.');
        self::assertStringContainsString('<option value="post" selected>POST</option>', $html, 'Current value must render selected.');
        self::assertStringContainsString('yii-debug-select', $html, 'Select must carry the shared class.');
    }

    public function testTextInputMergesExtraAttributes(): void
    {
        $filter = new PrefixedTextFilter('Debug', ['class' => 'yii-debug-input yii-debug-col-id-input']);

        $html = $filter->withContext(new Context('tag', null, 'filters-form'))->render();

        self::assertStringContainsString(
            'class="yii-debug-input yii-debug-col-id-input"',
            $html,
            'Extra attributes must override the defaults.',
        );
    }

    public function testTextInputRendersPrefixedNameValueAndForm(): void
    {
        $filter = new PrefixedTextFilter('Debug');

        $html = $filter->withContext(new Context('url', 'admin', 'filters-form'))->render();

        self::assertStringContainsString('name="Debug[url]"', $html, 'Input must carry the prefixed name.');
        self::assertStringContainsString('value="admin"', $html, 'Current value must render.');
        self::assertStringContainsString('form="filters-form"', $html, 'Input must bind to the filter form.');
        self::assertStringContainsString('yii-debug-input', $html, 'Input must carry the shared class.');
    }
}
