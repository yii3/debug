<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Db;

use PHPForge\Debug\Panel\Db\QueryRow;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Yii3\Debug\Collector\{DbCollector, ProfilingCollector};
use Yii3\Debug\Db\DebugDbProfiler;
use Yii3\Debug\Tests\Provider\DebugDbProfilerProvider;
use Yii3\Debug\Tests\Support\{Captured, DatabaseFixture};
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Profiler\Context\{CommandContext, ConnectionContext};
use Yiisoft\Db\Profiler\ContextInterface;
use Yiisoft\Profiler\Profiler;

use function array_intersect_key;
use function array_map;
use function array_values;

/**
 * Tests for {@see DebugDbProfiler} covering SQLite command capture, driver row counts, application-profiler
 * forwarding, trace limits, and observer lifecycle and failure handling.
 *
 * {@see DebugDbProfilerProvider} for context forwarding cases.
 */
#[Group('db')]
final class DebugDbProfilerTest extends TestCase
{
    /**
     * @param CommandContext|ConnectionContext $context Native context containing diagnostic data.
     * @param string $expectedCategory Method category expected by the application profiler.
     */
    #[DataProviderExternal(DebugDbProfilerProvider::class, 'applicationProfilerContexts')]
    public function testApplicationProfilerReceivesOnlyTheMethodCategory(
        CommandContext|ConnectionContext $context,
        string $expectedCategory,
    ): void {
        $collector = new DbCollector();

        $collector->startup();

        $applicationProfiler = new Profiler(new NullLogger());
        $profiler = new DebugDbProfiler($collector, $applicationProfiler);

        $profiler->begin('SELECT 1', $context);
        $profiler->end('SELECT 1', $context);

        $messages = array_values($applicationProfiler->getMessages());

        self::assertCount(
            1,
            $messages,
            'The application profiler must complete exactly one span.',
        );
        self::assertSame(
            'SELECT 1',
            $messages[0]->token(),
            'The original profiling token must be preserved.',
        );

        $forwarded = $messages[0]->context();

        self::assertSame(
            $expectedCategory,
            $forwarded['category'] ?? null,
            'The native method must become the application profiler category.',
        );
        self::assertSame(
            [],
            array_intersect_key($forwarded, $context->asArray()),
            'Native context fields must not leak into the application profiler.',
        );
    }

    public function testArrayContextsAreIgnoredByCaptureAndForwarding(): void
    {
        $collector = new DbCollector();

        $collector->startup();

        $applicationProfiler = new Profiler(new NullLogger());
        $profiler = new DebugDbProfiler($collector, $applicationProfiler);

        $profiler->begin('SELECT 1', ['category' => 'ignored']);
        $profiler->end('SELECT 1', ['category' => 'ignored']);

        self::assertSame(
            [],
            Captured::db($collector)?->entries() ?? [],
            'A context without diagnostics must capture no query.',
        );
        self::assertSame(
            [],
            $applicationProfiler->getMessages(),
            'A context without diagnostics must reach no profiler.',
        );
    }

    public function testDeepApplicationTracesHaveAnExactFrameBudget(): void
    {
        $collector = new DbCollector();

        $collector->startup();

        $profiler = new DebugDbProfiler($collector);
        $context = new CommandContext('native.query', 'trace budget', 'SELECT 1', []);

        $this->captureAtDepth($profiler, $context, 30);

        $row = Captured::db($collector)?->entries()[0] ?? null;

        self::assertInstanceOf(
            QueryRow::class,
            $row,
            'The profiled query must be captured.',
        );

        $trace = $row->getTrace();

        self::assertCount(
            21,
            $trace,
            'The 24-frame raw trace budget must exclude three instrumentation frames.',
        );

        foreach ($trace as $frame) {
            self::assertSame(
                __FILE__,
                $frame['file'] ?? null,
                'Deep traces must contain only application source frames.',
            );
            self::assertIsInt(
                $frame['line'] ?? null,
                'Source lines must remain integers.'
            );
            self::assertSame(
                self::class,
                $frame['class'] ?? null,
                'Application source classes must be retained.'
            );
            self::assertSame(
                'captureAtDepth',
                $frame['function'] ?? null,
                'Application function names must be retained.'
            );
            self::assertSame(
                '->',
                $frame['type'] ?? null,
                'Application call types must be retained.'
            );
        }
    }

    public function testFailedSqlPreservesTheOriginalDatabaseException(): void
    {
        $collector = new DbCollector();

        $collector->startup();

        $db = DatabaseFixture::connection();

        $db->setProfiler(new DebugDbProfiler($collector));

        try {
            $db->createCommand('SELECT * FROM absent_table')->queryAll();

            self::fail(
                'Invalid SQL must retain its database exception.',
            );
        } catch (Exception $exception) {
            self::assertStringContainsString(
                'absent_table',
                $exception->getMessage(),
                'Database diagnostics must survive.'
            );
        }

        self::assertCount(
            1,
            Captured::db($collector)?->entries() ?? [],
            'Failed commands must still be diagnosable.'
        );
    }

    public function testInactiveObserversDoNotCaptureOrForwardCommands(): void
    {
        $collector = new DbCollector();
        $applicationProfiler = new Profiler(new NullLogger());
        $profiler = new DebugDbProfiler($collector, $applicationProfiler);
        $context = new CommandContext('native.query', 'inactive request', 'SELECT 1', []);

        $profiler->begin('SELECT 1', $context);
        $profiler->end('SELECT 1', $context);

        self::assertNull(
            Captured::db($collector),
            'An inactive collector must not produce a database snapshot.',
        );
        self::assertSame(
            [],
            $applicationProfiler->getMessages(),
            'Inactive commands must not reach the application profiler.',
        );
    }

    public function testInstrumentedCommandsReportDriverRowCounts(): void
    {
        $collector = new DbCollector();

        $collector->startup();

        $db = DatabaseFixture::connection();

        (new DebugDbProfiler($collector))->instrument($db);

        $db
            ->createCommand('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)')
            ->execute();
        $db
            ->createCommand('INSERT INTO items (id, name) VALUES (:id, :name)', [':id' => 1, ':name' => 'a'])
            ->execute();
        $db
            ->createCommand('INSERT INTO items (id, name) VALUES (:id, :name)', [':id' => 2, ':name' => 'b'])
            ->execute();
        $db
            ->createCommand('UPDATE items SET name = :name', [':name' => 'c'])
            ->execute();

        self::assertCount(
            2,
            $db->createCommand('SELECT id FROM items')->queryAll(),
            'The workload must run.'
        );
        self::assertSame(
            [0, 1, 1, 2, 0],
            $this->reported($collector),
            'Counts must be the unmodified driver values.'
        );
    }

    public function testInstrumentedConnectionsOpenedBeforeCaptureStillReportRowCounts(): void
    {
        $collector = new DbCollector();

        $db = DatabaseFixture::connection();

        (new DebugDbProfiler($collector))->instrument($db);

        $db
            ->createCommand('CREATE TABLE items (id INTEGER PRIMARY KEY)')
            ->execute();
        $collector->startup();
        $db
            ->createCommand('INSERT INTO items (id) VALUES (1)')
            ->execute();

        self::assertSame(
            [1],
            $this->reported($collector),
            'Setup queries must not cancel later instrumentation.'
        );
    }

    public function testInstrumentedFailuresLeaveTheRowCountUnreported(): void
    {
        $collector = new DbCollector();

        $collector->startup();

        $db = DatabaseFixture::connection();

        (new DebugDbProfiler($collector))->instrument($db);

        $db
            ->createCommand('CREATE TABLE items (id INTEGER PRIMARY KEY)')
            ->execute();
        $db
            ->createCommand('INSERT INTO items (id) VALUES (1)')
            ->execute();

        $statements = [
            'INSERT INTO items (id) VALUES (1)',
            'SELECT id FROM absent_table',
        ];

        foreach ($statements as $sql) {
            try {
                $db->createCommand($sql)->execute();
                self::fail('Rejected SQL must reach the caller.');
            } catch (Exception) {
                // A statement rejected on execute and one rejected on prepare must both stay countless.
            }
        }

        self::assertSame(
            [0, 1, null, null],
            $this->reported($collector),
            'Incomplete statements carry no count.',
        );
    }

    public function testInstrumentedOpenConnectionsReportRowCountsWithoutReopening(): void
    {
        $collector = new DbCollector();

        $collector->startup();

        $db = DatabaseFixture::connection();

        $db->open();

        (new DebugDbProfiler($collector))->instrument($db);

        $db
            ->createCommand('CREATE TABLE items (id INTEGER PRIMARY KEY)')
            ->execute();
        $db
            ->createCommand('INSERT INTO items (id) VALUES (1)')
            ->execute();

        self::assertSame(
            [0, 1],
            $this->reported($collector),
            'An open connection must be instrumented immediately.'
        );
    }

    public function testNativeConnectionAndDuplicateCommandsAlsoReachProfiling(): void
    {
        $collector = new DbCollector();
        $applicationProfiler = new Profiler(new NullLogger());
        $profiling = new ProfilingCollector($applicationProfiler);

        $profiling->startup();
        $collector->startup();

        $db = DatabaseFixture::connection();

        $db->setProfiler(new DebugDbProfiler($collector, $applicationProfiler));

        for ($i = 0; $i < 2; $i++) {
            $db
                ->createCommand('SELECT :value', [':value' => 7])
                ->queryScalar();
        }

        $spans = Captured::profiling($profiling)?->entries() ?? [];

        self::assertCount(
            3,
            $spans,
            'Profiling must retain the real connection and both command timings.',
        );
        self::assertSame(
            'Yiisoft\\Db\\Driver\\Pdo\\AbstractPdoConnection::open',
            $spans[1]->category,
            'Connection categories must use the native method.'
        );
        self::assertStringStartsWith(
            'Opening DB connection:',
            $spans[1]->info,
            'Connection diagnostics must retain their native token.'
        );

        foreach ([$spans[0], $spans[2]] as $span) {
            self::assertSame(
                'SELECT 7',
                $span->info,
                'Profiling must retain the actual driver-rendered SQL.'
            );
            self::assertSame(
                'Yiisoft\\Db\\Driver\\Pdo\\AbstractPdoCommand::queryInternal',
                $span->category,
                'Command categories must use the native method.'
            );
        }

        $rows = Captured::db($collector)?->entries() ?? [];

        self::assertCount(
            2,
            $rows,
            'Database must contain commands only, never connection events.'
        );
        self::assertSame(
            2,
            $rows[0]->getDuplicate(),
            'Database must mark both executions as duplicates.'
        );

        $collector->shutdown();

        $db
            ->createCommand('SELECT 8')
            ->queryScalar();

        self::assertCount(
            3,
            $applicationProfiler->getMessages(),
            'Inactive requests must not forward profiler messages.'
        );
    }

    public function testNonCommandContextsCannotOpenOrCloseCommandSpans(): void
    {
        $collector = new DbCollector();

        $collector->startup();

        $profiler = new DebugDbProfiler($collector);
        $command = new CommandContext('native.query', 'command span', 'SELECT 1', []);
        $connection = new ConnectionContext('native.open');

        $profiler->begin('ignored', $connection);
        $profiler->end('ignored', $command);

        $afterConnectionBegin = Captured::db($collector);

        $profiler->begin('SELECT 1', $command);
        $profiler->end('SELECT 1', $connection);

        $afterConnectionEnd = Captured::db($collector);

        $profiler->end('SELECT 1', $command);
        $afterCommandEnd = Captured::db($collector);

        self::assertSame(
            [],
            $afterConnectionBegin?->entries(),
            'Connection contexts must not begin command spans.'
        );
        self::assertSame(
            [],
            $afterConnectionEnd?->entries(),
            'Connection contexts must not complete command spans.'
        );
        self::assertCount(
            1,
            $afterCommandEnd?->entries() ?? [],
            'Only command contexts may complete a statement.'
        );
    }

    public function testThrowRuntimeExceptionWhenGuardReportsUnmatchedProfilerEnd(): void
    {
        $collector = new DbCollector();

        $collector->startup();

        $applicationProfiler = new Profiler(new NullLogger());
        $profiler = new DebugDbProfiler($collector, $applicationProfiler);
        $context = new CommandContext('native.query', 'unmatched span', 'SELECT 1', []);

        $profiler->end('SELECT 1', $context);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Unexpected ' . Profiler::class
            . '::end() call for category "native.query" token "SELECT 1". A matching begin() was not found.',
        );

        Captured::db($collector);
    }

    private function captureAtDepth(DebugDbProfiler $profiler, ContextInterface $context, int $depth): void
    {
        if ($depth > 0) {
            $this->captureAtDepth($profiler, $context, $depth - 1);

            return;
        }

        $profiler->begin('SELECT 1', $context);
        $profiler->end('SELECT 1', $context);
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
