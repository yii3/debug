<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

/**
 * Creates anonymous events for class-name normalization tests.
 */
final class AnonymousEventStubFactory
{
    private function __construct() {}

    public static function create(): object
    {
        return new class {};
    }
}
