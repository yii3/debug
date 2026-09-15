<?php

declare(strict_types=1);

namespace Yii3\Debug\Action;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};

/**
 * Contract every debugger endpoint implements, so the request handler resolves them without the application router.
 */
interface DebugActionInterface
{
    /**
     * Serves one debugger endpoint.
     *
     * @param ServerRequestInterface $request Request reaching the debugger endpoint.
     *
     * @return ResponseInterface Response produced for the endpoint.
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface;
}
