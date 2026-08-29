<?php

declare(strict_types=1);

namespace Yii3\Debug;

use Yiisoft\Assets\AssetBundle;

/**
 * Publishes the shared Debug Core page assets through Yii.
 */
final class DebugAsset extends AssetBundle
{
    public function __construct()
    {
        $this->basePath = '@assets/yii3-debug';
        $this->baseUrl = '@assetsUrl/yii3-debug';
        $this->sourcePath = ToolbarAsset::SOURCE_PATH;
        $this->css = ['dist/css/debug.min.css'];
        $this->js = ['dist/js/debug.min.js'];
        $this->jsOptions = ['type' => 'module'];
    }
}
