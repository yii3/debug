<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Web;

use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary};
use PHPUnit\Framework\TestCase;
use Yii3\Debug\ConfigDataFactory;
use Yii3\Debug\ExtensionRegistry;
use Yii3\Debug\Tests\Support\PackageConfiguration;
use Yii3\Debug\Tests\Support\Stubs\Cache\{AlphaPanel, BetaPanel, CachePanel};
use Yii3\Debug\Web\DebugPageRenderer;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};
use Yiisoft\View\WebView;

use function dirname;
use function getenv;
use function is_string;
use function preg_match_all;
use function putenv;
use function strpos;
use function substr;
use function sys_get_temp_dir;

/**
 * Integration tests for {@see DebugPageRenderer} ordering the sidebar `Extensions` group by configured positions.
 */
final class DebugPageRendererExtensionOrderTest extends TestCase
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

        self::assertInstanceOf(ExtensionRegistry::class, $registry, 'Packaged definition must build the registry.');

        $summary = RequestSummary::create('request-1')
            ->withRequest('https://example.test/', 'GET', '127.0.0.1', 1_725_000_756.0)
            ->withResponse(200)
            ->withProfiling(0.009, 1_145_324);

        $snapshot = new DebugSnapshot(
            $summary,
            [
                'alpha' => ['value' => true],
                'beta' => ['value' => true],
                'cache' => ['operations' => [['get', 'a', 'hit']]],
            ],
            [],
        );

        $html = self::renderer()
            ->withExtensionPanels($registry->panels())
            ->config('request-1', 'light', ['request-1' => $summary], $snapshot);

        self::assertSame(
            ['Beta', 'Alpha', 'Cache'],
            self::extensionLabels($html),
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

    /**
     * Extracts the sidebar labels listed under the `Extensions` group, in display order.
     *
     * @param string $html Rendered configuration page.
     *
     * @return list<string> Extension panel labels in display order.
     */
    private static function extensionLabels(string $html): array
    {
        $position = strpos($html, 'aria-label="Extensions debug panels"');

        if ($position === false) {
            return [];
        }

        preg_match_all('/title="View ([^"]+) panel"/', substr($html, $position), $matches);

        return $matches[1];
    }

    private static function renderer(): DebugPageRenderer
    {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-page-renderer-extension-order-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
            ],
        );
        $assetManager = (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(
                (new AssetPublisher($aliases))->withHashCallback(static fn(string $path): string => 'test'),
            );

        return new DebugPageRenderer(
            new WebView(),
            $assetManager,
            new ConfigDataFactory(['name' => 'Test application']),
            $aliases->get('@vendor/php-forge/debug-core/resources/views'),
        );
    }
}
