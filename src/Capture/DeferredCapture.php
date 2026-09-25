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
 *
 * While the request handler runs, {@see arm()} keeps a second, separate fallback for the capture in progress, so a
 * script that ends inside the handler, through `exit`, `dd()`, or a fatal error, still writes it, as the Yii2 logger
 * flush does at shutdown.
 */
final class DeferredCapture
{
    /**
     * Fallback writing the capture of the request still being handled, or `null` when none is armed.
     *
     * @var (Closure(): void)|null
     */
    private Closure|null $fallback = null;
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
     * Stores the fallback that writes the capture in progress when the script ends before the request handler returns.
     *
     * The fallback is kept apart from the pending finalizer, so a {@see defer()} or {@see finalize()} reached while the
     * handler runs never writes the capture in progress; only the PHP shutdown fallback runs it.
     *
     * @param Closure(): void $fallback Finalizer writing the capture of the request being handled.
     */
    public function arm(Closure $fallback): void
    {
        $this->fallback = $fallback;

        $this->register();
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

        $this->register();
    }

    /**
     * Drops the fallback {@see arm()} stored, once the request handler returned or failed.
     */
    public function disarm(): void
    {
        $this->fallback = null;
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

    /**
     * Hands the PHP shutdown fallback to the registrar the first time a capture is armed or deferred.
     */
    private function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        ($this->shutdownRegistrar)($this->shutdown(...));
    }

    /**
     * Writes the capture the script left behind: the armed one first, then the pending one.
     *
     * Each is cleared before it runs, so neither can run twice.
     */
    private function shutdown(): void
    {
        $fallback = $this->fallback;

        $this->fallback = null;

        if ($fallback !== null) {
            $fallback();
        }

        $this->finalize();
    }
}
