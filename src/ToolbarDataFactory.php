<?php

declare(strict_types=1);

namespace Yii3\Debug;

use PHPForge\Debug\Toolbar\ToolbarData;
use Yiisoft\Assets\AssetManager;

use function rawurlencode;
use function rtrim;
use function strlen;
use function substr;

use const PHP_VERSION;

/**
 * Creates toolbar data containing only Yii and PHP metadata.
 */
final readonly class ToolbarDataFactory
{
    private string $routePrefix;

    public function __construct(
        private AssetManager $assetManager,
        string $routePrefix = '/debug',
        private string $position = 'bottom',
        private int $height = 50,
    ) {
        $this->routePrefix = rtrim($routePrefix, '/');
    }

    public function create(string $tag): ToolbarData
    {
        $logo = $this->assetManager->getUrl(ToolbarAsset::class, 'svg/yii.svg');
        $iconBaseUrl = $this->assetManager->getUrl(ToolbarAsset::class, 'svg/ajax.svg');

        $iconBaseUrl = substr($iconBaseUrl, 0, -strlen('ajax.svg'));

        return ToolbarData::create($tag, 'Yii Debugger')
            ->withNavigation(
                $this->routePrefix,
                $this->routePrefix . '/view?tag=' . rawurlencode($tag) . '&panel=config',
                $this->routePrefix . '/php-info',
            )
            ->withPresentation($this->position, $this->height, $iconBaseUrl)
            ->withBranding($logo, $logo, PHP_VERSION, '3');
    }

    public function withPresentation(string $position, int $height): self
    {
        return new self(
            $this->assetManager,
            $this->routePrefix,
            $position,
            $height,
        );
    }

    public function withRoutePrefix(string $routePrefix): self
    {
        return new self(
            $this->assetManager,
            $routePrefix,
            $this->position,
            $this->height,
        );
    }
}
