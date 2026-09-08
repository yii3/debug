<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Action;

use HttpSoft\Message\{ResponseFactory, StreamFactory};
use PHPForge\Debug\Panel\Db\{DbSnapshot, QueryRow};
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary, SnapshotStore};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Action\DbExplainAction;
use Yii3\Debug\Db\DbExplain;
use Yii3\Debug\Tests\Support\{DatabaseFixture, HelperFactory};

/**
 * Unit tests for snapshot-only EXPLAIN lookup, malformed input, and diagnostic response contracts.
 */
#[Group('db')]
final class DbExplainActionTest extends TestCase
{
    public function testStoredQueryLookupRejectsMissingMalformedAndUncapturedRows(): void
    {
        $directory = sys_get_temp_dir() . '/debug-db-' . bin2hex(random_bytes(8));

        $store = new SnapshotStore(
            $directory,
            0o700,
            0o600,
        );
        $action = new DbExplainAction(
            $store,
            new DbExplain(DatabaseFixture::connection()),
            new ResponseFactory(),
            new StreamFactory()
        );
        $payload = new DbSnapshot(
            [QueryRow::create('SELECT 7', 1.0, 1000.0)->withSequence(2)],
        );

        $store
            ->writeSnapshot(
                new DebugSnapshot(
                    RequestSummary::create('valid'),
                    ['db' => $payload->jsonSerialize()],
                    [],
                ),
                50,
            );
        $store->writeSnapshot(
            new DebugSnapshot(
                RequestSummary::create('empty'),
                [],
                [],
            ),
            50,
        );

        try {
            foreach (['', '?tag=&seq=2', '?tag=valid', '?tag[]=valid&seq=2', '?tag=valid&seq[]=2', '?tag=valid&seq=-1', '?tag=valid&seq=2.0'] as $query) {
                self::assertSame(
                    400,
                    $action(HelperFactory::createRequest(uri: '/debug/db-explain' . $query))->getStatusCode(),
                    'Invalid query parameters must be rejected.',
                );
            }

            foreach (['tag=missing&seq=2', 'tag=empty&seq=2', 'tag=valid&seq=0', 'tag=valid&seq=02', 'tag=valid&seq=999999999999999999999999'] as $query) {
                self::assertSame(
                    404,
                    $action(HelperFactory::createRequest(uri: '/debug/db-explain?' . $query))->getStatusCode(),
                    'Only an exact stored sequence may be explained.',
                );
            }

            $response = $action(HelperFactory::createRequest(uri: '/debug/db-explain?tag=valid&seq=2&sql=DROP%20TABLE%20items'));

            self::assertSame(
                200,
                $response->getStatusCode(),
                'A stored supported query must produce an inline plan.',
            );
            self::assertSame(
                'no-store',
                $response->getHeaderLine('Cache-Control'),
                'Diagnostic SQL must not be cached.',
            );
            self::assertSame(
                'text/html; charset=UTF-8',
                $response->getHeaderLine('Content-Type'),
                'Plans must use the HTML response contract.',
            );
            self::assertStringContainsString(
                'SELECT',
                strip_tags((string) $response->getBody()),
                'The plan must use the stored SQL.',
            );
            self::assertStringNotContainsString(
                'DROP',
                (string) $response->getBody(),
                'Client-supplied SQL must be ignored.',
            );
        } finally {
            $store->clear();
        }
    }
}
