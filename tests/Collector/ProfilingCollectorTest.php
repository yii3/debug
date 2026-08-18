<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPUnit\Framework\TestCase;
use Yii3\Debug\Collector\ProfilingCollector;

/**
 * Unit tests for {@see ProfilingCollector} capturing request time and peak memory into the shared Profiling payload.
 */
final class ProfilingCollectorTest extends TestCase
{
    public function testCaptureReturnsElapsedTimeAndPeakMemoryDuringActiveLifecycle(): void
    {
        $collector = new ProfilingCollector();

        $collector->startup();
        $snapshot = $collector->capture();
        $collector->shutdown();

        self::assertNotNull($snapshot, 'Active collector must expose a snapshot.');
        self::assertGreaterThan(0.0, $snapshot->time, 'Elapsed time must be positive.');
        self::assertGreaterThan(0, $snapshot->memory, 'Peak memory must be positive.');
        self::assertSame([], $snapshot->entries(), 'Profile blocks must stay empty without a profiler source.');
        self::assertNull($collector->capture(), 'Collector must stop exposing data after shutdown.');
    }
    public function testCaptureReturnsNullWhenCollectorNeverStarted(): void
    {
        self::assertNull((new ProfilingCollector())->capture(), 'Inactive collector must not expose a snapshot.');
    }

    public function testStartupAnchorsOnRequestTimeFloatWhenAvailable(): void
    {
        $original = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 5.0;

        try {
            $collector = new ProfilingCollector();

            $collector->startup();
            $snapshot = $collector->capture();
            $collector->shutdown();
        } finally {
            $_SERVER['REQUEST_TIME_FLOAT'] = $original;
        }

        self::assertNotNull($snapshot, 'Active collector must expose a snapshot.');
        self::assertGreaterThan(4.0, $snapshot->time, 'Timing must anchor on the SAPI start timestamp.');
    }
}
