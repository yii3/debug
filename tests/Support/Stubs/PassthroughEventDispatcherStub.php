<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Returns the dispatched event unchanged.
 */
final class PassthroughEventDispatcherStub implements EventDispatcherInterface
{
    public function dispatch(object $event): object
    {
        return $event;
    }
}
