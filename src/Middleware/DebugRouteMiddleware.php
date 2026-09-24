<?php

declare(strict_types=1);

namespace Yii3\Debug\Middleware;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Throwable;
use Yii3\Debug\Capture\DeferredCapture;
use Yii3\Debug\Web\DebugRequestHandler;
use Yiisoft\NetworkUtilities\{IpHelper, IpRanges};

use function is_string;

/**
 * Serves the debugger endpoints under the configured route prefix and passes every other request through.
 *
 * Register it before {@see RequestCaptureMiddleware}, so a debugger page is answered before the application pipeline
 * runs.
 */
final readonly class DebugRouteMiddleware implements MiddlewareInterface
{
    /**
     * @param IpRanges $allowedIpRanges Ranges allowed to reach the debugger.
     * @param DebugRequestHandler $debugRequestHandler Handler serving the debugger endpoints.
     * @param DeferredCapture|null $deferredCapture Holder of a capture still pending from an earlier request, or
     * `null` when captures are finalized inside the pipeline.
     * @param ToolbarOptions $options Settings carrying the route prefix.
     */
    public function __construct(
        private IpRanges $allowedIpRanges,
        private DebugRequestHandler $debugRequestHandler,
        private DeferredCapture|null $deferredCapture,
        private ToolbarOptions $options,
    ) {}

    /**
     * Serves a request targeting the debugger itself, and hands every other request to the next handler untouched.
     *
     * A capture still pending from an earlier request is written before anything else, debugger pages and requests
     * from a disallowed address included: a worker that never dispatched the shutdown event stops the collectors
     * before serving them, so their activity stays out of that capture, and the capture reaches history at once. A
     * failing finalization propagates from the request that triggered it. {@see RequestCaptureMiddleware} applies the
     * same rule, so the guarantee holds whichever of the two runs first.
     *
     * @param ServerRequestInterface $request Request reaching the middleware.
     * @param RequestHandlerInterface $handler Next handler in the middleware stack.
     *
     * @throws Throwable when the pending capture, the debugger endpoint, or the request handler fails.
     *
     * @return ResponseInterface Debugger response, an empty `403 Forbidden` for a disallowed client, or the
     * application response for any other path.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->deferredCapture?->finalize();

        if ($this->options->isDebugPath($request->getUri()->getPath()) === false) {
            return $handler->handle($request);
        }

        return $this->isAllowed($request)
            ? $this->debugRequestHandler->handle($request)
            : $this->debugRequestHandler->forbidden();
    }

    /**
     * Returns whether the client address may reach the debugger.
     *
     * @param ServerRequestInterface $request Request reaching the middleware.
     *
     * @return bool `true` when the client address falls in an allowed range; `false` otherwise.
     */
    private function isAllowed(ServerRequestInterface $request): bool
    {
        $clientIp = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($clientIp)
            && IpHelper::isIp($clientIp)
            && $this->allowedIpRanges->isAllowed($clientIp);
    }
}
