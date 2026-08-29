<?php

declare(strict_types=1);

namespace Yii3\Debug\Action;

use JsonException;
use PHPForge\Debug\Storage\SnapshotStore;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface, StreamFactoryInterface};
use Yii3\Debug\ToolbarDataFactory;

use function is_string;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Serves the toolbar payload for a captured request.
 */
final readonly class ToolbarDataAction
{
    public function __construct(
        private ToolbarDataFactory $dataFactory,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private SnapshotStore|null $store = null,
    ) {}

    /**
     * @throws JsonException When the toolbar payload cannot be encoded.
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $tag = $request->getQueryParams()['tag'] ?? null;

        if (!is_string($tag) || $tag === '') {
            return $this->json(['error' => 'A debug request tag is required.'], 400);
        }

        $snapshot = $this->store?->readSnapshot($tag);
        $data = $snapshot === null
            ? $this->dataFactory->create($tag)
            : $this->dataFactory->createForSnapshot($snapshot);

        return $this->json($data->jsonSerialize());
    }

    private function json(mixed $data, int $status = 200): ResponseInterface
    {
        $content = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', 'application/json; charset=UTF-8')
            ->withBody($this->streamFactory->createStream($content));
    }
}
