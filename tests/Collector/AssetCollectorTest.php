<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPUnit\Framework\TestCase;
use Yii3\Debug\Collector\AssetCollector;
use Yii3\Debug\DebugAsset;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};

use function dirname;
use function sys_get_temp_dir;

/**
 * Unit tests for {@see AssetCollector} capturing registered Yii3 asset bundles into the shared Asset payload.
 */
final class AssetCollectorTest extends TestCase
{
    public function testCaptureListsRegisteredBundlesWithSharedRowShape(): void
    {
        $assetManager = $this->assetManager();
        $assetManager->register(DebugAsset::class);
        $collector = new AssetCollector($assetManager);

        $collector->startup();
        $snapshot = $collector->capture();
        $collector->shutdown();

        self::assertNotNull($snapshot, 'Active collector must expose a snapshot.');

        $bundles = $snapshot->bundles();

        self::assertCount(1, $bundles, 'One registered bundle must be captured.');
        self::assertSame(DebugAsset::class, $bundles[0]->name, 'Row must carry the bundle name.');
        self::assertSame(['dist/css/debug.min.css'], $bundles[0]->css, 'Row must list the bundle CSS files.');
        self::assertSame(['dist/js/debug.min.js'], $bundles[0]->js, 'Row must list the bundle JS files.');
        self::assertNull($collector->capture(), 'Collector must stop exposing data after shutdown.');
    }

    public function testCaptureReturnsEmptyBundleListWithoutRegistrations(): void
    {
        $collector = new AssetCollector($this->assetManager());

        $collector->startup();
        $snapshot = $collector->capture();
        $collector->shutdown();

        self::assertNotNull($snapshot, 'Active collector must expose a snapshot.');
        self::assertSame([], $snapshot->bundles(), 'No registrations must yield an empty bundle list.');
    }
    public function testCaptureReturnsNullWhenCollectorNeverStarted(): void
    {
        $collector = new AssetCollector($this->assetManager());

        self::assertNull($collector->capture(), 'Inactive collector must not expose a snapshot.');
    }

    /**
     * Creates an asset manager that publishes into the test runtime.
     *
     * @return AssetManager Configured asset manager.
     */
    private function assetManager(): AssetManager
    {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-asset-collector',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
            ],
        );

        return (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));
    }
}
