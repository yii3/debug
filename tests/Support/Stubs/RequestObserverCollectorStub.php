<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use PHPForge\Debug\CollectorInterface;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Yii3\Debug\Collector\RequestObserverInterface;

/**
 * Records the request and response the host middleware hands to an observing collector.
 */
final class RequestObserverCollectorStub implements CollectorInterface, RequestObserverInterface
{
    private ServerRequestInterface|null $request = null;
    private ResponseInterface|null $response = null;
    private bool $started = false;

    /**
     * @return array<string, mixed>|null Observed request and response pair; `null` when nothing was observed.
     */
    public function capture(): array|null
    {
        if ($this->started === false || $this->request === null || $this->response === null) {
            return null;
        }

        return [
            'method' => $this->request->getMethod(),
            'statusCode' => $this->response->getStatusCode(),
        ];
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
        return 'observer';
    }

    public function shutdown(): void
    {
        $this->request = null;
        $this->response = null;
        $this->started = false;
    }

    public function startup(): void
    {
        $this->started = true;
    }
}
