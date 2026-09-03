<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Log;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Yii3\Debug\Log\DebugLogTarget;
use Yiisoft\Log\Message;

use function array_map;

/**
 * Unit tests for the in-memory Yiisoft log target accumulator.
 */
#[Group('log')]
#[Group('log-target')]
final class DebugLogTargetTest extends TestCase
{
    public function testCollectExportsEveryBatchAndPreservesEmissionOrder(): void
    {
        $target = new DebugLogTarget();

        $first = self::message('first');
        $second = self::message('second');

        $target->collect(['first' => $first], false);

        self::assertSame(
            [$first],
            $target->messages(),
            'A non-final batch must be exported immediately for request capture.',
        );

        $target->collect(['second' => $second], false);

        self::assertSame(
            [$first, $second],
            $target->messages(),
            'Subsequent batches must append by value in emission order.',
        );
    }

    public function testResetClearsAccumulatedMessages(): void
    {
        $target = new DebugLogTarget();

        $target->collect([self::message('previous request')], false);
        $target->reset();

        self::assertSame(
            [],
            $target->messages(),
            'Reset must isolate the next request from previously accumulated messages.',
        );
    }

    public function testResetClearsMessagesStillBufferedByTheParentTarget(): void
    {
        $target = new DebugLogTarget();

        $target->setExportInterval(10);
        $target->collect([self::message('previous request')], false);
        $target->reset();
        $target->collect([self::message('current request')], true);

        self::assertSame(
            ['current request'],
            array_map(static fn(Message $message): string => $message->message(), $target->messages()),
            'Reset must clear both exported messages and messages buffered by the parent target.',
        );
    }

    private static function message(string $message): Message
    {
        return new Message(
            LogLevel::INFO,
            $message,
            [
                'category' => 'test',
                'memory' => 128,
                'time' => 1.0,
                'trace' => [],
            ],
        );
    }
}
