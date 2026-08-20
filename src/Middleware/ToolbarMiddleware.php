<?php

declare(strict_types=1);

namespace Yii3\Debug\Middleware;

use Closure;
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\Storage\{RequestSummary, SnapshotStore};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface, StreamFactoryInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Throwable;
use Yii3\Debug\Collector\{DbCollector, MailCollector, RequestCollector};
use Yii3\Debug\Web\{LocalAccessChecker, ToolbarRenderer};

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
 * Adds debug headers and injects the shared toolbar into eligible Yii3 HTML responses.
 */
final class ToolbarMiddleware implements MiddlewareInterface
{
    private readonly CapturePolicy $capturePolicy;
    /**
     * @var (Closure(Throwable): void)|null
     */
    private readonly Closure|null $cleanupFailureHandler;
    private readonly string $routePrefix;

    /**
     * @param CollectorCoordinator $coordinator Framework-neutral collector coordinator.
     * @param RequestCollector $requestCollector Native Yii3 request collector.
     * @param SnapshotStore $store Shared core snapshot store.
     * @param ToolbarRenderer $renderer Yii3 toolbar renderer.
     * @param StreamFactoryInterface $streamFactory PSR-17 response stream factory.
     * @param LocalAccessChecker $accessChecker Debug interface access policy.
     * @param string $routePrefix URL prefix reserved for debug endpoints.
     * @param list<string> $skipUrls Same-origin URLs excluded from AJAX tracking.
     * @param string $position Initial toolbar position.
     * @param int $height Initial drawer height percentage.
     * @param CapturePolicy|null $capturePolicy Persistent-capture policy, or `null` to use secure defaults.
     * @param (callable(\Throwable): void)|null $cleanupFailureHandler Optional observer for cleanup failures
     * suppressed behind a primary application failure.
     */
    public function __construct(
        private readonly CollectorCoordinator $coordinator,
        private readonly RequestCollector $requestCollector,
        private readonly SnapshotStore $store,
        private readonly ToolbarRenderer $renderer,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly LocalAccessChecker $accessChecker,
        private readonly int $historySize = 50,
        string $routePrefix = '/debug',
        private readonly array $skipUrls = [],
        private readonly string $position = 'bottom',
        private readonly int $height = 50,
        CapturePolicy|null $capturePolicy = null,
        callable|null $cleanupFailureHandler = null,
    ) {
        $this->capturePolicy = $capturePolicy ?? new CapturePolicy();
        $this->cleanupFailureHandler = $cleanupFailureHandler === null
            ? null
            : Closure::fromCallable($cleanupFailureHandler);
        $this->routePrefix = rtrim($routePrefix, '/');
    }

    /**
     * Profiles the request and injects the toolbar into eligible HTML responses.
     *
     * Usage example:
     *
     * ```php
     * $response = $middleware->process($request, $handler);
     * ```
     *
     * @param ServerRequestInterface $request Incoming server request.
     * @param RequestHandlerInterface $handler Next request handler.
     *
     * @return ResponseInterface Decorated response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->isDebugRequest($request)) {
            return $handler->handle($request);
        }

        if (!$this->accessChecker->allows($request)) {
            return $handler->handle($request);
        }

        $tag = str_replace('.', '', uniqid('', true));
        $start = self::requestStart($request);
        $response = $this->coordinator->run(
            fn(): ResponseInterface => $this->captureResponse($request, $handler, $tag, $start),
            $this->cleanupFailureHandler,
        );

        $viewUrl = $this->viewUrl($tag);
        $response = $response
            ->withHeader('X-Debug-Tag', $tag)
            ->withHeader(
                'X-Debug-Duration',
                number_format((microtime(true) - $start) * 1000, 0, '.', ''),
            )
            ->withHeader('X-Debug-Link', $viewUrl);

        if (!$this->shouldInject($request, $response)) {
            return $response;
        }

        $toolbar = $this->renderer->render(
            dataUrl: $this->routePrefix . '/toolbar?tag=' . rawurlencode($tag),
            skipUrls: $this->skipUrls,
            position: $this->position,
            height: $this->height,
        );
        $html = $this->renderer->inject((string) $response->getBody(), $toolbar);

        return $response
            ->withoutHeader('Content-Length')
            ->withBody($this->streamFactory->createStream($html));
    }

    /**
     * Handles and captures one application response while the collector lifecycle is active.
     */
    private function captureResponse(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
        string $tag,
        float $start,
    ): ResponseInterface {
        $this->requestCollector->collectRequest($request);
        $response = $handler->handle($request);

        // Force lazy response bodies (for example the deferred view renderer) to materialize now, so
        // view-time activity such as asset registration is visible to the collectors below.
        $response->getBody()->__toString();

        $this->requestCollector->collectResponse($response);

        $processingTime = microtime(true) - $start;
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '';
        $dbCollector = $this->coordinator->collector('db');
        $mailCollector = $this->coordinator->collector('mail');
        $mailCollector = $mailCollector instanceof MailCollector ? $mailCollector : null;
        $mailFiles = $mailCollector?->messageFiles() ?? [];
        $summary = $this->createSummary(
            $request,
            $response,
            $tag,
            $start,
            $processingTime,
            is_string($ip) ? $ip : '',
            $dbCollector instanceof DbCollector ? $dbCollector : null,
            $mailFiles,
        );

        $this->persistSnapshot($summary, $mailCollector, $mailFiles);

        return $response;
    }

    /**
     * Creates the manifest summary for a completed request.
     *
     * @param list<string> $mailFiles Captured mail files referenced by the request.
     */
    private function createSummary(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $tag,
        float $start,
        float $processingTime,
        string $ip,
        DbCollector|null $dbCollector,
        array $mailFiles,
    ): RequestSummary {
        return new RequestSummary(
            tag: $tag,
            url: $this->capturePolicy->redactUrl((string) $request->getUri()),
            ajax: strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest',
            method: $request->getMethod(),
            ip: $ip,
            time: $start,
            statusCode: $response->getStatusCode(),
            sqlCount: $dbCollector?->queryCount() ?? 0,
            excessiveCallersCount: 0,
            mailCount: count($mailFiles),
            mailFiles: $mailFiles,
            processingTime: $processingTime,
            peakMemory: memory_get_peak_usage(true),
        );
    }

    /**
     * Returns whether the current path belongs to the debug interface.
     *
     * @param ServerRequestInterface $request Incoming server request.
     *
     * @return bool Whether the request targets a debug endpoint.
     */
    private function isDebugRequest(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        return $path === $this->routePrefix || str_starts_with($path, $this->routePrefix . '/');
    }

    /**
     * Persists one snapshot and reconciles mail files after the commit.
     *
     * @param list<string> $mailFiles Captured mail files referenced by the snapshot.
     */
    private function persistSnapshot(
        RequestSummary $summary,
        MailCollector|null $mailCollector,
        array $mailFiles,
    ): void {
        try {
            $removed = $this->store->writeSnapshot($this->coordinator->capture($summary), $this->historySize);
        } catch (Throwable $failure) {
            $mailCollector?->removeFiles($mailFiles);

            throw $failure;
        }

        if ($mailCollector === null) {
            return;
        }

        foreach ($removed as $removedSummary) {
            $mailCollector->removeFiles($removedSummary->mailFiles);
        }

        $this->reconcileMailFiles($mailCollector);
    }

    /**
     * Removes aged mail files that are no longer referenced by the manifest.
     */
    private function reconcileMailFiles(MailCollector $mailCollector): void
    {
        $manifest = $this->store->loadManifestResult();

        if ($manifest->error !== null) {
            return;
        }

        $referencedFiles = [];

        foreach ($manifest->entries as $entry) {
            foreach ($entry->mailFiles as $file) {
                $referencedFiles[] = $file;
            }
        }

        $mailCollector->reconcileFiles($referencedFiles);
    }

    /**
     * Resolves the request start timestamp from server parameters.
     *
     * @param ServerRequestInterface $request Incoming server request.
     *
     * @return float Request start timestamp.
     */
    private static function requestStart(ServerRequestInterface $request): float
    {
        $start = $request->getServerParams()['REQUEST_TIME_FLOAT'] ?? null;

        return is_float($start) || is_int($start) ? $start : microtime(true);
    }

    /**
     * Returns whether a response may contain the debug toolbar.
     *
     * @param ServerRequestInterface $request Incoming server request.
     * @param ResponseInterface $response Outgoing response.
     *
     * @return bool Whether toolbar markup may be injected.
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

    /**
     * Builds the current snapshot URL.
     *
     * @param string $tag Captured request tag.
     *
     * @return string Snapshot URL.
     */
    private function viewUrl(string $tag): string
    {
        return $this->routePrefix . '/view?tag=' . rawurlencode($tag) . '&panel=request';
    }
}
