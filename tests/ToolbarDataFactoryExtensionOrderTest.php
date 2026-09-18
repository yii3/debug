<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\ExtensionRegistry;
use Yii3\Debug\Tests\Support\PackageConfiguration;
use Yii3\Debug\Tests\Support\Stubs\Cache\{AlphaPanel, BetaPanel, CachePanel};
use Yii3\Debug\ToolbarDataFactory;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};

use function dirname;
use function getenv;
use function is_string;
use function putenv;
use function sys_get_temp_dir;

/**
 * Unit tests for {@see ToolbarDataFactory} ordering extension chips by the positions declared in the packaged params.
 */
#[Group('toolbar')]
final class ToolbarDataFactoryExtensionOrderTest extends TestCase
{
    /**
     * Value of the `APP_ENV` environment variable before the test replaced it.
     */
    private string|false $environment = false;
    /**
     * Value of the `APP_ENV` server entry before the test replaced it, or `null` when it was absent or not a `string`.
     */
    private string|null $serverEnvironment = null;

    public function testConfiguredPositionsLeadAndRemainingExtensionsFollowByTitle(): void
    {
        $registry = PackageConfiguration::container(
            [
                'panels' => [
                    'alpha' => ['class' => AlphaPanel::class, 'position' => 2],
                    'beta' => ['class' => BetaPanel::class, 'position' => 1],
                    'cache' => CachePanel::class,
                ],
            ],
        )->get(ExtensionRegistry::class);

        self::assertInstanceOf(
            ExtensionRegistry::class,
            $registry,
            'Packaged definition must build the registry.',
        );

        $payload = (new ToolbarDataFactory(self::assetManager()))
            ->withExtensionPanels($registry->panels())
            ->createForSnapshot(self::snapshot())
            ->jsonSerialize();

        $extensions = [];

        foreach ($payload['items'] as $item) {
            if (($item['extension'] ?? false) === true) {
                $extensions[] = $item['id'];
            }
        }

        self::assertSame(
            ['beta', 'alpha', 'cache'],
            $extensions,
            'Order: positions ascending, then titles.',
        );
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

    private static function assetManager(): AssetManager
    {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-extension-order-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__) . '/vendor',
            ],
        );

        return (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));
    }

    private static function snapshot(): DebugSnapshot
    {
        return new DebugSnapshot(
            RequestSummary::create('request-1'),
            [
                'alpha' => ['value' => true],
                'beta' => ['value' => true],
                'cache' => ['operations' => [['get', 'a', 'hit']]],
            ],
            [],
        );
    }
}
