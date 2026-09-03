<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

/**
 * Invokable action used to verify middleware wrapper source metadata without execution.
 */
final class WrappedEventActionStub
{
    public static bool $invoked = false;

    public function __invoke(): void
    {
        self::$invoked = true;
    }
}
