<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;

use function is_float;
use function is_int;
use function memory_get_peak_usage;
use function microtime;

/**
 * Captures the request processing time and peak memory for the Profiling panel.
 *
 * Anchors the timing on `REQUEST_TIME_FLOAT`, falling back to the collector activation time when the SAPI does not
 * provide one; profile blocks stay empty until a Yii3 profiler source feeds them.
 *
 * Usage example:
 *
 * ```php
 * $snapshot = (new \Yii3\Debug\Collector\ProfilingCollector())->capture();
 * ```
 */
final class ProfilingCollector implements CollectorInterface
{
    private bool $active = false;
    private float $start = 0.0;

    /**
     * Snapshots the elapsed processing time and the peak memory usage.
     *
     * Usage example:
     *
     * ```php
     * $snapshot = $collector->capture();
     * ```
     *
     * @return ProfilingSnapshot|null Captured profiling payload; `null` when the collector never started.
     */
    public function capture(): ProfilingSnapshot|null
    {
        if (!$this->active) {
            return null;
        }

        return ProfilingSnapshot::capture(memory_get_peak_usage(), microtime(true) - $this->start, []);
    }

    /**
     * Returns the stable ID pairing this collector with the Profiling panel.
     *
     * Usage example:
     *
     * ```php
     * $id = $collector->id();
     * ```
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'profiling';
    }

    /**
     * Deactivates the collector, so a reused worker process starts clean.
     */
    public function shutdown(): void
    {
        $this->active = false;
        $this->start = 0.0;
    }

    /**
     * Activates the collector and anchors the request start timestamp.
     */
    public function startup(): void
    {
        if ($this->active) {
            return;
        }

        $this->active = true;
        $start = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        $this->start = is_float($start) || is_int($start) ? (float) $start : microtime(true);
    }
}
