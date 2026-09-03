<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPUnit\Framework\Attributes\{Group, IgnoreDeprecations};
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Yii3\Debug\Collector\ProfilingCollector;
use Yii3\Debug\Tests\Support\HelperFactory;
use Yiisoft\Profiler\Profiler;

use function array_key_exists;
use function microtime;

/**
 * Unit tests for {@see ProfilingCollector} request metrics, profiler blocks, and reusable-worker lifecycle.
 */
#[Group('collector')]
#[Group('profile')]
#[IgnoreDeprecations('Yiisoft\\\\Profiler\\\\Message::context\\(\\)')]
final class ProfilingCollectorTest extends TestCase
{
    public function testCaptureNormalizesNestedYiiProfilerMessages(): void
    {
        $profiler = new Profiler(new NullLogger());
        $collector = new ProfilingCollector($profiler);

        $collector
            ->startup();
        $profiler
            ->begin('service', ['category' => 'App\\Service::run']);
        $profiler
            ->begin('SELECT 1', ['category' => 'Yiisoft\\Db\\Command::query']);
        $profiler
            ->end('SELECT 1', ['category' => 'Yiisoft\\Db\\Command::query']);
        $profiler
            ->end('service', ['category' => 'App\\Service::run']);

        $snapshot = $collector->capture();

        $collector->shutdown();

        self::assertNotNull(
            $snapshot,
            'An active collector must expose a snapshot.',
        );

        $entries = $snapshot->entries();

        self::assertCount(
            2,
            $entries,
            'Both completed profiler blocks must be captured.',
        );
        self::assertSame(
            'App\\Service::run',
            $entries[0]->category,
            'The outer profiler category must be preserved.',
        );
        self::assertSame(
            0,
            $entries[0]->level,
            'The outer block must retain its top-level depth.',
        );
        self::assertSame(
            'SELECT 1',
            $entries[1]->info,
            'The nested profiler token must become the shared info field.',
        );
        self::assertSame(
            1,
            $entries[1]->level,
            'The nested block must preserve its explicit profiler depth.',
        );
        self::assertCount(
            4,
            $snapshot->samples(),
            'Every captured block must contribute begin and end memory samples.',
        );
    }

    public function testCaptureRetainsMessagesCompletedAfterProfilerFlush(): void
    {
        $profiler = new Profiler(new NullLogger());

        $profiler->begin('previous');
        $profiler->end('previous');

        $collector = new ProfilingCollector($profiler);

        $collector->startup();
        $profiler->flush();
        $profiler->begin('current-first');
        $profiler->end('current-first');
        $profiler->begin('current-second');
        $profiler->end('current-second');

        $snapshot = $collector->capture();

        $collector->shutdown();

        self::assertNotNull(
            $snapshot,
            'The active lifecycle must expose a snapshot after the profiler is flushed.',
        );

        $entries = $snapshot->entries();

        self::assertCount(
            2,
            $entries,
            'A stale pre-flush offset must not skip current profiler blocks.',
        );
        self::assertSame(
            'current-first',
            $entries[0]->info,
            'The first block completed after the flush must be retained.',
        );
        self::assertSame(
            'current-second',
            $entries[1]->info,
            'The second block completed after the flush must be retained.',
        );
    }

    public function testCaptureReturnsMetricsDuringActiveLifecycle(): void
    {
        $collector = new ProfilingCollector(new Profiler(new NullLogger()));

        self::assertSame(
            'profiling',
            $collector->id(),
            'The collector ID must match the Profiling panel payload key.',
        );
        self::assertNull(
            $collector->capture(),
            'An inactive collector must not produce a snapshot.',
        );

        $collector->startup();
        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'An active collector must expose a snapshot.',
        );
        self::assertGreaterThanOrEqual(
            0.0,
            $snapshot->time,
            'Elapsed time must not be negative.',
        );
        self::assertGreaterThan(
            0,
            $snapshot->memory,
            'Peak memory must be positive.',
        );
        self::assertSame(
            [],
            $snapshot->entries(),
            'A profiler with no completed blocks must keep the table empty.',
        );
        self::assertSame(
            [],
            $snapshot->samples(),
            'A profiler with no completed blocks must keep samples empty.',
        );

        $collector->shutdown();

        self::assertNull(
            $collector->capture(),
            'Shutdown must make the collector inactive.',
        );
    }

    public function testCollectRequestAnchorsOnCurrentRequestTimeFloat(): void
    {
        $hadRequestTime = array_key_exists('REQUEST_TIME_FLOAT', $_SERVER);

        $requestTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;

        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 3_600.0;

        try {
            $collector = new ProfilingCollector();

            $collector->startup();
            $collector->collectRequest(
                HelperFactory::createRequest(
                    serverParams: ['REQUEST_TIME_FLOAT' => microtime(true) - 5.0],
                ),
            );

            $snapshot = $collector->capture();

            $collector->shutdown();
        } finally {
            if ($hadRequestTime) {
                $_SERVER['REQUEST_TIME_FLOAT'] = $requestTime;
            } else {
                unset($_SERVER['REQUEST_TIME_FLOAT']);
            }
        }

        self::assertNotNull(
            $snapshot,
            'An active collector must expose a snapshot.',
        );
        self::assertGreaterThan(
            4.0,
            $snapshot->time,
            'Timing must anchor on the current PSR-7 request start timestamp.',
        );
        self::assertLessThan(
            60.0,
            $snapshot->time,
            'Timing must ignore a stale worker-global request timestamp.',
        );
    }

    public function testCollectRequestFallsBackWhenRequestTimeFloatIsUnavailable(): void
    {
        $hadRequestTime = array_key_exists('REQUEST_TIME_FLOAT', $_SERVER);

        $requestTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;

        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 3_600.0;

        try {
            $collector = new ProfilingCollector();

            $collector->startup();
            $collector->collectRequest(HelperFactory::createRequest());

            $snapshot = $collector->capture();

            $collector->shutdown();
        } finally {
            if ($hadRequestTime) {
                $_SERVER['REQUEST_TIME_FLOAT'] = $requestTime;
            } else {
                unset($_SERVER['REQUEST_TIME_FLOAT']);
            }
        }

        self::assertNotNull(
            $snapshot,
            'An active collector must expose a snapshot.',
        );
        self::assertGreaterThanOrEqual(
            0.0,
            $snapshot->time,
            'Fallback timing must not be negative.',
        );
        self::assertLessThan(
            60.0,
            $snapshot->time,
            'Fallback timing must use the current time rather than worker-global state.',
        );
    }

    public function testCollectRequestStartUsesResolvedMiddlewareTimestamp(): void
    {
        $collector = new ProfilingCollector();

        $collector->startup();
        $collector->collectRequestStart(microtime(true) - 5.0);

        $snapshot = $collector->capture();

        $collector->shutdown();

        self::assertNotNull(
            $snapshot,
            'An active collector must expose a snapshot.',
        );
        self::assertGreaterThan(
            4.0,
            $snapshot->time,
            'The middleware-resolved request timestamp must become the profiling origin.',
        );
        self::assertLessThan(
            60.0,
            $snapshot->time,
            'The resolved timestamp must remain scoped to the current request.',
        );
    }

    public function testNewLifecycleSkipsMessagesCompletedBeforeStartup(): void
    {
        $profiler = new Profiler(new NullLogger());

        $profiler->begin('previous');
        $profiler->end('previous');

        $collector = new ProfilingCollector($profiler);

        $collector->startup();
        $profiler->begin('current');
        $profiler->end('current');

        $snapshot = $collector->capture();

        $collector->shutdown();

        self::assertNotNull(
            $snapshot,
            'The active lifecycle must expose a snapshot.',
        );

        $entries = $snapshot->entries();

        self::assertCount(
            1,
            $entries,
            'Only the current lifecycle block must be captured.',
        );
        self::assertSame(
            'current',
            $entries[0]->info,
            'Messages completed before startup must be excluded.',
        );
    }

    public function testStartupAnchorsOnRequestTimeFloatForDirectLifecycleCallers(): void
    {
        $hadRequestTime = array_key_exists('REQUEST_TIME_FLOAT', $_SERVER);

        $requestTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;

        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 5.0;

        try {
            $collector = new ProfilingCollector();

            $collector->startup();

            $snapshot = $collector->capture();

            $collector->shutdown();
        } finally {
            if ($hadRequestTime) {
                $_SERVER['REQUEST_TIME_FLOAT'] = $requestTime;
            } else {
                unset($_SERVER['REQUEST_TIME_FLOAT']);
            }
        }

        self::assertNotNull(
            $snapshot,
            'An active collector must expose a snapshot.',
        );
        self::assertGreaterThan(
            4.0,
            $snapshot->time,
            'Direct lifecycle callers must retain the SAPI request-start fallback.',
        );
    }

    public function testStartupRemainsIdempotentWithoutAdvancingMessageCursor(): void
    {
        $profiler = new Profiler(new NullLogger());
        $collector = new ProfilingCollector($profiler);

        $collector->startup();

        $profiler->begin('current');
        $profiler->end('current');

        $collector->startup();

        $snapshot = $collector->capture();

        $collector->shutdown();

        self::assertNotNull(
            $snapshot,
            'An active collector must expose a snapshot after repeated startup calls.',
        );
        self::assertSame(
            'current',
            $snapshot->entries()[0]->info ?? null,
            'Repeated startup calls must not move the cursor past messages captured in the active lifecycle.',
        );
    }
}
