<?php

declare(strict_types=1);

namespace Yii3\Debug\Middleware;

use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\Instrumentation\InstrumentationGuard;
use PHPForge\Debug\Panel\Db\{DbSnapshot, DbSummary};
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary, SnapshotStore};
use PHPForge\Debug\Toolbar\DebugHeader;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface, StreamFactoryInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Throwable;
use Yii3\Debug\Capture\DeferredCapture;
use Yii3\Debug\Collector\{ProfilingCollector, RequestObserverInterface};
use Yii3\Debug\Web\{DebugRequestHandler, ToolbarRenderer};
use Yiisoft\NetworkUtilities\{IpHelper, IpRanges};

use function is_float;
use function is_int;
use function is_string;
use function memory_get_peak_usage;
use function microtime;
use function number_format;
use function rawurlencode;
use function rtrim;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function strtoupper;
use function uniqid;

/**
 * Captures debug snapshots and injects the toolbar into eligible HTML responses.
 */
final class ToolbarMiddleware implements MiddlewareInterface
{
    /**
     * Defines which request data the debugger may capture.
     */
    private CapturePolicy $capturePolicy;
    /**
     * Coordinates collectors while processing a request.
     */
    private CollectorCoordinator|null $collectorCoordinator = null;
    /**
     * Serves the debugger endpoints, or `null` to let the application handle the debugger paths.
     */
    private DebugRequestHandler|null $debugRequestHandler = null;
    /**
     * Holds the capture finalizer until the application shuts down, or `null` to finalize inside the pipeline.
     */
    private DeferredCapture|null $deferredCapture = null;
    /**
     * Defines the statements per call site that flag it as excessive, or `null` to disable the check.
     */
    private int|null $excessiveCallerThreshold = null;
    /**
     * Defines the toolbar height in pixels.
     */
    private int $height = 50;
    /**
     * Defines the number of snapshots retained in history.
     */
    private int $historySize = 50;
    /**
     * Defines the toolbar position within the page.
     */
    private string $position = 'bottom';
    /**
     * Defines the route prefix used by the debugger endpoints.
     */
    private string $routePrefix = '/debug';
    /**
     * Stores same-origin URLs excluded from AJAX tracking.
     *
     * @var list<string>
     */
    private array $skipUrls = [];

    /**
     * @param ToolbarRenderer $renderer Renderer producing the toolbar markup injected into the response.
     * @param StreamFactoryInterface $streamFactory Factory building the rewritten response body.
     * @param SnapshotStore $store Store the capture is written to.
     * @param IpRanges $allowedIpRanges Ranges allowed to reach the debugger.
     */
    public function __construct(
        private readonly ToolbarRenderer $renderer,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly SnapshotStore $store,
        private readonly IpRanges $allowedIpRanges,
    ) {
        $this->capturePolicy = new CapturePolicy();
    }

    /**
     * Captures the request, then injects the toolbar into an eligible HTML response.
     *
     * A request targeting the debugger itself is served by the configured debug request handler, and rejected with
     * `403 Forbidden` when the client address is not allowed. Requests from a disallowed address pass through
     * untouched.
     *
     * @param ServerRequestInterface $request Request reaching the middleware.
     * @param RequestHandlerInterface $handler Next handler in the middleware stack.
     *
     * @throws Throwable when the request handler fails.
     *
     * @return ResponseInterface Response, with the toolbar injected when the request qualifies.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->isDebugRequest($request)) {
            return $this->serveDebugRequest($request, $handler);
        }

        if (!$this->isAllowed($request)) {
            return $handler->handle($request);
        }

        $collectorCoordinator = $this->collectorCoordinator;

        if ($collectorCoordinator === null) {
            return $this->captureRequest($request, $handler);
        }

        $deferredCapture = $this->deferredCapture;

        if ($deferredCapture !== null) {
            return $this->deferCapture($request, $handler, $collectorCoordinator, $deferredCapture);
        }

        return $collectorCoordinator->run(
            fn(): ResponseInterface => $this->captureRequest($request, $handler),
        );
    }

    /**
     * Returns a copy applying another capture policy.
     *
     * @param CapturePolicy $capturePolicy Policy deciding which values are persisted and which are
     * redacted.
     *
     * @return self Middleware with the policy applied.
     */
    public function withCapturePolicy(CapturePolicy $capturePolicy): self
    {
        $new = clone $this;
        $new->capturePolicy = $capturePolicy;

        return $new;
    }

    /**
     * Returns a copy driving another set of collectors.
     *
     * @param CollectorCoordinator|null $collectorCoordinator Coordinator owning the collectors, or `null`
     * to capture nothing.
     *
     * @return self Middleware with the coordinator applied.
     */
    public function withCollectorCoordinator(CollectorCoordinator|null $collectorCoordinator): self
    {
        $new = clone $this;
        $new->collectorCoordinator = $collectorCoordinator;

        return $new;
    }

    /**
     * Returns a copy serving the debugger endpoints itself instead of letting the application handle them.
     *
     * @param DebugRequestHandler|null $debugRequestHandler Handler serving the debugger endpoints, or `null` to pass
     * the debugger paths through to the application.
     *
     * @return self Middleware with the handler applied.
     */
    public function withDebugRequestHandler(DebugRequestHandler|null $debugRequestHandler): self
    {
        $new = clone $this;
        $new->debugRequestHandler = $debugRequestHandler;

        return $new;
    }

    /**
     * Returns a copy finalizing the capture at application shutdown instead of inside the middleware pipeline.
     *
     * Deferral keeps the work that follows the pipeline in the snapshot: a lazily rendered response body, the log and
     * profiler flushes, and the events dispatched around emission. It requires a coordinator; without one the capture
     * is finalized immediately.
     *
     * @param DeferredCapture|null $deferredCapture Holder of the pending finalizer, or `null` to finalize inside the
     * pipeline.
     *
     * @return self Middleware with the deferral applied.
     */
    public function withDeferredCapture(DeferredCapture|null $deferredCapture): self
    {
        $new = clone $this;
        $new->deferredCapture = $deferredCapture;

        return $new;
    }

    /**
     * Returns a new instance with the threshold that flags a call site as issuing too many statements.
     *
     * @param int|null $excessiveCallerThreshold Statements per call site that flag it, or `null` to disable.
     *
     * @return self Middleware with the threshold applied.
     */
    public function withExcessiveCallerThreshold(int|null $excessiveCallerThreshold): self
    {
        $new = clone $this;
        $new->excessiveCallerThreshold = $excessiveCallerThreshold;

        return $new;
    }

    /**
     * Returns a copy retaining another number of captures.
     *
     * @param int $historySize Captures kept before the oldest are rotated out.
     *
     * @return self Middleware with the history size applied.
     */
    public function withHistorySize(int $historySize): self
    {
        $new = clone $this;
        $new->historySize = $historySize;

        return $new;
    }

    /**
     * Returns a copy carrying the drawer presentation settings.
     *
     * @param string $position Edge the toolbar docks to.
     * @param int $height Collapsed toolbar height, in pixels.
     *
     * @return self Middleware with the presentation applied.
     */
    public function withPresentation(string $position, int $height): self
    {
        $new = clone $this;
        $new->position = $position;
        $new->height = $height;

        return $new;
    }

    /**
     * Returns a copy building every debugger URL on another base path.
     *
     * @param string $routePrefix Base path; a trailing slash is trimmed.
     *
     * @return self Middleware with the prefix applied.
     */
    public function withRoutePrefix(string $routePrefix): self
    {
        $new = clone $this;
        $new->routePrefix = rtrim($routePrefix, '/');

        return $new;
    }

    /**
     * Returns a copy excluding more same-origin URLs from AJAX tracking.
     *
     * @param list<string> $skipUrls Same-origin URLs excluded from AJAX tracking.
     *
     * @return self Middleware with the exclusions applied.
     */
    public function withSkipUrls(array $skipUrls): self
    {
        $new = clone $this;
        $new->skipUrls = $skipUrls;

        return $new;
    }

    /**
     * Runs the request with the collectors active and writes the resulting capture before returning.
     *
     * @param ServerRequestInterface $request Request reaching the middleware.
     * @param RequestHandlerInterface $handler Next handler in the middleware stack.
     *
     * @throws Throwable when the request handler fails.
     *
     * @return ResponseInterface Response produced by the handler.
     */
    private function captureRequest(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $start = self::requestStart($request);

        [$response, $summary] = $this->handleRequest($request, $handler, $start);

        $this->finalizeCapture($summary);

        return $response;
    }

    /**
     * Reads the remote address reported for the request.
     *
     * @param ServerRequestInterface $request Request reaching the middleware.
     *
     * @return string Remote address of the request, or `''` when the server did not report one.
     */
    private static function clientIp(ServerRequestInterface $request): string
    {
        $clientIp = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($clientIp) && IpHelper::isIp($clientIp) ? $clientIp : '';
    }

    /**
     * Runs the request with the collectors active and hands the capture over to the application shutdown phase.
     *
     * A handler failure drops the pending capture and stops the collectors, keeping the application failure primary.
     *
     * @param ServerRequestInterface $request Request reaching the middleware.
     * @param RequestHandlerInterface $handler Next handler in the middleware stack.
     * @param CollectorCoordinator $collectorCoordinator Coordinator owning the collectors.
     * @param DeferredCapture $deferredCapture Holder of the pending finalizer.
     *
     * @throws Throwable when startup or the request handler fails.
     *
     * @return ResponseInterface Response produced by the handler.
     */
    private function deferCapture(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
        CollectorCoordinator $collectorCoordinator,
        DeferredCapture $deferredCapture,
    ): ResponseInterface {
        $collectorCoordinator->startup();

        $start = self::requestStart($request);

        try {
            [$response, $summary] = $this->handleRequest($request, $handler, $start);
        } catch (Throwable $primaryFailure) {
            $deferredCapture->cancel();

            (new InstrumentationGuard())->observe($collectorCoordinator->shutdown(...));

            throw $primaryFailure;
        }

        $deferredCapture->defer(
            function () use ($collectorCoordinator, $start, $summary): void {
                $this->finalizeCapture(
                    $summary->withProfiling(microtime(true) - $start, memory_get_peak_usage(true)),
                );

                (new InstrumentationGuard())->observe($collectorCoordinator->shutdown(...));
            },
        );

        return $response;
    }

    /**
     * Captures every collector into the summary and writes the snapshot to the store.
     *
     * @param RequestSummary $summary Metadata the capture is written for.
     */
    private function finalizeCapture(RequestSummary $summary): void
    {
        $snapshot = $this->collectorCoordinator?->capture($summary) ?? new DebugSnapshot($summary, [], []);

        if (isset($snapshot->panels['db'])) {
            $database = new DbSummary(DbSnapshot::fromArray($snapshot->panels['db'], '$.panels.db')->entries());
            $snapshot = new DebugSnapshot(
                $snapshot->summary->withDatabase(
                    $database->count,
                    $database->excessiveCallerCount($this->excessiveCallerThreshold),
                ),
                $snapshot->panels,
                $snapshot->failures,
            );
        }

        $this->store->writeSnapshot($snapshot, $this->historySize);
    }

    /**
     * Runs the request phase, returning the final response and the summary its capture is finalized from.
     *
     * The toolbar is injected here, so a lazily rendered body is materialized before any collector is read.
     *
     * @param ServerRequestInterface $request Request reaching the middleware.
     * @param RequestHandlerInterface $handler Next handler in the middleware stack.
     * @param float $start Request start, in seconds.
     *
     * @throws Throwable when the request handler fails.
     *
     * @return array{ResponseInterface, RequestSummary} Response returned to the client, and its summary carrying the
     * handler timing.
     */
    private function handleRequest(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
        float $start,
    ): array {
        $tag = str_replace('.', '', uniqid('', true));

        $profilingCollector = $this->collectorCoordinator?->collector('profiling');
        $requestCollector = $this->collectorCoordinator?->collector('request');

        if ($profilingCollector instanceof ProfilingCollector) {
            $profilingCollector->collectRequestStart($start);
        }

        $observers = [];
        foreach ($this->collectorCoordinator?->collectors() ?? [] as $collector) {
            if ($collector instanceof RequestObserverInterface) {
                $observers[] = $collector;
                $collector->collectRequest($request);
            }
        }

        $response = $handler->handle($request);

        $processingTime = microtime(true) - $start;

        $response = $response
            ->withHeader(DebugHeader::TAG->value, $tag)
            ->withHeader(
                DebugHeader::DURATION->value,
                number_format($processingTime * 1000, 0, '.', ''),
            )
            ->withHeader(
                DebugHeader::LINK->value,
                "{$this->routePrefix}/view?tag="
                    . rawurlencode($tag)
                    . '&panel=' . ($requestCollector === null ? 'config' : 'request'),
            );

        $injectToolbar = $this->shouldInject($request, $response);

        if ($injectToolbar) {
            $response = $response->withoutHeader('Content-Length');
        }

        foreach ($observers as $observer) {
            $observer->collectResponse($response);
        }

        $summary = RequestSummary::create($tag)
            ->withRequest(
                url: $this->capturePolicy->redactUrl((string) $request->getUri()),
                method: strtoupper($request->getMethod()),
                ip: self::clientIp($request),
                time: $start,
                ajax: strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest',
            )
            ->withResponse($response->getStatusCode())
            ->withProfiling($processingTime, memory_get_peak_usage(true));

        if ($injectToolbar) {
            $toolbar = $this->renderer->render(
                dataUrl: "{$this->routePrefix}/toolbar?tag=" . rawurlencode($tag),
                skipUrls: $this->skipUrls,
                position: $this->position,
                height: $this->height,
            );
            $html = $this->renderer->inject((string) $response->getBody(), $toolbar);

            $response = $response->withBody($this->streamFactory->createStream($html));
        }

        return [$response, $summary];
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

    /**
     * Returns whether the request targets the debugger itself.
     *
     * @param ServerRequestInterface $request Request reaching the middleware.
     *
     * @return bool `true` when the request targets the debugger itself; `false` otherwise.
     */
    private function isDebugRequest(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        return $path === $this->routePrefix || str_starts_with($path, $this->routePrefix . '/');
    }

    /**
     * Resolves one request-start origin shared by the summary and the profiler.
     *
     * @param ServerRequestInterface $request Request reaching the middleware.
     *
     * @return float Request start, in seconds.
     */
    private static function requestStart(ServerRequestInterface $request): float
    {
        $start = $request->getServerParams()['REQUEST_TIME_FLOAT'] ?? null;

        return is_float($start) || is_int($start) ? $start : microtime(true);
    }

    /**
     * Serves a request targeting the debugger itself.
     *
     * @param ServerRequestInterface $request Request reaching the middleware.
     * @param RequestHandlerInterface $handler Next handler in the middleware stack.
     *
     * @throws Throwable when the request handler fails.
     *
     * @return ResponseInterface Debugger response, `403 Forbidden` for a disallowed client, or the application
     * response when no debug request handler is configured.
     */
    private function serveDebugRequest(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $debugRequestHandler = $this->debugRequestHandler;

        if ($debugRequestHandler === null) {
            return $handler->handle($request);
        }

        return $this->isAllowed($request)
            ? $debugRequestHandler->handle($request)
            : $debugRequestHandler->forbidden();
    }

    /**
     * Returns whether the toolbar may be injected into the response body.
     *
     * @param ServerRequestInterface $request Request reaching the middleware.
     * @param ResponseInterface $response Response produced for the request.
     *
     * @return bool `true` when the toolbar may be injected into the body; `false` otherwise.
     */
    private function shouldInject(ServerRequestInterface $request, ResponseInterface $response): bool
    {
        $statusCode = $response->getStatusCode();

        if (
            strtoupper($request->getMethod()) === 'HEAD'
            || $statusCode < 200
            || $statusCode === 204
            || $statusCode === 205
            || $statusCode === 304
        ) {
            return false;
        }

        if (strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest') {
            return false;
        }

        $contentType = strtolower($response->getHeaderLine('Content-Type'));

        return str_contains($contentType, 'text/html') || str_contains($contentType, 'application/xhtml+xml');
    }
}
