<?php

declare(strict_types=1);

namespace Yii3\Debug\Action;

use PHPForge\Debug\Panel\Db\{DbSnapshot, QueryRow};
use PHPForge\Debug\Storage\SnapshotStore;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface, StreamFactoryInterface};
use Yii3\Debug\Db\DbExplain;

use function ctype_digit;
use function is_string;

/**
 * Serves a plan for a stored query selected by tag and sequence, never for SQL supplied by the request.
 */
final readonly class DbExplainAction implements DebugActionInterface
{
    /**
     * @param SnapshotStore $store Store the captured snapshots are read from.
     * @param DbExplain $explain Runner producing the plan for a stored statement.
     * @param ResponseFactoryInterface $responseFactory Factory building the PSR-7 response.
     * @param StreamFactoryInterface $streamFactory Factory building the PSR-7 response body.
     */
    public function __construct(
        private SnapshotStore $store,
        private DbExplain $explain,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    /**
     * Renders the plan for the stored statement the tag and sequence select.
     *
     * @param ServerRequestInterface $request Incoming request carrying the query parameters.
     *
     * @return ResponseInterface Rendered page, or a `404` response when the capture is missing.
     */
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

        $entries = DbSnapshot::fromArray($payload, '$.panels.db')->entries();
        $row = QueryRow::findBySequence($entries, $seq);

        if ($row === null) {
            return $this->response('', 404);
        }

        return $this->response($this->explain->render($row));
    }

    /**
     * Builds the PSR-7 response carrying the given body.
     *
     * @param string $body Response body.
     * @param int $status HTTP status code.
     *
     * @return ResponseInterface HTML response carrying the body.
     */
    private function response(string $body, int $status = 200): ResponseInterface
    {
        return $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($this->streamFactory->createStream($body));
    }
}
