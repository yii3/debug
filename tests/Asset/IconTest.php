<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Asset;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Asset\Icon;
use Yiisoft\Aliases\Aliases;

/**
 * Unit tests for {@see Icon} resolving shared SVG files through Yii3 aliases.
 *
 * @since 0.1
 */
#[Group('toolbar')]
final class IconTest extends TestCase
{
    public function testRenderRejectsInvalidOrMissingIconNames(): void
    {
        $icon = $this->icon();

        self::assertSame('', $icon->render('../request'), 'Traversal-like icon names must be rejected.');
        self::assertSame('', $icon->render("request\n"), 'Trailing newlines must invalidate an icon name.');
        self::assertSame('', $icon->render('missing-icon'), 'Missing icons must return an empty string.');
    }

    public function testRenderReturnsBundledSvgMarkup(): void
    {
        self::assertStringContainsString(
            '<svg',
            $this->icon()->render('request'),
            'Bundled icons must return their SVG markup.',
        );
    }

    /**
     * Creates an icon resolver backed by the test vendor directory.
     *
     * @return Icon Icon resolver under test.
     */
    private function icon(): Icon
    {
        return new Icon(new Aliases(['@vendor' => dirname(__DIR__, 2) . '/vendor']));
    }
}
