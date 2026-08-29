<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Panel\Inertia\InertiaSnapshot;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};

use function is_string;

/**
 * Captures one resolved Inertia page and its request negotiation metadata.
 */
final class InertiaCollector implements CollectorInterface
{
    /**
     * @var list<string>
     */
    private const array REQUEST_HEADERS = [
        'X-Inertia',
        'X-Inertia-Partial-Component',
        'X-Inertia-Partial-Data',
        'X-Inertia-Partial-Except',
        'X-Inertia-Reset',
        'X-Inertia-Error-Bag',
        'X-Inertia-Except-Once-Props',
        'X-Inertia-Infinite-Scroll-Merge-Intent',
        'X-Inertia-Version',
    ];

    /**
     * @var array<string, mixed>|null
     */
    private array|null $page = null;
    private ServerRequestInterface|null $request = null;
    private ResponseInterface|null $response = null;
    /**
     * @var list<string>
     */
    private array $sharedKeys = [];
    private bool $started = false;

    public function __construct(private readonly CapturePolicy $capturePolicy = new CapturePolicy()) {}

    public function capture(): InertiaSnapshot|null
    {
        if ($this->started === false || $this->request === null || $this->response === null) {
            return null;
        }

        $requestHeaders = $this->requestHeaders();

        $page = $this->page === null ? null : $this->capturePolicy->redact($this->page);
        $pageUrl = $page['url'] ?? null;

        if (is_string($pageUrl)) {
            $page['url'] = $this->capturePolicy->redactUrl($pageUrl);
        }

        $location = $this->response->getHeaderLine('X-Inertia-Location');

        return InertiaSnapshot::capture(
            location: $location === '' ? null : $this->capturePolicy->redactUrl($location),
            page: $page,
            requestHeaders: $requestHeaders,
            sharedKeys: $this->sharedKeys,
            statusCode: $this->response->getStatusCode(),
        );
    }

    public function collectRequest(ServerRequestInterface $request): void
    {
        if ($this->started) {
            $this->request = $request;
        }
    }

    public function collectResponse(ResponseInterface $response): void
    {
        if ($this->started) {
            $this->response = $response;
        }
    }

    public function id(): string
    {
        return 'inertia';
    }

    /**
     * @param array<string, mixed> $page Resolved Inertia page payload.
     * @param list<string> $sharedKeys Top-level props supplied by the shared-prop configuration.
     */
    public function observe(array $page, array $sharedKeys): void
    {
        if ($this->started) {
            $this->page = $page;
            $this->sharedKeys = $sharedKeys;
        }
    }

    public function shutdown(): void
    {
        $this->started = false;
        $this->page = null;
        $this->request = null;
        $this->response = null;
        $this->sharedKeys = [];
    }

    public function startup(): void
    {
        if ($this->started) {
            return;
        }

        $this->page = null;
        $this->request = null;
        $this->response = null;
        $this->sharedKeys = [];
        $this->started = true;
    }

    /**
     * @return array<string, string>
     */
    private function requestHeaders(): array
    {
        $headers = [];

        foreach (self::REQUEST_HEADERS as $name) {
            $value = $this->request?->getHeaderLine($name) ?? '';

            if ($value !== '') {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
