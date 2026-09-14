<?php

declare(strict_types=1);

namespace Yii3\Debug\Action;

use PHPForge\Debug\Storage\SnapshotStore;
use PHPForge\Debug\Theme\ThemeResolver;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface, StreamFactoryInterface};
use Yii3\Debug\Exception\Message;
use Yii3\Debug\Web\DebugPageRenderer;

use function array_key_exists;
use function is_string;

/**
 * Serves the live Yii configuration page and captured extension panels.
 */
final readonly class ConfigAction
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
     * Renders the live configuration page for the capture the query parameters select.
     *
     * @param ServerRequestInterface $request Incoming request carrying the query parameters.
     *
     * @return ResponseInterface Rendered page, or a `404` response when the capture is missing.
     */
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

        if (!is_string($panel)) {
            return $this->response(Message::DEBUG_PANEL_UNAVAILABLE->value, 'text/plain; charset=UTF-8', 400);
        }

        $manifest = $this->store->loadManifest();
        $snapshot = $this->store->readSnapshot($tag);

        if ($panel !== 'auto' && $panel !== 'config' && !$this->renderer->hasExtensionPanel($panel)
            && !isset($snapshot->panels[$panel]) && !isset($snapshot->failures[$panel])) {
            return $this->response(Message::DEBUG_PANEL_UNAVAILABLE->value, 'text/plain; charset=UTF-8', 400);
        }

        $theme = ThemeResolver::resolve(
            $request->getCookieParams(),
            $query,
        );

        if ($panel === 'auto') {
            $panel = $snapshot !== null
                && $this->renderer->hasExtensionPanel('request')
                && (
                    array_key_exists('request', $snapshot->panels)
                    || array_key_exists('request', $snapshot->failures)
                ) ? 'request' : 'config';
        }

        if ($panel !== 'config') {
            if ($snapshot === null) {
                return $this->response(Message::DEBUG_SNAPSHOT_NOT_FOUND->value, 'text/plain; charset=UTF-8', 404);
            }

            if (
                !array_key_exists($panel, $snapshot->panels)
                && !array_key_exists($panel, $snapshot->failures)
            ) {
                return $this->response(Message::DEBUG_PANEL_NOT_CAPTURED->value, 'text/plain; charset=UTF-8', 404);
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

    /**
     * Builds the PSR-7 response carrying the given body.
     *
     * @param string $content Response body.
     * @param string $contentType Media type of the body.
     * @param int $status HTTP status code.
     *
     * @return ResponseInterface Response carrying the body and its media type.
     */
    private function response(string $content, string $contentType, int $status = 200): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', $contentType)
            ->withBody($this->streamFactory->createStream($content));
    }
}
