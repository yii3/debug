<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use Closure;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\{EventDispatcherInterface, ListenerProviderInterface};
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use RuntimeException;
use stdClass;
use Yii3\Debug\Collector\EventCollector;
use Yii3\Debug\DebugServiceProvider;
use Yii3\Debug\Event\DebugEventDispatcher;
use Yii3\Debug\Log\DebugLogTarget;
use Yii3\Debug\Tests\Support\Stubs\ContainerStub;
use Yiisoft\Definitions\Reference;
use Yiisoft\Di\{Container, ContainerConfig};
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;
use Yiisoft\EventDispatcher\Provider\{ListenerCollection, Provider};
use Yiisoft\Log\{Logger, StreamTarget};

/**
 * Unit tests for {@see DebugServiceProvider} decorating the application logger and PSR-14 dispatcher.
 */
final class DebugServiceProviderTest extends TestCase
{
    public function testExtensionsDecorateEachServiceOnceWhenTheProviderIsRegisteredTwice(): void
    {
        $container = self::container();

        $dispatcher = $container->get(EventDispatcherInterface::class);
        $logger = $container->get(LoggerInterface::class);

        self::assertInstanceOf(
            DebugEventDispatcher::class,
            $dispatcher,
            'Dispatcher must record events.',
        );
        self::assertInstanceOf(
            Dispatcher::class,
            (new ReflectionProperty(DebugEventDispatcher::class, 'dispatcher'))->getValue($dispatcher),
            'Recording must wrap the application dispatcher exactly once.',
        );
        self::assertSame(
            $container->get(EventCollector::class),
            (new ReflectionProperty(DebugEventDispatcher::class, 'collector'))->getValue($dispatcher),
            'Recording must use the shared collector.',
        );
        self::assertInstanceOf(
            Logger::class,
            $logger,
            'Logger must stay a Yii logger.',
        );

        $targets = $logger->getTargets();

        self::assertCount(
            2,
            $targets,
            'The debugger target must be attached exactly once.',
        );
        self::assertInstanceOf(
            StreamTarget::class,
            $targets[0] ?? null,
            'Order: application targets first.',
        );
        self::assertSame(
            $container->get(DebugLogTarget::class),
            $targets[1] ?? null,
            'The attached target must be the shared one.',
        );
    }

    public function testExtensionsReturnForeignServicesUnchanged(): void
    {
        $container = new ContainerStub();
        $foreign = new stdClass();
        $recording = new DebugEventDispatcher(self::dispatcher(), new EventCollector());

        self::assertSame(
            $foreign,
            (self::extension(EventDispatcherInterface::class))($container, $foreign),
            'A service that is no PSR-14 dispatcher must pass through.',
        );
        self::assertSame(
            $recording,
            (self::extension(EventDispatcherInterface::class))($container, $recording),
            'An already recording dispatcher must pass through.',
        );
        self::assertSame(
            $foreign,
            (self::extension(LoggerInterface::class))($container, $foreign),
            'A service that is no PSR-3 logger must pass through.',
        );
    }

    public function testGetDefinitionsAddsNoService(): void
    {
        self::assertSame(
            [],
            (new DebugServiceProvider())->getDefinitions(),
            'The debugger must only extend services the application declares.',
        );
    }

    public function testThrowRuntimeExceptionWhenTheContainerResolvesAnotherEventCollector(): void
    {
        $container = new ContainerStub([EventCollector::class => new stdClass()]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The debug service Yii3\Debug\Collector\EventCollector is not available.',
        );

        (self::extension(EventDispatcherInterface::class))($container, self::dispatcher());
    }

    public function testThrowRuntimeExceptionWhenTheContainerResolvesAnotherLogTarget(): void
    {
        $container = new ContainerStub([DebugLogTarget::class => new stdClass()]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The debug service Yii3\Debug\Log\DebugLogTarget is not available.',
        );

        (self::extension(LoggerInterface::class))($container, new Logger());
    }

    /**
     * @return Container Container declaring an application logger and dispatcher, with the provider registered twice.
     */
    private static function container(): Container
    {
        return new Container(
            ContainerConfig::create()
                ->withDefinitions(
                    [
                        EventDispatcherInterface::class => Dispatcher::class,
                        ListenerProviderInterface::class => Provider::class,
                        LoggerInterface::class => [
                            'class' => Logger::class,
                            '__construct()' => [
                                'targets' => [Reference::to(StreamTarget::class)],
                            ],
                        ],
                    ],
                )
                ->withProviders([new DebugServiceProvider(), new DebugServiceProvider()]),
        );
    }

    private static function dispatcher(): Dispatcher
    {
        return new Dispatcher(new Provider(new ListenerCollection()));
    }

    /**
     * @param class-string $id Service the provider is expected to extend.
     *
     * @return Closure(ContainerInterface, object): object Decoration the provider applies to the service.
     */
    private static function extension(string $id): Closure
    {
        return (new DebugServiceProvider())->getExtensions()[$id] ?? self::fail("The provider must extend {$id}.");
    }
}
