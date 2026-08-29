<?php

declare(strict_types=1);

namespace Yii3\Debug\Middleware;

use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary, SnapshotStore};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface, StreamFactoryInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Yii3\Debug\Collector\InertiaCollector;
use Yii3\Debug\Web\ToolbarRenderer;
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
final readonly class ToolbarMiddleware implements MiddlewareInterface
{
    private string $routePrefix;

    /**
     * @param list<string> $skipUrls Same-origin URLs excluded from AJAX tracking.
     */
    public function __construct(
        private ToolbarRenderer $renderer,
        private StreamFactoryInterface $streamFactory,
        private SnapshotStore $store,
        private IpRanges $allowedIpRanges,
        string $routePrefix = '/debug',
        private int $historySize = 50,
        private array $skipUrls = [],
        private string $position = 'bottom',
        private int $height = 50,
        private CollectorCoordinator|null $collectorCoordinator = null,
    ) {
        $this->routePrefix = rtrim($routePrefix, '/');
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->isDebugRequest($request) || !$this->isAllowed($request)) {
            return $handler->handle($request);
        }

        if ($this->collectorCoordinator !== null) {
            return $this->collectorCoordinator->run(
                fn(): ResponseInterface => $this->captureRequest($request, $handler),
            );
        }

        return $this->captureRequest($request, $handler);
    }

    public function withHistorySize(int $historySize): self
    {
        return new self(
            renderer: $this->renderer,
            streamFactory: $this->streamFactory,
            store: $this->store,
            allowedIpRanges: $this->allowedIpRanges,
            routePrefix: $this->routePrefix,
            historySize: $historySize,
            skipUrls: $this->skipUrls,
            position: $this->position,
            height: $this->height,
            collectorCoordinator: $this->collectorCoordinator,
        );
    }

    public function withPresentation(string $position, int $height): self
    {
        return new self(
            renderer: $this->renderer,
            streamFactory: $this->streamFactory,
            store: $this->store,
            allowedIpRanges: $this->allowedIpRanges,
            routePrefix: $this->routePrefix,
            historySize: $this->historySize,
            skipUrls: $this->skipUrls,
            position: $position,
            height: $height,
            collectorCoordinator: $this->collectorCoordinator,
        );
    }

    public function withRoutePrefix(string $routePrefix): self
    {
        return new self(
            renderer: $this->renderer,
            streamFactory: $this->streamFactory,
            store: $this->store,
            allowedIpRanges: $this->allowedIpRanges,
            routePrefix: $routePrefix,
            historySize: $this->historySize,
            skipUrls: $this->skipUrls,
            position: $this->position,
            height: $this->height,
            collectorCoordinator: $this->collectorCoordinator,
        );
    }

    /**
     * @param list<string> $skipUrls Same-origin URLs excluded from AJAX tracking.
     */
    public function withSkipUrls(array $skipUrls): self
    {
        return new self(
            renderer: $this->renderer,
            streamFactory: $this->streamFactory,
            store: $this->store,
            allowedIpRanges: $this->allowedIpRanges,
            routePrefix: $this->routePrefix,
            historySize: $this->historySize,
            skipUrls: $skipUrls,
            position: $this->position,
            height: $this->height,
            collectorCoordinator: $this->collectorCoordinator,
        );
    }

    private function captureRequest(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $tag = str_replace('.', '', uniqid('', true));

        $start = self::requestStart($request);

        $inertiaCollector = $this->collectorCoordinator?->collector('inertia');

        if ($inertiaCollector instanceof InertiaCollector) {
            $inertiaCollector->collectRequest($request);
        }

        $response = $handler->handle($request);

        if ($inertiaCollector instanceof InertiaCollector) {
            $inertiaCollector->collectResponse($response);
        }

        $processingTime = microtime(true) - $start;

        $response = $response
            ->withHeader('X-Debug-Tag', $tag)
            ->withHeader(
                'X-Debug-Duration',
                number_format($processingTime * 1000, 0, '.', ''),
            );

        $summary = RequestSummary::create($tag)
            ->withRequest(
                url: (string) $request->getUri(),
                method: strtoupper($request->getMethod()),
                ip: self::clientIp($request),
                time: $start,
                ajax: strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest',
            )
            ->withResponse($response->getStatusCode())
            ->withProfiling($processingTime, memory_get_peak_usage(true));

        $this->store->writeSnapshot(
            $this->collectorCoordinator?->capture($summary) ?? new DebugSnapshot($summary, [], []),
            $this->historySize,
        );

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

    private static function clientIp(ServerRequestInterface $request): string
    {
        $clientIp = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($clientIp) && IpHelper::isIp($clientIp) ? $clientIp : '';
    }

    private function isAllowed(ServerRequestInterface $request): bool
    {
        $clientIp = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($clientIp)
            && IpHelper::isIp($clientIp)
            && $this->allowedIpRanges->isAllowed($clientIp);
    }

    private function isDebugRequest(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        return $path === $this->routePrefix || str_starts_with($path, $this->routePrefix . '/');
    }

    private static function requestStart(ServerRequestInterface $request): float
    {
        $start = $request->getServerParams()['REQUEST_TIME_FLOAT'] ?? null;

        return is_float($start) || is_int($start) ? $start : microtime(true);
    }

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
