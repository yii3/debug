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
    /**
     * @param ToolbarDataFactory $dataFactory Factory building the toolbar payload.
     * @param ResponseFactoryInterface $responseFactory Factory building the PSR-7 response.
     * @param StreamFactoryInterface $streamFactory Factory building the PSR-7 response body.
     * @param SnapshotStore|null $store Store the capture is read from, or `null` when none is configured.
     */
    public function __construct(
        private ToolbarDataFactory $dataFactory,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private SnapshotStore|null $store = null,
    ) {}

    /**
     * Serves the toolbar payload for the capture the query parameters select.
     *
     * @param ServerRequestInterface $request Incoming request carrying the query parameters.
     *
     * @throws JsonException when the toolbar payload cannot be encoded.
     *
     * @return ResponseInterface JSON toolbar payload, or an error response when the capture is missing.
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

    /**
     * Builds the PSR-7 response carrying the payload as JSON.
     *
     * @param mixed $data Payload to encode.
     * @param int $status HTTP status code.
     *
     * @throws JsonException when the payload cannot be encoded.
     *
     * @return ResponseInterface JSON response carrying the encoded payload.
     */
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
