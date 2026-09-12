<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Throwable;

/**
 * Creates anonymous middleware doubles used to exercise Yii action-wrapper metadata handling.
 */
final class AnonymousMiddlewareStubFactory
{
    private function __construct() {}

    /**
     * @param array<string, mixed> $debugInfo
     */
    public static function create(
        array $debugInfo = [],
        Throwable|null $failure = null,
    ): AnonymousMiddlewareStubInterface {
        return new class ($debugInfo, $failure) implements AnonymousMiddlewareStubInterface {
            private int $debugInfoCalls = 0;

            /**
             * @param array<string, mixed> $debugInfo
             */
            public function __construct(
                private readonly array $debugInfo,
                private readonly Throwable|null $failure,
            ) {}

            public function __debugInfo(): array
            {
                $this->debugInfoCalls++;

                if ($this->failure !== null) {
                    throw $this->failure;
                }

                return $this->debugInfo;
            }

            public function debugInfoCalls(): int
            {
                return $this->debugInfoCalls;
            }

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                return $handler->handle($request);
            }
        };
    }

    /**
     * Creates a double whose debug metadata is absent rather than an array, the only non-array shape PHP allows.
     */
    public static function createWithoutDebugInfoArray(): MiddlewareInterface
    {
        return new class (null) implements MiddlewareInterface {
            /**
             * @param array<string, mixed>|null $debugInfo Metadata to expose; `?array` is the widest shape PHP allows.
             */
            public function __construct(private readonly array|null $debugInfo) {}

            /**
             * @return array<string, mixed>|null
             */
            public function __debugInfo(): array|null
            {
                return $this->debugInfo;
            }

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                return $handler->handle($request);
            }
        };
    }
}
