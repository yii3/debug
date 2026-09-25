<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use PHPForge\Debug\Helper\Trace;
use PHPForge\Debug\Panel\Asset\{AssetBundleRow, AssetSnapshot};
use PHPForge\Debug\Panel\Event\{EventRow, EventSnapshot};
use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPForge\Debug\Panel\Mail\MailSnapshot;
use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;
use PHPForge\Debug\Panel\Request\RequestSnapshot;
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary, SnapshotStore};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\{ConfigDataFactory, ExtensionRegistry, ToolbarDataFactory};
use Yii3\Debug\Db\DbExplain;
use Yii3\Debug\Panel\{
    AssetPanel,
    BuiltInPanelList,
    BuiltInPanels,
    DbPanel,
    EventPanel,
    LogPanel,
    MailPanel,
    ProfilingPanel,
    RequestPanel,
};
use Yii3\Debug\Tests\Support\DatabaseFixture;
use Yii3\Debug\Tests\Support\Stubs\Cache\AlphaPanel;
use Yii3\Debug\Web\{DebugPageRenderer, DebugUrlGenerator};
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};
use Yiisoft\View\WebView;

use function array_map;
use function array_slice;
use function count;
use function dirname;
use function preg_match_all;
use function sys_get_temp_dir;

/**
 * Integration tests for {@see BuiltInPanels::IDS} as the one built-in order the sidebar and the toolbar both follow.
 */
#[Group('toolbar')]
final class BuiltInPanelOrderTest extends TestCase
{
    public function testSidebarAndToolbarFollowTheIdClassificationOrder(): void
    {
        $builtIns = BuiltInPanelList::fromMap(
            [
                'asset' => new AssetPanel(),
                'db' => new DbPanel(new DbExplain(), new DebugUrlGenerator(), Trace::create()),
                'event' => new EventPanel(),
                'log' => new LogPanel(Trace::create()),
                'mail' => new MailPanel(new SnapshotStore(self::aliases()->get('@assets'), 0o700, 0o600)),
                'profiling' => new ProfilingPanel(),
                'request' => new RequestPanel(),
            ],
        );
        $panels = ExtensionRegistry::create(panels: [new AlphaPanel()])->panelsWithBuiltIns($builtIns->panels());
        $summary = RequestSummary::create('request-1')
            ->withRequest('https://example.test/', 'GET', '127.0.0.1', 1_725_000_756.0)
            ->withResponse(200)
            ->withProfiling(0.009, 1_145_324);

        $snapshot = self::snapshot($summary);

        $toolbar = array_map(
            static fn(array $panel): string => $panel['id'],
            (new ToolbarDataFactory(self::assetManager()))
                ->withExtensionPanels($panels)
                ->createForSnapshot($snapshot)
                ->jsonSerialize()['items'],
        );

        $html = self::renderer()
            ->withExtensionPanels($panels)
            ->config('request-1', 'light', ['request-1' => $summary], $snapshot);

        preg_match_all(
            '/class="yii-debug-nav-link" href="\/debug\/view\?tag=request-1&amp;panel=([^"]+)"/',
            $html,
            $matches,
        );

        $sidebar = $matches[1];

        self::assertSame(
            BuiltInPanels::IDS,
            array_slice($toolbar, 0, count(BuiltInPanels::IDS)),
            'Toolbar built-ins must follow the ID classification.',
        );
        self::assertSame(
            BuiltInPanels::IDS,
            array_slice($sidebar, 0, count(BuiltInPanels::IDS)),
            'Sidebar built-ins must follow the ID classification.',
        );
        self::assertSame(
            $sidebar,
            $toolbar,
            'Sidebar and toolbar must list the same panels in the same order.',
        );
    }

    private static function aliases(): Aliases
    {
        return new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-built-in-order-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__) . '/vendor',
            ],
        );
    }

    private static function assetManager(): AssetManager
    {
        $aliases = self::aliases();

        return (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(
                (new AssetPublisher($aliases))->withHashCallback(static fn(string $path): string => 'test'),
            );
    }

    private static function renderer(): DebugPageRenderer
    {
        return new DebugPageRenderer(
            new WebView(),
            self::assetManager(),
            new ConfigDataFactory(['name' => 'Test application']),
            self::aliases()->get('@vendor/php-forge/debug-core/resources/views'),
        );
    }

    /**
     * @param RequestSummary $summary Summary of the capture.
     *
     * @return DebugSnapshot Capture in which every built-in panel and one extension panel have content.
     */
    private static function snapshot(RequestSummary $summary): DebugSnapshot
    {
        return new DebugSnapshot(
            $summary,
            [
                'alpha' => ['value' => true],
                'asset' => (
                    new AssetSnapshot(
                        [
                            new AssetBundleRow(
                                'App\\Asset\\AppAsset',
                                '@app/assets',
                                '/public/assets',
                                '/assets',
                                [],
                                [],
                                [],
                            ),
                        ],
                        null,
                    )
                )->jsonSerialize(),
                'db' => DatabaseFixture::snapshot()->jsonSerialize(),
                'event' => (
                    new EventSnapshot(
                        [
                            new EventRow(
                                2.0,
                                'App\\Event\\Rendered',
                                'App\\Event\\Rendered',
                                '0',
                                'App\\Action',
                            ),
                        ]
                    )
                )->jsonSerialize(),
                'log' => LogSnapshot::capture([['request started', 4, 'application', 1.0, [], 1024]])->jsonSerialize(),
                'mail' => MailSnapshot::capture(
                    [['from' => 'a@example.com', 'to' => 'b@example.com']],
                )->jsonSerialize(),
                'profiling' => (new ProfilingSnapshot(2_097_152, 0.25, [], []))->jsonSerialize(),
                'request' => RequestSnapshot::capture(['statusCode' => 200])->jsonSerialize(),
            ],
            [],
        );
    }
}
