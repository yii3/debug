<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Action;

use GuzzleHttp\Psr7\{HttpFactory, ServerRequest};
use PHPForge\Debug\Panel\Db\{DbExplainRenderer, DbSnapshot, QueryRow};
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary, SnapshotStore};
use PHPUnit\Framework\Attributes\{Group, IgnoreDeprecations};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii3\Debug\Action\DbExplainAction;
use Yii3\Debug\Web\{LocalAccessChecker, ResponseBuilder};
use Yiisoft\Db\Command\CommandInterface;
use Yiisoft\Db\Connection\ConnectionInterface;

use function is_dir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Unit tests for {@see DbExplainAction} loading captured queries and returning the shared EXPLAIN fragment.
 */
#[Group('db')]
#[IgnoreDeprecations]
final class DbExplainActionTest extends TestCase
{
    private string $path = '';

    public function testInvokeExplainsStoredSqliteQuery(): void
    {
        $store = new SnapshotStore($this->path, 0o777, null);
        $store->writeSnapshot($this->snapshot(), 10);
        $results = [['id' => 2, 'detail' => 'SCAN users']];
        $command = $this->createMock(CommandInterface::class);
        $command->expects(self::once())->method('queryAll')->willReturn($results);
        $db = $this->createMock(ConnectionInterface::class);
        $db->expects(self::once())->method('getDriverName')->willReturn('sqlite');
        $db->expects(self::once())
            ->method('createCommand')
            ->with('EXPLAIN QUERY PLAN SELECT * FROM users')
            ->willReturn($command);
        $factory = new HttpFactory();
        $action = new DbExplainAction(
            $store,
            new LocalAccessChecker(),
            $db,
            new ResponseBuilder($factory, $factory),
        );
        $request = (new ServerRequest(
            'GET',
            'https://example.test/debug/db-explain?tag=request-1&seq=7',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        ))->withQueryParams(['tag' => 'request-1', 'seq' => '7']);

        $response = $action($request);

        self::assertSame(200, $response->getStatusCode(), 'A stored explainable query must return success.');
        self::assertSame(
            DbExplainRenderer::render('SELECT * FROM users', $results),
            (string) $response->getBody(),
            'The action must return the shared EXPLAIN fragment exactly.',
        );
    }

    public function testInvokeRejectsInvalidQuerySequence(): void
    {
        $factory = new HttpFactory();
        $action = new DbExplainAction(
            new SnapshotStore($this->path, 0o777, null),
            new LocalAccessChecker(),
            self::createStub(ConnectionInterface::class),
            new ResponseBuilder($factory, $factory),
        );
        $request = (new ServerRequest(
            'GET',
            'https://example.test/debug/db-explain',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        ))->withQueryParams(['tag' => 'request-1', 'seq' => '-1']);

        self::assertSame(400, $action($request)->getStatusCode(), 'Invalid sequence values must be rejected.');
    }

    public function testInvokeRendersDriverFailureInsideTheExplainContract(): void
    {
        $store = new SnapshotStore($this->path, 0o777, null);
        $store->writeSnapshot($this->snapshot(), 10);
        $command = $this->createMock(CommandInterface::class);
        $command->expects(self::once())
            ->method('queryAll')
            ->willThrowException(new RuntimeException('schema expired'));
        $db = $this->createMock(ConnectionInterface::class);
        $db->expects(self::once())->method('getDriverName')->willReturn('sqlite');
        $db->expects(self::once())->method('createCommand')->willReturn($command);
        $factory = new HttpFactory();
        $action = new DbExplainAction(
            $store,
            new LocalAccessChecker(),
            $db,
            new ResponseBuilder($factory, $factory),
        );
        $request = (new ServerRequest(
            'GET',
            'https://example.test/debug/db-explain',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        ))->withQueryParams(['tag' => 'request-1', 'seq' => '7']);

        $response = $action($request);

        self::assertSame(200, $response->getStatusCode(), 'Driver failures must remain renderable by the AJAX toggle.');
        self::assertSame(
            DbExplainRenderer::renderError('SELECT * FROM users', 'schema expired'),
            (string) $response->getBody(),
            'Driver failures must use the shared EXPLAIN error fragment.',
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir() . '/yii3-debug-db-explain-action-' . uniqid('', true);
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

    private function snapshot(): DebugSnapshot
    {
        return new DebugSnapshot(
            new RequestSummary(
                tag: 'request-1',
                url: 'https://example.test/',
                ajax: false,
                method: 'GET',
                ip: '127.0.0.1',
                time: 1.0,
                statusCode: 200,
                sqlCount: 1,
                excessiveCallersCount: 0,
                mailCount: 0,
                mailFiles: [],
                processingTime: 0.01,
                peakMemory: 1024,
            ),
            [
                'db' => (new DbSnapshot(
                    [
                        new QueryRow(
                            type: 'SELECT',
                            query: 'SELECT * FROM users',
                            duration: 1.0,
                            trace: [],
                            traceHash: 'hash',
                            timestamp: 1.0,
                            seq: 7,
                            duplicate: 1,
                            rows: null,
                        ),
                    ],
                ))->jsonSerialize(),
            ],
            [],
        );
    }
}
