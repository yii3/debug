<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Creates an anonymous caller for event-dispatcher instrumentation tests.
 */
final class AnonymousEventCallerStubFactory
{
    private function __construct() {}

    public static function create(): AnonymousEventCallerStubInterface
    {
        return new class implements AnonymousEventCallerStubInterface {
            public function dispatch(EventDispatcherInterface $dispatcher, object $event): object
            {
                return $dispatcher->dispatch($event);
            }
        };
    }
}
