<?php

declare(strict_types=1);

namespace Yii3\Debug;

use Yiisoft\Assets\AssetBundle;

/**
 * Defines the asset bundle for the shared toolbar runtime.
 */
final class ToolbarAsset extends AssetBundle
{
    public const string SOURCE_PATH = '@vendor/php-forge/debug-core/resources/assets';

    public function __construct()
    {
        $this->basePath = '@assets/yii3-debug';
        $this->baseUrl = '@assetsUrl/yii3-debug';
        $this->sourcePath = self::SOURCE_PATH;
        $this->js = ['dist/js/toolbar.min.js'];
        $this->jsOptions = ['type' => 'module'];
    }
}
