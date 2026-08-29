<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Middleware;

use GuzzleHttp\Psr7\{HttpFactory, Response, ServerRequest};
use PHPForge\Debug\Storage\SnapshotStore;
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
        $store = $this->store();

        $request = (new ServerRequest('GET', 'https://example.test/data', serverParams: ['REMOTE_ADDR' => '127.0.0.1']))
            ->withHeader('X-Requested-With', 'XMLHttpRequest');

        $response = $this->middleware($store)
            ->process(
                $request,
                $this->handler(new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}')),
            );

        self::assertNotSame(
            '',
            $response->getHeaderLine('X-Debug-Tag'),
            'AJAX requests must expose a tag.',
        );
        self::assertNotSame(
            '',
            $response->getHeaderLine('X-Debug-Duration'),
            'AJAX requests must expose duration.',
        );
        self::assertSame(
            '{"ok":true}',
            (string) $response->getBody(),
            'AJAX bodies must remain unchanged.',
        );
        self::assertStringNotContainsString(
            '<yii-debug-toolbar',
            (string) $response->getBody(),
            'AJAX responses must not receive toolbar markup.',
        );

        $manifest = $store->loadManifest();

        self::assertCount(
            1,
            $manifest,
            'AJAX requests must be available in history.',
        );

        $summary = array_values($manifest)[0];

        self::assertTrue(
            $summary->ajax,
            'Captured AJAX requests must retain their request type.',
        );
        self::assertSame(
            'https://example.test/data',
            $summary->url,
            'Captured AJAX requests must retain their URL.',
        );
    }

    public function testProcessBypassesDebugRouteAndDeniedClients(): void
    {
        $store = $this->store();

        $debugRequest = new ServerRequest('GET', '/debug/toolbar', serverParams: ['REMOTE_ADDR' => '127.0.0.1']);
        $deniedRequest = new ServerRequest('GET', '/', serverParams: ['REMOTE_ADDR' => '203.0.113.10']);

        $middleware = $this->middleware($store);

        $debugResponse = $middleware->process(
            $debugRequest,
            $this->handler(new Response(204)),
        );
        $deniedResponse = $middleware->process(
            $deniedRequest,
            $this->handler(new Response(204)),
        );

        self::assertSame(
            '',
            $debugResponse->getHeaderLine('X-Debug-Tag'),
            'Debugger routes must bypass request capture.',
        );
        self::assertSame(
            '',
            $deniedResponse->getHeaderLine('X-Debug-Tag'),
            'Denied clients must not receive a debug tag.',
        );
        self::assertSame(
            [],
            $store->loadManifest(),
            'Bypassed requests must not be captured.',
        );
    }

    public function testProcessInjectsToolbarAndDebugMetadataIntoHtml(): void
    {
        $store = $this->store();

        $request = new ServerRequest(
            'GET',
            'https://example.test/',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1', 'REQUEST_TIME_FLOAT' => 1_700_000_000.0],
        );

        $response = $this->middleware($store)->process(
            $request,
            $this->handler(new Response(200, ['Content-Type' => 'text/html'], '<html><body>App</body></html>')),
        );

        self::assertNotSame(
            '',
            $response->getHeaderLine('X-Debug-Tag'),
            'HTML requests must expose a debug tag.',
        );
        self::assertNotSame(
            '',
            $response->getHeaderLine('X-Debug-Duration'),
            'HTML requests must expose their processing duration.',
        );
        self::assertSame(
            '',
            $response->getHeaderLine('X-Debug-Link'),
            'No debugger page must be linked.',
        );
        self::assertStringContainsString(
            '<yii-debug-toolbar',
            (string) $response->getBody(),
            'HTML responses must receive toolbar markup.',
        );
        self::assertStringContainsString(
            '/debug/toolbar?tag=',
            (string) $response->getBody(),
            'Injected toolbar markup must reference its data endpoint.',
        );

        $manifest = $store->loadManifest();

        self::assertCount(
            1,
            $manifest,
            'HTML requests must be available in history.',
        );

        $summary = array_values($manifest)[0];

        self::assertSame(
            $response->getHeaderLine('X-Debug-Tag'),
            $summary->tag,
            'Stored summary tag must match the response metadata.',
        );
        self::assertSame(
            'GET',
            $summary->method,
            'Stored summary must retain the request method.',
        );
        self::assertSame(
            'https://example.test/',
            $summary->url,
            'Stored summary must retain the request URL.',
        );
        self::assertSame(
            200,
            $summary->statusCode,
            'Stored summary must retain the response status code.',
        );
        self::assertSame(
            '127.0.0.1',
            $summary->ip,
            'Stored summary must retain the client IP address.',
        );
        self::assertFalse(
            $summary->ajax,
            'Regular HTML requests must not be marked as AJAX.',
        );
        self::assertNotNull(
            $summary->processingTime,
            'Stored summary must include the processing duration.',
        );
        self::assertNotNull(
            $summary->peakMemory,
            'Stored summary must include peak memory usage.',
        );
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

    private function middleware(SnapshotStore $store): ToolbarMiddleware
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
            $store,
            new IpRanges(['127.0.0.1', '::1']),
        );
    }

    private function store(): SnapshotStore
    {
        return new SnapshotStore(
            sys_get_temp_dir() . '/yii3-debug-middleware-' . uniqid(),
            0o700,
            0o600,
        );
    }
}
