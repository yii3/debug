<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};

/**
 * Optional host-local request hooks for registered collectors, independent of any extension ID.
 */
interface RequestObserverInterface
{
    /**
     * Records the incoming request.
     *
     * @param ServerRequestInterface $request Request reaching the debugger middleware.
     */
    public function collectRequest(ServerRequestInterface $request): void;
    /**
     * Records the outgoing response.
     *
     * @param ResponseInterface $response Response produced for the captured request.
     */
    public function collectResponse(ResponseInterface $response): void;
}
