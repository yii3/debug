<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPUnit\Framework\TestCase;
use Yii3\Debug\Collector\TimelineCollector;

/**
 * Unit tests for {@see TimelineCollector} capturing request boundaries and peak memory.
 */
final class TimelineCollectorTest extends TestCase
{
    public function testCaptureReturnsNullBeforeStartup(): void
    {
        self::assertNull((new TimelineCollector())->capture(), 'Inactive collector must not expose a snapshot.');
    }
    public function testCaptureReturnsRequestGeometryDuringActiveLifecycle(): void
    {
        $collector = new TimelineCollector();

        $collector->startup();
        $snapshot = $collector->capture();
        $collector->shutdown();

        self::assertNotNull($snapshot, 'Active collector must expose a Timeline snapshot.');
        self::assertGreaterThanOrEqual($snapshot->start, $snapshot->end, 'Request end must not precede its start.');
        self::assertGreaterThan(0, $snapshot->memory, 'Timeline must capture positive peak memory.');
        self::assertNull($collector->capture(), 'Shutdown collector must stop exposing data.');
    }
}
