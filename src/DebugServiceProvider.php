<?php

declare(strict_types=1);

namespace Yii3\Debug;

use Closure;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Yii3\Debug\Collector\EventCollector;
use Yii3\Debug\Event\DebugEventDispatcher;
use Yii3\Debug\Exception\Message;
use Yii3\Debug\Log\{DebugLogTarget, LoggerDecorator};
use Yiisoft\Di\ServiceProviderInterface;

/**
 * Attaches the debugger to the application logger and PSR-14 dispatcher without redefining either service.
 *
 * Both decorations are idempotent, so an application registering the provider in more than one configuration group
 * still ends up with each service decorated exactly once.
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
     * Returns the decorations applied to the application logger and PSR-14 dispatcher.
     *
     * Each service keeps its application definition; the decoration runs once, when the container first resolves it.
     *
     * @return array<class-string, Closure(ContainerInterface, object): object> Decoration keyed by extended service.
     */
    public function getExtensions(): array
    {
        return [
            EventDispatcherInterface::class => self::extendEventDispatcher(...),
            LoggerInterface::class => self::extendLogger(...),
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
     * Records every logged message in the Logs panel.
     *
     * @param ContainerInterface $container Container the debugger log target is resolved from.
     * @param object $logger Logger the application declared.
     *
     * @throws RuntimeException when the container resolves the debugger log target to another type.
     *
     * @return object Decorated logger, or the service unchanged when it is no PSR-3 logger.
     */
    private static function extendLogger(ContainerInterface $container, object $logger): object
    {
        return $logger instanceof LoggerInterface
            ? LoggerDecorator::decorate($logger, self::service($container, DebugLogTarget::class))
            : $logger;
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
