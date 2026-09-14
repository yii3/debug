<?php

declare(strict_types=1);

namespace Yii3\Debug\Db;

use PHPForge\Debug\Panel\Db\{DbExplainRenderer, DbExplainSupport, DbMessage, QueryRow};
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Exception\Exception;

/**
 * Runs non-ANALYZE plans for captured single DML statements on an explicitly configured development connection.
 */
final readonly class DbExplain
{
    /**
     * @param ConnectionInterface|null $connection Development connection the plans run on, or `null` to disable
     * the feature.
     */
    public function __construct(private ConnectionInterface|null $connection = null) {}

    /**
     * Returns whether query plans can be produced for the configured connection.
     *
     * @return bool `true` when a connection is configured and its driver supports plans; `false` otherwise.
     */
    public function available(): bool
    {
        return $this->connection !== null && DbExplainSupport::isSupported($this->connection->getDriverName());
    }

    /**
     * Runs the plan for one captured statement and renders it.
     *
     * @param QueryRow $row Captured statement to explain.
     *
     * @return string Rendered plan, or a rendered error when the statement cannot be explained.
     */
    public function render(QueryRow $row): string
    {
        if (!$this->available() || $this->connection === null || !$row->isExplainable()) {
            return DbExplainRenderer::renderError($row->getQuery(), DbMessage::EXPLAIN_UNAVAILABLE->value);
        }

        $prefix = DbExplainSupport::prefix($this->connection->getDriverName());

        try {
            $results = $this->connection->createCommand($prefix . $row->getQuery())->queryAll();
        } catch (Exception $exception) {
            return DbExplainRenderer::renderError($row->getQuery(), $exception->getMessage());
        }

        return DbExplainRenderer::render($row->getQuery(), $results);
    }
}
