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
use Yii3\Debug\Collector\{MailCollector, ProfilingCollector, RequestObserverInterface};
use Yii3\Debug\Web\ToolbarRenderer;
use Yiisoft\NetworkUtilities\{IpHelper, IpRanges};

use function count;
use function is_float;
use function is_int;
use function is_string;
use function memory_get_peak_usage;
use function microtime;
use function number_format;
use function rawurlencode;
use function str_contains;
use function str_replace;
use function strtolower;
use function strtoupper;
use function uniqid;

/**
 * Captures debug snapshots and injects the toolbar into eligible HTML responses.
 *
 * Requests targeting the debugger pass through uncaptured, so {@see DebugRouteMiddleware} can serve them whichever of
 * the two runs first, and an application that answers the debugger paths itself can register this middleware alone.
 */
final readonly class RequestCaptureMiddleware implements MiddlewareInterface
{
    /**
     * @param ToolbarRenderer $renderer Renderer producing the toolbar markup injected into the response.
     * @param StreamFactoryInterface $streamFactory Factory building the rewritten response body.
     * @param SnapshotStore $store Store the capture is written to.
     * @param IpRanges $allowedIpRanges Ranges allowed to reach the debugger.
     * @param ToolbarOptions $options Settings carrying the route prefix, the history size, the excessive-caller
     * threshold, and the toolbar presentation.
     * @param CapturePolicy $capturePolicy Policy deciding which values are persisted and which are redacted.
     * @param CollectorCoordinator|null $collectorCoordinator Coordinator owning the collectors, or `null` to capture
     * nothing but the request summary.
     * @param DeferredCapture|null $deferredCapture Holder of the pending finalizer, or `null` to finalize inside the
     * pipeline. Deferral requires a coordinator; without one the capture is finalized immediately.
     */
    public function __construct(
        private ToolbarRenderer $renderer,
        private StreamFactoryInterface $streamFactory,
        private SnapshotStore $store,
        private IpRanges $allowedIpRanges,
        private ToolbarOptions $options,
        private CapturePolicy $capturePolicy,
        private CollectorCoordinator|null $collectorCoordinator,
        private DeferredCapture|null $deferredCapture,
    ) {}

    /**
     * Captures the request, then injects the toolbar into an eligible HTML response.
     *
     * A capture still pending from an earlier request is written before anything else, debugger pages and requests
     * from a disallowed address included: a worker that never dispatched the shutdown event stops the collectors
     * before serving them, so their activity stays out of that capture, and the capture reaches history at once. A
     * failing finalization propagates from the request that triggered it. {@see DebugRouteMiddleware} applies the
     * same rule, so the guarantee holds whichever of the two runs first.
     *
     * Requests targeting the debugger itself and requests from a disallowed address pass through untouched.
     *
     * @param ServerRequestInterface $request Request reaching the middleware.
     * @param RequestHandlerInterface $handler Next handler in the middleware stack.
     *
     * @throws Throwable when the pending capture or the request handler fails.
     *
     * @return ResponseInterface Response, with the toolbar injected when the request qualifies.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->deferredCapture?->finalize();

        if ($this->options->isDebugPath($request->getUri()->getPath()) || $this->isAllowed($request) === false) {
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
     * The collectors also stop when the deferred finalization fails, and that failure reaches the shutdown phase.
     *
     * @param ServerRequestInterface $request Request reaching the middleware.
     * @param RequestHandlerInterface $handler Next handler in the middleware stack.
     * @param CollectorCoordinator $collectorCoordinator Coordinator owning the collectors.
     * @param DeferredCapture $deferredCapture Holder of the pending finalizer.
     *
     * @throws Throwable when the collector startup or the request handler fails.
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
                try {
                    $this->finalizeCapture(
                        $summary->withProfiling(microtime(true) - $start, memory_get_peak_usage(true)),
                    );
                } finally {
                    (new InstrumentationGuard())->observe($collectorCoordinator->shutdown(...));
                }
            },
        );

        return $response;
    }

    /**
     * Captures every collector into the summary and writes the snapshot to the store.
     *
     * The summary records the `.eml` files the Mail collector stored, so the History grid counts them and a capture
     * rotated out of the history, or one that failed to commit, takes its files along, as the Yii2 log target does.
     *
     * @param RequestSummary $summary Metadata the capture is written for.
     *
     * @throws Throwable when the snapshot cannot be written.
     */
    private function finalizeCapture(RequestSummary $summary): void
    {
        $mailCollector = $this->collectorCoordinator?->collector('mail');

        if ($mailCollector instanceof MailCollector) {
            $mailFiles = $mailCollector->mailFiles();
            $summary = $summary->withMail(count($mailFiles), $mailFiles);
        }

        $snapshot = $this->collectorCoordinator?->capture($summary) ?? new DebugSnapshot($summary, [], []);

        if (isset($snapshot->panels['db'])) {
            $database = new DbSummary(DbSnapshot::fromArray($snapshot->panels['db'], '$.panels.db')->entries());
            $snapshot = new DebugSnapshot(
                $snapshot->summary->withDatabase(
                    $database->count,
                    $database->excessiveCallerCount($this->options->excessiveCallerThreshold),
                ),
                $snapshot->panels,
                $snapshot->failures,
            );
        }

        try {
            $result = $this->store->writeSnapshotResult($snapshot, $this->options->historySize);
        } catch (Throwable $failure) {
            if ($mailCollector instanceof MailCollector) {
                $mailCollector->removeFiles($snapshot->summary->mailFiles);
            }

            throw $failure;
        }

        if ($mailCollector instanceof MailCollector) {
            $referencedFiles = [];

            foreach ($result->removed as $removed) {
                $mailCollector->removeFiles($removed->mailFiles);
            }

            foreach ($result->entries as $entry) {
                foreach ($entry->mailFiles as $file) {
                    $referencedFiles[] = $file;
                }
            }

            $mailCollector->reconcileFiles($referencedFiles);
        }
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
                "{$this->options->routePrefix}/view?tag="
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
                dataUrl: "{$this->options->routePrefix}/toolbar?tag=" . rawurlencode($tag),
                skipUrls: $this->options->skipUrls,
                position: $this->options->position,
                height: $this->options->height,
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
