<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\ToolbarDataFactory;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};

use function sys_get_temp_dir;

use const PHP_VERSION;

/**
 * Unit tests for the minimal toolbar payload.
 */
#[Group('toolbar')]
final class ToolbarDataFactoryTest extends TestCase
{
    public function testCreateExposesOnlyYiiPhpAndAjaxMetadata(): void
    {
        $toolbarDataFactory = new ToolbarDataFactory($this->assetManager());

        $payload = $toolbarDataFactory
            ->create('request-1')
            ->jsonSerialize();

        self::assertSame(
            'request-1',
            $payload['tag'],
            'Toolbar payload must preserve the request tag.',
        );
        self::assertSame(
            '/debug',
            $payload['indexUrl'],
            'The toolbar title must link to request history.',
        );
        self::assertSame(
            '/debug/view?tag=request-1&panel=config',
            $payload['configUrl'],
            'The Yii chip must link to the live Configuration page.',
        );
        self::assertSame(
            [],
            $payload['items'],
            'No diagnostic panels must be exposed.',
        );
        self::assertSame(
            '/debug/php-info',
            $payload['phpInfoUrl'],
            'The PHP chip must link to phpinfo.',
        );
        self::assertSame(
            PHP_VERSION,
            $payload['phpVersion'],
            'Toolbar payload must expose the current PHP version.',
        );
        self::assertSame(
            '3',
            $payload['yiiVersion'],
            'Toolbar payload must expose the Yii major version.',
        );
        self::assertStringEndsWith(
            '/svg/',
            $payload['iconBaseUrl'],
            'AJAX icons must use the published SVG path.',
        );
    }

    public function testCreateForwardsToolbarPresentationSettings(): void
    {
        $toolbarDataFactory = new ToolbarDataFactory($this->assetManager());

        $payload = $toolbarDataFactory
            ->withRoutePrefix('/developer/debug/')
            ->withPresentation('top', 65)
            ->create('request-1')
            ->jsonSerialize();

        self::assertSame(
            '/developer/debug/view?tag=request-1&panel=config',
            $payload['configUrl'],
            'Configured route prefix must be applied to the Yii chip URL.',
        );
        self::assertSame(
            '/developer/debug/php-info',
            $payload['phpInfoUrl'],
            'Configured route prefix must be applied to the PHP chip URL.',
        );
        self::assertSame(
            '/developer/debug',
            $payload['indexUrl'],
            'Configured route prefix must be normalized for the history URL.',
        );
        self::assertSame(
            'top',
            $payload['position'],
            'Configured toolbar position must reach the payload.',
        );
        self::assertSame(
            65,
            $payload['defaultHeight'],
            'Configured toolbar height must reach the payload.',
        );
    }

    private function assetManager(): AssetManager
    {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-toolbar-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__) . '/vendor',
            ],
        );

        return (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));
    }
}
