<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use Closure;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\{EventDispatcherInterface, ListenerProviderInterface};
use ReflectionProperty;
use RuntimeException;
use stdClass;
use Yii3\Debug\Collector\EventCollector;
use Yii3\Debug\DebugServiceProvider;
use Yii3\Debug\Event\DebugEventDispatcher;
use Yii3\Debug\Tests\Support\Stubs\ContainerStub;
use Yiisoft\Di\{Container, ContainerConfig};
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;
use Yiisoft\EventDispatcher\Provider\{ListenerCollection, Provider};

/**
 * Unit tests for {@see DebugServiceProvider} decorating the application PSR-14 dispatcher.
 */
final class DebugServiceProviderTest extends TestCase
{
    public function testExtensionsDecorateTheDispatcherOnceWhenTheProviderIsRegisteredTwice(): void
    {
        $container = self::container();

        $dispatcher = $container->get(EventDispatcherInterface::class);

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

    /**
     * @return Container Container declaring an application dispatcher, with the provider registered twice.
     */
    private static function container(): Container
    {
        return new Container(
            ContainerConfig::create()
                ->withDefinitions(
                    [
                        EventDispatcherInterface::class => Dispatcher::class,
                        ListenerProviderInterface::class => Provider::class,
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
