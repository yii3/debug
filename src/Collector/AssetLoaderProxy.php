<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use Yiisoft\Assets\{AssetBundle, AssetLoaderInterface};

/**
 * Intercepts `AssetLoaderInterface::loadBundle()` to feed the loaded bundle to the `AssetCollector`.
 */
final readonly class AssetLoaderProxy implements AssetLoaderInterface
{
    /**
     * @param AssetLoaderInterface $loader Application loader every call is delegated to.
     * @param AssetCollector $collector Collector recording the bundles the application loads.
     */
    public function __construct(private AssetLoaderInterface $loader, private AssetCollector $collector) {}

    /**
     * Returns the asset URL the application loader resolves.
     *
     * @param AssetBundle $bundle Bundle the asset file belongs to.
     * @param string $assetPath Asset path declared by the bundle.
     *
     * @return string Actual URL of the asset.
     */
    public function getAssetUrl(AssetBundle $bundle, string $assetPath): string
    {
        return $this->loader->getAssetUrl($bundle, $assetPath);
    }

    /**
     * Records the bundle the application loader resolved, then returns it unchanged.
     *
     * @param string $name Bundle class name to load.
     * @param array $config Bundle instance configuration.
     *
     * @phpstan-param array<string, mixed> $config
     *
     * @return AssetBundle Bundle as returned by the application loader.
     */
    public function loadBundle(string $name, array $config = []): AssetBundle
    {
        $bundle = $this->loader->loadBundle($name, $config);

        $this->collector->collect($bundle, $name);

        return $bundle;
    }
}
