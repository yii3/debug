<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Dispatches an event from an anonymous caller used by instrumentation tests.
 */
interface AnonymousEventCallerStubInterface
{
    public function dispatch(EventDispatcherInterface $dispatcher, object $event): object;
}
