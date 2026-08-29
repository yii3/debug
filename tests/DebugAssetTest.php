<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use PHPUnit\Framework\TestCase;
use Yii3\Debug\{DebugAsset, ToolbarAsset};

/**
 * Unit tests for Debug Core page assets.
 */
final class DebugAssetTest extends TestCase
{
    public function testDefinitionPublishesOnlyTheSharedPageRuntime(): void
    {
        $asset = new DebugAsset();

        self::assertSame(
            ToolbarAsset::SOURCE_PATH,
            $asset->sourcePath,
            'Page assets must use the shared source path.',
        );
        self::assertSame(
            ['dist/css/debug.min.css'],
            $asset->css,
            'Page assets must publish the shared stylesheet.',
        );
        self::assertSame(
            ['dist/js/debug.min.js'],
            $asset->js,
            'Page assets must publish the shared runtime.',
        );
        self::assertSame(
            ['type' => 'module'],
            $asset->jsOptions,
            'Page runtime must load as an ES module.',
        );
    }
}
