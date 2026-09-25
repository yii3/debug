<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Capture;

use Closure;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Capture\DeferredCapture;

/**
 * Unit tests for {@see DeferredCapture} pending-finalizer lifecycle and shutdown fallback registration.
 */
#[Group('capture')]
final class DeferredCaptureTest extends TestCase
{
    public function testArmedFallbackRunsOnlyFromTheShutdownFallback(): void
    {
        $registered = [];
        $runs = [];

        $capture = new DeferredCapture(self::registrar($registered));

        $capture->arm(
            static function () use (&$runs): void {
                $runs[] = 'armed';
            },
        );
        $capture->defer(
            static function () use (&$runs): void {
                $runs[] = 'pending';
            },
        );
        $capture->finalize();

        $beforeShutdown = $runs;

        self::assertCount(
            1,
            $registered,
            'Arming and deferring must share one fallback.',
        );

        $registered[0]();
        $registered[0]();

        self::assertSame(
            ['pending'],
            $beforeShutdown,
            'Deferral and finalization must leave the armed capture alone.',
        );
        self::assertSame(
            ['pending', 'armed'],
            $runs,
            'Shutdown must write the armed capture exactly once.',
        );
    }

    public function testCancelDropsThePendingFinalizerWithoutRunningIt(): void
    {
        $registered = [];
        $runs = 0;

        $capture = new DeferredCapture(self::registrar($registered));

        $capture->defer(
            static function () use (&$runs): void {
                $runs++;
            },
        );
        $capture->cancel();
        $capture->finalize();

        self::assertFalse(
            $capture->isPending(),
            'Cancelled work must leave nothing pending.',
        );
        self::assertSame(
            0,
            $runs,
            'Cancelled work must never run.',
        );
    }

    public function testDefaultRegistrarHandsTheFallbackToPhpShutdown(): void
    {
        $capture = new DeferredCapture();
        $runs = 0;

        $capture->defer(
            static function () use (&$runs): void {
                $runs++;
            },
        );

        self::assertTrue(
            $capture->isPending(),
            'Deferred work must wait for the shutdown phase.',
        );

        $capture->finalize();

        self::assertSame(
            1,
            $runs,
            'The default registrar must not change how the finalizer runs.',
        );
        self::assertFalse(
            $capture->isPending(),
            'Nothing may stay pending for the PHP shutdown fallback.',
        );
    }

    public function testDeferFinalizesTheCaptureStillPendingFromAnEarlierRequest(): void
    {
        $registered = [];
        $runs = [];

        $capture = new DeferredCapture(self::registrar($registered));

        $capture->defer(
            static function () use (&$runs): void {
                $runs[] = 'first';
            },
        );
        $capture->defer(
            static function () use (&$runs): void {
                $runs[] = 'second';
            },
        );

        $afterReplacement = $runs;

        $capture->finalize();

        self::assertSame(
            ['first'],
            $afterReplacement,
            'The replaced capture must be written before the new one is stored.',
        );
        self::assertSame(
            ['first', 'second'],
            $runs,
            'The stored capture must still run at shutdown.',
        );
    }

    public function testDeferRegistersTheShutdownFallbackOnceForEveryDeferral(): void
    {
        $registered = [];

        $capture = new DeferredCapture(self::registrar($registered));

        $capture->defer(static function (): void {});
        $capture->finalize();
        $capture->defer(static function (): void {});

        self::assertCount(
            1,
            $registered,
            'One instance must never hold more than one fallback.',
        );
    }

    public function testDisarmDropsTheArmedFallbackWithoutRunningIt(): void
    {
        $registered = [];
        $runs = 0;

        $capture = new DeferredCapture(self::registrar($registered));

        $capture->arm(
            static function () use (&$runs): void {
                $runs++;
            },
        );
        $capture->disarm();

        self::assertCount(
            1,
            $registered,
            'Arming must register the fallback.',
        );

        $registered[0]();

        self::assertSame(
            0,
            $runs,
            'A disarmed capture must never run.',
        );
    }

    public function testFinalizeRunsThePendingFinalizerExactlyOnce(): void
    {
        $registered = [];
        $runs = 0;

        $capture = new DeferredCapture(self::registrar($registered));

        self::assertFalse(
            $capture->isPending(),
            'A fresh instance must hold nothing.',
        );

        $capture->defer(
            static function () use (&$runs): void {
                $runs++;
            },
        );

        self::assertTrue(
            $capture->isPending(),
            'Deferred work must wait for the shutdown phase.',
        );

        $capture->finalize();
        $capture->finalize();

        self::assertSame(
            1,
            $runs,
            'A second call must stay a no-op.',
        );
        self::assertFalse(
            $capture->isPending(),
            'The finalizer must be cleared before it runs.',
        );
    }

    public function testFinalizeWithoutAPendingFinalizerDoesNothing(): void
    {
        $registered = [];

        $capture = new DeferredCapture(self::registrar($registered));

        $capture->finalize();

        self::assertFalse(
            $capture->isPending(),
            'An empty instance must stay empty.',
        );
        self::assertSame(
            [],
            $registered,
            'Nothing may be registered before the first deferral.',
        );
    }

    public function testRegisteredFallbackFinalizesTheCaptureTheApplicationNeverFinalized(): void
    {
        $registered = [];
        $runs = 0;

        $capture = new DeferredCapture(self::registrar($registered));

        $capture->defer(
            static function () use (&$runs): void {
                $runs++;
            },
        );

        self::assertCount(
            1,
            $registered,
            'The first deferral must register the fallback.',
        );

        $registered[0]();

        self::assertSame(
            1,
            $runs,
            'The fallback must write the capture the application left pending.',
        );
        self::assertFalse(
            $capture->isPending(),
            'The fallback must clear the pending finalizer.',
        );
    }

    /**
     * Builds a registrar recording every fallback handed to it, in registration order.
     *
     * @param list<Closure(): void> $registered Recorded fallbacks.
     *
     * @return Closure(callable(): void): void Recording registrar.
     */
    private static function registrar(array &$registered): Closure
    {
        /** @param callable(): void $finalize */
        return static function (callable $finalize) use (&$registered): void {
            $registered[] = $finalize(...);
        };
    }
}
