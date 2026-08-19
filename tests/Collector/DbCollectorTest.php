<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Yii3\Debug\Collector\DbCollector;
use Yiisoft\Db\Profiler\Context\CommandContext;
use Yiisoft\Profiler\Profiler;

/**
 * Unit tests for {@see DbCollector} recording profiled queries into the shared Database payload.
 */
final class DbCollectorTest extends TestCase
{
    public function testBeginIsIgnoredWhileCollectorIsInactive(): void
    {
        $collector = new DbCollector();

        $collector->begin('SELECT 1');
        $collector->startup();
        $collector->end('SELECT 1');

        self::assertSame(0, $collector->queryCount(), 'Blocks opened before startup must be discarded.');

        $collector->shutdown();
    }

    public function testCaptureRecordsProfiledQueriesWithTypeDurationAndDuplicates(): void
    {
        $collector = new DbCollector();

        $collector->startup();
        $collector->begin('SELECT * FROM users');
        $collector->end('SELECT * FROM users');
        $collector->begin('SELECT * FROM users');
        $collector->end('SELECT * FROM users');
        $collector->begin('  insert INTO logs VALUES (1)');
        $collector->end('  insert INTO logs VALUES (1)');

        self::assertSame(3, $collector->queryCount(), 'Completed blocks must be counted.');

        $snapshot = $collector->capture();

        $collector->shutdown();

        self::assertNotNull($snapshot, 'Active collector must expose a snapshot.');

        $entries = $snapshot->entries();

        self::assertCount(3, $entries, 'Every completed block must produce a row.');
        self::assertSame('SELECT', $entries[0]->type, 'Leading verb must become the query type.');
        self::assertSame(2, $entries[0]->duplicate, 'Repeated SQL must be counted as duplicate.');
        self::assertSame('INSERT', $entries[2]->type, 'Type extraction must trim and uppercase.');
        self::assertGreaterThanOrEqual(0.0, $entries[0]->duration, 'Duration must be non-negative.');
        self::assertNull($collector->capture(), 'Collector must stop exposing data after shutdown.');
    }
    public function testCaptureReturnsNullWhenCollectorNeverStarted(): void
    {
        self::assertNull((new DbCollector())->capture(), 'Inactive collector must not expose a snapshot.');
    }

    public function testEndIgnoresTokensWithoutMatchingBegin(): void
    {
        $collector = new DbCollector();

        $collector->startup();
        $collector->end('SELECT 1');

        self::assertSame(0, $collector->queryCount(), 'Unmatched end must not record a timing.');

        $collector->shutdown();
    }

    #[IgnoreDeprecations('Yiisoft\\\\Profiler\\\\Message::context\\(\\)')]
    public function testForwardsCompletedQueriesToTheApplicationProfiler(): void
    {
        $profiler = new Profiler(new NullLogger());
        $collector = new DbCollector($profiler);
        $context = new CommandContext('Yiisoft\\Db\\Command::queryAll', 'database', 'SELECT 1', []);

        $collector->startup();
        $collector->begin('SELECT 1', $context);
        $collector->end('SELECT 1', $context);
        $collector->shutdown();

        $messages = $profiler->getMessages();
        $message = $messages[0] ?? self::fail('Expected one forwarded profiler message.');

        self::assertCount(1, $messages, 'Every completed DB block must be forwarded once.');
        self::assertSame('SELECT 1', $message->token(), 'Forwarded token must preserve the SQL statement.');
        self::assertSame(
            'Yiisoft\\Db\\Command::queryAll',
            $message->context('category'),
            'Forwarded category must activate Profiling SQL rendering and the Timeline database variant.',
        );
        self::assertIsArray($message->context('trace'), 'Forwarded context must retain the captured DB backtrace.');
    }
}
