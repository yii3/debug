<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support;

use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\Panel\Db\QueryRow;
use PHPForge\Debug\Storage\SnapshotStore;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Yii3\Debug\Action\ToolbarDataAction;
use Yii3\Debug\Capture\DeferredCapture;
use Yii3\Debug\Collector\DbCollector;
use Yii3\Debug\Middleware\{DebugRouteMiddleware, RequestCaptureMiddleware, ToolbarOptions};
use Yii3\Debug\Tests\Support\Stubs\{ContainerStub, DebugActionStub};
use Yii3\Debug\Web\{DebugRequestHandler, ToolbarRenderer};
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};
use Yiisoft\NetworkUtilities\IpRanges;
use Yiisoft\View\WebView;

use function array_reverse;
use function dirname;
use function file_put_contents;
use function sys_get_temp_dir;
use function uniqid;

/**
 * Builds the debugger middlewares and the stores, handlers, and capture holders their tests share.
 */
final class MiddlewareFactory
{
    /**
     * Client ranges both middlewares allow, matching the packaged `allowedIPs` default.
     */
    private const array ALLOWED_IPS = ['127.0.0.1', '::1'];

    /**
     * Builds the debug-route middleware serving the `toolbar` endpoint through a stub action.
     *
     * @param DeferredCapture|null $deferredCapture Holder of a pending capture, or `null` to finalize nothing.
     * @param ToolbarOptions $options Settings carrying the route prefix.
     */
    public static function debugRoute(
        DeferredCapture|null $deferredCapture = null,
        ToolbarOptions $options = new ToolbarOptions(),
    ): DebugRouteMiddleware {
        return new DebugRouteMiddleware(
            new IpRanges(self::ALLOWED_IPS),
            new DebugRequestHandler(
                new ContainerStub([ToolbarDataAction::class => new DebugActionStub('toolbar')]),
                HelperFactory::createResponseFactory(),
                $options,
            ),
            $deferredCapture,
            $options,
        );
    }

    /**
     * Builds a capture holder whose PHP shutdown fallback is replaced by a no-op.
     */
    public static function deferredCapture(): DeferredCapture
    {
        return new DeferredCapture(static function (callable $finalize): void {});
    }

    /**
     * Builds a handler that always answers with the given response.
     */
    public static function handler(ResponseInterface $response): RequestHandlerInterface
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
     * Chains middlewares in registration order in front of a final handler, the way the middleware dispatcher does.
     *
     * @param list<MiddlewareInterface> $middlewares Middlewares in registration order.
     * @param RequestHandlerInterface $handler Handler reached once every middleware delegated.
     */
    public static function pipeline(array $middlewares, RequestHandlerInterface $handler): RequestHandlerInterface
    {
        foreach (array_reverse($middlewares) as $middleware) {
            $handler = new readonly class ($middleware, $handler) implements RequestHandlerInterface {
                public function __construct(
                    private MiddlewareInterface $middleware,
                    private RequestHandlerInterface $next,
                ) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->middleware->process($request, $this->next);
                }
            };
        }

        return $handler;
    }

    /**
     * Builds a handler observing one statement before answering `204 No Content`.
     */
    public static function queryingHandler(DbCollector $collector): RequestHandlerInterface
    {
        return new readonly class ($collector) implements RequestHandlerInterface {
            public function __construct(private DbCollector $collector) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->collector->observe(QueryRow::create('SELECT 1', 1.0, 1000.0));

                return HelperFactory::createResponse(204);
            }
        };
    }

    /**
     * Builds the capture middleware with the packaged renderer, stream factory, and default capture policy.
     *
     * @param SnapshotStore $store Store the capture is written to.
     * @param CollectorCoordinator|null $collectorCoordinator Coordinator owning the collectors, or `null` to capture
     * only the summary.
     * @param DeferredCapture|null $deferredCapture Holder of the pending finalizer, or `null` to finalize inside the
     * pipeline.
     * @param ToolbarOptions $options Settings carrying the route prefix, history size, threshold, and presentation.
     */
    public static function requestCapture(
        SnapshotStore $store,
        CollectorCoordinator|null $collectorCoordinator = null,
        DeferredCapture|null $deferredCapture = null,
        ToolbarOptions $options = new ToolbarOptions(),
    ): RequestCaptureMiddleware {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-middleware-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
            ],
        );
        $assets = (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));

        return new RequestCaptureMiddleware(
            new ToolbarRenderer(
                new WebView(),
                $assets,
                $aliases->get('@vendor/php-forge/debug-core/resources/views'),
            ),
            HelperFactory::createStreamFactory(),
            $store,
            new IpRanges(self::ALLOWED_IPS),
            $options,
            new CapturePolicy(),
            $collectorCoordinator,
            $deferredCapture,
        );
    }

    /**
     * Builds a store in a fresh temporary directory.
     */
    public static function store(): SnapshotStore
    {
        return new SnapshotStore(sys_get_temp_dir() . '/yii3-debug-middleware-' . uniqid(), 0o700, 0o600);
    }

    /**
     * Builds a store whose directory cannot be created, because a regular file holds its parent path.
     */
    public static function unwritableStore(): SnapshotStore
    {
        $path = sys_get_temp_dir() . '/yii3-debug-middleware-' . uniqid();

        file_put_contents($path, '');

        return new SnapshotStore($path . '/snapshots', 0o700, 0o600);
    }
}
