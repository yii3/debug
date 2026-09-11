<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPForge\Debug\Panel\Db\QueryRow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii3\Debug\Collector\DbCollector;
use Yii3\Debug\Tests\Support\Captured;

use function array_map;

/**
 * Unit tests for {@see DbCollector} lifecycle, completed queries, and isolated observer failures.
 */
#[Group('db')]
final class DbCollectorTest extends TestCase
{
    public function testLifecycleResetsRowsAndUnfinishedQueriesWithoutDoubleStarting(): void
    {
        $collector = new DbCollector();

        $collector->observe(QueryRow::create('ignored', 0.0, 0.0));
        $collector->begin('ignored', 0.0, []);
        $collector->end('ignored', 1.0);

        self::assertFalse(
            $collector->isStarted(),
            'A new collector must be inactive.',
        );
        self::assertNull(
            Captured::db($collector),
            'Inactive capture must not produce a payload.'
        );
        self::assertSame(
            'db',
            $collector->id(),
            'Database must use the shared panel ID.'
        );

        $collector->startup();
        $collector->begin('SELECT 1', 2.0, [['file' => '/app.php', 'line' => 12]]);
        $collector->startup();
        $collector->end('unknown', 3.0);
        $collector->end('SELECT 1', 2.25);
        $collector->end('SELECT 1', 3.0);
        $snapshot = Captured::db($collector);

        self::assertNotNull(
            $snapshot,
            'An active collector must expose a snapshot.'
        );
        self::assertCount(
            1,
            $snapshot->entries(),
            'Unmatched or repeated ends must not add phantom queries.'
        );

        $row = $snapshot->entries()[0];

        self::assertSame(
            250.0,
            $row->getDuration(),
            'Seconds must be converted to milliseconds exactly once.'
        );
        self::assertSame(
            2000.0,
            $row->getTimestamp(),
            'Capture timestamps must use milliseconds.'
        );
        self::assertSame(
            [['file' => '/app.php', 'line' => 12]],
            $row->getTrace(),
            'The source trace must survive completion.'
        );

        $collector->begin('pending', 3.0, []);
        $collector->shutdown();

        self::assertNull(
            Captured::db($collector),
            'Shutdown must stop capture.'
        );

        $collector->startup();
        $collector->end('pending', 4.0);

        self::assertSame(
            [],
            Captured::db($collector)?->entries(),
            'New requests must not inherit unfinished queries.'
        );

        $collector->observe(QueryRow::create('SELECT 2', 1.0, 1000.0)->withSequence(9));

        $row = Captured::db($collector)?->entries()[0] ?? null;

        self::assertInstanceOf(
            QueryRow::class,
            $row,
            'The observed query must be captured.',
        );
        self::assertSame(
            0,
            $row->getSequence(),
            'New requests must restart the sequence.'
        );
    }

    public function testReportedRowCountsAttachToTheNextCompletedQueryOnly(): void
    {
        $collector = new DbCollector();

        $collector->reportRows(9);
        $collector->startup();
        $collector->begin('SELECT 1', 0.0, []);
        $collector->end('SELECT 1', 1.0);
        $collector->begin('UPDATE t', 1.0, []);
        $collector->reportRows(2);
        $collector->reportRows(5);
        $collector->end('UPDATE t', 2.0);
        $collector->begin('SELECT 2', 2.0, []);
        $collector->end('SELECT 2', 3.0);

        self::assertSame(
            [null, 5, null],
            $this->reported($collector),
            'Only the last report before an end counts.'
        );

        $collector->reportRows(7);
        $collector->shutdown();
        $collector->startup();
        $collector->begin('SELECT 3', 3.0, []);
        $collector->end('SELECT 3', 4.0);

        self::assertSame(
            [null],
            $this->reported($collector),
            'A new request must not inherit a pending count.'
        );
    }

    public function testThrowRuntimeExceptionWhenInstrumentationFailedAndResetOnShutdown(): void
    {
        $collector = new DbCollector();

        $collector->startup();

        $failure = new RuntimeException('observer failure');

        $collector->reportFailure($failure);

        try {
            Captured::db($collector);

            self::fail(
                'Capture must surface the instrumentation failure to the coordinator.',
            );
        } catch (RuntimeException $caught) {
            self::assertSame(
                $failure,
                $caught,
                'The original observer failure must be retained.'
            );
        }

        $collector->shutdown();
        $collector->startup();

        self::assertSame(
            [],
            Captured::db($collector)?->entries(),
            'Failures must not contaminate a later request.'
        );
    }

    /**
     * @return list<int|null>
     */
    private function reported(DbCollector $collector): array
    {
        return array_map(
            static fn(QueryRow $row): int|null => $row->getRows(),
            Captured::db($collector)?->entries() ?? [],
        );
    }
}
