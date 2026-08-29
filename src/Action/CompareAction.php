<?php

declare(strict_types=1);

namespace Yii3\Debug\Action;

use PHPForge\Debug\Data\QueryInput;
use PHPForge\Debug\Storage\SnapshotStore;
use PHPForge\Debug\Theme\ThemeResolver;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface, StreamFactoryInterface};
use Yii3\Debug\Comparison\HistoryComparison;
use Yii3\Debug\Web\DebugPageRenderer;

use function array_keys;
use function count;

/**
 * Compares two retained debugger snapshots.
 */
final readonly class CompareAction
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

        $tags = array_keys($manifest);

        $baseline = QueryInput::scalar($query, 'baseline');
        $target = QueryInput::scalar($query, 'target');

        if (($baseline === null || $target === null) && count($tags) < 2) {
            return $this->response(
                'At least two captured requests are required for comparison.',
                'text/plain; charset=UTF-8',
                404,
            );
        }

        $target ??= $tags[0] ?? '';
        $baseline ??= $tags[1] ?? '';

        if (!isset($manifest[$baseline], $manifest[$target])) {
            return $this->notFound(isset($manifest[$baseline]) ? $target : $baseline);
        }

        $baselineSnapshot = $this->store->readSnapshot($baseline);

        if ($baselineSnapshot === null) {
            return $this->notFound($baseline);
        }

        $targetSnapshot = $this->store->readSnapshot($target);

        if ($targetSnapshot === null) {
            return $this->notFound($target);
        }

        return $this->response(
            $this->renderer->compare(
                HistoryComparison::fromSnapshots($baselineSnapshot, $targetSnapshot),
                $manifest,
                ThemeResolver::resolve($request->getCookieParams(), $query),
            ),
            'text/html; charset=UTF-8',
        );
    }

    private function notFound(string $tag): ResponseInterface
    {
        return $this->response(
            "Unable to find debug data tagged with '{$tag}'.",
            'text/plain; charset=UTF-8',
            404,
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
