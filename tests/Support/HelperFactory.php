<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support;

use HttpSoft\Message\{
    Response,
    ResponseFactory,
    ServerRequest,
    ServerRequestFactory,
    StreamFactory,
    UploadedFile,
    UploadedFileFactory,
    Uri,
};
use Psr\Http\Message\{
    ResponseFactoryInterface,
    ResponseInterface,
    ServerRequestFactoryInterface,
    ServerRequestInterface,
    StreamFactoryInterface,
    StreamInterface,
    UploadedFileFactoryInterface,
    UploadedFileInterface,
    UriInterface,
};

use function is_string;
use function parse_str;

use const UPLOAD_ERR_OK;

/**
 * Creates PSR-7 and PSR-17 objects used by tests.
 */
final class HelperFactory
{
    /**
     * @param array<string, list<string>|string> $headers
     * @param array<string, mixed>|object|null $parsedBody
     * @param array<string, mixed> $serverParams
     */
    public static function createRequest(
        string $method = 'GET',
        string $uri = '',
        array $headers = [],
        array|object|null $parsedBody = null,
        array $serverParams = [],
    ): ServerRequestInterface {
        /** @var array<string, mixed> $queryParams */
        $queryParams = [];

        $uri = new Uri($uri);

        parse_str($uri->getQuery(), $queryParams);

        return new ServerRequest(
            serverParams: $serverParams,
            queryParams: $queryParams,
            parsedBody: $parsedBody,
            method: $method,
            uri: $uri,
            headers: $headers,
        );
    }

    /**
     * @param array<string, list<string>|string> $headers
     * @param resource|StreamInterface|string|null $body
     */
    public static function createResponse(
        int $statusCode = 200,
        array $headers = [],
        $body = null,
        string $protocol = '1.1',
        string $reasonPhrase = '',
    ): ResponseInterface {
        if (is_string($body)) {
            $response = new Response($statusCode, $headers, null, $protocol, $reasonPhrase);

            $response->getBody()->write($body);

            return $response;
        }

        return new Response($statusCode, $headers, $body, $protocol, $reasonPhrase);
    }

    public static function createResponseFactory(): ResponseFactoryInterface
    {
        return new ResponseFactory();
    }

    public static function createServerRequestFactory(): ServerRequestFactoryInterface
    {
        return new ServerRequestFactory();
    }

    public static function createStream(string $content = ''): StreamInterface
    {
        return self::createStreamFactory()->createStream($content);
    }

    public static function createStreamFactory(): StreamFactoryInterface
    {
        return new StreamFactory();
    }

    public static function createUploadedFile(
        string $name = '',
        string $type = '',
        string|StreamInterface $content = '',
        int $error = UPLOAD_ERR_OK,
        int|null $size = null,
    ): UploadedFileInterface {
        $stream = is_string($content) ? self::createStream($content) : $content;

        return new UploadedFile($stream, (int) ($size ?? $stream->getSize()), $error, $name, $type);
    }

    public static function createUploadedFileFactory(): UploadedFileFactoryInterface
    {
        return new UploadedFileFactory();
    }

    public static function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }
}
