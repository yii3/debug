<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use LogicException;
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Helper\SensitiveDataRedactor;
use PHPForge\Debug\Panel\Request\RequestSnapshot;
use PHPForge\Debug\Panel\Request\Routing\RouteDefinition;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface, UploadedFileInterface};
use Yii3\Debug\Routing\RouteDefinitionExtractor;
use Yiisoft\Router\{CurrentRoute, RouteCollectionInterface};

use function count;
use function is_array;
use function is_string;
use function min;
use function preg_replace_callback;
use function reset;
use function str_ends_with;
use function stripos;
use function strlen;
use function strtolower;
use function substr;

/**
 * Captures PSR-7 request and response state in the canonical Request panel shape.
 */
final class RequestCollector implements CollectorInterface
{
    /**
     * Header fields whose complete value is a URI reference rather than a compound field value.
     *
     * @var array<string, true>
     */
    private const array URL_HEADERS = [
        'content-location' => true,
        'destination' => true,
        'location' => true,
        'origin' => true,
        'referer' => true,
        'referrer' => true,
    ];

    /**
     * @var array<string, mixed>|null
     */
    private array|null $request = null;

    /**
     * @var array<string, mixed>|null
     */
    private array|null $response = null;

    private bool $started = false;

    public function __construct(
        private readonly CurrentRoute|null $currentRoute = null,
        private readonly RouteCollectionInterface|null $routes = null,
        private readonly CapturePolicy $capturePolicy = new CapturePolicy(),
    ) {}

    public function capture(): RequestSnapshot|null
    {
        if ($this->started === false || $this->request === null || $this->response === null) {
            return null;
        }

        return RequestSnapshot::capture([...$this->request, ...$this->response]);
    }

    public function collectRequest(ServerRequestInterface $request): void
    {
        if ($this->started === false) {
            throw new LogicException('The request collector must be started before collecting a request.');
        }

        $parsedBody = $request->getParsedBody();
        $rawBody = $this->rawBody($request);
        $requestBody = $rawBody === '' ? [] : $this->capturePolicy->redactBody($rawBody, $parsedBody);
        $userAgent = $request->getHeaderLine('User-Agent');

        $isAjax = strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';

        $this->request = $this->capturePolicy->redact([
            'action' => null,
            'actionParams' => [],
            'flashes' => [],
            'general' => [
                'isAjax' => $isAjax,
                'isFlash' => stripos($userAgent, 'Shockwave') !== false || stripos($userAgent, 'Flash') !== false,
                'isPjax' => $isAjax && $request->hasHeader('X-Pjax'),
                'isSecureConnection' => $request->getUri()->getScheme() === 'https',
                'method' => $request->getMethod(),
            ],
            'requestBody' => $requestBody === [] ? [] : [
                'Content Type' => $request->getHeaderLine('Content-Type'),
                'Decoded' => $requestBody['decoded'],
                'Raw' => $requestBody['raw'],
            ],
            'requestHeaders' => $this->collapseHeaders($request->getHeaders()),
            'route' => '',
            'COOKIE' => $request->getCookieParams(),
            'FILES' => self::uploadedFiles($request->getUploadedFiles()),
            'GET' => $request->getQueryParams(),
            'POST' => is_array($parsedBody) ? $parsedBody : [],
            'SERVER' => $this->redactServerUrls($request->getServerParams()),
            'SESSION' => [],
        ]);
    }

    public function collectResponse(ResponseInterface $response): void
    {
        if ($this->started === false) {
            throw new LogicException('The request collector must be started before collecting a response.');
        }

        $route = $this->currentRoute?->getName() ?? '';
        $routeDefinition = $route === '' ? null : RouteDefinitionExtractor::find($route, $this->routes);
        $routeDefinition ??= $this->currentRoute === null
            ? null
            : RouteDefinitionExtractor::fromCurrentRoute($this->currentRoute);

        $this->response = $this->capturePolicy->redact([
            'action' => $routeDefinition?->getAction(),
            'actionParams' => $this->currentRoute?->getArguments() ?? [],
            'responseHeaders' => $this->collapseHeaders($response->getHeaders()),
            'route' => $route,
            'routeDefinition' => null,
            'statusCode' => $response->getStatusCode(),
        ]);
        $this->response['routeDefinition'] = $this->redactRouteDefinition($routeDefinition);
    }

    public function id(): string
    {
        return 'request';
    }

    public function shutdown(): void
    {
        $this->started = false;
        $this->request = null;
        $this->response = null;
    }

    public function startup(): void
    {
        if ($this->started) {
            return;
        }

        $this->request = null;
        $this->response = null;
        $this->started = true;
    }

    /**
     * @param array<array-key, array<array-key, string>> $headers
     *
     * @return array<array-key, array<array-key, string>|string>
     */
    private function collapseHeaders(array $headers): array
    {
        $collapsed = [];

        foreach ($headers as $name => $values) {
            foreach ($values as $index => $value) {
                $values[$index] = $this->redactHeaderValue((string) $name, $value);
            }

            $collapsed[$name] = count($values) === 1 ? reset($values) : $values;
        }

        return $collapsed;
    }

    /**
     * Reads a seekable request body without changing its cursor and never consumes a non-seekable stream.
     */
    private function rawBody(ServerRequestInterface $request): string
    {
        $body = $request->getBody();

        if ($body->isSeekable() === false) {
            return '';
        }

        $position = $body->tell();

        try {
            $body->seek(0);

            $remaining = $this->capturePolicy->maxBodyBytes();
            $raw = '';

            while ($remaining > 0 && $body->eof() === false) {
                $chunk = $body->read(min(8192, $remaining));

                if ($chunk === '') {
                    break;
                }

                $raw .= $chunk;
                $remaining -= strlen($chunk);
            }

            if ($remaining === 0 && $body->eof() === false) {
                $raw .= $body->read(1);
            }
        } finally {
            $body->seek($position);
        }

        return $raw;
    }

    private function redactHeaderValue(string $name, string $value): string
    {
        $name = strtolower($name);

        if ($name === 'link') {
            return preg_replace_callback(
                '~<([^>]*)>~',
                fn(array $matches): string => '<' . $this->capturePolicy->redactUrl($matches[1]) . '>',
                $value,
            ) ?? $value;
        }

        if (
            isset(self::URL_HEADERS[$name])
            || str_ends_with($name, '-location')
            || str_ends_with($name, '-uri')
            || str_ends_with($name, '-url')
        ) {
            return $this->capturePolicy->redactUrl($value);
        }

        return $this->capturePolicy->redactText($value);
    }

    /**
     * Redacts route fields while retaining the persisted definition's scalar schema.
     *
     * @return array{
     *     name: string,
     *     pattern: string,
     *     methods: list<string>,
     *     hosts: list<string>,
     *     action: string|null,
     *     middlewares: list<string>|null,
     *     target?: string,
     *     suffix?: string,
     *     mode?: string,
     *     type?: string
     * }|null
     */
    private function redactRouteDefinition(RouteDefinition|null $definition): array|null
    {
        if (
            $definition === null
            || $this->capturePolicy->isSensitiveKey('route')
            || $this->capturePolicy->isSensitiveKey('routeDefinition')
        ) {
            return null;
        }

        $data = $this->capturePolicy->redact($definition->toArray());

        foreach (['methods' => 'method', 'hosts' => 'host', 'middlewares' => 'middleware'] as $key => $alias) {
            if ($this->capturePolicy->isSensitiveKey($alias)) {
                $data[$key] = [SensitiveDataRedactor::PLACEHOLDER];
            } elseif (is_string($data[$key] ?? null)) {
                $data[$key] = [$data[$key]];
            }
        }

        return RouteDefinition::fromArray($data)->toArray();
    }

    /**
     * @param array<array-key, mixed> $server
     *
     * @return array<array-key, mixed>
     */
    private function redactServerUrls(array $server): array
    {
        foreach ($server as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                continue;
            }

            $normalizedName = strtolower($name);

            if ($normalizedName === 'query_string') {
                $server[$name] = substr($this->capturePolicy->redactUrl('?' . $value), 1);

                continue;
            }

            if ($normalizedName === 'request_uri' || $normalizedName === 'http_referer') {
                $server[$name] = $this->capturePolicy->redactUrl($value);
            }
        }

        return $server;
    }

    /**
     * @param array<array-key, mixed> $uploadedFiles
     *
     * @return array<array-key, mixed>
     */
    private static function uploadedFiles(array $uploadedFiles): array
    {
        $summary = [];

        foreach ($uploadedFiles as $name => $file) {
            if ($file instanceof UploadedFileInterface) {
                $summary[$name] = [
                    'name' => $file->getClientFilename(),
                    'type' => $file->getClientMediaType(),
                    'size' => $file->getSize(),
                    'error' => $file->getError(),
                ];

                continue;
            }

            if (is_array($file)) {
                $summary[$name] = self::uploadedFiles($file);
            }
        }

        return $summary;
    }
}
