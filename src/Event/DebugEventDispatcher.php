<?php

declare(strict_types=1);

namespace Yii3\Debug\Event;

use PHPForge\Debug\Instrumentation\InstrumentationGuard;
use Psr\EventDispatcher\EventDispatcherInterface;
use Yii3\Debug\Collector\EventCollector;

use function debug_backtrace;

use const DEBUG_BACKTRACE_IGNORE_ARGS;

/**
 * Records PSR-14 event and immediate caller metadata before delegating to the application dispatcher.
 */
final readonly class DebugEventDispatcher implements EventDispatcherInterface
{
    /**
     * Guard isolating capture failures, so a broken collector never breaks dispatch.
     */
    private InstrumentationGuard $guard;

    /**
     * @param EventDispatcherInterface $dispatcher Application dispatcher every event is delegated to.
     * @param EventCollector $collector Collector recording the event and its immediate caller.
     * @param InstrumentationGuard|null $guard Guard isolating capture failures, or `null` to build the default.
     */
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private EventCollector $collector,
        InstrumentationGuard|null $guard = null,
    ) {
        $this->guard = $guard ?? new InstrumentationGuard();
    }

    /**
     * Records the event and its immediate caller, then delegates to the application dispatcher.
     *
     * @param object $event Event being dispatched.
     *
     * @return object Event as returned by the application dispatcher.
     */
    public function dispatch(object $event): object
    {
        // Frame zero is this method; frame one is the immediate dispatch caller.
        $frame = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? [];

        $senderClass = $frame['class'] ?? '';

        $this->guard->observe(fn() => $this->collector->record($event, $senderClass));

        return $this->dispatcher->dispatch($event);
    }
}
