<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPUnit\Framework\TestCase;
use Yii3\Debug\Collector\{AssetCollector, AssetLoaderProxy};
use Yii3\Debug\Tests\Support\Captured;
use Yiisoft\Assets\{AssetBundle, AssetLoaderInterface};

/**
 * Unit tests for {@see AssetLoaderProxy} delegating to the wrapped loader and feeding the collector.
 */
final class AssetLoaderProxyTest extends TestCase
{
    public function testGetAssetUrlDelegatesToLoader(): void
    {
        $bundle = new AssetBundle();
        $collector = new AssetCollector();
        $proxy = new AssetLoaderProxy($this->loaderWithUrl('/static/app.css'), $collector);

        self::assertSame(
            '/static/app.css',
            $proxy->getAssetUrl($bundle, 'css/app.css'),
            'URL must come from the inner loader.',
        );
    }

    public function testLoadBundleDelegatesToLoaderAndFeedsCollector(): void
    {
        $bundle = new AssetBundle();

        $bundle->basePath = '/assets';
        $bundle->css = ['css/app.css'];

        $collector = new AssetCollector();

        $collector->startup();

        $proxy = new AssetLoaderProxy($this->loaderReturning($bundle), $collector);

        $returned = $proxy->loadBundle('app\assets\AppAsset');

        self::assertSame(
            $bundle,
            $returned,
            'Proxy must return the bundle the inner loader produced.',
        );

        $bundles = Captured::asset($collector)?->bundles() ?? [];

        self::assertCount(
            1,
            $bundles,
            'Collector must receive the loaded bundle.',
        );
        self::assertSame(
            'app\assets\AppAsset',
            $bundles[0]->name,
            'Collector must record the bundle class name.',
        );
    }

    /**
     * Builds a stub loader that always returns the given bundle from `loadBundle`.
     */
    private function loaderReturning(AssetBundle $bundle): AssetLoaderInterface
    {
        return new class ($bundle) implements AssetLoaderInterface {
            public function __construct(private readonly AssetBundle $bundle) {}

            public function getAssetUrl(AssetBundle $bundle, string $assetPath): string
            {
                return '';
            }

            public function loadBundle(string $name, array $config = []): AssetBundle
            {
                return $this->bundle;
            }
        };
    }

    /**
     * Builds a stub loader whose `getAssetUrl` always returns the given URL.
     */
    private function loaderWithUrl(string $url): AssetLoaderInterface
    {
        return new class ($url) implements AssetLoaderInterface {
            public function __construct(private readonly string $url) {}

            public function getAssetUrl(AssetBundle $bundle, string $assetPath): string
            {
                return $this->url;
            }

            public function loadBundle(string $name, array $config = []): AssetBundle
            {
                return new AssetBundle();
            }
        };
    }
}
