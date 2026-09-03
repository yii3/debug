<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

/**
 * Throws a configured listener failure during dispatch.
 */
final readonly class ThrowingEventDispatcherStub implements EventDispatcherInterface
{
    public function __construct(private RuntimeException $failure) {}

    public function dispatch(object $event): object
    {
        throw $this->failure;
    }
}
