<?php

declare(strict_types=1);

/**
 * Declares the "after" dispatcher event with a loose subject type, standing in for a future package version that no
 * longer guarantees a PSR-7 response.
 *
 * The file is required from a process-isolated test, before the installed package declares the same name.
 */

namespace Yiisoft\Middleware\Dispatcher\Event;

final class AfterMiddleware
{
    public function __construct(private readonly object $middleware, private readonly object $response) {}

    public function getMiddleware(): object
    {
        return $this->middleware;
    }

    public function getResponse(): object
    {
        return $this->response;
    }
}
