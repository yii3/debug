<?php

declare(strict_types=1);

namespace Yii3\Debug\Db;

use Psr\Container\ContainerInterface;
use Psr\Log\{LoggerAwareInterface, LoggerInterface};
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Driver\Pdo\PdoConnectionInterface;
use Yiisoft\Db\Profiler\ProfilerAwareInterface;

/**
 * Attaches the debugger to the application database connection at bootstrap.
 */
final readonly class ConnectionInstrumentation
{
    /**
     * Gives the application connection the application logger and the debugger profiler.
     *
     * An application without a connection, without a logger, or with a connection no PDO driver backs, is left
     * untouched. Attaching is idempotent, so an application that already wires the connection itself keeps working.
     *
     * @param ContainerInterface $container Container the connection and its observers are resolved from.
     */
    public static function apply(ContainerInterface $container): void
    {
        $connection = self::service($container, ConnectionInterface::class);

        if ($connection instanceof LoggerAwareInterface) {
            $logger = self::service($container, LoggerInterface::class);

            if ($logger !== null) {
                $connection->setLogger($logger);
            }
        }

        if ($connection instanceof PdoConnectionInterface && $connection instanceof ProfilerAwareInterface) {
            self::service($container, DebugDbProfiler::class)?->instrument($connection);
        }
    }

    /**
     * Resolves one optional service from the container.
     *
     * @param ContainerInterface $container Container the service is resolved from.
     * @param string $id Service the debugger attaches to.
     *
     * @template T of object
     *
     * @phpstan-param class-string<T> $id
     *
     * @return object|null Service, or `null` when the container declares none or resolves it to another type.
     *
     * @phpstan-return T|null
     */
    private static function service(ContainerInterface $container, string $id): object|null
    {
        if (!$container->has($id)) {
            return null;
        }

        $service = $container->get($id);

        return $service instanceof $id ? $service : null;
    }
}
