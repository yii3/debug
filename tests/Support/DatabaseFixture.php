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
        return new Connection(new Driver('sqlite::memory:'), new SchemaCache(new ArrayCache()));
    }

    public static function snapshot(): DbSnapshot
    {
        return new DbSnapshot([
            new QueryRow('SELECT', 'SELECT alpha', 3.0, [], 'same', 1000.0, 0, 2, 10),
            new QueryRow('UPDATE', 'UPDATE beta', 1.0, [], 'same', 2000.0, 1, 1, 0),
            new QueryRow('SELECT', 'SELECT gamma', 2.0, [], 'same', 3000.0, 2, 2, null),
        ]);
    }
}
