<?php

declare(strict_types=1);

namespace Yii3\Debug\Middleware;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface, StreamFactoryInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Yii3\Debug\Web\ToolbarRenderer;
use Yiisoft\NetworkUtilities\{IpHelper, IpRanges};

use function is_float;
use function is_int;
use function is_string;
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
 * Injects the minimal debug toolbar into eligible HTML responses.
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
        private IpRanges $allowedIpRanges,
        string $routePrefix = '/debug',
        private array $skipUrls = [],
        private string $position = 'bottom',
        private int $height = 50,
    ) {
        $this->routePrefix = rtrim($routePrefix, '/');
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->isDebugRequest($request) || !$this->isAllowed($request)) {
            return $handler->handle($request);
        }

        $tag = str_replace('.', '', uniqid('', true));
        $start = self::requestStart($request);
        $response = $handler->handle($request)
            ->withHeader('X-Debug-Tag', $tag)
            ->withHeader(
                'X-Debug-Duration',
                number_format((microtime(true) - $start) * 1000, 0, '.', ''),
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
