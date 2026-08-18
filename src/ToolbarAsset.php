<?php

declare(strict_types=1);

namespace Yii3\Debug;

use Yiisoft\Assets\AssetBundle;

/**
 * Defines the Yii3 asset bundle for the shared toolbar runtime.
 */
final class ToolbarAsset extends AssetBundle
{
    /**
     * Points Yii3 asset publishing at the framework-neutral toolbar files.
     */
    public function __construct()
    {
        $this->basePath = '@assets/yii3-debug';
        $this->baseUrl = '@assetsUrl/yii3-debug';
        $this->sourcePath = DebugAsset::SOURCE_PATH;
        $this->js = ['dist/js/toolbar.min.js'];
        $this->jsOptions = ['type' => 'module'];
    }
}
