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
    public function __construct(private ConnectionInterface|null $connection = null) {}

    public function available(): bool
    {
        return $this->connection !== null && DbExplainSupport::isSupported($this->connection->getDriverName());
    }

    public function render(QueryRow $row): string
    {
        if (!$this->available() || $this->connection === null || !$row->isExplainable()) {
            return DbExplainRenderer::renderError(
                $row->getQuery(),
                DbMessage::EXPLAIN_UNAVAILABLE->value,
            );
        }

        $prefix = DbExplainSupport::prefix($this->connection->getDriverName());

        try {
            $results = $this->connection->createCommand($prefix . $row->getQuery())->queryAll();
        } catch (Exception $exception) {
            return DbExplainRenderer::renderError(
                $row->getQuery(),
                $exception->getMessage(),
            );
        }

        return DbExplainRenderer::render(
            $row->getQuery(),
            $results,
        );
    }
}
