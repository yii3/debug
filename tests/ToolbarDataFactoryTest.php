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
        $payload = (new ToolbarDataFactory($this->assetManager()))
            ->create('request-1')
            ->jsonSerialize();

        self::assertSame('request-1', $payload['tag']);
        self::assertSame('', $payload['indexUrl'], 'No debugger page must be linked.');
        self::assertSame('', $payload['configUrl'], 'The Yii chip must remain static.');
        self::assertSame([], $payload['items'], 'No diagnostic panels must be exposed.');
        self::assertNull($payload['phpInfoUrl'], 'The PHP chip must remain static.');
        self::assertSame(PHP_VERSION, $payload['phpVersion']);
        self::assertSame('3', $payload['yiiVersion']);
        self::assertStringEndsWith('/svg/', $payload['iconBaseUrl'], 'AJAX icons must use the published SVG path.');
    }

    public function testCreateForwardsToolbarPresentationSettings(): void
    {
        $payload = (new ToolbarDataFactory($this->assetManager(), 'top', 65))
            ->create('request-1')
            ->jsonSerialize();

        self::assertSame('top', $payload['position']);
        self::assertSame(65, $payload['defaultHeight']);
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
