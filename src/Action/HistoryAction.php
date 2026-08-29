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
final readonly class HistoryAction
{
    public function __construct(
        private SnapshotStore $store,
        private DebugPageRenderer $renderer,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

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
