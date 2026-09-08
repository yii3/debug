<?php

declare(strict_types=1);

namespace Yii3\Debug\Action;

use PHPForge\Debug\Panel\Db\DbSnapshot;
use PHPForge\Debug\Storage\SnapshotStore;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface, StreamFactoryInterface};
use Yii3\Debug\Db\DbExplain;

use function ctype_digit;
use function is_string;

/**
 * Serves a plan for a stored query selected by tag and sequence, never for SQL supplied by the request.
 */
final readonly class DbExplainAction
{
    public function __construct(
        private SnapshotStore $store,
        private DbExplain $explain,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();

        $tag = $query['tag'] ?? null;
        $seq = $query['seq'] ?? null;

        if (!is_string($tag) || $tag === '' || !is_string($seq) || !ctype_digit($seq)) {
            return $this->response('', 400);
        }

        $payload = $this->store->readSnapshot($tag)->panels['db'] ?? null;

        if ($payload === null) {
            return $this->response('', 404);
        }

        foreach (DbSnapshot::fromArray($payload, '$.panels.db')->entries() as $row) {
            if ((string) $row->seq === $seq) {
                return $this->response($this->explain->render($row));
            }
        }

        return $this->response('', 404);
    }

    private function response(string $body, int $status = 200): ResponseInterface
    {
        return $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($this->streamFactory->createStream($body));
    }
}
