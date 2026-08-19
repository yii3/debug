<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Action;

use GuzzleHttp\Psr7\{HttpFactory, ServerRequest};
use PHPForge\Debug\Panel\Queue\{JobRecord, QueueSnapshot};
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary, SnapshotStore};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Action\QueueJobAction;
use Yii3\Debug\Asset\Icon;
use Yii3\Debug\Panel\QueuePanel;
use Yii3\Debug\Tests\Support\GridFactory;
use Yii3\Debug\Web\{DebugPageRenderer, LocalAccessChecker, ResponseBuilder};
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};
use Yiisoft\View\WebView;

use function dirname;
use function is_dir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Unit tests for {@see QueueJobAction} loading one record and rendering its shared detail card.
 */
#[Group('queue')]
final class QueueJobActionTest extends TestCase
{
    private string $path = '';

    public function testInvokeRejectsInvalidSequence(): void
    {
        $request = (new ServerRequest(
            'GET',
            'https://example.test/debug/queue-job',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        ))->withQueryParams(['tag' => 'request-1', 'seq' => '-1']);

        self::assertSame(
            400,
            $this->action(new SnapshotStore($this->path, 0o777, null))($request)->getStatusCode(),
            'Invalid sequence must be rejected.',
        );
    }

    public function testInvokeRendersStoredQueueRecord(): void
    {
        $store = new SnapshotStore($this->path, 0o777, null);
        $store->writeSnapshot($this->snapshot(), 10);
        $request = (new ServerRequest(
            'GET',
            'https://example.test/debug/queue-job',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        ))->withQueryParams(['tag' => 'request-1', 'seq' => '0']);

        $response = $this->action($store)($request);
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode(), 'Stored queue record must render.');
        self::assertStringContainsString('SendMail', $html, 'Job class must surface in the detail card.');
        self::assertStringContainsString('ada@example.test', $html, 'Payload tree must surface non-sensitive fields.');
        self::assertStringContainsString('← Back to grid', $html, 'Dedicated page must link back to Queue.');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir() . '/yii3-debug-queue-action-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->path . '/*');

        foreach ($files === false ? [] : $files as $file) {
            unlink($file);
        }

        if (is_dir($this->path)) {
            rmdir($this->path);
        }

        parent::tearDown();
    }

    private function action(SnapshotStore $store): QueueJobAction
    {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-queue-action-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
                '@yii3DebugViews' => '@vendor/php-forge/debug-core/resources/views',
            ],
        );
        $assetManager = (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));
        $factory = new HttpFactory();

        return new QueueJobAction(
            $store,
            new LocalAccessChecker(),
            new DebugPageRenderer(
                new WebView(),
                $assetManager,
                new Icon($aliases),
                GridFactory::panelGrid(),
                $aliases,
                panels: [new QueuePanel(GridFactory::panelGrid())],
            ),
            new ResponseBuilder($factory, $factory),
        );
    }

    private function snapshot(): DebugSnapshot
    {
        return new DebugSnapshot(
            new RequestSummary(
                'request-1',
                'https://example.test/',
                false,
                'GET',
                '127.0.0.1',
                1.0,
                200,
                0,
                0,
                0,
                [],
                0.01,
                1024,
            ),
            [
                'queue' => (new QueueSnapshot([
                    new JobRecord(
                        JobRecord::TYPE_PUSH,
                        'emails',
                        'Sync',
                        'Yiisoft\\Queue\\SyncQueueProducer',
                        false,
                        'App\\Message\\SendMail',
                        ['payload' => ['email' => 'ada@example.test']],
                        1.0,
                        'job-1',
                        null,
                        null,
                        null,
                        null,
                        null,
                        '',
                    ),
                ]))->jsonSerialize(),
            ],
            [],
        );
    }
}
