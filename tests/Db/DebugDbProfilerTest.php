<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Db;

use PHPForge\Debug\Panel\Db\QueryRow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Yii3\Debug\Collector\DbCollector;
use Yii3\Debug\Collector\ProfilingCollector;
use Yii3\Debug\Db\DebugDbProfiler;
use Yii3\Debug\Tests\Support\DatabaseFixture;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Profiler\ContextInterface;
use Yiisoft\Profiler\Profiler;

use function array_map;

/**
 * Integration tests for optional Yii DB 2 profiling with real parameterized SQLite commands.
 */
#[Group('db')]
final class DebugDbProfilerTest extends TestCase
{
    public function testApplicationProfilerReceivesOnlyTheMethodCategoryOrContextType(): void
    {
        foreach ([[], ['method' => 'native.query', 'params' => ['private' => 'diagnostic marker']]] as $data) {
            $collector = new DbCollector();

            $collector->startup();

            $context = self::createStub(ContextInterface::class);

            $context
                ->method('getType')
                ->willReturn('command');
            $context
                ->method('asArray')
                ->willReturn($data);

            $applicationProfiler = $this->createMock(\Yiisoft\Profiler\ProfilerInterface::class);

            $forwarded = ['category' => $data['method'] ?? 'command'];

            $applicationProfiler
                ->expects(self::once())
                ->method('begin')
                ->with('SELECT 1', $forwarded);
            $applicationProfiler
                ->expects(self::once())
                ->method('end')
                ->with('SELECT 1', $forwarded);

            $profiler = new DebugDbProfiler($collector, $applicationProfiler);

            $profiler->begin('SELECT 1', $context);
            $profiler->end('SELECT 1', $context);
        }
    }
    public function testDeepApplicationTracesHaveAnExactFrameBudget(): void
    {
        $collector = new DbCollector();

        $collector->startup();

        $profiler = new DebugDbProfiler($collector);

        $context = self::createStub(ContextInterface::class);

        $context
            ->method('getType')
            ->willReturn('command');

        $this->captureAtDepth($profiler, $context, 30);

        $trace = $collector->capture()?->entries()[0]->trace ?? [];

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
            $collector->capture()?->entries() ?? [],
            'Failed commands must still be diagnosable.'
        );
    }

    public function testInactiveObserversNeverInspectContexts(): void
    {
        $context = $this->createMock(ContextInterface::class);

        $context
            ->expects(self::never())
            ->method('getType');

        $profiler = new DebugDbProfiler(new DbCollector());

        $profiler->begin('inactive', $context);
        $profiler->end('inactive', $context);
    }
    public function testInstrumentedCommandsReportDriverRowCounts(): void
    {
        $collector = new DbCollector();

        $collector->startup();

        $db = DatabaseFixture::connection();

        (new DebugDbProfiler($collector))->instrument($db);

        $db->createCommand('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)')->execute();
        $db->createCommand('INSERT INTO items (id, name) VALUES (:id, :name)', [':id' => 1, ':name' => 'a'])->execute();
        $db->createCommand('INSERT INTO items (id, name) VALUES (:id, :name)', [':id' => 2, ':name' => 'b'])->execute();
        $db->createCommand('UPDATE items SET name = :name', [':name' => 'c'])->execute();

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

        $db->createCommand('CREATE TABLE items (id INTEGER PRIMARY KEY)')->execute();
        $collector->startup();
        $db->createCommand('INSERT INTO items (id) VALUES (1)')->execute();

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
        $db->createCommand('CREATE TABLE items (id INTEGER PRIMARY KEY)')->execute();
        $db->createCommand('INSERT INTO items (id) VALUES (1)')->execute();

        foreach (['INSERT INTO items (id) VALUES (1)', 'SELECT id FROM absent_table'] as $sql) {
            try {
                $db->createCommand($sql)->execute();
                self::fail('Rejected SQL must reach the caller.');
            } catch (Exception) {
                // A statement rejected on execute and one rejected on prepare must both stay countless.
            }
        }

        self::assertSame([0, 1, null, null], $this->reported($collector), 'Incomplete statements carry no count.');
    }

    public function testInstrumentedOpenConnectionsReportRowCountsWithoutReopening(): void
    {
        $collector = new DbCollector();
        $collector->startup();
        $db = DatabaseFixture::connection();
        $db->open();
        (new DebugDbProfiler($collector))->instrument($db);
        $db->createCommand('CREATE TABLE items (id INTEGER PRIMARY KEY)')->execute();
        $db->createCommand('INSERT INTO items (id) VALUES (1)')->execute();
        self::assertSame([0, 1], $this->reported($collector), 'An open connection must be instrumented immediately.');
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
            $db->createCommand('SELECT :value', [':value' => 7])->queryScalar();
        }
        $spans = $profiling->capture()?->entries() ?? [];
        self::assertCount(3, $spans, 'Profiling must retain the real connection and both command timings.');
        self::assertSame('Yiisoft\\Db\\Driver\\Pdo\\AbstractPdoConnection::open', $spans[1]->category, 'Connection categories must use the native method.');
        self::assertStringStartsWith('Opening DB connection:', $spans[1]->info, 'Connection diagnostics must retain their native token.');
        foreach ([$spans[0], $spans[2]] as $span) {
            self::assertSame('SELECT 7', $span->info, 'Profiling must retain the actual driver-rendered SQL.');
            self::assertSame('Yiisoft\\Db\\Driver\\Pdo\\AbstractPdoCommand::queryInternal', $span->category, 'Command categories must use the native method.');
        }
        $rows = $collector->capture()?->entries() ?? [];
        self::assertCount(2, $rows, 'Database must contain commands only, never connection events.');
        self::assertSame(2, $rows[0]->duplicate, 'Database must mark both executions as duplicates.');
        $collector->shutdown();
        $db->createCommand('SELECT 8')->queryScalar();
        self::assertCount(3, $applicationProfiler->getMessages(), 'Inactive requests must not forward profiler messages.');
    }

    public function testNonCommandContextsCannotOpenOrCloseCommandSpans(): void
    {
        $collector = new DbCollector();
        $collector->startup();
        $profiler = new DebugDbProfiler($collector);
        $command = self::createStub(ContextInterface::class);
        $command->method('getType')->willReturn('command');
        $connection = self::createStub(ContextInterface::class);
        $connection->method('getType')->willReturn('connection');
        $profiler->begin('ignored', $connection);
        $profiler->end('ignored', $command);
        $afterConnectionBegin = $collector->capture();
        $profiler->begin('SELECT 1', $command);
        $profiler->end('SELECT 1', $connection);
        $afterConnectionEnd = $collector->capture();
        $profiler->end('SELECT 1', $command);
        $afterCommandEnd = $collector->capture();
        self::assertSame([], $afterConnectionBegin?->entries(), 'Connection contexts must not begin command spans.');
        self::assertSame([], $afterConnectionEnd?->entries(), 'Connection contexts must not complete command spans.');
        self::assertCount(1, $afterCommandEnd?->entries() ?? [], 'Only command contexts may complete a statement.');
    }

    public function testThrowRuntimeExceptionWhenGuardReportsObserverFailures(): void
    {
        $collector = new DbCollector();
        $collector->startup();
        $profiler = new DebugDbProfiler($collector);
        $context = self::createStub(ContextInterface::class);
        $failure = new RuntimeException('broken observer');
        $context->method('getType')->willThrowException($failure);
        $profiler->begin('SELECT 1', $context);
        $profiler->end('SELECT 1', $context);
        $this->expectExceptionObject($failure);
        $collector->capture();
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
            static fn(QueryRow $row): int|null => $row->rows,
            $collector->capture()?->entries() ?? [],
        );
    }
}
