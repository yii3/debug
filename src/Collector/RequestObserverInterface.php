<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};

/**
 * Optional host-local request hooks for registered collectors, independent of any extension ID.
 */
interface RequestObserverInterface
{
    public function collectRequest(ServerRequestInterface $request): void;
    public function collectResponse(ResponseInterface $response): void;
}
