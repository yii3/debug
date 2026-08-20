<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use LogicException;
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Panel\Request\RequestSnapshot;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface, UploadedFileInterface};
use Yiisoft\Router\{CurrentRoute, RouteCollectionInterface};

use function count;
use function is_array;
use function reset;
use function str_contains;

/**
 * Captures the PSR-7 request and response state into the shared Request panel payload.
 *
 * Produces the same snapshot shape as the Yii2 adapter (routing target, headers, status code, body, and parameter
 * buckets), so the shared Request panel presentation renders identically for both frameworks.
 */
final class RequestCollector implements CollectorInterface
{
    private bool $active = false;
    private readonly CapturePolicy $capturePolicy;

    /**
     * @var array<string, mixed>|null
     */
    private array|null $request = null;

    /**
     * @var array<string, mixed>
     */
    private array $response = [];

    /**
     * @param CurrentRoute|null $currentRoute Matched-route holder supplying the route name and arguments, or `null`
     * when routing information is unavailable.
     * @param RouteCollectionInterface|null $routes Route collection resolving the dispatched action descriptor, or
     * `null` when unavailable.
     * @param CapturePolicy|null $capturePolicy Persistent-capture policy, or `null` to use secure defaults.
     */
    public function __construct(
        private readonly CurrentRoute|null $currentRoute = null,
        private readonly RouteCollectionInterface|null $routes = null,
        CapturePolicy|null $capturePolicy = null,
    ) {
        $this->capturePolicy = $capturePolicy ?? new CapturePolicy();
    }

    /**
     * Returns the captured request payload in the shared Request panel shape.
     *
     * Usage example:
     *
     * ```php
     * $snapshot = $collector->capture();
     * ```
     *
     * @return RequestSnapshot|null Captured payload; `null` when the collector never started or saw no request.
     */
    public function capture(): RequestSnapshot|null
    {
        if (!$this->active || $this->request === null) {
            return null;
        }

        return RequestSnapshot::capture([...$this->request, ...$this->response]);
    }

    /**
     * Captures metadata from the incoming PSR-7 server request.
     *
     * Usage example:
     *
     * ```php
     * $collector->collectRequest($request);
     * ```
     *
     * @param ServerRequestInterface $request Incoming request.
     */
    public function collectRequest(ServerRequestInterface $request): void
    {
        if (!$this->active) {
            throw new LogicException('The request collector must be started before collecting a request.');
        }

        $parsedBody = $request->getParsedBody();
        $rawBody = self::rawBody($request);
        $requestBody = $rawBody === '' ? [] : $this->capturePolicy->redactBody($rawBody, $parsedBody);
        $userAgent = $request->getHeaderLine('User-Agent');

        $this->request = $this->capturePolicy->redact([
            'action' => null,
            'actionParams' => [],
            'flashes' => [],
            'general' => [
                'isAjax' => $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest',
                'isFlash' => str_contains($userAgent, 'Shockwave') || str_contains($userAgent, 'Flash'),
                'isPjax' => $request->hasHeader('X-Pjax'),
                'isSecureConnection' => $request->getUri()->getScheme() === 'https',
                'method' => $request->getMethod(),
            ],
            'requestBody' => $requestBody === [] ? [] : [
                'Content Type' => $request->getHeaderLine('Content-Type'),
                'Decoded' => $requestBody['decoded'],
                'Raw' => $requestBody['raw'],
            ],
            'requestHeaders' => self::collapseHeaders($request->getHeaders()),
            'route' => '',
            'COOKIE' => $request->getCookieParams(),
            'FILES' => self::uploadedFiles($request->getUploadedFiles()),
            'GET' => $request->getQueryParams(),
            'POST' => is_array($parsedBody) ? $parsedBody : [],
            'SERVER' => $request->getServerParams(),
            'SESSION' => [],
        ]);
    }

    /**
     * Captures metadata from the outgoing PSR-7 response and the resolved route.
     *
     * Usage example:
     *
     * ```php
     * $collector->collectResponse($response);
     * ```
     *
     * @param ResponseInterface $response Outgoing response.
     */
    public function collectResponse(ResponseInterface $response): void
    {
        if (!$this->active) {
            throw new LogicException('The request collector must be started before collecting a response.');
        }

        $route = $this->currentRoute?->getName() ?? '';

        $this->response = $this->capturePolicy->redact([
            'action' => RouteActionResolver::resolve($route, $this->routes),
            'actionParams' => $this->currentRoute?->getArguments() ?? [],
            'responseHeaders' => self::collapseHeaders($response->getHeaders()),
            'route' => $route,
            'statusCode' => $response->getStatusCode(),
        ]);
    }

    /**
     * Returns the stable ID pairing this collector with the shared Request panel.
     *
     * Usage example:
     *
     * ```php
     * $id = $collector->id();
     * ```
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'request';
    }

    /**
     * Clears the captured request state, so a reused worker process starts clean.
     */
    public function shutdown(): void
    {
        if (!$this->active) {
            return;
        }

        $this->request = null;
        $this->response = [];
        $this->active = false;
    }

    /**
     * Activates the collector for the current request cycle.
     */
    public function startup(): void
    {
        if ($this->active) {
            return;
        }

        $this->request = null;
        $this->response = [];
        $this->active = true;
    }

    /**
     * Collapses single-value header lists to their scalar value, mirroring the Yii2 capture shape.
     *
     * @param array<array-key, array<array-key, string>> $headers PSR-7 header map.
     *
     * @return array<array-key, array<array-key, string>|string> Collapsed header map.
     */
    private static function collapseHeaders(array $headers): array
    {
        $collapsed = [];

        foreach ($headers as $name => $values) {
            $collapsed[$name] = count($values) === 1 ? reset($values) : $values;
        }

        return $collapsed;
    }

    /**
     * Reads the raw request body without consuming a non-seekable stream.
     */
    private static function rawBody(ServerRequestInterface $request): string
    {
        $body = $request->getBody();

        if (!$body->isSeekable()) {
            return '';
        }

        $raw = (string) $body;

        $body->rewind();

        return $raw;
    }

    /**
     * Summarizes the uploaded files as `name => client file name` entries.
     *
     * @param array<array-key, mixed> $uploadedFiles PSR-7 uploaded-file tree.
     *
     * @return array<array-key, mixed> Display-safe summary preserving the tree shape.
     */
    private static function uploadedFiles(array $uploadedFiles): array
    {
        $summary = [];

        foreach ($uploadedFiles as $name => $file) {
            if ($file instanceof UploadedFileInterface) {
                $summary[$name] = $file->getClientFilename() ?? '';

                continue;
            }

            if (is_array($file)) {
                $summary[$name] = self::uploadedFiles($file);
            }
        }

        return $summary;
    }
}
