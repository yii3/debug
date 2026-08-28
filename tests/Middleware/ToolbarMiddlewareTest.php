<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Middleware;

use GuzzleHttp\Psr7\{HttpFactory, Response, ServerRequest};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use Yii3\Debug\Middleware\ToolbarMiddleware;
use Yii3\Debug\Web\ToolbarRenderer;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};
use Yiisoft\NetworkUtilities\IpRanges;
use Yiisoft\View\WebView;

use function sys_get_temp_dir;

/**
 * Unit tests for toolbar injection and AJAX response metadata.
 */
#[Group('toolbar')]
final class ToolbarMiddlewareTest extends TestCase
{
    public function testProcessAddsAjaxMetadataWithoutInjectingMarkup(): void
    {
        $request = (new ServerRequest('GET', 'https://example.test/data', serverParams: ['REMOTE_ADDR' => '127.0.0.1']))
            ->withHeader('X-Requested-With', 'XMLHttpRequest');
        $response = $this->middleware()->process(
            $request,
            $this->handler(new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}')),
        );

        self::assertNotSame('', $response->getHeaderLine('X-Debug-Tag'), 'AJAX requests must expose a tag.');
        self::assertNotSame('', $response->getHeaderLine('X-Debug-Duration'), 'AJAX requests must expose duration.');
        self::assertSame('{"ok":true}', (string) $response->getBody(), 'AJAX bodies must remain unchanged.');
        self::assertStringNotContainsString('<yii-debug-toolbar', (string) $response->getBody());
    }

    public function testProcessBypassesDebugRouteAndDeniedClients(): void
    {
        $debugRequest = new ServerRequest('GET', '/debug/toolbar', serverParams: ['REMOTE_ADDR' => '127.0.0.1']);
        $deniedRequest = new ServerRequest('GET', '/', serverParams: ['REMOTE_ADDR' => '203.0.113.10']);

        $debugResponse = $this->middleware()->process($debugRequest, $this->handler(new Response(204)));
        $deniedResponse = $this->middleware()->process($deniedRequest, $this->handler(new Response(204)));

        self::assertSame('', $debugResponse->getHeaderLine('X-Debug-Tag'));
        self::assertSame('', $deniedResponse->getHeaderLine('X-Debug-Tag'));
    }
    public function testProcessInjectsToolbarAndDebugMetadataIntoHtml(): void
    {
        $request = new ServerRequest(
            'GET',
            'https://example.test/',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1', 'REQUEST_TIME_FLOAT' => 1_700_000_000.0],
        );
        $response = $this->middleware()->process(
            $request,
            $this->handler(new Response(200, ['Content-Type' => 'text/html'], '<html><body>App</body></html>')),
        );

        self::assertNotSame('', $response->getHeaderLine('X-Debug-Tag'));
        self::assertNotSame('', $response->getHeaderLine('X-Debug-Duration'));
        self::assertSame('', $response->getHeaderLine('X-Debug-Link'), 'No debugger page must be linked.');
        self::assertStringContainsString('<yii-debug-toolbar', (string) $response->getBody());
        self::assertStringContainsString('/debug/toolbar?tag=', (string) $response->getBody());
    }

    private function handler(ResponseInterface $response): RequestHandlerInterface
    {
        return new readonly class ($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }

    private function middleware(): ToolbarMiddleware
    {
        $factory = new HttpFactory();
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-middleware-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
            ],
        );
        $assets = (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));

        return new ToolbarMiddleware(
            new ToolbarRenderer(
                new WebView(),
                $assets,
                $aliases->get('@vendor/php-forge/debug-core/resources/views'),
            ),
            $factory,
            new IpRanges(['127.0.0.1', '::1']),
        );
    }
}
