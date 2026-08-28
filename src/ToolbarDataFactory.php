<?php

declare(strict_types=1);

namespace Yii3\Debug;

use PHPForge\Debug\Toolbar\ToolbarData;
use Yiisoft\Assets\AssetManager;

use function strlen;
use function substr;

use const PHP_VERSION;

/**
 * Creates toolbar data containing only Yii and PHP metadata.
 */
final readonly class ToolbarDataFactory
{
    public function __construct(
        private AssetManager $assetManager,
        private string $position = 'bottom',
        private int $height = 50,
    ) {}

    public function create(string $tag): ToolbarData
    {
        $logo = $this->assetManager->getUrl(ToolbarAsset::class, 'svg/yii.svg');
        $iconBaseUrl = $this->assetManager->getUrl(ToolbarAsset::class, 'svg/ajax.svg');
        $iconBaseUrl = substr($iconBaseUrl, 0, -strlen('ajax.svg'));

        return new ToolbarData(
            tag: $tag,
            title: 'Yii Debugger',
            indexUrl: '',
            configUrl: '',
            items: [],
            position: $this->position,
            defaultHeight: $this->height,
            iconBaseUrl: $iconBaseUrl,
            logo: $logo,
            logoFallback: $logo,
            phpInfoUrl: null,
            phpVersion: PHP_VERSION,
            yiiVersion: '3',
        );
    }
}
