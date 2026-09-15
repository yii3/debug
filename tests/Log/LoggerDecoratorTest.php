<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Log;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Yii3\Debug\Log\{DebugLogTarget, LoggerDecorator};
use Yiisoft\Log\{Logger, StreamTarget};

/**
 * Unit tests for {@see LoggerDecorator} attaching {@see DebugLogTarget} to the application logger.
 */
#[Group('log')]
final class LoggerDecoratorTest extends TestCase
{
    public function testDecorateAppendsTheTargetAndKeepsTheApplicationTargets(): void
    {
        $applicationTarget = new StreamTarget();
        $logger = new Logger(['stream' => $applicationTarget]);
        $target = new DebugLogTarget();

        $decorated = LoggerDecorator::decorate($logger, $target);

        self::assertInstanceOf(
            Logger::class,
            $decorated,
            'The rebuilt logger must stay a Yii logger.',
        );
        self::assertNotSame(
            $logger,
            $decorated,
            'The application logger must be rebuilt, not mutated.',
        );
        self::assertSame(
            ['stream' => $applicationTarget, $target],
            $decorated->getTargets(),
            'Order: application targets first, debugger target last.',
        );
        self::assertSame(
            ['stream' => $applicationTarget],
            $logger->getTargets(),
            'The original logger must keep its own targets.',
        );
    }

    public function testDecorateReturnsAForeignLoggerUnchanged(): void
    {
        $logger = new NullLogger();

        self::assertSame(
            $logger,
            LoggerDecorator::decorate($logger, new DebugLogTarget()),
            'A logger with no target API must pass through.',
        );
    }

    public function testDecorateSkipsALoggerAlreadyWritingToTheTarget(): void
    {
        $target = new DebugLogTarget();
        $logger = new Logger([new StreamTarget(), $target]);

        self::assertSame(
            $logger,
            LoggerDecorator::decorate($logger, $target),
            'Decorating twice must not add a second target.',
        );
    }
}
