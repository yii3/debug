<?php

declare(strict_types=1);

namespace Yii3\Debug\Db;

use PDOStatement;
use Throwable;
use Yii3\Debug\Collector\DbCollector;

/**
 * Reports the driver row count of every executed prepared statement to the request-scoped Database collector.
 *
 * Yii DB 2 profiling hands observers the SQL and its context only, so the {@see PDOStatement} and its `rowCount()` are
 * unreachable downstream. {@see DebugDbProfiler::instrument()} registers this class through `PDO::ATTR_STATEMENT_CLASS`
 * on the live PDO instance, making every prepared statement report its own count.
 */
final class DebugStatement extends PDOStatement
{
    protected function __construct(private readonly DbCollector $collector) {}

    /**
     * Executes the prepared statement and reports its row count, or `null` when the driver rejects it.
     *
     * A rejected statement still reports, holding `null`, so a command that never produced a count is not credited with
     * the count of an earlier attempt.
     *
     * @param array<array-key, mixed>|null $params Values bound to the statement placeholders, if any.
     */
    public function execute(array|null $params = null): bool
    {
        try {
            $result = parent::execute($params);
        } catch (Throwable $exception) {
            $this->collector->reportRows(null);

            throw $exception;
        }

        $this->collector->reportRows($this->rowCount());

        return $result;
    }
}
