<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Event;

use Error;
use PHPForge\Debug\Instrumentation\InstrumentationGuard;
use PHPForge\Debug\Panel\Event\EventRow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionProperty;
use RuntimeException;
use Throwable;
use Yii3\Debug\Collector\EventCollector;
use Yii3\Debug\Event\DebugEventDispatcher;
use Yii3\Debug\Tests\Support\Stubs\{
    AlternateEventStub,
    AnonymousEventCallerStubFactory,
    CollectorAwareEventDispatcherStub,
    EventStub,
    NestedEventDispatcherStub,
    PassthroughEventDispatcherStub,
    RecordingEventDispatcherStub,
    ResultStub,
    StoppableEventStub,
    StoppingEventDispatcherStub,
    ThrowingEventDispatcherStub,
};

use function array_map;
use function dirname;
use function get_debug_type;

/**
 * Unit tests for transparent, fail-open PSR-14 event-dispatcher instrumentation.
 */
#[Group('event')]
final class DebugEventDispatcherTest extends TestCase
{
    public function testCollectorFailureIsReportedButNeverBreaksTheRealDispatch(): void
    {
        $collector = new EventCollector();

        $collector->startup();

        $events = new ReflectionProperty(EventCollector::class, 'events');

        $events->setValue(
            $collector,
            [PHP_INT_MAX => new EventRow(0.0, 'broken', 'broken', '0', '')],
        );

        $reportedFailure = null;

        $guard = new InstrumentationGuard(
            static function (Throwable $failure) use (&$reportedFailure): void {
                $reportedFailure = $failure;
            },
        );
        $event = new EventStub();
        $innerResult = new ResultStub();
        $inner = new RecordingEventDispatcherStub($innerResult);
        $returned = (new DebugEventDispatcher($inner, $collector, $guard))
            ->dispatch($event);

        self::assertInstanceOf(
            Error::class,
            $reportedFailure,
            'The guard must report the collector append failure.',
        );
        self::assertSame(
            $event,
            $inner->received,
            'Collector failure must not prevent the real dispatch.',
        );
        self::assertSame(
            $innerResult,
            $returned,
            'Collector failure must not alter the real dispatcher result.',
        );
    }

    public function testDispatchNormalizesAnonymousCallerWithoutExposingItsDeclarationPath(): void
    {
        $collector = new EventCollector();

        $caller = AnonymousEventCallerStubFactory::create();

        $event = new EventStub();

        $collector->startup();

        $returned = $caller->dispatch(
            new DebugEventDispatcher(new PassthroughEventDispatcherStub(), $collector),
            $event,
        );

        $senderClass = $collector->capture()?->entries()[0]->senderClass ?? null;

        self::assertSame(
            $event,
            $returned,
            'An anonymous caller must not change the dispatcher result.',
        );
        self::assertSame(
            get_debug_type($caller),
            $senderClass,
            'An anonymous caller must retain its type label without PHP source-location metadata.',
        );
        self::assertStringNotContainsString(
            "\0",
            $senderClass,
            'The source label must not contain a NUL byte.',
        );
        self::assertStringNotContainsString(
            dirname(__DIR__),
            $senderClass,
            'The source label must not expose the test declaration path.',
        );
    }

    public function testDispatchOutsideClassScopeDoesNotInventASender(): void
    {
        $collector = new EventCollector();
        $event = new EventStub();
        $inner = new PassthroughEventDispatcherStub();

        $collector->startup();

        dispatchEventFromFunction(new DebugEventDispatcher($inner, $collector), $event);

        self::assertSame(
            '',
            $collector->capture()?->entries()[0]->senderClass ?? null,
            'A dispatch made outside class scope must keep the sender metadata empty.',
        );
    }

    public function testDispatchPreservesNestedDispatchOrder(): void
    {
        $collector = new EventCollector();
        $outerEvent = new EventStub();
        $nestedEvent = new AlternateEventStub();
        $inner = new NestedEventDispatcherStub($nestedEvent);
        $dispatcher = new DebugEventDispatcher($inner, $collector);

        $inner->decorator = $dispatcher;

        $collector->startup();

        $returned = $dispatcher->dispatch($outerEvent);

        self::assertSame(
            $outerEvent,
            $returned,
            'Nested dispatch must not change the outer return object.',
        );
        self::assertSame(
            [$outerEvent, $nestedEvent],
            $inner->received,
            'The real dispatcher must receive outer and nested events in call order.',
        );
        self::assertSame(
            [$outerEvent::class, $nestedEvent::class],
            array_map(
                static fn(EventRow $row): string => $row->class,
                $collector->capture()?->entries() ?? [],
            ),
            'The collector must preserve the outer-before-nested dispatch order.',
        );
        self::assertSame(
            [self::class, NestedEventDispatcherStub::class],
            array_map(
                static fn(EventRow $row): string => $row->senderClass,
                $collector->capture()?->entries() ?? [],
            ),
            'Each row must identify the immediate class that invoked the decorated dispatcher.',
        );
    }

    public function testDispatchPreservesStoppableEventPropagation(): void
    {
        $collector = new EventCollector();
        $event = new StoppableEventStub();
        $inner = new StoppingEventDispatcherStub();

        $collector->startup();

        $returned = (new DebugEventDispatcher($inner, $collector))->dispatch($event);

        self::assertSame(
            $event,
            $returned,
            'The stoppable event object must round-trip unchanged.',
        );
        self::assertSame(
            1,
            $inner->calledListeners,
            'The decorator must not interfere with stopped propagation.',
        );
        self::assertTrue(
            $event->isPropagationStopped(),
            'The inner dispatcher must retain control of propagation.',
        );
        self::assertCount(
            1,
            $collector->capture()?->entries() ?? [],
            'A stoppable event must still produce exactly one metadata row.',
        );
    }

    public function testDispatchPropagatesTheExactInnerExceptionAfterRecording(): void
    {
        $collector = new EventCollector();
        $event = new EventStub();
        $failure = new RuntimeException('Listener failed.');
        $inner = new ThrowingEventDispatcherStub($failure);

        $collector->startup();

        $caught = null;

        try {
            (new DebugEventDispatcher($inner, $collector))->dispatch($event);
        } catch (Throwable $throwable) {
            $caught = $throwable;
        }

        self::assertSame(
            $failure,
            $caught,
            'The decorator must not replace or suppress dispatcher exceptions.',
        );
        self::assertSame(
            [$event::class],
            array_map(
                static fn(EventRow $row): string => $row->class,
                $collector->capture()?->entries() ?? [],
            ),
            'A failed real dispatch must remain visible because recording happens first.',
        );
        self::assertSame(
            self::class,
            $collector->capture()?->entries()[0]->senderClass ?? null,
            'A failed real dispatch must retain its immediate caller class.',
        );
    }
    public function testDispatchRecordsBeforeDelegatingAndReturnsTheInnerResultExactly(): void
    {
        $collector = new EventCollector();
        $event = new EventStub();
        $innerResult = new ResultStub();
        $inner = new CollectorAwareEventDispatcherStub($collector, $innerResult);

        $collector->startup();

        $returned = (new DebugEventDispatcher($inner, $collector))->dispatch($event);

        self::assertSame(
            $event,
            $inner->received,
            'The inner dispatcher must receive the original event object.',
        );
        self::assertTrue(
            $inner->wasRecordedBeforeDelegation,
            'Event metadata must be recorded before the real dispatcher runs.',
        );
        self::assertSame(
            self::class,
            $collector->capture()?->entries()[0]->senderClass ?? null,
            'The collector must receive the class whose test method invoked the dispatcher.',
        );
        self::assertSame(
            $innerResult,
            $returned,
            'The decorator must return exactly the object returned by the real dispatcher.',
        );
    }
}

function dispatchEventFromFunction(EventDispatcherInterface $dispatcher, object $event): object
{
    return $dispatcher->dispatch($event);
}
