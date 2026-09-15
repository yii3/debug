<?php

declare(strict_types=1);

namespace Yii3\Debug;

use Closure;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Yii3\Debug\Collector\EventCollector;
use Yii3\Debug\Event\DebugEventDispatcher;
use Yii3\Debug\Exception\Message;
use Yiisoft\Di\ServiceProviderInterface;

/**
 * Attaches the debugger to the application PSR-14 dispatcher without redefining the service.
 *
 * The decoration is idempotent, so an application registering the provider in more than one configuration group still
 * ends up with the dispatcher decorated exactly once.
 *
 * The Logs panel is fed by the `debug` target the package merges into the `yiisoft/log` parameters, so the application
 * logger is built by the application itself and is never decorated here.
 */
final class DebugServiceProvider implements ServiceProviderInterface
{
    /**
     * Returns the services the provider owns.
     *
     * @return array<string, mixed> Empty set; the debugger only extends services the application already declares.
     */
    public function getDefinitions(): array
    {
        return [];
    }

    /**
     * Returns the decoration applied to the application PSR-14 dispatcher.
     *
     * The service keeps its application definition; the decoration runs once, when the container first resolves it.
     *
     * @return array<class-string, Closure(ContainerInterface, object): object> Decoration keyed by extended service.
     */
    public function getExtensions(): array
    {
        return [
            EventDispatcherInterface::class => self::extendEventDispatcher(...),
        ];
    }

    /**
     * Records every dispatched event in the Events panel.
     *
     * @param ContainerInterface $container Container the event collector is resolved from.
     * @param object $dispatcher Dispatcher the application declared.
     *
     * @throws RuntimeException when the container resolves the event collector to another type.
     *
     * @return object Recording dispatcher, or the service unchanged when it is already recording or is no PSR-14
     * dispatcher.
     */
    private static function extendEventDispatcher(ContainerInterface $container, object $dispatcher): object
    {
        if ($dispatcher instanceof DebugEventDispatcher || !$dispatcher instanceof EventDispatcherInterface) {
            return $dispatcher;
        }

        return new DebugEventDispatcher($dispatcher, self::service($container, EventCollector::class));
    }

    /**
     * Resolves one service the decoration requires.
     *
     * @param ContainerInterface $container Container the service is resolved from.
     * @param string $id Service the decoration requires.
     *
     * @template T of object
     *
     * @phpstan-param class-string<T> $id
     *
     * @throws RuntimeException when the container resolves the service to another type.
     *
     * @return object Service the decoration requires.
     *
     * @phpstan-return T
     */
    private static function service(ContainerInterface $container, string $id): object
    {
        $service = $container->get($id);

        if (!$service instanceof $id) {
            throw new RuntimeException(Message::DEBUG_SERVICE_UNAVAILABLE->getMessage($id));
        }

        return $service;
    }
}
