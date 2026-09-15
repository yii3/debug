<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Db;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\{LoggerInterface, NullLogger};
use ReflectionProperty;
use stdClass;
use Yii3\Debug\Collector\DbCollector;
use Yii3\Debug\Db\{ConnectionInstrumentation, DebugDbProfiler};
use Yii3\Debug\Tests\Support\DatabaseFixture;
use Yii3\Debug\Tests\Support\Stubs\ContainerStub;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Driver\Pdo\PdoConnectionInterface;

/**
 * Unit tests for {@see ConnectionInstrumentation} attaching the debugger to the application connection.
 */
#[Group('db')]
final class ConnectionInstrumentationTest extends TestCase
{
    public function testApplyAttachesTheLoggerAndTheProfilerOnlyOnce(): void
    {
        $connection = DatabaseFixture::connection();

        $logger = new NullLogger();
        $profiler = new DebugDbProfiler(new DbCollector());
        $container = new ContainerStub(
            [
                ConnectionInterface::class => $connection,
                DebugDbProfiler::class => $profiler,
                LoggerInterface::class => $logger,
            ],
        );

        ConnectionInstrumentation::apply($container);
        ConnectionInstrumentation::apply($container);

        self::assertSame(
            $logger,
            (new ReflectionProperty($connection, 'logger'))->getValue($connection),
            'Connection must log through the application logger.',
        );
        self::assertSame(
            $profiler,
            (new ReflectionProperty($connection, 'profiler'))->getValue($connection),
            'Connection must report statements to the debugger profiler.',
        );
        self::assertSame(
            $connection,
            (new ReflectionProperty(DebugDbProfiler::class, 'connection'))->getValue($profiler),
            'Profiler must observe the application connection.',
        );
    }

    public function testApplyIgnoresAConnectionResolvedToAnotherType(): void
    {
        $profiler = new DebugDbProfiler(new DbCollector());

        ConnectionInstrumentation::apply(
            new ContainerStub(
                [
                    ConnectionInterface::class => new stdClass(),
                    DebugDbProfiler::class => $profiler,
                    LoggerInterface::class => new NullLogger(),
                ],
            ),
        );

        self::assertNull(
            (new ReflectionProperty(DebugDbProfiler::class, 'connection'))->getValue($profiler),
            'A foreign connection service must be left alone.',
        );
    }

    public function testApplyIgnoresAConnectionWithoutAPdoDriver(): void
    {
        $profiler = new DebugDbProfiler(new DbCollector());

        $connection = self::createStub(ConnectionInterface::class);

        ConnectionInstrumentation::apply(
            new ContainerStub(
                [
                    ConnectionInterface::class => $connection,
                    DebugDbProfiler::class => $profiler,
                    LoggerInterface::class => new NullLogger(),
                ],
            ),
        );

        self::assertNull(
            (new ReflectionProperty(DebugDbProfiler::class, 'connection'))->getValue($profiler),
            'A connection with no PDO driver must not be instrumented.',
        );
    }

    public function testApplyIgnoresAContainerWithoutAConnection(): void
    {
        $profiler = new DebugDbProfiler(new DbCollector());

        ConnectionInstrumentation::apply(new ContainerStub([DebugDbProfiler::class => $profiler]));

        self::assertNull(
            (new ReflectionProperty(DebugDbProfiler::class, 'connection'))->getValue($profiler),
            'An application without a database must be left alone.',
        );
    }

    public function testApplyIgnoresAPdoConnectionThatReportsNoProfiler(): void
    {
        $profiler = new DebugDbProfiler(new DbCollector());
        $connection = self::createStub(PdoConnectionInterface::class);

        ConnectionInstrumentation::apply(
            new ContainerStub(
                [
                    ConnectionInterface::class => $connection,
                    DebugDbProfiler::class => $profiler,
                    LoggerInterface::class => new NullLogger(),
                ],
            ),
        );

        self::assertNull(
            (new ReflectionProperty(DebugDbProfiler::class, 'connection'))->getValue($profiler),
            'A connection that accepts no profiler must not be instrumented.',
        );
    }

    public function testApplyLeavesTheConnectionUntouchedWithoutALoggerOrProfiler(): void
    {
        $connection = DatabaseFixture::connection();

        ConnectionInstrumentation::apply(new ContainerStub([ConnectionInterface::class => $connection]));

        self::assertNull(
            (new ReflectionProperty($connection, 'logger'))->getValue($connection),
            'An application without a logger must leave the connection silent.',
        );
        self::assertNull(
            (new ReflectionProperty($connection, 'profiler'))->getValue($connection),
            'An application without the debugger profiler must leave the connection unprofiled.',
        );
    }
}
