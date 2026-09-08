<?php

declare(strict_types=1);

namespace Yii3\Debug\Db;

use PDO;
use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Instrumentation\InstrumentationGuard;
use Yii3\Debug\Collector\DbCollector;
use Yiisoft\Db\Driver\Pdo\PdoConnectionInterface;
use Yiisoft\Db\Profiler\Context\ConnectionContext;
use Yiisoft\Db\Profiler\{ContextInterface, ProfilerAwareInterface, ProfilerInterface};
use Yiisoft\Profiler\ProfilerInterface as ApplicationProfilerInterface;

use function array_flip;
use function array_intersect_key;
use function debug_backtrace;
use function microtime;
use function str_contains;

use const DEBUG_BACKTRACE_IGNORE_ARGS;

/**
 * Adapts Yii DB 2 command profiling to request-scoped Database observations without recording parameters or arguments.
 *
 * Connection and command timings are also forwarded to the application profiler when configured.
 * Only the native method category is forwarded; parameter arrays and exception objects are not copied.
 *
 * Usage example: `(new DebugDbProfiler($collector))->instrument($connection);` in development configuration only.
 */
final class DebugDbProfiler implements ProfilerInterface
{
    /**
     * The active database connection being profiled.
     */
    private PdoConnectionInterface|null $connection = null;
    /**
     * The instrumentation guard used to observe database operations.
     */
    private readonly InstrumentationGuard $guard;

    public function __construct(
        private readonly DbCollector $collector,
        private readonly ApplicationProfilerInterface|null $profiler = null,
    ) {
        $this->guard = new InstrumentationGuard($collector->reportFailure(...));
    }

    /**
     * @param array<array-key, mixed>|ContextInterface $context
     */
    public function begin(string $token, array|ContextInterface $context = []): void
    {
        $this->guard->observe(function () use ($token, $context): void {
            if (!$this->collector->isStarted()) {
                return;
            }

            if (!$context instanceof ContextInterface) {
                return;
            }

            $this->profiler?->begin($token, ['category' => Coerce::string($context->asArray()['method'] ?? $context->getType())]);

            if ($context->getType() !== 'command') {
                return;
            }

            $trace = [];

            foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 24) as $frame) {
                $file = $frame['file'] ?? '';

                if (($frame['class'] ?? '') === self::class
                    || ($frame['class'] ?? '') === InstrumentationGuard::class
                    || $file === ''
                    || str_contains($file, '/vendor/yiisoft/db')) {
                    continue;
                }

                $trace[] = array_intersect_key($frame, array_flip(['file', 'line', 'class', 'function', 'type']));
            }

            $this->collector->begin($token, microtime(true), $trace);
        });
    }

    /**
     * @param array<array-key, mixed>|ContextInterface $context
     */
    public function end(string $token, array|ContextInterface $context = []): void
    {
        $this->guard->observe(function () use ($token, $context): void {
            if ($context instanceof ConnectionContext) {
                $this->installStatementClass();
            }

            if (!$this->collector->isStarted()) {
                return;
            }

            if (!$context instanceof ContextInterface) {
                return;
            }

            $this->profiler?->end($token, ['category' => Coerce::string($context->asArray()['method'] ?? $context->getType())]);

            if ($context->getType() !== 'command') {
                return;
            }

            $this->collector->end($token, microtime(true));
        });
    }

    /**
     * Registers this profiler on the connection and enables driver-reported row counts.
     *
     * Statements are instrumented through `PDO::ATTR_STATEMENT_CLASS`, installed on the live PDO instance immediately
     * when the connection is already open and again on every open, including the opens that precede the captured
     * request. A connection wired with `setProfiler()` alone is still captured, but its Rows column stays empty.
     *
     * Usage example:
     * ```php
     * $profiler = new \Yii3\Debug\Db\DebugDbProfiler($dbCollector);
     * $profiler->instrument($connection);
     * ```
     *
     * @param PdoConnectionInterface&ProfilerAwareInterface $connection Development connection to observe.
     */
    public function instrument(PdoConnectionInterface&ProfilerAwareInterface $connection): void
    {
        $this->connection = $connection;

        $connection->setProfiler($this);

        $this->installStatementClass();
    }

    private function installStatementClass(): void
    {
        $this->connection?->getPdo()?->setAttribute(
            PDO::ATTR_STATEMENT_CLASS,
            [DebugStatement::class, [$this->collector]],
        );
    }
}
