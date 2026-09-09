<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support;

use PHPForge\Debug\Panel\Db\{DbSnapshot, QueryRow};
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Sqlite\{Connection, Driver};

/**
 * Shared deterministic SQL rows and isolated in-memory SQLite connections for Database tests.
 */
final class DatabaseFixture
{
    public static function connection(): Connection
    {
        return new Connection(
            new Driver('sqlite::memory:'),
            new SchemaCache(new ArrayCache()),
        );
    }

    public static function snapshot(): DbSnapshot
    {
        return new DbSnapshot(
            [
                QueryRow::create('SELECT alpha', 3.0, 1000.0)
                    ->withTraceHash('same')
                    ->withDuplicate(2)
                    ->withRows(10),
                QueryRow::create('UPDATE beta', 1.0, 2000.0)
                    ->withTraceHash('same')
                    ->withSequence(1)
                    ->withRows(0),
                QueryRow::create('SELECT gamma', 2.0, 3000.0)
                    ->withTraceHash('same')
                    ->withSequence(2)
                    ->withDuplicate(2),
            ],
        );
    }
}
