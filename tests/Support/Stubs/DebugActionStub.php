<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Yii3\Debug\Action\DebugActionInterface;
use Yii3\Debug\Tests\Support\HelperFactory;

/**
 * Names the debugger endpoint the request handler dispatched to, and echoes the path it received.
 */
final readonly class DebugActionStub implements DebugActionInterface
{
    public function __construct(private string $id) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return HelperFactory::createResponse(200, ['X-Debug-Action' => $this->id], $request->getUri()->getPath());
    }
}
