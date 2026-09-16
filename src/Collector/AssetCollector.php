<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\CollectorInterface;
use PHPForge\Debug\Panel\Asset\{AssetBundleRow, AssetSnapshot};
use Yii3\Debug\ToolbarAsset;
use Yiisoft\Assets\AssetBundle;

use function array_values;

/**
 * Captures `yiisoft/assets` bundles loaded during the request for the Asset Bundles panel.
 */
final class AssetCollector implements CollectorInterface
{
    /**
     * Stores the captured bundles in load order, keyed by bundle class name.
     *
     * @var array<string, AssetBundleRow>
     */
    private array $rows = [];
    /**
     * Indicates whether collection is active for the current request lifecycle.
     */
    private bool $started = false;

    /**
     * Encodes the captured bundles into the Asset Bundles panel payload.
     *
     * @return array<string, mixed>|null Encoded Asset panel payload; `null` when the collector never started.
     */
    public function capture(): array|null
    {
        if (!$this->started) {
            return null;
        }

        return (new AssetSnapshot(array_values($this->rows), null))->jsonSerialize();
    }

    /**
     * Narrows one loaded bundle into a typed row, keyed by class name so a repeated load never duplicates it.
     *
     * The debugger's own toolbar bundle is excluded because it is infrastructure rather than application content.
     *
     * @param AssetBundle $bundle Bundle the loader resolved.
     * @param string $name Bundle class name the loader was asked for.
     */
    public function collect(AssetBundle $bundle, string $name): void
    {
        if ($this->started && $name !== ToolbarAsset::class) {
            $this->rows[$name] = AssetBundleRow::fromBundle(
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
    }

    /**
     * Returns the stable identifier of this collector.
     *
     * @return string Stable ID pairing this collector with its panel.
     */
    public function id(): string
    {
        return 'asset';
    }

    /**
     * Stops capturing and clears the bundles accumulated for the request.
     */
    public function shutdown(): void
    {
        $this->started = false;
        $this->rows = [];
    }

    /**
     * Starts capturing, discarding anything left from a previous request.
     */
    public function startup(): void
    {
        if ($this->started) {
            return;
        }

        $this->rows = [];
        $this->started = true;
    }
}
