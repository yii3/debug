<?php

declare(strict_types=1);

namespace Yii3\Debug\Action;

use PHPForge\Debug\Storage\SnapshotStore;
use PHPForge\Debug\Theme\ThemeResolver;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Yii3\Debug\Web\{DebugPageRenderer, LocalAccessChecker, ResponseBuilder};

/**
 * Serves the captured Yii3 request history.
 */
final readonly class HistoryAction
{
    public function __construct(
        private SnapshotStore $store,
        private LocalAccessChecker $accessChecker,
        private DebugPageRenderer $renderer,
        private ResponseBuilder $responseBuilder,
    ) {}

    /**
     * Returns the debug history page for an allowed client.
     *
     * @param ServerRequestInterface $request Incoming server request.
     *
     * @return ResponseInterface Debug history or forbidden response.
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->accessChecker->allows($request)) {
            return $this->responseBuilder->text('Forbidden', 403);
        }

        $queryParams = $request->getQueryParams();

        return $this->responseBuilder->html(
            $this->renderer->history(
                $this->store->loadManifest(),
                $queryParams,
                ThemeResolver::resolve($request->getCookieParams(), $queryParams),
            ),
        );
    }
}
