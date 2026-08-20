<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Middleware;

use GuzzleHttp\Psr7\{HttpFactory, Response, ServerRequest};
use PHPForge\Debug\Collector\{CollectorCoordinator, CollectorInterface};
use PHPForge\Debug\Storage\SnapshotStore;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface, StreamInterface};
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Throwable;
use Yii3\Debug\Collector\{MailCollector, RequestCollector};
use Yii3\Debug\Middleware\ToolbarMiddleware;
use Yii3\Debug\Tests\Support\CustomCollector;
use Yii3\Debug\Web\{LocalAccessChecker, ToolbarRenderer};
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};
use Yiisoft\Mailer\Message;
use Yiisoft\View\WebView;

use function file_get_contents;
use function is_dir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Unit tests for {@see ToolbarMiddleware} persisting native collectors and injecting the toolbar safely.
 */
#[Group('toolbar')]
final class ToolbarMiddlewareTest extends TestCase
{
    private string $path = '';

    public function testProcessBypassesDebugEndpointWithoutRecursiveCapture(): void
    {
        [$middleware, $store] = $this->middleware();
        $request = new ServerRequest(
            'GET',
            'https://example.test/debug',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        );
        $response = $middleware->process(
            $request,
            $this->handler(new Response(200, ['Content-Type' => 'text/html'], '<p>History</p>')),
        );

        self::assertSame('<p>History</p>', (string) $response->getBody(), 'Debug page body must remain unchanged.');
        self::assertFalse($response->hasHeader('X-Debug-Tag'), 'Debug page must not emit recursive debug headers.');
        self::assertSame([], $store->loadManifest(), 'Debug endpoint must not create a recursive snapshot.');
    }

    public function testProcessDoesNotInjectIntoAjaxResponse(): void
    {
        [$middleware, $store] = $this->middleware();
        $request = new ServerRequest(
            'GET',
            'https://example.test/data',
            ['X-Requested-With' => 'XMLHttpRequest'],
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        );
        $response = $middleware->process(
            $request,
            $this->handler(new Response(200, ['Content-Type' => 'text/html'], '<p>Partial</p>')),
        );

        self::assertSame('<p>Partial</p>', (string) $response->getBody(), 'AJAX body must remain unchanged.');
        self::assertNotSame('', $response->getHeaderLine('X-Debug-Tag'), 'AJAX response must retain debug headers.');
        self::assertNotNull(
            $store->readSnapshot($response->getHeaderLine('X-Debug-Tag')),
            'AJAX request snapshot must still be persisted.',
        );
    }

    public function testProcessDoesNotInjectIntoBodylessOrNonHtmlResponses(): void
    {
        $cases = [
            ['GET', new Response(103, ['Content-Type' => 'text/html'], '')],
            ['HEAD', new Response(200, ['Content-Type' => 'text/html'], '<p>Head</p>')],
            ['GET', new Response(204, ['Content-Type' => 'text/html'], '')],
            ['GET', new Response(205, ['Content-Type' => 'text/html'], '')],
            ['GET', new Response(304, ['Content-Type' => 'text/html'], '')],
            ['GET', new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}')],
        ];

        foreach ($cases as [$method, $expectedResponse]) {
            [$middleware, $store] = $this->middleware();
            $request = new ServerRequest(
                $method,
                'https://example.test/data',
                serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
            );
            $response = $middleware->process($request, $this->handler($expectedResponse));

            self::assertSame(
                (string) $expectedResponse->getBody(),
                (string) $response->getBody(),
                "{$method} {$expectedResponse->getStatusCode()} body must remain unchanged.",
            );
            self::assertStringNotContainsString(
                '<yii-debug-toolbar',
                (string) $response->getBody(),
                "{$method} {$expectedResponse->getStatusCode()} response must not receive toolbar markup.",
            );
            self::assertNotSame(
                '',
                $response->getHeaderLine('X-Debug-Tag'),
                "{$method} {$expectedResponse->getStatusCode()} response must retain debug metadata.",
            );
            self::assertNotNull(
                $store->readSnapshot($response->getHeaderLine('X-Debug-Tag')),
                "{$method} {$expectedResponse->getStatusCode()} request must remain inspectable.",
            );
        }
    }

    public function testProcessDoesNotPersistRequestSecretsToJson(): void
    {
        [$middleware] = $this->middleware();
        $request = (new ServerRequest(
            method: 'POST',
            uri: 'https://example.test/login?token=query-secret',
            headers: ['Authorization' => 'Bearer header-secret', 'Content-Type' => 'application/json'],
            body: '{"password":"body-secret"}',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        ))
            ->withQueryParams(['token' => 'query-secret'])
            ->withParsedBody(['password' => 'body-secret'])
            ->withCookieParams(['session_id' => 'cookie-secret']);
        $response = $middleware->process(
            $request,
            $this->handler(new Response(200, ['Content-Type' => 'application/json'], '{}')),
        );
        $json = file_get_contents($this->path . '/' . $response->getHeaderLine('X-Debug-Tag') . '.json');

        self::assertIsString($json, 'The persisted snapshot must be readable.');
        self::assertStringNotContainsString('query-secret', $json, 'Persisted URLs must not contain query secrets.');
        self::assertStringNotContainsString('body-secret', $json, 'Persisted bodies must not contain body secrets.');
        self::assertStringNotContainsString('header-secret', $json, 'Persisted headers must not contain credentials.');
        self::assertStringNotContainsString('cookie-secret', $json, 'Persisted cookies must not contain session values.');
        self::assertStringContainsString('[redacted]', $json, 'Persisted snapshots must retain an explicit marker.');
    }

    public function testProcessInjectsToolbarIntoRedirectAndErrorHtmlResponses(): void
    {
        foreach ([302, 404, 500] as $statusCode) {
            [$middleware] = $this->middleware();
            $response = $middleware->process(
                new ServerRequest(
                    'GET',
                    'https://example.test/status',
                    serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
                ),
                $this->handler(
                    new Response(
                        $statusCode,
                        ['Content-Type' => 'text/html'],
                        "<html><body>Status {$statusCode}</body></html>",
                    ),
                ),
            );

            self::assertSame($statusCode, $response->getStatusCode(), 'Middleware must preserve response status.');
            self::assertStringContainsString(
                '<yii-debug-toolbar',
                (string) $response->getBody(),
                "HTML response {$statusCode} must remain debuggable.",
            );
        }
    }

    public function testProcessIsolatesFailingCustomCollector(): void
    {
        [$middleware, $store] = $this->middleware([new CustomCollector(failCapture: true)]);
        $response = $middleware->process(
            new ServerRequest(
                'GET',
                'https://example.test/',
                serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
            ),
            $this->handler(new Response(200, ['Content-Type' => 'application/json'], '{}')),
        );

        $snapshot = $store->readSnapshot($response->getHeaderLine('X-Debug-Tag'));

        self::assertNotNull($snapshot, 'Snapshot must remain loadable after one collector fails.');
        self::assertArrayHasKey('request', $snapshot->panels, 'Successful native collector must remain persisted.');
        self::assertArrayHasKey('app.example', $snapshot->failures, 'Custom capture failure must be persisted by ID.');
    }

    public function testProcessPersistsNativeAndCustomCollectorsAndInjectsToolbar(): void
    {
        [$middleware, $store] = $this->middleware([new CustomCollector()]);
        $request = new ServerRequest(
            'GET',
            'https://example.test/',
            serverParams: [
                'REMOTE_ADDR' => '127.0.0.1',
                'REQUEST_TIME_FLOAT' => microtime(true) - 0.01,
            ],
        );
        $response = $middleware->process(
            $request,
            $this->handler(
                new Response(
                    200,
                    ['Content-Type' => 'text/html; charset=UTF-8', 'Content-Length' => '31'],
                    '<html><body>Home</body></html>',
                ),
            ),
        );

        self::assertStringContainsString(
            '<yii-debug-toolbar',
            (string) $response->getBody(),
            'HTML response must contain the shared custom element.',
        );
        self::assertStringContainsString(
            '/debug/toolbar?tag=',
            (string) $response->getBody(),
            'Custom element must load data for the active request.',
        );
        self::assertNotSame('', $response->getHeaderLine('X-Debug-Tag'), 'Response must expose its debug tag.');
        self::assertSame(
            '/debug/view?tag=' . $response->getHeaderLine('X-Debug-Tag') . '&panel=request',
            $response->getHeaderLine('X-Debug-Link'),
            'Debug link must target the captured summary.',
        );
        self::assertFalse($response->hasHeader('Content-Length'), 'Stale body length must be removed.');

        $snapshot = $store->readSnapshot($response->getHeaderLine('X-Debug-Tag'));

        self::assertNotNull($snapshot, 'Captured request snapshot must be loadable from the shared store.');
        self::assertArrayHasKey('request', $snapshot->panels, 'Native request payload must be persisted by stable ID.');
        self::assertSame(
            ['value' => 'custom payload'],
            $snapshot->panels['app.example'] ?? null,
            'Custom collector payload must be persisted by its application ID.',
        );
    }

    public function testProcessPreservesPrimaryFailureAndReportsSecondaryCleanupFailure(): void
    {
        $collector = new CustomCollector(failShutdown: true);
        $cleanupFailure = null;
        [$middleware] = $this->middleware(
            [$collector],
            cleanupFailureHandler: static function (Throwable $throwable) use (&$cleanupFailure): void {
                $cleanupFailure = $throwable;
            },
        );
        $primary = new RuntimeException('Application failed.');
        $handler = new readonly class ($primary) implements RequestHandlerInterface {
            public function __construct(private RuntimeException $failure) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw $this->failure;
            }
        };

        try {
            $middleware->process(
                new ServerRequest(
                    'GET',
                    'https://example.test/failure',
                    serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
                ),
                $handler,
            );

            self::fail('An application failure must propagate.');
        } catch (RuntimeException $throwable) {
            self::assertSame($primary, $throwable, 'Cleanup must not replace the exact primary throwable.');
        }

        self::assertInstanceOf(RuntimeException::class, $cleanupFailure, 'Secondary cleanup failure must be observable.');
        self::assertSame(
            'Custom collector shutdown failed.',
            $cleanupFailure->getMessage(),
            'The cleanup observer must receive the secondary failure.',
        );
    }

    public function testProcessRemovesCapturedMailWhenSnapshotPersistenceFails(): void
    {
        $mailPath = $this->path . '-mail';
        $mailCollector = new MailCollector($mailPath, 0o700, 0o600);
        [$middleware] = $this->middleware([$mailCollector], historySize: -1);
        $handler = new readonly class ($mailCollector) implements RequestHandlerInterface {
            public function __construct(private MailCollector $collector) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->collector->collectMessage(new Message(textBody: 'orphan candidate'), true);

                return new Response(200, ['Content-Type' => 'application/json'], '{}');
            }
        };

        try {
            $middleware->process(
                new ServerRequest('GET', 'https://example.test/', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
                $handler,
            );

            self::fail('An invalid history limit must make snapshot persistence fail.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Invalid debug history size: -1',
                $exception->getMessage(),
                'Mail cleanup must preserve the snapshot persistence failure.',
            );
        }

        $files = glob($mailPath . '/*.eml');

        self::assertSame([], $files === false ? [] : $files, 'A failed snapshot must not leave captured mail files.');

        if (is_dir($mailPath)) {
            rmdir($mailPath);
        }
    }

    public function testProcessRetainsMailReferencedByCommittedSnapshot(): void
    {
        $mailPath = $this->path . '-mail';
        $mailCollector = new MailCollector($mailPath, 0o700, 0o600);
        [$middleware, $store] = $this->middleware([$mailCollector]);
        $handler = new readonly class ($mailCollector) implements RequestHandlerInterface {
            public function __construct(private MailCollector $collector) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->collector->collectMessage(new Message(textBody: 'retained message'), true);

                return new Response(200, ['Content-Type' => 'application/json'], '{}');
            }
        };

        $response = $middleware->process(
            new ServerRequest('GET', 'https://example.test/', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
            $handler,
        );
        $snapshot = $store->readSnapshot($response->getHeaderLine('X-Debug-Tag'));

        self::assertNotNull($snapshot, 'Mail request snapshot must be committed.');
        $file = $snapshot->summary->mailFiles[0] ?? self::fail('Snapshot must reference the captured mail file.');
        self::assertFileExists("{$mailPath}/{$file}", 'Manifest-referenced mail must survive orphan reconciliation.');

        $mailCollector->removeFiles([$file]);
        rmdir($mailPath);
    }

    public function testProcessShutsDownCollectorsWhenInitialRequestCaptureFails(): void
    {
        $collector = new CustomCollector();
        [$middleware, $store] = $this->middleware([$collector]);
        $primary = new RuntimeException('Request body failed.');
        $body = self::createStub(StreamInterface::class);

        $body->method('isSeekable')->willReturn(true);
        $body->method('__toString')->willThrowException($primary);

        try {
            $middleware->process(
                (new ServerRequest(
                    'POST',
                    'https://example.test/failure',
                    serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
                ))->withBody($body),
                $this->handler(new Response()),
            );

            self::fail('An initial request-capture failure must propagate.');
        } catch (RuntimeException $throwable) {
            self::assertSame($primary, $throwable, 'The exact request-capture failure must propagate.');
        }

        self::assertSame(1, $collector->startupCount, 'Collector must start before request capture.');
        self::assertSame(1, $collector->shutdownCount, 'Collector must shut down after request capture fails.');
        self::assertSame([], $store->loadManifest(), 'Failed request capture must not persist a partial snapshot.');
    }

    public function testProcessShutsDownCollectorsWhenTheHandlerThrows(): void
    {
        $collector = new CustomCollector();
        [$middleware, $store] = $this->middleware([$collector]);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('Application failed.');
            }
        };

        try {
            $middleware->process(
                new ServerRequest(
                    'GET',
                    'https://example.test/failure',
                    serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
                ),
                $handler,
            );

            self::fail('Unhandled application exceptions must propagate.');
        } catch (RuntimeException $exception) {
            self::assertSame('Application failed.', $exception->getMessage(), 'Original exception must propagate.');
        }

        self::assertSame(1, $collector->startupCount, 'Collector must start before request handling.');
        self::assertSame(1, $collector->shutdownCount, 'Collector must shut down after an application exception.');
        self::assertSame([], $store->loadManifest(), 'A request without a response must not persist a partial snapshot.');
    }

    public function testProcessSkipsCaptureForDeniedClients(): void
    {
        [$middleware, $store] = $this->middleware(accessChecker: new LocalAccessChecker(['127.0.0.2']));
        $response = $middleware->process(
            new ServerRequest(
                'GET',
                'https://example.test/',
                serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
            ),
            $this->handler(new Response(200, ['Content-Type' => 'text/html'], '<p>Home</p>')),
        );

        self::assertSame('<p>Home</p>', (string) $response->getBody(), 'Denied response body must remain unchanged.');
        self::assertFalse($response->hasHeader('X-Debug-Tag'), 'Denied response must not expose debug metadata.');
        self::assertSame([], $store->loadManifest(), 'Denied request must not create a debug snapshot.');
    }

    /**
     * Creates an isolated temporary storage path.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir() . '/yii3-debug-middleware-' . uniqid('', true);
    }

    /**
     * Removes temporary storage files.
     */
    protected function tearDown(): void
    {
        $files = glob($this->path . '/*');

        foreach ($files === false ? [] : $files as $file) {
            unlink($file);
        }

        if (is_dir($this->path)) {
            rmdir($this->path);
        }

        parent::tearDown();
    }

    /**
     * Creates aliases for shared views and the test asset runtime.
     *
     * @return Aliases Configured aliases.
     */
    private function aliases(): Aliases
    {
        return new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-toolbar-middleware-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
                '@yii3DebugViews' => '@vendor/php-forge/debug-core/resources/views',
            ],
        );
    }

    /**
     * Creates an asset manager that publishes into the test runtime.
     *
     * @param Aliases $aliases Alias resolver.
     *
     * @return AssetManager Configured asset manager.
     */
    private function assetManager(Aliases $aliases): AssetManager
    {
        return (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));
    }

    /**
     * Creates a request handler returning a fixed response.
     *
     * @param ResponseInterface $response Fixed response.
     *
     * @return RequestHandlerInterface Fixed response handler.
     */
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

    /**
     * Creates middleware with native collectors and shared storage.
     *
     * @param list<CollectorInterface> $collectors Additional application collectors.
     * @param (callable(Throwable): void)|null $cleanupFailureHandler Cleanup failure observer.
     *
     * @return array{ToolbarMiddleware, SnapshotStore} Configured middleware and its store.
     */
    private function middleware(
        array $collectors = [],
        LocalAccessChecker $accessChecker = new LocalAccessChecker(),
        callable|null $cleanupFailureHandler = null,
        int $historySize = 50,
    ): array {
        $aliases = $this->aliases();
        $requestCollector = new RequestCollector();
        $store = new SnapshotStore($this->path, 0o777, null);
        $middleware = new ToolbarMiddleware(
            new CollectorCoordinator([$requestCollector, ...$collectors]),
            $requestCollector,
            $store,
            new ToolbarRenderer(
                new WebView(),
                $this->assetManager($aliases),
                $aliases->get('@yii3DebugViews'),
            ),
            new HttpFactory(),
            $accessChecker,
            historySize: $historySize,
            cleanupFailureHandler: $cleanupFailureHandler,
        );

        return [$middleware, $store];
    }
}
