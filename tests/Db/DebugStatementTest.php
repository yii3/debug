<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Db;

use PDO;
use PDOException;
use PHPForge\Debug\Panel\Db\QueryRow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Collector\DbCollector;
use Yii3\Debug\Db\DebugStatement;

use function array_map;

/**
 * Unit tests for {@see DebugStatement} row-count reporting over a real in-memory SQLite connection.
 */
#[Group('db')]
final class DebugStatementTest extends TestCase
{
    public function testExecutedStatementsReportTheDriverRowCount(): void
    {
        $collector = new DbCollector();

        $collector->startup();

        $pdo = $this->pdo($collector);

        $insert = $pdo->prepare('INSERT INTO items (id, name) VALUES (?, ?)');

        self::assertInstanceOf(
            DebugStatement::class,
            $insert,
            'PDO must build the instrumented statement class.',
        );

        $collector->begin('INSERT', 0.0, []);

        self::assertTrue(
            $insert->execute([1, 'alpha']),
            'The driver return value must survive.'
        );

        $collector->end('INSERT', 1.0);
        $insert->execute([2, 'beta']);
        $update = $pdo->prepare('UPDATE items SET name = ?');

        self::assertInstanceOf(
            DebugStatement::class,
            $update,
            'PDO must build the instrumented statement class.'
        );

        $collector->begin('UPDATE', 1.0, []);
        $update->execute(['gamma']);
        $collector->end('UPDATE', 2.0);
        $select = $pdo->prepare('SELECT id FROM items');

        self::assertInstanceOf(
            DebugStatement::class,
            $select,
            'PDO must build the instrumented statement class.'
        );

        $collector->begin('SELECT', 2.0, []);
        $select->execute();
        $collector->end('SELECT', 3.0);

        self::assertSame(
            [1, 2, 0],
            $this->reported($collector),
            'Counts must be the unmodified driver values.'
        );
    }

    public function testThrowPDOExceptionWhenExecutionIsRejectedWithoutReportingACount(): void
    {
        $collector = new DbCollector();

        $collector->startup();

        $pdo = $this->pdo($collector);

        $insert = $pdo->prepare('INSERT INTO items (id, name) VALUES (?, ?)');

        self::assertInstanceOf(
            DebugStatement::class,
            $insert,
            'PDO must build the instrumented statement class.'
        );

        $insert->execute([1, 'alpha']);
        $collector->begin('INSERT', 0.0, []);

        try {
            $insert->execute([1, 'duplicate']);

            self::fail(
                'A primary-key conflict must reach the caller.',
            );
        } catch (PDOException $exception) {
            self::assertStringContainsString(
                'UNIQUE',
                $exception->getMessage(),
                'Driver diagnostics must survive.'
            );
        }

        $collector->end('INSERT', 1.0);

        self::assertSame(
            [null],
            $this->reported($collector),
            'A rejected attempt must overwrite the earlier count.'
        );
    }

    private function pdo(DbCollector $collector): PDO
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [DebugStatement::class, [$collector]]);
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');

        return $pdo;
    }

    /**
     * @return list<int|null>
     */
    private function reported(DbCollector $collector): array
    {
        return array_map(
            static fn(QueryRow $row): int|null => $row->getRows(),
            $collector->capture()?->entries() ?? [],
        );
    }
}
