<?php

declare(strict_types=1);

namespace Yii3\Debug\Action;

use JsonException;
use PHPForge\Debug\Storage\SnapshotStore;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Yii3\Debug\ToolbarDataFactory;
use Yii3\Debug\Web\{LocalAccessChecker, ResponseBuilder};

use function is_string;

/**
 * Serves toolbar data for one captured Yii3 request.
 */
final readonly class ToolbarDataAction
{
    public function __construct(
        private SnapshotStore $store,
        private LocalAccessChecker $accessChecker,
        private ToolbarDataFactory $dataFactory,
        private ResponseBuilder $responseBuilder,
    ) {}

    /**
     * Returns the portable toolbar payload for an allowed client.
     *
     * @param ServerRequestInterface $request Incoming server request.
     *
     * @return ResponseInterface Toolbar JSON, validation error, or forbidden response.
     *
     * @throws JsonException When the toolbar payload cannot be encoded.
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->accessChecker->allows($request)) {
            return $this->responseBuilder->json(['error' => 'Forbidden'], 403);
        }

        $tag = $request->getQueryParams()['tag'] ?? null;

        if (!is_string($tag) || $tag === '') {
            return $this->responseBuilder->json(['error' => 'A debug snapshot tag is required.'], 400);
        }

        $snapshot = $this->store->readSnapshot($tag);

        if ($snapshot === null) {
            return $this->responseBuilder->json(['error' => 'Debug snapshot not found.'], 404);
        }

        return $this->responseBuilder->json(
            $this->dataFactory->create($snapshot)->jsonSerialize(),
        );
    }
}
