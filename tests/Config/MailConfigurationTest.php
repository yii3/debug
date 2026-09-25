<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Config;

use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Collector\MailCollector;
use Yii3\Debug\Mail\MailFileStore;
use Yii3\Debug\Tests\Support\{PackageConfiguration, TemporaryDirectory};
use Yiisoft\Aliases\Aliases;
use Yiisoft\Di\Container;

use function array_keys;
use function file_get_contents;

/**
 * Unit tests for the packaged Mail wiring: the storage path parameter, the file store definition, and the built-in
 * collector order.
 */
final class MailConfigurationTest extends TestCase
{
    private string $root = '';

    public function testCollectorCoordinatorPlacesMailBetweenDatabaseAndAssets(): void
    {
        $coordinator = $this->container()->get(CollectorCoordinator::class);

        self::assertInstanceOf(
            CollectorCoordinator::class,
            $coordinator,
            'Coordinator must be packaged.',
        );
        self::assertSame(
            ['request', 'log', 'event', 'profiling', 'db', 'mail', 'asset'],
            array_keys($coordinator->collectors()),
            'Mail must follow the built-in panel order.',
        );
        self::assertInstanceOf(
            MailCollector::class,
            $coordinator->collector('mail'),
            'Mail collector must be built in.',
        );
    }

    public function testMailFileStoreWritesUnderTheResolvedMailPath(): void
    {
        $debug = PackageConfiguration::params()['yii3/debug'] ?? null;

        self::assertIsArray(
            $debug,
            'Package parameters must be declared.',
        );

        $storage = $debug['storage'] ?? null;

        self::assertIsArray(
            $storage,
            'Storage parameters must be declared.',
        );
        self::assertSame(
            '@runtime/debug/mail',
            $storage['mailPath'] ?? null,
            'Default path must match the Yii2 collector.',
        );

        $store = $this->container()->get(MailFileStore::class);

        self::assertInstanceOf(
            MailFileStore::class,
            $store,
            'File store must be packaged.',
        );

        $file = $store->write('Subject: packaged');

        self::assertSame(
            'Subject: packaged',
            file_get_contents("{$this->root}/debug/mail/{$file}"),
            'Alias must be resolved before writing.',
        );
    }

    protected function setUp(): void
    {
        $this->root = TemporaryDirectory::create('yii3-debug-mail-config-');
    }

    protected function tearDown(): void
    {
        TemporaryDirectory::remove($this->root);
    }

    private function container(): Container
    {
        $params = PackageConfiguration::params();

        return PackageConfiguration::container(
            definitions: [
                ...PackageConfiguration::serviceDefinitions('collectors', $params),
                ...PackageConfiguration::serviceDefinitions('storage', $params),
                Aliases::class => new Aliases(['@runtime' => $this->root]),
            ],
        );
    }
}
