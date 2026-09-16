<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPUnit\Framework\TestCase;
use Yii3\Debug\Collector\AssetCollector;
use Yii3\Debug\Tests\Support\Captured;
use Yii3\Debug\ToolbarAsset;
use Yiisoft\Assets\AssetBundle;

/**
 * Unit tests for {@see AssetCollector} lifecycle, bundle collection, and request isolation.
 */
final class AssetCollectorTest extends TestCase
{
    public function testCaptureReturnsNullBeforeStartup(): void
    {
        $collector = new AssetCollector();

        self::assertNull(
            Captured::asset($collector),
            'Inactive collector must not produce a payload.',
        );
    }

    public function testCollectBeforeStartupIsIgnored(): void
    {
        $collector = new AssetCollector();
        $bundle = new AssetBundle();

        $bundle->basePath = '/assets';

        $collector->collect($bundle, 'app\assets\AppAsset');

        $collector->startup();

        self::assertSame(
            [],
            Captured::asset($collector)?->bundles(),
            'Bundles collected before startup must not appear in the snapshot.',
        );
    }

    public function testCollectIgnoresToolbarAsset(): void
    {
        $collector = new AssetCollector();

        $collector->startup();

        $collector->collect(new ToolbarAsset(), ToolbarAsset::class);

        self::assertSame(
            [],
            Captured::asset($collector)?->bundles(),
            'Debugger toolbar bundle must not be listed.',
        );

        $bundle = new AssetBundle();

        $bundle->basePath = '/assets';

        $collector->collect($bundle, 'app\assets\AppAsset');

        $bundles = Captured::asset($collector)?->bundles() ?? [];

        self::assertCount(
            1,
            $bundles,
            'Application bundle must still be captured.',
        );
        self::assertSame(
            'app\assets\AppAsset',
            $bundles[0]->name,
            'Remaining row must be the application bundle.',
        );
    }

    public function testDoubleStartupPreservesCollectedRows(): void
    {
        $collector = new AssetCollector();
        $bundle = new AssetBundle();

        $bundle->basePath = '/assets';

        $collector->startup();

        $collector->collect($bundle, 'app\assets\AppAsset');

        $collector->startup();

        self::assertCount(
            1,
            Captured::asset($collector)?->bundles() ?? [],
            'A second startup must not reset rows collected in the same request.',
        );
    }

    public function testDuplicateNameOverwritesExistingRow(): void
    {
        $collector = new AssetCollector();
        $first = new AssetBundle();

        $first->basePath = '/first';

        $second = new AssetBundle();

        $second->basePath = '/second';

        $collector->startup();

        $collector->collect($first, 'app\assets\AppAsset');
        $collector->collect($second, 'app\assets\AppAsset');

        $bundles = Captured::asset($collector)?->bundles() ?? [];

        self::assertCount(
            1,
            $bundles,
            'Collecting the same class name twice must deduplicate.',
        );
        self::assertSame(
            '/second',
            $bundles[0]->basePath,
            'Last-collected bundle must win.',
        );
    }

    public function testIdReturnsAsset(): void
    {
        self::assertSame(
            'asset',
            (new AssetCollector())->id(),
            'Asset panel ID must be stable.',
        );
    }

    public function testLifecycleCollectCaptureShutdown(): void
    {
        $collector = new AssetCollector();
        $bundle = new AssetBundle();

        $bundle->basePath = '/assets';
        $bundle->baseUrl = '/static';
        $bundle->css = ['css/app.css'];
        $bundle->js = ['js/app.js'];
        $bundle->depends = ['app\assets\SiteAsset'];
        $bundle->sourcePath = '/src/assets';

        $collector->startup();

        $collector->collect($bundle, 'app\assets\AppAsset');

        $snapshot = Captured::asset($collector);

        self::assertNotNull(
            $snapshot,
            'Active collector must expose a snapshot.',
        );
        self::assertCount(
            1,
            $snapshot->bundles(),
            'One collected bundle must produce one row.',
        );

        $row = $snapshot->bundles()[0];

        self::assertSame(
            'app\assets\AppAsset',
            $row->name,
            'Bundle name must be preserved.',
        );
        self::assertSame(
            '/assets',
            $row->basePath,
            'basePath must be captured.',
        );
        self::assertSame(
            '/static',
            $row->baseUrl,
            'baseUrl must be captured.',
        );
        self::assertSame(
            ['css/app.css'],
            $row->css,
            'CSS files must be captured.',
        );
        self::assertSame(
            ['js/app.js'],
            $row->js,
            'JS files must be captured.',
        );
        self::assertSame(
            ['app\assets\SiteAsset'],
            $row->depends,
            'Dependencies must be captured.',
        );
        self::assertSame(
            '/src/assets',
            $row->sourcePath,
            'sourcePath must be captured.',
        );

        $collector->shutdown();

        self::assertNull(
            Captured::asset($collector),
            'Shutdown must stop capture.',
        );
    }

    public function testShutdownThenStartupClearsRowsFromPreviousRequest(): void
    {
        $collector = new AssetCollector();
        $bundle = new AssetBundle();

        $bundle->basePath = '/assets';

        $collector->startup();

        $collector->collect($bundle, 'app\assets\AppAsset');

        $collector->shutdown();
        $collector->startup();

        self::assertSame(
            [],
            Captured::asset($collector)?->bundles(),
            'New request must not inherit bundles from previous request.',
        );
    }
}
