<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use Psr\EventDispatcher\{EventDispatcherInterface, StoppableEventInterface};

/**
 * Simulates listener dispatch that stops after the first listener.
 */
final class StoppingEventDispatcherStub implements EventDispatcherInterface
{
    public int $calledListeners = 0;

    public function dispatch(object $event): object
    {
        foreach ([1, 2] as $_) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }

            $this->calledListeners++;

            if ($event instanceof StoppableEventStub) {
                $event->stopPropagation();
            }
        }

        return $event;
    }
}
