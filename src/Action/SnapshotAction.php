<?php

declare(strict_types=1);

namespace Yii3\Debug\Action;

use JsonException;
use PHPForge\Debug\Storage\SnapshotStore;
use PHPForge\Debug\Theme\ThemeResolver;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Yii3\Debug\Web\{DebugPageRenderer, LocalAccessChecker, ResponseBuilder};

use function is_string;

/**
 * Serves one captured Yii3 debug snapshot.
 */
final readonly class SnapshotAction
{
    public function __construct(
        private SnapshotStore $store,
        private LocalAccessChecker $accessChecker,
        private DebugPageRenderer $renderer,
        private ResponseBuilder $responseBuilder,
    ) {}

    /**
     * Returns the selected snapshot panel for an allowed client.
     *
     * @param ServerRequestInterface $request Incoming server request.
     *
     * @return ResponseInterface Snapshot page, validation error, or forbidden response.
     *
     * @throws JsonException When the stored payload cannot be rendered as JSON.
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->accessChecker->allows($request)) {
            return $this->responseBuilder->text('Forbidden', 403);
        }

        $query = $request->getQueryParams();
        $tag = $query['tag'] ?? null;

        if (!is_string($tag) || $tag === '') {
            return $this->responseBuilder->text('A debug snapshot tag is required.', 400);
        }

        $snapshot = $this->store->readSnapshot($tag);

        if ($snapshot === null) {
            return $this->responseBuilder->text('Debug snapshot not found.', 404);
        }

        $panel = $query['panel'] ?? null;

        return $this->responseBuilder->html(
            $this->renderer->snapshot(
                $snapshot,
                is_string($panel) ? $panel : null,
                $this->store->loadManifest(),
                $query,
                ThemeResolver::resolve($request->getCookieParams(), $query),
            ),
        );
    }
}
