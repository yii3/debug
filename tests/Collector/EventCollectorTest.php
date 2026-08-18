<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use stdClass;
use Yii3\Debug\Collector\EventCollector;
use Yii3\Debug\Event\DebugEventDispatcher;

/**
 * Unit tests for {@see EventCollector} and {@see DebugEventDispatcher} capturing PSR-14 events.
 */
final class EventCollectorTest extends TestCase
{
    public function testCaptureReturnsNullWhenCollectorNeverStarted(): void
    {
        self::assertNull((new EventCollector())->capture(), 'Inactive collector must not expose a snapshot.');
    }

    public function testDispatchRecordsEventAndDelegatesToRealDispatcher(): void
    {
        $collector = new EventCollector();
        $inner = new class implements EventDispatcherInterface {
            public object|null $dispatched = null;

            public function dispatch(object $event): object
            {
                $this->dispatched = $event;

                return $event;
            }
        };
        $dispatcher = new DebugEventDispatcher($inner, $collector);
        $event = new stdClass();

        $collector->startup();
        $returned = $dispatcher->dispatch($event);
        $snapshot = $collector->capture();
        $collector->shutdown();

        self::assertSame($event, $returned, 'Dispatch must return the real dispatcher result.');
        self::assertSame($event, $inner->dispatched, 'Real dispatcher must receive the event.');
        self::assertNotNull($snapshot, 'Active collector must expose a snapshot.');
        self::assertCount(1, $snapshot->entries(), 'One dispatched event must be recorded.');
        self::assertSame(stdClass::class, $snapshot->entries()[0]->class, 'Row must carry the event class.');
        self::assertNull($collector->capture(), 'Collector must stop exposing data after shutdown.');
    }

    public function testRecordIgnoresEventsWhileCollectorIsInactive(): void
    {
        $collector = new EventCollector();

        $collector->record(new stdClass());
        $collector->startup();
        $snapshot = $collector->capture();
        $collector->shutdown();

        self::assertNotNull($snapshot, 'Active collector must expose a snapshot.');
        self::assertSame([], $snapshot->entries(), 'Events before startup must be discarded.');
    }
}
