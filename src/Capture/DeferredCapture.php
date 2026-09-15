<?php

declare(strict_types=1);

namespace Yii3\Debug\Capture;

use Closure;

use function register_shutdown_function;

/**
 * Holds the pending capture finalizer until the application reaches its shutdown phase.
 *
 * Writing the capture from the middleware misses everything produced after the pipeline returns: a lazily rendered
 * response body, log and profiler flushes, and the events dispatched around emission. Deferring the finalizer to
 * `Yiisoft\Yii\Http\Event\ApplicationShutdown` keeps those in the snapshot, and the registered PHP shutdown fallback
 * still writes it when the application never dispatches that event.
 */
final class DeferredCapture
{
    /**
     * Finalizer waiting to run, or `null` when nothing is pending.
     *
     * @var (Closure(): void)|null
     */
    private Closure|null $finalizer = null;
    /**
     * Whether the PHP shutdown fallback was already registered for this instance.
     */
    private bool $registered = false;
    /**
     * Registers the PHP shutdown fallback that finalizes a capture the application never finalized itself.
     *
     * @var Closure(callable(): void): void
     */
    private readonly Closure $shutdownRegistrar;

    /**
     * @param (Closure(callable(): void): void)|null $shutdownRegistrar Registrar receiving the shutdown fallback, or
     * `null` to register it with PHP itself.
     */
    public function __construct(Closure|null $shutdownRegistrar = null)
    {
        $this->shutdownRegistrar = $shutdownRegistrar ?? register_shutdown_function(...);
    }

    /**
     * Drops the pending finalizer without running it.
     */
    public function cancel(): void
    {
        $this->finalizer = null;
    }

    /**
     * Stores the finalizer to run at shutdown, finalizing a capture still pending from an earlier request.
     *
     * @param Closure(): void $finalizer Finalizer writing the capture.
     */
    public function defer(Closure $finalizer): void
    {
        $this->finalize();

        $this->finalizer = $finalizer;

        if ($this->registered) {
            return;
        }

        $this->registered = true;

        ($this->shutdownRegistrar)($this->finalize(...));
    }

    /**
     * Runs the pending finalizer exactly once, clearing it first so a second call stays a no-op.
     */
    public function finalize(): void
    {
        $finalizer = $this->finalizer;

        $this->finalizer = null;

        if ($finalizer !== null) {
            $finalizer();
        }
    }

    /**
     * @return bool `true` when a finalizer is waiting to run; `false` otherwise.
     */
    public function isPending(): bool
    {
        return $this->finalizer !== null;
    }
}
