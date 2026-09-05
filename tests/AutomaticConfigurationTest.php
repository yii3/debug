<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use Closure;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\{EventDispatcherInterface, ListenerProviderInterface};
use Psr\Log\LoggerInterface;
use stdClass;
use Yii3\Debug\Collector\{EventCollector, InertiaCollector, ViteCollector};
use Yii3\Debug\Event\DebugEventDispatcher;
use Yii3\Debug\ExtensionRegistry;
use Yii3\Debug\Log\DebugLogTarget;
use Yii3\Debug\Panel\{InertiaPanel, VitePanel};
use Yiisoft\Config\{Config, ConfigPaths};
use Yiisoft\Config\Modifier\RecursiveMerge;
use Yiisoft\Definitions\Reference;
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;
use Yiisoft\Log\StreamTarget;

use function dirname;
use function getenv;
use function putenv;

/**
 * Verifies automatic configuration merging, explicit extensions, and production isolation.
 */
final class AutomaticConfigurationTest extends TestCase
{
    private string|false $environment;
    private mixed $serverEnvironment;

    public function testConfiguredDispatcherPreservesListenerProviderAndCapturesEvents(): void
    {
        putenv('APP_ENV=dev');

        $definitions = $this->config('dev')->get('di');

        self::assertArrayHasKey(
            EventDispatcherInterface::class,
            $definitions,
            'The event factory must be registered.',
        );

        $factory = $definitions[EventDispatcherInterface::class];

        self::assertInstanceOf(
            Closure::class,
            $factory,
            'Event capture must use the package factory.',
        );

        $event = new stdClass();

        $provider = $this->createMock(ListenerProviderInterface::class);

        $provider
            ->expects(self::once())
            ->method('getListenersForEvent')
            ->with($event)
            ->willReturn([]);

        $collector = new EventCollector();

        $collector->startup();

        $dispatcher = $factory(new Dispatcher($provider), $collector);

        self::assertInstanceOf(
            DebugEventDispatcher::class,
            $dispatcher,
            'The factory must decorate the concrete dispatcher.',
        );
        self::assertSame(
            $event,
            $dispatcher->dispatch($event),
            'Dispatch must retain the original event and listener provider.',
        );

        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'The configured collector must capture the event.'
        );
        self::assertCount(
            1,
            $snapshot->entries(),
            'One dispatch must produce exactly one event row.'
        );
    }

    public function testDefaultWebConfigurationDoesNotResolveOptionalIntegrations(): void
    {
        putenv('APP_ENV=dev');

        $params = require dirname(__DIR__) . '/config/params.php';
        $definitions = require dirname(__DIR__) . '/config/di-web.php';

        self::assertSame(
            [ExtensionRegistry::class => ['__construct()' => ['collectors' => [], 'panels' => []]]],
            $definitions,
            'Default web configuration must not require Inertia or Vite application parameters.',
        );
    }

    public function testDevelopmentConfigurationMergesAutomatically(): void
    {
        foreach (['debug', 'dev', 'test'] as $environment) {
            putenv('APP_ENV=' . $environment);

            $config = $this->config($environment);

            $definitions = $config->get('di-web');

            self::assertArrayHasKey(
                EventDispatcherInterface::class,
                $definitions,
                'The dispatcher binding must exist.',
            );
            self::assertInstanceOf(
                Closure::class,
                $definitions[EventDispatcherInterface::class],
                'The vendor override layer must replace Yii dispatching without duplicate keys.',
            );
            self::assertArrayHasKey(
                ExtensionRegistry::class,
                $definitions,
                'Extensions must merge into standard web DI.'
            );
            self::assertArrayHasKey(
                LoggerInterface::class,
                $definitions,
                'The logger binding must exist.'
            );

            $logger = $definitions[LoggerInterface::class];

            self::assertIsArray(
                $logger,
                'The application logger must retain its definition.',
            );
            self::assertArrayHasKey(
                '__construct()',
                $logger,
                'Logger constructor arguments must exist.'
            );

            $constructor = $logger['__construct()'];

            self::assertIsArray(
                $constructor,
                'The logger must configure constructor arguments.'
            );
            self::assertArrayHasKey(
                'targets',
                $constructor,
                'Logger targets must be configured.'
            );
            self::assertEquals(
                ['debug' => Reference::to(DebugLogTarget::class), 'stream' => Reference::to(StreamTarget::class)],
                $constructor['targets'],
                'Recursive parameter merging must retain application and debugger log targets.',
            );
        }
    }

    public function testEnabledExtensionsUseSharedReferencesInRegistrationOrder(): void
    {
        putenv('APP_ENV=dev');

        $definitions = $this->config('dev')->get('di-web');

        self::assertArrayHasKey(
            ExtensionRegistry::class,
            $definitions,
            'The registry binding must exist.'
        );
        self::assertEquals(
            [
                '__construct()' => [
                    'collectors' => [Reference::to(InertiaCollector::class), Reference::to(ViteCollector::class)],
                    'panels' => [Reference::to(InertiaPanel::class), Reference::to(VitePanel::class)],
                ],
            ],
            $definitions[ExtensionRegistry::class],
            'The registry must resolve the same configured collectors and panels in extension order.',
        );
    }

    public function testMissingAndUnknownEnvironmentsDisableEveryPackageConfig(): void
    {
        unset($_SERVER['APP_ENV']);

        foreach ([null, '', 'production', 'staging'] as $environment) {
            putenv($environment === null ? 'APP_ENV' : 'APP_ENV=' . $environment);

            foreach (['params', 'di', 'di-web', 'routes'] as $group) {
                self::assertSame(
                    [],
                    require dirname(__DIR__) . '/config/' . $group . '.php',
                    'Missing or unrecognized environments must contribute no debugger configuration.',
                );
            }
        }
    }

    public function testProcessEnvironmentTakesPrecedenceOverServerEnvironment(): void
    {
        $_SERVER['APP_ENV'] = 'dev';

        putenv('APP_ENV=prod');

        self::assertFalse(
            require dirname(__DIR__) . '/config/enabled.php',
            'A development server value must not override a production process.'
        );

        putenv('APP_ENV');

        self::assertTrue(
            require dirname(__DIR__) . '/config/enabled.php',
            'The server environment must be used when the process value is absent.',
        );
    }

    public function testProductionConfigurationExcludesDebuggerWithDevelopmentDependenciesInstalled(): void
    {
        putenv('APP_ENV=prod');

        $config = $this->config('prod');

        $definitions = $config->get('di-web');
        $params = $config->get('params');

        self::assertArrayHasKey(
            EventDispatcherInterface::class,
            $definitions,
            'The default dispatcher binding must exist.',
        );
        self::assertSame(
            Dispatcher::class,
            $definitions[EventDispatcherInterface::class],
            'Production must retain Yii dispatching.',
        );
        self::assertArrayNotHasKey(
            ExtensionRegistry::class,
            $definitions,
            'Production must not register extension services.',
        );
        self::assertArrayNotHasKey(
            'yiisoft/middleware-dispatcher',
            $params,
            'Production must not receive toolbar middleware.',
        );
        self::assertSame(
            [],
            $config->get('routes'),
            'Production must not expose debugger routes.',
        );
    }

    protected function setUp(): void
    {
        $this->environment = getenv('APP_ENV');

        $this->serverEnvironment = $_SERVER['APP_ENV'] ?? null;
    }

    protected function tearDown(): void
    {
        putenv($this->environment === false ? 'APP_ENV' : 'APP_ENV=' . $this->environment);

        if ($this->serverEnvironment === null) {
            unset($_SERVER['APP_ENV']);
        } else {
            $_SERVER['APP_ENV'] = $this->serverEnvironment;
        }
    }

    private function config(string $environment): Config
    {
        return new Config(
            new ConfigPaths(dirname(__DIR__), 'tests/Support/Config', ''),
            $environment,
            [RecursiveMerge::groups('params')],
            mergePlanFile: 'merge-plan.php',
        );
    }
}
