<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\{DebugAsset, ToolbarAsset};

/**
 * Unit tests for {@see DebugAsset} defining Yii3 publication of the shared frontend.
 *
 * @since 0.1.0
 */
#[Group('toolbar')]
final class DebugAssetTest extends TestCase
{
    public function testDefinitionsShipSharedFocusRuntime(): void
    {
        self::assertFileExists(
            dirname(__DIR__) . '/vendor/php-forge/debug-core/resources/assets/dist/js/focus.min.js',
            'Published shared assets must include the toolbar keyboard-focus runtime.',
        );
    }

    public function testDefinitionUsesCoreAssets(): void
    {
        $asset = new DebugAsset();

        self::assertSame('@assets/yii3-debug', $asset->basePath, 'Definition must use the Yii3 asset path alias.');
        self::assertSame('@assetsUrl/yii3-debug', $asset->baseUrl, 'Definition must use the Yii3 asset URL alias.');
        self::assertSame(DebugAsset::SOURCE_PATH, $asset->sourcePath, 'Source must use the shared vendor alias.');
        self::assertSame(['dist/css/debug.min.css'], $asset->css, 'Definition must register the shared stylesheet.');
        self::assertSame(['dist/js/debug.min.js'], $asset->js, 'Definition must register the shared runtime.');
        self::assertSame(['type' => 'module'], $asset->jsOptions, 'Runtime must load as an ES module.');
    }

    public function testToolbarDefinitionUsesCoreAssets(): void
    {
        $asset = new ToolbarAsset();

        self::assertSame('@assets/yii3-debug', $asset->basePath, 'Toolbar must use the Yii3 asset path alias.');
        self::assertSame('@assetsUrl/yii3-debug', $asset->baseUrl, 'Toolbar must use the Yii3 asset URL alias.');
        self::assertSame(DebugAsset::SOURCE_PATH, $asset->sourcePath, 'Toolbar source must use the shared vendor alias.');
        self::assertSame([], $asset->css, 'Toolbar must not register full-page styles.');
        self::assertSame(['dist/js/toolbar.min.js'], $asset->js, 'Toolbar must register its shared runtime.');
        self::assertSame(['type' => 'module'], $asset->jsOptions, 'Toolbar runtime must load as an ES module.');
    }
}
