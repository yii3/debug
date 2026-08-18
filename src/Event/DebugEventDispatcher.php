<?php

declare(strict_types=1);

namespace Yii3\Debug\Event;

use Psr\EventDispatcher\EventDispatcherInterface;
use Yii3\Debug\Collector\EventCollector;

/**
 * Records every dispatched PSR-14 event into the debug Event collector before delegating to the real dispatcher.
 *
 * Usage example:
 *
 * ```php
 * use Yii3\Debug\Collector\EventCollector;
 * use Yii3\Debug\Event\DebugEventDispatcher;
 *
 * $dispatcher = new DebugEventDispatcher($realDispatcher, new EventCollector());
 * ```
 */
final readonly class DebugEventDispatcher implements EventDispatcherInterface
{
    /**
     * @param EventDispatcherInterface $dispatcher Real application dispatcher receiving every event.
     * @param EventCollector $collector Debug collector recording dispatched events.
     */
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private EventCollector $collector,
    ) {}

    /**
     * Records the event and delegates dispatching to the real dispatcher.
     *
     * Usage example:
     *
     * ```php
     * $event = $dispatcher->dispatch($event);
     * ```
     *
     * @param object $event Event object to dispatch.
     *
     * @return object The event returned by the real dispatcher.
     */
    public function dispatch(object $event): object
    {
        $this->collector->record($event);

        return $this->dispatcher->dispatch($event);
    }
}
