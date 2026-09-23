<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Config;

use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\CollectorInterface;
use PHPForge\Debug\Helper\SensitiveDataRedactor;
use PHPForge\Inertia\Debug\{InertiaCollector, InertiaPanel};
use PHPForge\Inertia\{PageInput, Protocol, RequestContext};
use PHPForge\Vite\Debug\{ViteCollector, VitePanel};
use PHPUnit\Framework\TestCase;
use Throwable;
use Yii3\Debug\ExtensionRegistry;
use Yii3\Debug\Panel\{ExtensionPanelInterface, ProviderPanel};
use Yii3\Debug\Tests\Support\HelperFactory;
use Yii3\Debug\Tests\Support\PackageConfiguration;
use Yii3\Debug\Tests\Support\Stubs\Cache\{Cache, CacheCollector, CachePanel};

use function array_keys;
use function array_map;
use function getenv;
use function is_string;
use function putenv;

/**
 * Integration tests for the packaged `config/*.php` files building {@see ExtensionRegistry} from application params.
 */
final class ExtensionConfigurationTest extends TestCase
{
    /**
     * Value of the `APP_ENV` environment variable before the test replaced it.
     */
    private string|false $environment = false;
    /**
     * Value of the `APP_ENV` server entry before the test replaced it, or `null` when it was absent or not a `string`.
     */
    private string|null $serverEnvironment = null;

    public function testApplicationCanDisableTheInertiaProvider(): void
    {
        $registry = PackageConfiguration::container(
            [
                'collectors' => [
                    'inertia' => ['class' => InertiaCollector::class, 'enabled' => false],
                    'vite' => ViteCollector::class,
                ],
                'panels' => [
                    'inertia' => ['class' => InertiaPanel::class, 'enabled' => false],
                    'vite' => VitePanel::class,
                ],
            ],
        )->get(ExtensionRegistry::class);

        self::assertInstanceOf(
            ExtensionRegistry::class,
            $registry,
            'Packaged definition must build the registry.',
        );
        self::assertSame(
            ['vite'],
            self::collectorIds($registry),
            'Disabling one provider must leave the other one collecting.',
        );
        self::assertSame(
            ['vite'],
            self::panelIds($registry),
            'Disabling one provider must leave the other panel.',
        );
        self::assertSame(
            ['inertia'],
            $registry->disabled(),
            'Only the disabled provider must be reported.',
        );
    }

    public function testDisabledEntryDoesNotRequireItsClass(): void
    {
        $registry = PackageConfiguration::container(
            [
                'collectors' => [
                    'cache' => CacheCollector::class,
                    'ghost' => ['class' => 'Acme\\Missing\\GhostCollector', 'enabled' => false],
                ],
                'panels' => [
                    'cache' => ['class' => CachePanel::class, 'title' => 'Cache operations', 'icon' => 'asset'],
                    'ghost' => ['class' => 'Acme\\Missing\\GhostPanel', 'enabled' => false],
                ],
            ],
        )->get(ExtensionRegistry::class);

        self::assertInstanceOf(
            ExtensionRegistry::class,
            $registry,
            'Packaged definition must build the registry.',
        );
        self::assertSame(
            ['cache'],
            self::collectorIds($registry),
            'A disabled collector must never be instantiated.',
        );
        self::assertSame(
            ['cache'],
            self::panelIds($registry),
            'A disabled panel must never be instantiated.',
        );
    }

    /**
     * Characterization test: replaying a stored capture already ignores the live service.
     */
    public function testHistoricalReplayIgnoresLiveCacheState(): void
    {
        $collector = new CacheCollector();
        $cache = new Cache($collector);

        $collector->startup();
        $cache->set('a', 1);
        $cache->get('a');

        $payload = $collector->capture();

        $collector->shutdown();

        $cache->set('b', 2);

        self::assertIsArray(
            $payload,
            'A started collector must return a payload.',
        );

        $html = (new ProviderPanel(new CachePanel()))->render(HelperFactory::createPanelRenderInput($payload));

        self::assertStringContainsString(
            "\na\n",
            $html,
            'Stored key must reach the replay.',
        );
        self::assertStringNotContainsString(
            "\nb\n",
            $html,
            'Live state must stay out of the replay.',
        );
    }

    public function testPackagedConfigurationRegistersConfiguredCollectorsAndPanels(): void
    {
        $registry = PackageConfiguration::container(
            [
                'collectors' => ['cache' => CacheCollector::class],
                'panels' => [
                    'cache' => ['class' => CachePanel::class, 'title' => 'Cache operations', 'icon' => 'asset'],
                ],
            ],
        )->get(ExtensionRegistry::class);

        self::assertInstanceOf(
            ExtensionRegistry::class,
            $registry,
            'Packaged definition must build the registry.',
        );

        $collectors = $registry->collectors();
        $panels = $registry->panels();

        self::assertCount(
            1,
            $collectors,
            'One configured collector must produce one entry.',
        );
        self::assertInstanceOf(
            CacheCollector::class,
            $collectors[0],
            'Configured class must be instantiated.',
        );
        self::assertCount(
            1,
            $panels,
            'One configured panel must produce one entry.',
        );
        self::assertSame(
            'cache',
            $panels[0]->id(),
            'Stable ID must come from the registration key.',
        );
        self::assertSame(
            'Cache operations',
            $panels[0]->name(),
            'Title override must beat the provider default.',
        );
        self::assertSame(
            'asset',
            $panels[0]->icon(),
            'Icon override must beat the provider default.',
        );
    }

    public function testPackagedConfigurationRegistersNoProviderByDefault(): void
    {
        $registry = PackageConfiguration::container()->get(ExtensionRegistry::class);

        self::assertInstanceOf(
            ExtensionRegistry::class,
            $registry,
            'Packaged definition must build the registry.',
        );
        self::assertSame(
            [],
            self::collectorIds($registry),
            'An installed provider package must not collect until the application registers it.',
        );
        self::assertSame(
            [],
            self::panelIds($registry),
            'An installed provider package must not add a panel until the application registers it.',
        );
    }

    public function testPackagedEventsConfigurationDeclaresOnlyTheShutdownListener(): void
    {
        $listeners = PackageConfiguration::events(PackageConfiguration::params());

        // yiisoft/yii-http is not a dependency of this package; the packaged configuration only names the class.
        self::assertSame(
            ['Yiisoft\\Yii\\Http\\Event\\ApplicationShutdown'],
            array_keys($listeners),
            'No provider listener may be packaged.',
        );
    }

    public function testProviderRecipeBuildsTheInertiaCollectorWithTheHostCapturePolicy(): void
    {
        $container = PackageConfiguration::container(
            [
                'collectors' => ['inertia' => InertiaCollector::class],
                'panels' => ['inertia' => InertiaPanel::class],
            ],
            [
                InertiaCollector::class => static fn(CapturePolicy $policy): InertiaCollector => new InertiaCollector(
                    $policy->redact(...),
                    $policy->redactUrl(...),
                ),
            ],
        );

        $registry = $container->get(ExtensionRegistry::class);

        self::assertInstanceOf(
            ExtensionRegistry::class,
            $registry,
            'Packaged definition must build the registry.',
        );

        $collector = $registry->collectors()[0] ?? null;

        self::assertSame(
            $container->get(InertiaCollector::class),
            $collector,
            'The listener instance must be the registered collector.',
        );
        self::assertInstanceOf(
            InertiaCollector::class,
            $collector,
            'Registry must hold the Inertia collector.',
        );

        $collector->startup();

        Protocol::create(eventDispatcher: $collector)->page(
            new RequestContext('GET', '/home', 'https://example.test/home', ['X-Inertia' => 'true']),
            PageInput::create('Home', ['answer' => 42, 'password' => 'secret'], ''),
        );

        $capture = $collector->capture();

        self::assertIsArray(
            $capture,
            'A protocol result must be captured.',
        );

        $page = $capture['page'] ?? null;

        self::assertIsArray(
            $page,
            'Capture must carry the page.',
        );

        $props = $page['props'] ?? null;

        self::assertIsArray(
            $props,
            'Capture must carry the page props.',
        );
        self::assertSame(
            42,
            $props['answer'] ?? null,
            'Plain props must survive.',
        );
        self::assertSame(
            SensitiveDataRedactor::PLACEHOLDER,
            $props['password'] ?? null,
            'Sensitive props must follow the host policy.',
        );
    }

    public function testSameCollectorInstanceServesEventsAndCapture(): void
    {
        $container = PackageConfiguration::container(
            [
                'collectors' => ['cache' => CacheCollector::class],
                'panels' => [
                    'cache' => ['class' => CachePanel::class, 'title' => 'Cache operations', 'icon' => 'asset'],
                ],
            ],
        );

        $registry = $container->get(ExtensionRegistry::class);

        self::assertInstanceOf(
            ExtensionRegistry::class,
            $registry,
            'Packaged definition must build the registry.',
        );

        $collectors = $registry->collectors();

        self::assertCount(
            1,
            $collectors,
            'One configured collector must produce one entry.',
        );
        self::assertSame(
            $container->get(CacheCollector::class),
            $collectors[0],
            'Listener and capture must share one instance.',
        );
    }

    public function testThrowThrowableForMismatchedPanelKey(): void
    {
        $this->expectException(Throwable::class);
        $this->expectExceptionMessageMatches(
            '/must match|mismatch/i',
        );

        PackageConfiguration::container(['panels' => ['wrong' => CachePanel::class]])
            ->get(ExtensionRegistry::class);
    }

    public function testThrowThrowableForUnknownPanelOption(): void
    {
        $this->expectException(Throwable::class);
        $this->expectExceptionMessageMatches(
            '/colour/',
        );

        PackageConfiguration::container(
            ['panels' => ['cache' => ['class' => CachePanel::class, 'colour' => 'red']]],
        )->get(ExtensionRegistry::class);
    }

    protected function setUp(): void
    {
        $this->environment = getenv('APP_ENV');
        $serverEnvironment = $_SERVER['APP_ENV'] ?? null;

        $this->serverEnvironment = is_string($serverEnvironment) ? $serverEnvironment : null;

        putenv('APP_ENV=test');

        $_SERVER['APP_ENV'] = 'test';
    }

    protected function tearDown(): void
    {
        putenv($this->environment === false ? 'APP_ENV' : 'APP_ENV=' . $this->environment);

        if ($this->serverEnvironment === null) {
            unset($_SERVER['APP_ENV']);

            return;
        }

        $_SERVER['APP_ENV'] = $this->serverEnvironment;
    }

    /**
     * @return list<string> Registered collector IDs in capture order.
     */
    private static function collectorIds(ExtensionRegistry $registry): array
    {
        return array_map(
            static fn(CollectorInterface $collector): string => $collector->id(),
            $registry->collectors(),
        );
    }

    /**
     * @return list<string> Registered panel IDs in navigation order.
     */
    private static function panelIds(ExtensionRegistry $registry): array
    {
        return array_map(
            static fn(ExtensionPanelInterface $panel): string => $panel->id(),
            $registry->panels(),
        );
    }
}
