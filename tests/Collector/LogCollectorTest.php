<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPForge\Debug\Helper\LogLevel;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Collector\LogCollector;
use Yii3\Debug\Log\DebugLogTarget;
use Yiisoft\Log\Logger;

/**
 * Unit tests for {@see LogCollector} converting Yii3 log messages into the shared Logs payload.
 */
final class LogCollectorTest extends TestCase
{
    public function testCaptureFlushesLoggerAndMapsSeverityNamesOntoWireLevels(): void
    {
        $target = new DebugLogTarget();
        $logger = new Logger([$target]);
        $collector = new LogCollector($target, $logger);

        $collector->startup();
        $logger->error('database went away');
        $logger->warning('slow query detected');
        $logger->info('request started');
        $logger->debug('resolving container id');

        $snapshot = $collector->capture();

        $collector->shutdown();

        self::assertNotNull($snapshot, 'Active collector must expose a snapshot.');

        $entries = $snapshot->entries();

        self::assertCount(4, $entries, 'Every logged message must be captured.');
        self::assertSame(LogLevel::ERROR, $entries[0]->level, 'PSR-3 `error` must map to the shared error level.');
        self::assertSame(LogLevel::WARNING, $entries[1]->level, 'PSR-3 `warning` must map to the warning level.');
        self::assertSame(LogLevel::INFO, $entries[2]->level, 'PSR-3 `info` must map to the info level.');
        self::assertSame(LogLevel::TRACE, $entries[3]->level, 'PSR-3 `debug` must map to the trace level.');
        self::assertSame('database went away', $entries[0]->message, 'Message text must be preserved.');
        self::assertGreaterThan(0.0, $entries[0]->time, 'Message timestamp must be captured.');
        self::assertNull($collector->capture(), 'Collector must stop exposing data after shutdown.');
    }
    public function testCaptureReturnsNullWhenCollectorNeverStarted(): void
    {
        $collector = new LogCollector(new DebugLogTarget());

        self::assertNull($collector->capture(), 'Inactive collector must not expose a snapshot.');
    }

    public function testShutdownClearsTheAccumulatedTarget(): void
    {
        $target = new DebugLogTarget();
        $logger = new Logger([$target]);
        $collector = new LogCollector($target, $logger);

        $collector->startup();
        $logger->info('first request');
        $logger->flush();
        $collector->shutdown();

        self::assertSame([], $target->messages(), 'Accumulator must be empty after shutdown.');
    }
}
