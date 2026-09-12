<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPForge\Debug\Panel\Event\{EventInspection, EventRow};
use PHPUnit\Framework\Attributes\{Group, PreserveGlobalState, RunTestsInSeparateProcesses};
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Collector\EventCollector;
use Yii3\Debug\Tests\Support\Captured;
use Yii3\Debug\Tests\Support\Stubs\EventStub;

/**
 * Tests opt-in trace capture when the shared capture helper can no longer read the backtrace.
 *
 * The test runs isolated so the stub can declare the helper before the installed package does.
 */
#[Group('collector')]
#[Group('event')]
#[PreserveGlobalState(false)]
#[RunTestsInSeparateProcesses]
final class UnavailableTraceCaptureTest extends TestCase
{
    public function testCaptureReportsAFailedTraceWithoutLosingTheEvent(): void
    {
        require __DIR__ . '/../Support/Stubs/unavailable-event-capture.php';

        $collector = new EventCollector();

        $collector->traceLimit = 8;

        $collector->startup();

        $collector->record(new EventStub());

        $entry = Captured::event($collector)?->entries()[0] ?? null;

        self::assertInstanceOf(
            EventRow::class,
            $entry,
            'A failed trace must still produce the event row.',
        );

        $inspection = $entry->inspection();

        self::assertInstanceOf(
            EventInspection::class,
            $inspection,
            'A recorded event must carry an inspection.',
        );
        self::assertSame(
            'failed',
            $inspection->getTraceStatus(),
            'An unreadable backtrace must report a failed capture.',
        );
        self::assertSame(
            [],
            $inspection->getTrace(),
            'A failed capture must expose no partial trace.',
        );
    }
}
