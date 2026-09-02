<?php

declare(strict_types=1);

namespace Yii3\Debug\Action;

use PHPForge\Debug\Storage\SnapshotStore;
use PHPForge\Debug\Theme\ThemeResolver;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface, StreamFactoryInterface};
use Yii3\Debug\Web\DebugPageRenderer;

use function array_key_exists;
use function is_string;

/**
 * Serves the live Yii configuration page and captured extension panels.
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

        $panel = $query['panel'] ?? null;

        if (
            !is_string($panel)
            || ($panel !== 'auto' && $panel !== 'config' && !$this->renderer->hasExtensionPanel($panel))
        ) {
            return $this->response(
                'The requested debug panel is not available.',
                'text/plain; charset=UTF-8',
                400,
            );
        }

        $manifest = $this->store->loadManifest();
        $snapshot = $this->store->readSnapshot($tag);

        $theme = ThemeResolver::resolve($request->getCookieParams(), $query);

        if ($panel === 'auto') {
            $panel = $snapshot !== null
                && $this->renderer->hasExtensionPanel('request')
                && (array_key_exists('request', $snapshot->panels)
                    || array_key_exists('request', $snapshot->failures))
                    ? 'request'
                    : 'config';
        }

        if ($panel !== 'config') {
            if ($snapshot === null) {
                return $this->response(
                    'Debug snapshot not found.',
                    'text/plain; charset=UTF-8',
                    404,
                );
            }

            if (
                !array_key_exists($panel, $snapshot->panels)
                && !array_key_exists($panel, $snapshot->failures)
            ) {
                return $this->response(
                    'Debug panel was not captured.',
                    'text/plain; charset=UTF-8',
                    404,
                );
            }

            return $this->response(
                $this->renderer->extension($snapshot, $panel, $theme, $manifest, $query),
                'text/html; charset=UTF-8',
            );
        }

        return $this->response(
            $this->renderer->config(
                $tag,
                $theme,
                $manifest,
                $snapshot,
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
