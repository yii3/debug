<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPForge\Debug\Panel\Event\EventRow;
use PHPUnit\Framework\Attributes\{Group, PreserveGlobalState, RunTestsInSeparateProcesses};
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Collector\EventCollector;
use Yii3\Debug\Tests\Support\{Captured, HelperFactory};
use Yii3\Debug\Tests\Support\Stubs\MiddlewareStub;

/**
 * Tests the conversion of the monotonic lifecycle clock from nanoseconds to seconds.
 *
 * The test runs isolated so the stub can replace the clock before the collector first reads it.
 */
#[Group('collector')]
#[Group('event')]
#[PreserveGlobalState(false)]
#[RunTestsInSeparateProcesses]
final class EventCollectorClockTest extends TestCase
{
    public function testCaptureConvertsTheMonotonicClockToSeconds(): void
    {
        require __DIR__ . '/../Support/Stubs/fixed-event-clock.php';

        $beforeMiddleware = 'Yiisoft\\Middleware\\Dispatcher\\Event\\BeforeMiddleware';

        $collector = new EventCollector();

        $collector->startup();

        $collector->record(new $beforeMiddleware(new MiddlewareStub(), HelperFactory::createRequest()));

        $entry = Captured::event($collector)?->entries()[0] ?? null;

        self::assertInstanceOf(
            EventRow::class,
            $entry,
            'A lifecycle event must produce one typed row.',
        );
        self::assertSame(
            1.5,
            $entry->inspection()?->getClock(),
            'A 1.5e9 ns reading must be stored as 1.5 seconds.',
        );
    }
}
