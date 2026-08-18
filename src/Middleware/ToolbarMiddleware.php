<?php

declare(strict_types=1);

namespace Yii3\Debug\Middleware;

use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\Storage\{RequestSummary, SnapshotStore};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface, StreamFactoryInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Yii3\Debug\Collector\{DbCollector, RequestCollector};
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
    ) {
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

        $this->coordinator->startup();
        $this->requestCollector->collectRequest($request);

        $tag = str_replace('.', '', uniqid('', true));
        $start = self::requestStart($request);
        $response = null;

        try {
            $response = $handler->handle($request);

            // Force lazy response bodies (for example the deferred view renderer) to materialize now, so
            // view-time activity such as asset registration is visible to the collectors below.
            $response->getBody()->__toString();

            $this->requestCollector->collectResponse($response);

            $processingTime = microtime(true) - $start;
            $serverParams = $request->getServerParams();
            $ip = $serverParams['REMOTE_ADDR'] ?? '';
            $dbCollector = $this->coordinator->collector('db');
            $summary = new RequestSummary(
                tag: $tag,
                url: (string) $request->getUri(),
                ajax: strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest',
                method: $request->getMethod(),
                ip: is_string($ip) ? $ip : '',
                time: $start,
                statusCode: $response->getStatusCode(),
                sqlCount: $dbCollector instanceof DbCollector ? $dbCollector->queryCount() : 0,
                excessiveCallersCount: 0,
                mailCount: 0,
                mailFiles: [],
                processingTime: $processingTime,
                peakMemory: memory_get_peak_usage(true),
            );

            $this->store->writeSnapshot($this->coordinator->capture($summary), $this->historySize);
        } finally {
            $this->coordinator->shutdown();
        }

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
        if (strtoupper($request->getMethod()) === 'HEAD' || $response->getStatusCode() === 204) {
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
