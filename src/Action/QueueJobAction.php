<?php

declare(strict_types=1);

namespace Yii3\Debug\Action;

use PHPForge\Debug\Panel\Queue\QueueSnapshot;
use PHPForge\Debug\Storage\SnapshotStore;
use PHPForge\Debug\Theme\ThemeResolver;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Throwable;
use Yii3\Debug\Web\{DebugPageRenderer, LocalAccessChecker, ResponseBuilder};

use function ctype_digit;
use function is_string;

/**
 * Renders the dedicated payload card for one captured queue record.
 */
final readonly class QueueJobAction
{
    public function __construct(
        private SnapshotStore $store,
        private LocalAccessChecker $accessChecker,
        private DebugPageRenderer $renderer,
        private ResponseBuilder $responseBuilder,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->accessChecker->allows($request)) {
            return $this->responseBuilder->text('Forbidden', 403);
        }

        $query = $request->getQueryParams();

        $tag = $query['tag'] ?? null;
        $seq = $query['seq'] ?? null;

        if (!is_string($tag) || $tag === '' || !is_string($seq) || !ctype_digit($seq)) {
            return $this->responseBuilder->text('A valid debug tag and queue sequence are required.', 400);
        }

        $snapshot = $this->store->readSnapshot($tag);

        if ($snapshot === null || !isset($snapshot->panels['queue'])) {
            return $this->responseBuilder->text('Queue job record not found.', 404);
        }

        try {
            $records = QueueSnapshot::fromArray($snapshot->panels['queue'], 'panels.queue')->entries();
        } catch (Throwable) {
            return $this->responseBuilder->text('Queue job record not found.', 404);
        }

        $record = $records[(int) $seq] ?? null;

        if ($record === null) {
            return $this->responseBuilder->text('Queue job record not found.', 404);
        }

        return $this->responseBuilder->html(
            $this->renderer->queueJob(
                $snapshot,
                $record,
                $this->store->loadManifest(),
                ThemeResolver::resolve($request->getCookieParams(), $query),
            ),
        );
    }
}
