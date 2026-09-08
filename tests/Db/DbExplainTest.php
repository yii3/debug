<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Db;

use PHPForge\Debug\Panel\Db\QueryRow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Db\DbExplain;
use Yii3\Debug\Tests\Support\DatabaseFixture;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * Integration tests for non-executing query plans and explicit database rejection diagnostics.
 */
#[Group('db')]
final class DbExplainTest extends TestCase
{
    public function testSqlitePlansNeverExecuteCapturedMutations(): void
    {
        $db = DatabaseFixture::connection();

        $db->createCommand('CREATE TABLE items (id INTEGER)')->execute();

        $explain = new DbExplain($db);

        self::assertTrue(
            $explain->available(),
            'SQLite must support query plans.',
        );

        $html = $explain->render(QueryRow::create('select * FROM items', 1.0, 1000.0));

        self::assertStringContainsString(
            'SCAN items',
            $html,
            'SQLite must return an EXPLAIN QUERY PLAN result.'
        );

        $explain->render(QueryRow::create('INSERT INTO items VALUES (7)', 1.0, 1000.0));

        self::assertSame(
            '0',
            $db->createCommand('SELECT COUNT(*) FROM items')->queryScalar(),
            'EXPLAIN must not execute DML.'
        );

        foreach (['DROP TABLE items', 'SELECT 1; DROP TABLE items', 'EXPLAIN ANALYZE SELECT 1'] as $sql) {
            self::assertStringContainsString(
                'not available',
                $explain->render(QueryRow::create($sql, 0.0, 0.0)),
                'Unsafe statements must be rejected.'
            );
        }

        self::assertStringContainsString(
            'EXPLAIN failed:',
            $explain->render(QueryRow::create('SELECT * FROM missing', 0.0, 0.0)),
            'Driver errors must render inline.'
        );
    }

    public function testUnavailableConnectionsDoNotProducePlans(): void
    {
        self::assertFalse(
            (new DbExplain())->available(),
            'A missing connection must disable EXPLAIN.'
        );
        self::assertStringContainsString(
            'not available',
            (new DbExplain())->render(QueryRow::create('SELECT 1', 0.0, 0.0)),
            'Missing connections must produce diagnostics.'
        );

        $db = $this->createMock(ConnectionInterface::class);

        $db
            ->method('getDriverName')
            ->willReturn('sqlsrv');
        $db
            ->expects(self::never())
            ->method('createCommand');

        self::assertFalse(
            (new DbExplain($db))->available(),
            'Unsupported drivers must not advertise query plans.'
        );
    }
}
