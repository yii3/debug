<?php

declare(strict_types=1);

namespace Yii3\Debug;

use Yiisoft\Assets\AssetBundle;

/**
 * Defines the Yii3 asset bundle backed by the shared Debug Core frontend.
 */
final class DebugAsset extends AssetBundle
{
    /**
     * Yii vendor alias containing the framework-neutral frontend files.
     */
    public const string SOURCE_PATH = '@vendor/php-forge/debug-core/resources/assets';

    /**
     * Points Yii3 asset publishing at the framework-neutral frontend files.
     */
    public function __construct()
    {
        $this->basePath = '@assets/yii3-debug';
        $this->baseUrl = '@assetsUrl/yii3-debug';
        $this->sourcePath = self::SOURCE_PATH;
        $this->css = ['dist/css/debug.min.css'];
        $this->js = ['dist/js/debug.min.js'];
        $this->jsOptions = ['type' => 'module'];
    }
}
