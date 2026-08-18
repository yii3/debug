<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Data;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Data\PageSize;

/**
 * Unit tests for {@see PageSize} covering the `per-page` resolution semantics and the shared selector markup.
 *
 * @since 0.1
 */
#[Group('data')]
#[Group('pagination')]
final class PageSizeTest extends TestCase
{
    public function testCurrentFallsBackToTheDefaultWhenNoValueIsSupplied(): void
    {
        self::assertSame('50', PageSize::current(null), 'Missing values must fall back to the default.');
        self::assertSame('all', PageSize::current('all'), 'Raw values must pass through untouched.');
        self::assertSame('25', PageSize::current(null, 25), 'A custom default must surface as a string.');
    }

    public function testResolveCapsNumericValuesAtTheMaximum(): void
    {
        self::assertSame(1000, PageSize::resolve('5000'), 'Values above the cap must clamp to `1000`.');
        self::assertSame(25, PageSize::resolve('25'), 'Valid numeric values must pass through.');
    }

    public function testResolveFallsBackToTheDefaultForMissingOrInvalidValues(): void
    {
        self::assertSame(50, PageSize::resolve(null), 'Missing values must resolve to the default.');
        self::assertSame(50, PageSize::resolve('abc'), 'Non-numeric values must resolve to the default.');
        self::assertSame(50, PageSize::resolve('-5'), 'Non-positive values must resolve to the default.');
        self::assertSame(20, PageSize::resolve(null, 20), 'A custom default must be honored.');
    }

    public function testResolveReturnsNullForTheAllKeyword(): void
    {
        self::assertNull(PageSize::resolve('all'), "The literal 'all' must disable pagination.");
        self::assertNull(PageSize::resolve('ALL'), 'The keyword must match case-insensitively.');
    }

    public function testSelectorHtmlMarksTheCurrentOptionSelected(): void
    {
        $html = PageSize::selectorHtml('25');

        self::assertStringContainsString('data-yii-debug-pagesize', $html, 'Selector must carry the JS hook.');
        self::assertStringContainsString('name="per-page"', $html, 'Selector must submit as `per-page`.');
        self::assertStringContainsString(
            '<option value="25" selected>',
            $html,
            'The current value must render selected.',
        );
        self::assertMatchesRegularExpression(
            '/<option value="all">\s*All\s*<\/option>/',
            $html,
            "The 'all' option must render with its display label.",
        );
        self::assertStringContainsString('yii-debug-grid-pagesize-label', $html, 'Label span must carry its class.');
    }
}
