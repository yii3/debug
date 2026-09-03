<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use Psr\Http\Server\MiddlewareInterface;

/**
 * Defines observable debug metadata for anonymous middleware test doubles.
 */
interface AnonymousMiddlewareStubInterface extends MiddlewareInterface
{
    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array;

    public function debugInfoCalls(): int;
}
