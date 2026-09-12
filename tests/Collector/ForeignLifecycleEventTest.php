<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPForge\Debug\Panel\Event\{EventInspection, EventRow};
use PHPUnit\Framework\Attributes\{Group, PreserveGlobalState, RunTestsInSeparateProcesses};
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Yii3\Debug\Collector\EventCollector;
use Yii3\Debug\Tests\Support\Captured;
use Yii3\Debug\Tests\Support\Stubs\MiddlewareStub;

/**
 * Tests lifecycle capture when the dispatcher events no longer guarantee PSR-7 subjects.
 *
 * Each test runs isolated so the fixture can declare the event names before the installed package does.
 */
#[Group('collector')]
#[Group('event')]
#[PreserveGlobalState(false)]
#[RunTestsInSeparateProcesses]
final class ForeignLifecycleEventTest extends TestCase
{
    public function testCaptureRejectsALifecycleRequestThatIsNotAnHttpMessage(): void
    {
        require __DIR__ . '/../Support/Stubs/before-middleware-event.php';

        $event = self::lifecycleEvent('Yiisoft\\Middleware\\Dispatcher\\Event\\BeforeMiddleware');

        self::assertSame(
            'failed',
            self::inspect($event)->getContextStatus(),
            'A non-HTTP request must report a failed capture instead of being inspected.',
        );
    }

    public function testCaptureRejectsALifecycleResponseThatIsNotAnHttpMessage(): void
    {
        require __DIR__ . '/../Support/Stubs/after-middleware-event.php';

        $event = self::lifecycleEvent('Yiisoft\\Middleware\\Dispatcher\\Event\\AfterMiddleware');

        self::assertSame(
            'failed',
            self::inspect($event)->getContextStatus(),
            'A non-HTTP response must report a failed capture instead of being inspected.',
        );
    }

    /**
     * Records one lifecycle event with context capture enabled and returns its inspection.
     */
    private static function inspect(object $event): EventInspection
    {
        $collector = new EventCollector();

        $collector->captureContext = true;

        $collector->startup();

        $collector->record($event);

        $entry = Captured::event($collector)?->entries()[0] ?? null;

        self::assertInstanceOf(
            EventRow::class,
            $entry,
            'A lifecycle event must produce one typed row.',
        );

        $inspection = $entry->inspection();

        self::assertInstanceOf(
            EventInspection::class,
            $inspection,
            'A lifecycle event must carry an inspection.',
        );

        return $inspection;
    }

    /**
     * Builds a fixture event reflectively, since its signature deliberately differs from the installed package.
     *
     * @param class-string $class Lifecycle event declared by the fixture.
     */
    private static function lifecycleEvent(string $class): object
    {
        return (new ReflectionClass($class))->newInstance(new MiddlewareStub(), new stdClass());
    }
}
