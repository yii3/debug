<?php

declare(strict_types=1);

namespace Yii3\Debug\Action;

use PHPForge\Debug\Storage\SnapshotStore;
use PHPForge\Debug\Theme\ThemeResolver;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface, StreamFactoryInterface};
use Yii3\Debug\Web\DebugPageRenderer;

/**
 * Serves the Debug Core phpinfo page.
 */
final readonly class PhpInfoAction
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

        return $this->responseFactory
            ->createResponse()
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withBody(
                $this->streamFactory->createStream(
                    $this->renderer->phpInfo(
                        ThemeResolver::resolve($request->getCookieParams(), $query),
                        $this->store->loadManifest(),
                    ),
                ),
            );
    }
}
