<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Yii3\Debug\Collector\ProfilingCollector;
use Yiisoft\Profiler\Profiler;

/**
 * Unit tests for {@see ProfilingCollector} capturing request time and peak memory into the shared Profiling payload.
 */
final class ProfilingCollectorTest extends TestCase
{
    #[IgnoreDeprecations('Yiisoft\\\\Profiler\\\\Message::context\\(\\)')]
    public function testCaptureNormalizesNestedYiiProfilerMessages(): void
    {
        $profiler = new Profiler(new NullLogger());
        $collector = new ProfilingCollector($profiler);

        $collector->startup();
        $profiler->begin('service', ['category' => 'App\\Service::run']);
        $profiler->begin('SELECT 1', ['category' => 'Yiisoft\\Db\\Command::query']);
        $profiler->end('SELECT 1', ['category' => 'Yiisoft\\Db\\Command::query']);
        $profiler->end('service', ['category' => 'App\\Service::run']);
        $snapshot = $collector->capture();
        $collector->shutdown();

        self::assertNotNull($snapshot, 'Active collector must expose a snapshot.');
        $entries = $snapshot->entries();

        self::assertCount(3, $entries, 'Request root and both completed profiler blocks must be captured.');
        self::assertSame('Yii3\\Application::handle', $entries[0]->category, 'Synthetic request root must come first.');
        self::assertSame(0, $entries[0]->level, 'Request root must sit at nesting level zero.');
        self::assertSame('App\\Service::run', $entries[1]->category, 'Outer profiler category must be preserved.');
        self::assertSame(1, $entries[1]->level, 'Outer profiler block must nest under the request root.');
        self::assertSame('SELECT 1', $entries[2]->info, 'Nested profiler token must become the shared info field.');
        self::assertSame(2, $entries[2]->level, 'Nested DB block must preserve its depth below the request root.');
        self::assertCount(6, $snapshot->samples(), 'Every captured block must contribute begin and end memory samples.');
    }
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
