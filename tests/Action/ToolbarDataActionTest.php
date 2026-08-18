<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Action;

use GuzzleHttp\Psr7\{HttpFactory, ServerRequest};
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary, SnapshotStore};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Action\ToolbarDataAction;
use Yii3\Debug\ToolbarDataFactory;
use Yii3\Debug\Web\{LocalAccessChecker, ResponseBuilder};
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};

use function is_dir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Unit tests for {@see ToolbarDataAction} serving stored snapshots as toolbar JSON.
 *
 * @since 0.1
 */
#[Group('toolbar')]
final class ToolbarDataActionTest extends TestCase
{
    private string $path = '';

    public function testInvokeReturnsToolbarDataForKnownTag(): void
    {
        $store = new SnapshotStore($this->path, 0o777, null);
        $store->writeSnapshot($this->snapshot(), 10);
        $factory = new HttpFactory();
        $action = new ToolbarDataAction(
            $store,
            new LocalAccessChecker(),
            new ToolbarDataFactory($this->assetManager()),
            new ResponseBuilder($factory, $factory),
        );
        $request = (new ServerRequest(
            'GET',
            'https://example.test/debug/toolbar?tag=request-1',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        ))->withQueryParams(['tag' => 'request-1']);

        $response = $action($request);
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode(), 'Known snapshot must return a successful response.');
        self::assertSame('application/json; charset=UTF-8', $response->getHeaderLine('Content-Type'), 'Media type must be JSON.');
        self::assertIsArray($payload, 'Response body must decode to an object.');
        self::assertSame('request-1', $payload['tag'] ?? null, 'Payload must identify the requested snapshot.');
    }

    /**
     * Creates an isolated temporary storage path.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir() . '/yii3-debug-toolbar-action-' . uniqid('', true);
    }

    /**
     * Removes temporary storage files.
     */
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

    /**
     * Creates an asset manager that publishes into the test runtime.
     *
     * @return AssetManager Configured asset manager.
     */
    private function assetManager(): AssetManager
    {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-toolbar-action-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
            ],
        );

        return (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));
    }

    /**
     * Creates a representative stored snapshot.
     *
     * @return DebugSnapshot Representative snapshot.
     */
    private function snapshot(): DebugSnapshot
    {
        return new DebugSnapshot(
            new RequestSummary(
                tag: 'request-1',
                url: 'https://example.test/',
                ajax: false,
                method: 'GET',
                ip: '127.0.0.1',
                time: 1_700_000_000.0,
                statusCode: 200,
                sqlCount: 0,
                excessiveCallersCount: 0,
                mailCount: 0,
                mailFiles: [],
                processingTime: 0.01,
                peakMemory: 2_097_152,
            ),
            [
                'request' => ['request' => [], 'response' => []],
            ],
            [],
        );
    }
}
