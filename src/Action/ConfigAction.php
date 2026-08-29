<?php

declare(strict_types=1);

namespace Yii3\Debug\Action;

use PHPForge\Debug\Storage\SnapshotStore;
use PHPForge\Debug\Theme\ThemeResolver;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface, StreamFactoryInterface};
use Yii3\Debug\Web\DebugPageRenderer;

use function is_string;

/**
 * Serves the live Yii configuration page.
 */
final readonly class ConfigAction
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

        $tag = $query['tag'] ?? null;

        if (!is_string($tag) || $tag === '') {
            return $this->response(
                'A debug request tag is required.',
                'text/plain; charset=UTF-8',
                400,
            );
        }

        if (($query['panel'] ?? null) !== 'config') {
            return $this->response(
                'Only the configuration panel is available.',
                'text/plain; charset=UTF-8',
                400,
            );
        }

        return $this->response(
            $this->renderer->config(
                $tag,
                ThemeResolver::resolve($request->getCookieParams(), $query),
                $this->store->loadManifest(),
            ),
            'text/html; charset=UTF-8',
        );
    }

    private function response(string $content, string $contentType, int $status = 200): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', $contentType)
            ->withBody($this->streamFactory->createStream($content));
    }
}
