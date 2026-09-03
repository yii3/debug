<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPForge\Debug\Helper\LogLevel;
use PHPForge\Debug\Panel\Log\LogRow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\{LogLevel as PsrLogLevel, NullLogger};
use Yii3\Debug\Collector\LogCollector;
use Yii3\Debug\Log\DebugLogTarget;
use Yiisoft\Log\{Logger, Message};

use function array_map;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Unit tests for Yiisoft log capture and conversion to the canonical Log panel shape.
 */
#[Group('collector')]
#[Group('log')]
final class LogCollectorTest extends TestCase
{
    public function testCaptureAcceptsANonYiisoftPsrLogger(): void
    {
        $target = new DebugLogTarget();
        $collector = new LogCollector($target, new NullLogger());

        $collector->startup();
        $target->collect([self::message('already exported')], false);

        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'A non-Yiisoft PSR logger must not prevent capture of already exported target messages.',
        );
        self::assertSame(
            ['already exported'],
            array_map(static fn(LogRow $row): string => $row->message, $snapshot->entries()),
            'The optional logger must only be used for Yiisoft-specific flushing.',
        );
    }

    public function testCaptureFlushesYiisoftLoggerAndMapsEveryPsrSeverity(): void
    {
        $target = new DebugLogTarget();
        $logger = new Logger([$target]);
        $collector = new LogCollector($target, $logger);

        $collector->startup();

        foreach (
            [
                PsrLogLevel::EMERGENCY,
                PsrLogLevel::ALERT,
                PsrLogLevel::CRITICAL,
                PsrLogLevel::ERROR,
                PsrLogLevel::WARNING,
                PsrLogLevel::NOTICE,
                PsrLogLevel::INFO,
                PsrLogLevel::DEBUG,
            ] as $level
        ) {
            $logger->log($level, $level);
        }

        self::assertSame(
            [],
            $target->messages(),
            'The fixture must leave messages buffered in the Yiisoft logger before capture.',
        );

        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'An active collector must produce a Log snapshot.',
        );
        self::assertSame(
            [
                LogLevel::ERROR,
                LogLevel::ERROR,
                LogLevel::ERROR,
                LogLevel::ERROR,
                LogLevel::WARNING,
                LogLevel::INFO,
                LogLevel::INFO,
                LogLevel::TRACE,
            ],
            array_map(static fn(LogRow $row): int => $row->level, $snapshot->entries()),
            'Every PSR-3 severity must map to its canonical Log panel wire level.',
        );
        self::assertSame(
            [
                PsrLogLevel::EMERGENCY,
                PsrLogLevel::ALERT,
                PsrLogLevel::CRITICAL,
                PsrLogLevel::ERROR,
                PsrLogLevel::WARNING,
                PsrLogLevel::NOTICE,
                PsrLogLevel::INFO,
                PsrLogLevel::DEBUG,
            ],
            array_map(static fn(LogRow $row): string => $row->message, $snapshot->entries()),
            'Flushing during capture must retain every buffered message in emission order.',
        );
    }

    public function testCaptureNormalizesMalformedAndBinaryLogDataForJsonStorage(): void
    {
        $target = new DebugLogTarget();
        $logger = new Logger([$target]);
        $collector = new LogCollector($target, $logger);

        $collector->startup();
        $logger->info(
            "message\xB1",
            [
                'category' => "category\xB2",
                'trace' => [
                    'not a frame',
                    [
                        'file' => "/app/source\xB3.php",
                        'line' => 42,
                        'function' => "run\xB4",
                        'unsupported' => static fn(): string => 'not JSON serializable',
                    ],
                    ['file', 12],
                    ['file' => new \stdClass()],
                ],
            ],
        );

        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'The active request must produce a Log snapshot.',
        );

        $entries = $snapshot->entries();

        self::assertCount(
            1,
            $entries,
            'Malformed metadata must not discard the complete log message.',
        );
        self::assertSame(
            '(binary: base64 bWVzc2FnZbE=)',
            $entries[0]->message,
            'Binary message text must use the shared JSON-safe representation.',
        );
        self::assertSame(
            '(binary: base64 Y2F0ZWdvcnmy)',
            $entries[0]->category,
            'Binary category text must use the shared JSON-safe representation.',
        );
        self::assertSame(
            [
                [
                    'file' => '(binary: base64 L2FwcC9zb3VyY2WzLnBocA==)',
                    'line' => 42,
                    'function' => '(binary: base64 cnVutA==)',
                ],
            ],
            $entries[0]->trace,
            'Only valid standard trace fields must be retained and their strings must be JSON-safe.',
        );

        self::assertStringContainsString(
            '(binary: base64',
            json_encode($snapshot->jsonSerialize(), JSON_THROW_ON_ERROR),
            'The normalized snapshot must be writable by the JSON snapshot store.',
        );
    }

    public function testCapturePreservesTimeCategoryTraceAndMemory(): void
    {
        $target = new DebugLogTarget();
        $logger = new Logger([$target]);
        $collector = new LogCollector($target, $logger);

        $time = 1_725_000_000.125;
        $trace = [
            [
                'file' => '/app/src/Service.php',
                'line' => 42,
                'function' => 'run',
                'class' => 'App\\Service',
                'type' => '::',
            ],
        ];

        $collector->startup();
        $logger->info(
            'request started',
            [
                'category' => 'App\\Service::run',
                'memory' => 4096,
                'time' => $time,
                'trace' => $trace,
            ],
        );

        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'An active collector must preserve message metadata in a snapshot.',
        );

        $entries = $snapshot->entries();

        self::assertCount(
            1,
            $entries,
            'The logged message must produce one canonical row.',
        );

        $entry = $entries[0];

        self::assertSame(
            'App\\Service::run',
            $entry->category,
            'The Yiisoft message category must be preserved.',
        );
        self::assertSame(
            $time * 1000,
            $entry->time,
            'The Yiisoft timestamp must be preserved in the canonical millisecond representation.',
        );
        self::assertSame(
            $trace,
            $entry->trace,
            'The Yiisoft trace frames must be preserved.',
        );
        self::assertSame(
            4096,
            $entry->memory,
            'The Yiisoft memory sample must be preserved.',
        );
    }

    public function testCollectorDoesNotFlushYiisoftLoggerWithoutItsDebugTarget(): void
    {
        $target = new DebugLogTarget();
        $unrelatedTarget = new DebugLogTarget();
        $logger = new Logger([$unrelatedTarget]);
        $collector = new LogCollector($target, $logger);

        $logger->info('unrelated buffered message');
        $collector->startup();
        $target->collect([self::message('debug target message')], false);

        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'An active collector must produce a Log snapshot.',
        );
        self::assertSame(
            ['debug target message'],
            array_map(static fn(LogRow $row): string => $row->message, $snapshot->entries()),
            'The collector must keep capturing messages already exported to its own target.',
        );
        self::assertSame(
            [],
            $unrelatedTarget->messages(),
            'The collector must not change the flush cadence of a logger that does not contain its debug target.',
        );
    }

    public function testLifecycleDiscardsLoggerMessagesBufferedOutsideTheActiveRequest(): void
    {
        $target = new DebugLogTarget();
        $logger = new Logger([$target]);
        $collector = new LogCollector($target, $logger);

        $logger->info('before startup');
        $collector->startup();
        $logger->info('current request');

        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'The active request must produce a Log snapshot.',
        );
        self::assertSame(
            ['current request'],
            array_map(static fn(LogRow $row): string => $row->message, $snapshot->entries()),
            'Startup must flush application targets without attributing previously buffered messages to this request.',
        );

        $logger->info('after capture');
        $collector->shutdown();
        $collector->startup();
        $logger->info('next request');

        $nextSnapshot = $collector->capture();

        self::assertNotNull(
            $nextSnapshot,
            'The next active request must produce a Log snapshot.',
        );
        self::assertSame(
            ['next request'],
            array_map(static fn(LogRow $row): string => $row->message, $nextSnapshot->entries()),
            'Shutdown must flush and discard messages emitted after capture instead of leaking them into the next request.',
        );
    }

    public function testLifecycleIsIdempotentAndIsolatesRequestMessages(): void
    {
        $target = new DebugLogTarget();
        $collector = new LogCollector($target);

        self::assertSame(
            'log',
            $collector->id(),
            'Collector ID must match the Log panel payload key.',
        );
        self::assertNull(
            $collector->capture(),
            'An inactive collector must not produce a snapshot.',
        );

        $target->collect([self::message('before startup')], false);
        $collector->startup();

        $emptySnapshot = $collector->capture();

        self::assertNotNull(
            $emptySnapshot,
            'An active collector with no messages must still produce a typed snapshot.',
        );
        self::assertSame(
            [],
            $emptySnapshot->entries(),
            'Startup must discard messages accumulated before the request lifecycle.',
        );

        $currentMessage = self::message('current request');

        $target->collect([$currentMessage], false);
        $collector->startup();

        $currentSnapshot = $collector->capture();

        self::assertNotNull(
            $currentSnapshot,
            'Repeated startup must keep the collector active.',
        );
        self::assertSame(
            ['current request'],
            array_map(static fn(LogRow $row): string => $row->message, $currentSnapshot->entries()),
            'Repeated startup must not reset an already active request.',
        );

        $collector->shutdown();

        self::assertNull(
            $collector->capture(),
            'Shutdown must make the collector inactive.',
        );
        self::assertSame(
            [],
            $target->messages(),
            'Shutdown must clear the completed request accumulator.',
        );

        $target->collect([self::message('outside lifecycle')], false);
        $collector->shutdown();

        self::assertSame(
            [],
            $target->messages(),
            'Repeated shutdown must leave request-scoped state cleared.',
        );
    }

    private static function message(string $message): Message
    {
        return new Message(
            PsrLogLevel::INFO,
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
