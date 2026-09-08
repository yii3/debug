<?php

declare(strict_types=1);

namespace Yii3\Debug\Db;

use PHPForge\Debug\Panel\Db\{DbExplainRenderer, DbMessage, QueryRow};
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Exception\Exception;

use function in_array;

/**
 * Runs non-ANALYZE plans for captured single DML statements on an explicitly configured development connection.
 */
final readonly class DbExplain
{
    public function __construct(private ConnectionInterface|null $connection = null) {}

    public function available(): bool
    {
        return $this->connection !== null
            && in_array($this->connection->getDriverName(), ['mysql', 'sqlite', 'pgsql'], true);
    }

    public function render(QueryRow $row): string
    {
        if (!$this->available() || $this->connection === null || !$row->isExplainable()) {
            return DbExplainRenderer::renderError($row->query, DbMessage::EXPLAIN_UNAVAILABLE->value);
        }

        $prefix = $this->connection->getDriverName() === 'sqlite' ? 'EXPLAIN QUERY PLAN ' : 'EXPLAIN ';

        try {
            $results = $this->connection->createCommand($prefix . $row->query)->queryAll();
        } catch (Exception $exception) {
            return DbExplainRenderer::renderError($row->query, $exception->getMessage());
        }

        return DbExplainRenderer::render($row->query, $results);
    }
}
