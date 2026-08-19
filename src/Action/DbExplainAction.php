<?php

declare(strict_types=1);

namespace Yii3\Debug\Action;

use PHPForge\Debug\Data\QueryInput;
use PHPForge\Debug\Panel\Db\{DbExplainRenderer, DbQueryRenderer, DbSnapshot, QueryRow};
use PHPForge\Debug\Storage\SnapshotStore;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Throwable;
use Yii3\Debug\Web\{LocalAccessChecker, ResponseBuilder};
use Yiisoft\Db\Connection\ConnectionInterface;

use function preg_match;

/**
 * Executes and renders the EXPLAIN plan for one query stored in a captured Yii3 snapshot.
 */
final readonly class DbExplainAction
{
    public function __construct(
        private SnapshotStore $store,
        private LocalAccessChecker $accessChecker,
        private ConnectionInterface $db,
        private ResponseBuilder $responseBuilder,
    ) {}

    /**
     * Returns the shared EXPLAIN fragment for an allowed client and a valid query sequence.
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->accessChecker->allows($request)) {
            return $this->responseBuilder->text('Forbidden', 403);
        }

        $queryParams = $request->getQueryParams();

        $tag = QueryInput::scalar($queryParams, 'tag');
        $seq = QueryInput::scalar($queryParams, 'seq');

        if ($tag === null || $tag === '' || $seq === null || preg_match('/^\d+$/D', $seq) !== 1) {
            return $this->responseBuilder->text(
                'A debug snapshot tag and query sequence are required.',
                400,
            );
        }

        $snapshot = $this->store->readSnapshot($tag);
        $payload = $snapshot?->panels['db'] ?? null;

        if ($payload === null) {
            return $this->responseBuilder->text(
                'Database query not found.',
                404,
            );
        }

        $row = self::findRow(DbSnapshot::fromArray($payload, 'panels.db')->entries(), (int) $seq);

        if ($row === null) {
            return $this->responseBuilder->text('Database query not found.', 404);
        }

        if (!DbQueryRenderer::canBeExplained($row->type)) {
            return $this->responseBuilder->text(
                'This database query cannot be explained.',
                400,
            );
        }

        $prefix = $this->db->getDriverName() === 'sqlite' ? 'EXPLAIN QUERY PLAN ' : 'EXPLAIN ';
        try {
            $results = $this->db->createCommand($prefix . $row->query)->queryAll();
        } catch (Throwable $throwable) {
            return $this->responseBuilder->html(
                DbExplainRenderer::renderError($row->query, $throwable->getMessage()),
            );
        }

        return $this->responseBuilder->html(DbExplainRenderer::render($row->query, $results));
    }

    /**
     * @param list<QueryRow> $rows Captured query rows.
     */
    private static function findRow(array $rows, int $seq): QueryRow|null
    {
        foreach ($rows as $row) {
            if ($row->seq === $seq) {
                return $row;
            }
        }

        return null;
    }
}
