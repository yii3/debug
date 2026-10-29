<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPForge\Debug\Panel\Event\EventRow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Xepozz\InternalMocker\MockerState;
use Yii3\Debug\Collector\EventCollector;
use Yii3\Debug\Tests\Support\{Captured, HelperFactory};
use Yii3\Debug\Tests\Support\Stubs\MiddlewareStub;

/**
 * Tests the conversion of the monotonic lifecycle clock from nanoseconds to seconds.
 *
 * The native `hrtime()` is mocked in the collector namespace, so the lifecycle clock reading becomes deterministic.
 */
#[Group('collector')]
#[Group('event')]
final class EventCollectorClockTest extends TestCase
{
    public function testCaptureConvertsTheMonotonicClockToSeconds(): void
    {
        MockerState::addCondition('Yii3\\Debug\\Collector', 'hrtime', [true], 1_500_000_000);

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
