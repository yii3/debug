<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use JsonException;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, StreamFactoryInterface};

use function json_encode;
use function str_replace;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Creates PSR-7 responses for the Yii3 debug endpoints.
 */
final readonly class ResponseBuilder
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    /**
     * Creates an attachment response for captured debugger artifacts.
     */
    public function download(
        string $content,
        string $filename,
        string $contentType = 'application/octet-stream',
    ): ResponseInterface {
        $safeFilename = str_replace(["\r", "\n", '"'], '', $filename);

        return $this->response($content, $contentType, 200)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $safeFilename . '"');
    }

    /**
     * Creates an HTML response.
     *
     * Usage example:
     *
     * ```php
     * $response = $builder->html('<h1>Debug</h1>');
     * ```
     *
     * @param string $html Response document.
     * @param int $status HTTP status code.
     *
     * @return ResponseInterface HTML response.
     */
    public function html(string $html, int $status = 200): ResponseInterface
    {
        return $this->response($html, 'text/html; charset=UTF-8', $status);
    }

    /**
     * Creates a JSON response.
     *
     * Usage example:
     *
     * ```php
     * $response = $builder->json(['tag' => 'request-1']);
     * ```
     *
     * @param mixed $data JSON-serializable response data.
     * @param int $status HTTP status code.
     *
     * @return ResponseInterface JSON response.
     *
     * @throws JsonException When the response data cannot be encoded.
     */
    public function json(mixed $data, int $status = 200): ResponseInterface
    {
        return $this->response(
            json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ),
            'application/json; charset=UTF-8',
            $status,
        );
    }

    /**
     * Creates a plain-text response.
     *
     * Usage example:
     *
     * ```php
     * $response = $builder->text('Forbidden', 403);
     * ```
     *
     * @param string $text Response text.
     * @param int $status HTTP status code.
     *
     * @return ResponseInterface Plain-text response.
     */
    public function text(string $text, int $status = 200): ResponseInterface
    {
        return $this->response($text, 'text/plain; charset=UTF-8', $status);
    }

    /**
     * Creates a response with a typed body.
     *
     * @param string $content Response content.
     * @param string $contentType Response media type.
     * @param int $status HTTP status code.
     *
     * @return ResponseInterface Typed response.
     */
    private function response(string $content, string $contentType, int $status): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', $contentType)
            ->withBody($this->streamFactory->createStream($content));
    }
}
