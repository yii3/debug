<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Action;

use GuzzleHttp\Psr7\{HttpFactory, ServerRequest};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Action\ToolbarDataAction;
use Yii3\Debug\ToolbarDataFactory;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};

use function sys_get_temp_dir;

/**
 * Unit tests for the minimal toolbar-data endpoint.
 */
#[Group('toolbar')]
final class ToolbarDataActionTest extends TestCase
{
    public function testInvokeRejectsMissingOrMalformedTags(): void
    {
        $action = $this->action();

        foreach ([[], ['tag' => ['request-1']]] as $query) {
            $request = (new ServerRequest('GET', '/debug/toolbar'))->withQueryParams($query);

            $response = $action($request);

            $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame(
                400,
                $response->getStatusCode(),
                'Invalid toolbar tag must fail safely.',
            );
            self::assertIsArray(
                $payload,
                'Error payload must decode to an array.',
            );
            self::assertSame(
                'A debug request tag is required.',
                $payload['error'] ?? null,
                'Invalid toolbar requests must explain that a request tag is required.',
            );
        }
    }

    public function testInvokeReturnsStaticToolbarDataForAnyValidTag(): void
    {
        $request = (new ServerRequest('GET', '/debug/toolbar?tag=request-1'))
            ->withQueryParams(['tag' => 'request-1']);

        $response = ($this->action())($request);

        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(
            200,
            $response->getStatusCode(),
            'Valid toolbar requests must succeed.',
        );
        self::assertSame(
            'application/json; charset=UTF-8',
            $response->getHeaderLine('Content-Type'),
            'Toolbar responses must use the JSON content type.',
        );
        self::assertIsArray(
            $payload,
            'Toolbar payload must decode to an array.',
        );
        self::assertSame(
            'request-1',
            $payload['tag'] ?? null,
            'Toolbar payload must preserve the request tag.',
        );
        self::assertSame(
            '/debug',
            $payload['indexUrl'] ?? null,
            'Toolbar title must link to request history.',
        );
        self::assertSame(
            '/debug/view?tag=request-1&panel=config',
            $payload['configUrl'] ?? null,
            'Yii chip must link to the request configuration page.',
        );
        self::assertSame(
            '/debug/php-info',
            $payload['phpInfoUrl'] ?? null,
            'PHP chip must link to the PHP information page.',
        );
        self::assertSame(
            [],
            $payload['items'] ?? null,
            'No diagnostic panels must be returned.',
        );
    }

    private function action(): ToolbarDataAction
    {
        $factory = new HttpFactory();

        return new ToolbarDataAction(
            new ToolbarDataFactory($this->assetManager()),
            $factory,
            $factory,
        );
    }

    private function assetManager(): AssetManager
    {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-toolbar-action-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
            ],
        );

        return (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));
    }
}
