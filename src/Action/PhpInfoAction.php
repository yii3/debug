<?php

declare(strict_types=1);

namespace Yii3\Debug\Action;

use PHPForge\Debug\Storage\SnapshotStore;
use PHPForge\Debug\Theme\ThemeResolver;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface, StreamFactoryInterface};
use Yii3\Debug\Web\DebugPageRenderer;

use function array_key_first;

/**
 * Serves the Debug Core phpinfo page.
 */
final readonly class PhpInfoAction implements DebugActionInterface
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
     * Renders the `phpinfo()` page inside the debugger shell.
     *
     * @param ServerRequestInterface $request Incoming request carrying the query parameters.
     *
     * @return ResponseInterface Rendered `phpinfo()` page.
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
                    $this->renderer->phpInfo(
                        ThemeResolver::resolve($request->getCookieParams(), $query),
                        $manifest,
                        $newestTag === null ? null : $this->store->readSnapshot($newestTag),
                    ),
                ),
            );
    }
}
