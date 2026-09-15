<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Profiling;

use PHPUnit\Framework\Attributes\{Group, IgnoreDeprecations};
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Yii3\Debug\Profiling\DebugProfilerTarget;
use Yiisoft\Profiler\Message;
use Yiisoft\Profiler\Profiler;

use function array_map;

/**
 * Unit tests for {@see DebugProfilerTarget} in-memory accumulation of the spans the profiler flushes.
 */
#[Group('profile')]
#[IgnoreDeprecations('Yiisoft\\\\Profiler\\\\Message::context\\(\\)')]
final class DebugProfilerTargetTest extends TestCase
{
    public function testCollectKeepsOnlyTheCategoriesTheTargetIsInterestedIn(): void
    {
        $profiler = new Profiler(new NullLogger());

        $profiler->begin('kept', ['category' => 'application']);
        $profiler->end('kept', ['category' => 'application']);
        $profiler->begin('dropped', ['category' => 'database']);
        $profiler->end('dropped', ['category' => 'database']);

        $target = (new DebugProfilerTarget())->exclude(['database']);

        $target->collect($profiler->getMessages());

        self::assertSame(
            ['kept'],
            self::tokens($target),
            'Excluded categories must not reach the accumulator.',
        );
    }

    public function testExportAccumulatesEveryFlushOfTheRequest(): void
    {
        $target = new DebugProfilerTarget();
        $profiler = new Profiler(new NullLogger(), [$target]);

        self::assertSame(
            [],
            $target->messages(),
            'A fresh target must hold nothing.',
        );

        $profiler->begin('first');
        $profiler->end('first');
        $profiler->flush();
        $profiler->begin('second');
        $profiler->end('second');
        $profiler->flush();

        self::assertSame(
            ['first', 'second'],
            self::tokens($target),
            'Order: flush order preserved.',
        );
    }

    public function testExportKeepsNothingForAnEmptyFlush(): void
    {
        $target = new DebugProfilerTarget();
        $profiler = new Profiler(new NullLogger(), [$target]);

        $profiler->flush();

        self::assertSame(
            [],
            $target->messages(),
            'A flush with no spans must leave the accumulator empty.',
        );
    }

    public function testResetClearsTheMessagesOfThePreviousRequest(): void
    {
        $target = new DebugProfilerTarget();
        $profiler = new Profiler(new NullLogger(), [$target]);

        $profiler->begin('previous');
        $profiler->end('previous');
        $profiler->flush();

        $target->reset();

        self::assertSame(
            [],
            $target->messages(),
            'Reset must drop every accumulated span.',
        );

        $profiler->begin('current');
        $profiler->end('current');
        $profiler->flush();

        self::assertSame(
            ['current'],
            self::tokens($target),
            'Only the spans of the new request may remain.',
        );
    }

    /**
     * @return list<string> Tokens of the messages accumulated by the target, in export order.
     */
    private static function tokens(DebugProfilerTarget $target): array
    {
        return array_map(static fn(Message $message): string => $message->token(), $target->messages());
    }
}
