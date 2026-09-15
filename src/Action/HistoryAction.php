<?php

declare(strict_types=1);

namespace Yii3\Debug\Action;

use PHPForge\Debug\Storage\SnapshotStore;
use PHPForge\Debug\Theme\ThemeResolver;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface, StreamFactoryInterface};
use Yii3\Debug\Web\DebugPageRenderer;

use function array_key_first;

/**
 * Serves the captured request history grid.
 */
final readonly class HistoryAction implements DebugActionInterface
{
    /**
     * @param SnapshotStore $store Store the captured snapshots are read from.
     * @param DebugPageRenderer $renderer Renderer producing the debugger page markup.
     * @param ResponseFactoryInterface $responseFactory Factory building the PSR-7 response.
     * @param StreamFactoryInterface $streamFactory Factory building the PSR-7 response body.
     */
    public function __construct(
        private SnapshotStore $store,
        private DebugPageRenderer $renderer,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    /**
     * Renders the captured request history, filtered and paginated by the query parameters.
     *
     * @param ServerRequestInterface $request Incoming request carrying the query parameters.
     *
     * @return ResponseInterface Rendered history page.
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();
        $manifest = $this->store->loadManifest();

        $newestTag = array_key_first($manifest);

        return $this->responseFactory
            ->createResponse()
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withBody(
                $this->streamFactory->createStream(
                    $this->renderer->history(
                        $manifest,
                        $query,
                        ThemeResolver::resolve($request->getCookieParams(), $query),
                        $newestTag === null ? null : $this->store->readSnapshot($newestTag),
                    ),
                ),
            );
    }
}
