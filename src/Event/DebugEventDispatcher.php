<?php

declare(strict_types=1);

namespace Yii3\Debug\Event;

use PHPForge\Debug\Instrumentation\InstrumentationGuard;
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
    private InstrumentationGuard $guard;

    /**
     * @param EventDispatcherInterface $dispatcher Real application dispatcher receiving every event.
     * @param EventCollector $collector Debug collector recording dispatched events.
     * @param InstrumentationGuard|null $guard Fail-open observer guard, or `null` to use the default guard.
     */
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private EventCollector $collector,
        InstrumentationGuard|null $guard = null,
    ) {
        $this->guard = $guard ?? new InstrumentationGuard();
    }

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
        $this->guard->observe(fn() => $this->collector->record($event));

        return $this->dispatcher->dispatch($event);
    }
}
