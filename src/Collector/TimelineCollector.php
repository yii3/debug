<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Panel\Timeline\TimelineSnapshot;

use function is_float;
use function is_int;
use function memory_get_peak_usage;
use function microtime;

/**
 * Captures the request boundaries and peak memory consumed by the Yii3 Timeline panel.
 */
final class TimelineCollector implements CollectorInterface
{
    private bool $active = false;
    private float $start = 0.0;

    /**
     * @return TimelineSnapshot|null Captured Timeline payload, or `null` before startup.
     */
    public function capture(): TimelineSnapshot|null
    {
        if (!$this->active) {
            return null;
        }

        return new TimelineSnapshot(
            $this->start,
            microtime(true),
            memory_get_peak_usage(),
        );
    }

    public function id(): string
    {
        return 'timeline';
    }

    public function shutdown(): void
    {
        $this->active = false;
        $this->start = 0.0;
    }

    public function startup(): void
    {
        if ($this->active) {
            return;
        }

        $start = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;

        $this->active = true;

        $this->start = is_float($start) || is_int($start) ? (float) $start : microtime(true);
    }
}
