<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\ToolbarAsset;

/**
 * Unit tests for the toolbar asset definition.
 */
#[Group('toolbar')]
final class ToolbarAssetTest extends TestCase
{
    public function testDefinitionUsesOnlyTheSharedToolbarRuntime(): void
    {
        $asset = new ToolbarAsset();

        self::assertSame(
            '@vendor/php-forge/debug-core/resources/assets',
            $asset->sourcePath,
            'Toolbar assets must use the shared Debug Core source path.',
        );
        self::assertSame(
            [],
            $asset->css,
            'The toolbar must not publish full debugger page styles.',
        );
        self::assertSame(
            ['dist/js/toolbar.min.js'],
            $asset->js,
            'Only the toolbar runtime must be published.',
        );
        self::assertSame(
            ['type' => 'module'],
            $asset->jsOptions,
            'Toolbar runtime must load as an ES module.',
        );
    }
}
