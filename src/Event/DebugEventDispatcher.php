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
    private InstrumentationGuard $guard;

    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private EventCollector $collector,
        InstrumentationGuard|null $guard = null,
    ) {
        $this->guard = $guard ?? new InstrumentationGuard();
    }

    public function dispatch(object $event): object
    {
        // Frame zero is this method; frame one is the immediate dispatch caller.
        $frame = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? [];

        $senderClass = $frame['class'] ?? '';

        $this->guard->observe(fn() => $this->collector->record($event, $senderClass));

        return $this->dispatcher->dispatch($event);
    }
}
