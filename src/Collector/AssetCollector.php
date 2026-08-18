<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Panel\Asset\{AssetBundleRow, AssetSnapshot};
use ReflectionProperty;
use Throwable;
use Yiisoft\Assets\{AssetBundle, AssetManager};

use function is_array;
use function is_string;

/**
 * Captures the asset bundles registered on the request for the Asset Bundles panel.
 *
 * Reads the registered-bundle map of the Yii3 asset manager, which exposes no public enumeration, through
 * reflection; each bundle's paths, files, and dependency list land in the shared Asset payload shape.
 *
 * Usage example:
 *
 * ```php
 * $snapshot = (new \Yii3\Debug\Collector\AssetCollector($assetManager))->capture();
 * ```
 */
final class AssetCollector implements CollectorInterface
{
    private bool $active = false;

    /**
     * @param AssetManager $assetManager Yii3 asset manager whose registered bundles are captured.
     */
    public function __construct(private readonly AssetManager $assetManager) {}

    /**
     * Snapshots every registered asset bundle in the shared Asset payload shape.
     *
     * Usage example:
     *
     * ```php
     * $snapshot = $collector->capture();
     * ```
     *
     * @return AssetSnapshot|null Captured bundle payload; `null` when the collector never started or the registered
     * bundles cannot be read.
     */
    public function capture(): AssetSnapshot|null
    {
        if (!$this->active) {
            return null;
        }

        try {
            $bundles = (new ReflectionProperty(AssetManager::class, 'registeredBundles'))
                ->getValue($this->assetManager);
        } catch (Throwable) {
            return null;
        }

        $rows = [];

        foreach (is_array($bundles) ? $bundles : [] as $name => $bundle) {
            if (!is_string($name) || !$bundle instanceof AssetBundle) {
                continue;
            }

            $rows[] = AssetBundleRow::fromBundle(
                $name,
                [
                    'basePath' => $bundle->basePath,
                    'baseUrl' => $bundle->baseUrl,
                    'css' => $bundle->css,
                    'depends' => $bundle->depends,
                    'js' => $bundle->js,
                    'sourcePath' => $bundle->sourcePath,
                ],
            );
        }

        return new AssetSnapshot($rows, null);
    }

    /**
     * Returns the stable ID pairing this collector with the Asset Bundles panel.
     *
     * Usage example:
     *
     * ```php
     * $id = $collector->id();
     * ```
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'asset';
    }

    /**
     * Deactivates the collector, so a reused worker process starts clean.
     */
    public function shutdown(): void
    {
        $this->active = false;
    }

    /**
     * Activates the collector for the current request cycle.
     */
    public function startup(): void
    {
        $this->active = true;
    }
}
