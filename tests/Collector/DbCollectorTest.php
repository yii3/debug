<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPUnit\Framework\TestCase;
use Yii3\Debug\Collector\DbCollector;

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
}
