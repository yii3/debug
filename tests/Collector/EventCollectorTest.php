<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPForge\Debug\Panel\Event\{EventRow, EventSnapshot};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii3\Debug\Collector\EventCollector;
use Yii3\Debug\Tests\Support\HelperFactory;
use Yii3\Debug\Tests\Support\Stubs\{
    AlternateEventStub,
    AnonymousEventStubFactory,
    AnonymousMiddlewareStubFactory,
    EventStub,
    MiddlewareStub,
    SensitiveEventStub,
    WrappedEventActionStub,
};

use function array_map;
use function array_shift;
use function dirname;
use function get_debug_type;
use function json_encode;
use function microtime;

use const JSON_THROW_ON_ERROR;

/**
 * Unit tests for request-scoped, metadata-only PSR-14 event capture.
 */
#[Group('collector')]
#[Group('event')]
final class EventCollectorTest extends TestCase
{
    public function testCaptureDoesNotInspectMiddlewareMetadataWhileInactive(): void
    {
        $middleware = AnonymousMiddlewareStubFactory::create(
            ['callback' => [WrappedEventActionStub::class, '__invoke']],
        );

        $request = HelperFactory::createRequest();

        $beforeMiddleware = 'Yiisoft\\Middleware\\Dispatcher\\Event\\BeforeMiddleware';

        $collector = new EventCollector();

        $collector->record(new $beforeMiddleware($middleware, $request), 'AnonymousMiddlewareStack');

        self::assertSame(
            0,
            $middleware->debugInfoCalls(),
            'An inactive collector must not inspect middleware metadata.',
        );
        self::assertNull(
            $collector->capture(),
            'An inactive collector must not expose a snapshot.',
        );
    }

    public function testCaptureDoesNotInspectOrPersistTheEventPayload(): void
    {
        $event = new SensitiveEventStub();

        $collector = new EventCollector();

        $collector->startup();
        $collector->record($event);

        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'An active collector must expose its Event snapshot.',
        );

        $entries = $snapshot->entries();

        $entry = array_shift($entries);

        self::assertInstanceOf(
            EventRow::class,
            $entry,
            'The dispatched event must produce one typed row.',
        );
        self::assertSame(
            [
                'entries' => [
                    [
                        'time' => $entry->time,
                        'name' => $event::class,
                        'class' => $event::class,
                        'isStatic' => '0',
                        'senderClass' => '',
                    ],
                ],
            ],
            $snapshot->jsonSerialize(),
            'The persisted row must contain metadata only.',
        );
        self::assertStringNotContainsString(
            $event->secret,
            json_encode($snapshot->jsonSerialize(), JSON_THROW_ON_ERROR),
            'The serialized snapshot must not contain event properties.',
        );
    }

    public function testCaptureFallsBackForUntrustedAnonymousMiddlewareDebugMetadata(): void
    {
        $invalidMiddleware = AnonymousMiddlewareStubFactory::create(
            ['callback' => ['Missing\\Action', '__invoke']],
        );
        $throwingMiddleware = AnonymousMiddlewareStubFactory::create(
            failure: new RuntimeException('Debug metadata is unavailable.'),
        );

        $request = HelperFactory::createRequest();

        $beforeMiddleware = 'Yiisoft\\Middleware\\Dispatcher\\Event\\BeforeMiddleware';

        $collector = new EventCollector();

        $collector->startup();
        $collector->record(new $beforeMiddleware($invalidMiddleware, $request), 'AnonymousMiddlewareStack');
        $collector->record(new $beforeMiddleware($throwingMiddleware, $request), 'AnonymousMiddlewareStack');

        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'An active collector must expose its Event snapshot.',
        );
        $sources = array_map(static fn(EventRow $row): string => $row->senderClass, $snapshot->entries());

        self::assertSame(
            [get_debug_type($invalidMiddleware), get_debug_type($throwingMiddleware)],
            $sources,
            'Untrusted or failing debug metadata must retain the anonymous middleware class as the safe source.',
        );

        foreach ($sources as $source) {
            self::assertStringNotContainsString(
                "\0",
                $source,
                'Anonymous middleware source metadata must not retain PHP\'s internal NUL-delimited path suffix.',
            );
            self::assertStringNotContainsString(
                dirname(__DIR__),
                $source,
                'Anonymous middleware source metadata must not expose the tests filesystem path.',
            );
        }
    }

    public function testCaptureNormalizesAnonymousEventClassWithoutExposingItsDeclarationPath(): void
    {
        $event = AnonymousEventStubFactory::create();

        $collector = new EventCollector();

        $collector->startup();
        $collector->record($event);

        $entry = $collector->capture()?->entries()[0] ?? null;

        self::assertInstanceOf(
            EventRow::class,
            $entry,
            'An anonymous dispatched event must produce one typed row.',
        );

        $expectedClass = get_debug_type($event);

        self::assertSame(
            $expectedClass,
            $entry->name,
            'An anonymous event name must use PHP\'s path-free debug type.',
        );
        self::assertSame(
            $expectedClass,
            $entry->class,
            'An anonymous event class must use PHP\'s path-free debug type.',
        );

        foreach ([$entry->name, $entry->class] as $class) {
            self::assertStringNotContainsString(
                "\0",
                $class,
                'Anonymous event metadata must not retain PHP\'s internal NUL-delimited path suffix.',
            );
            self::assertStringNotContainsString(
                dirname(__DIR__),
                $class,
                'Anonymous event metadata must not expose the tests filesystem path.',
            );
        }
    }

    public function testCaptureRecordsOnlyEventMetadataInDispatchOrder(): void
    {
        $collector = new EventCollector();

        $first = new EventStub();
        $second = new AlternateEventStub();

        $before = microtime(true);

        $collector->startup();
        $collector->record($first, 'App\\Service\\FirstDispatcher');
        $collector->record($second, 'App\\Service\\SecondDispatcher');

        $after = microtime(true);

        $snapshot = $collector->capture();

        self::assertInstanceOf(
            EventSnapshot::class,
            $snapshot,
            'An active collector must expose the canonical Event snapshot.',
        );

        $entries = $snapshot->entries();

        $firstEntry = array_shift($entries);
        $secondEntry = array_shift($entries);

        self::assertInstanceOf(
            EventRow::class,
            $firstEntry,
            'The first dispatch must produce a typed row.',
        );
        self::assertInstanceOf(
            EventRow::class,
            $secondEntry,
            'The second dispatch must produce a typed row.',
        );
        self::assertSame(
            [$first::class, $second::class],
            [$firstEntry->class, $secondEntry->class],
            'Events must remain in dispatch order.',
        );
        self::assertGreaterThanOrEqual(
            $before,
            $firstEntry->time,
            'The first timestamp must be captured after recording begins.',
        );
        self::assertLessThanOrEqual(
            $after,
            $secondEntry->time,
            'The last timestamp must be captured before recording finishes.',
        );
        self::assertLessThanOrEqual(
            $secondEntry->time,
            $firstEntry->time,
            'Capture timestamps must follow dispatch order.',
        );

        foreach ([$firstEntry, $secondEntry] as $index => $entry) {
            $class = $index === 0 ? $first::class : $second::class;
            $senderClass = $index === 0
                ? 'App\\Service\\FirstDispatcher'
                : 'App\\Service\\SecondDispatcher';

            self::assertSame(
                $class,
                $entry->name,
                'The event name must be its FQCN.',
            );
            self::assertSame(
                '0',
                $entry->isStatic,
                'PSR-14 events must be marked as non-static.',
            );
            self::assertSame(
                $senderClass,
                $entry->senderClass,
                'The row must retain the dispatch caller class supplied by the decorator.',
            );
        }
    }

    public function testCaptureReturnsAPointInTimeSnapshot(): void
    {
        $collector = new EventCollector();

        $collector->startup();
        $collector->record(new EventStub());

        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'An active collector must expose a snapshot.',
        );

        $collector->record(new AlternateEventStub());

        self::assertCount(
            1,
            $snapshot->entries(),
            'A captured snapshot must not change when later events are dispatched.',
        );
        self::assertCount(
            2,
            $collector->capture()?->entries() ?? [],
            'A later capture must include events dispatched after the first snapshot.',
        );
    }
    public function testCaptureUsesTheConcreteMiddlewareClassAsLifecycleEventSource(): void
    {
        $middleware = new MiddlewareStub();

        $request = HelperFactory::createRequest();

        $beforeMiddleware = 'Yiisoft\\Middleware\\Dispatcher\\Event\\BeforeMiddleware';
        $afterMiddleware = 'Yiisoft\\Middleware\\Dispatcher\\Event\\AfterMiddleware';

        $collector = new EventCollector();

        $collector->startup();
        $collector->record(new $beforeMiddleware($middleware, $request), 'AnonymousMiddlewareStack');
        $collector->record(new $afterMiddleware($middleware, null), 'AnonymousMiddlewareStack');

        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'An active collector must expose its Event snapshot.',
        );
        self::assertSame(
            [$middleware::class, $middleware::class],
            array_map(static fn(EventRow $row): string => $row->senderClass, $snapshot->entries()),
            'Middleware lifecycle rows must identify the concrete middleware instead of the stack wrapper.',
        );
        self::assertStringNotContainsString(
            'AnonymousMiddlewareStack',
            json_encode($snapshot->jsonSerialize(), JSON_THROW_ON_ERROR),
            'The generic middleware-stack caller must not replace the useful lifecycle-event source.',
        );
    }

    public function testCaptureUsesTheWrappedActionNameAsLifecycleEventSource(): void
    {
        WrappedEventActionStub::$invoked = false;

        $middleware = AnonymousMiddlewareStubFactory::create(
            [
                'callback' => [WrappedEventActionStub::class, '__invoke'],
                'secret' => '<middleware-secret>',
            ],
        );

        $request = HelperFactory::createRequest();

        $beforeMiddleware = 'Yiisoft\\Middleware\\Dispatcher\\Event\\BeforeMiddleware';

        $collector = new EventCollector();

        $collector->startup();
        $collector->record(new $beforeMiddleware($middleware, $request), 'AnonymousMiddlewareStack');

        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'An active collector must expose its Event snapshot.',
        );

        $entries = $snapshot->entries();

        $entry = array_shift($entries);

        self::assertInstanceOf(
            EventRow::class,
            $entry,
            'The wrapped action must produce one typed event row.',
        );
        self::assertSame(
            WrappedEventActionStub::class . '::__invoke',
            $entry->senderClass,
            'A generated action wrapper must expose only its class and method name as the event source.',
        );
        self::assertFalse(
            WrappedEventActionStub::$invoked,
            'Resolving a wrapped action name must not execute its callback.',
        );
        self::assertStringNotContainsString(
            '<middleware-secret>',
            json_encode($snapshot->jsonSerialize(), JSON_THROW_ON_ERROR),
            'Unrelated middleware debug metadata must not be persisted.',
        );
    }

    public function testLifecycleIsIdempotentAndIsolatesReusedWorkerRequests(): void
    {
        $collector = new EventCollector();

        $beforeStartup = new EventStub();
        $firstRequest = new AlternateEventStub();
        $outsideLifecycle = new EventStub();

        self::assertSame(
            'event',
            $collector->id(),
            'The collector ID must match the Event panel payload key.',
        );
        self::assertNull(
            $collector->capture(),
            'An inactive collector must not expose a snapshot.',
        );

        $collector->record($beforeStartup);
        $collector->startup();

        $emptySnapshot = $collector->capture();

        self::assertNotNull(
            $emptySnapshot,
            'An active collector must expose a typed snapshot even without events.',
        );
        self::assertSame(
            [],
            $emptySnapshot->entries(),
            'Events dispatched before startup must not enter the request.',
        );

        $collector->record($firstRequest);
        $collector->startup();

        $firstSnapshot = $collector->capture();

        self::assertNotNull(
            $firstSnapshot,
            'Repeated startup must keep the current request active.',
        );
        self::assertSame(
            [$firstRequest::class],
            array_map(static fn(EventRow $row): string => $row->class, $firstSnapshot->entries()),
            'Repeated startup must not erase events from the active request.',
        );

        $collector->shutdown();

        self::assertNull(
            $collector->capture(),
            'Shutdown must stop snapshot capture.',
        );

        $collector->record($outsideLifecycle);
        $collector->shutdown();
        $collector->startup();

        $nextSnapshot = $collector->capture();

        self::assertNotNull(
            $nextSnapshot,
            'A reused worker must start the next request with a typed snapshot.',
        );
        self::assertSame(
            [],
            $nextSnapshot->entries(),
            'Shutdown and the next startup must prevent events from leaking between requests.',
        );
    }
}
