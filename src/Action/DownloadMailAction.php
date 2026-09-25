<?php

declare(strict_types=1);

namespace Yii3\Debug\Action;

use PHPForge\Debug\Panel\Mail\MailMessage;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface, StreamFactoryInterface};
use Yii3\Debug\Mail\MailFileStore;

use function is_string;

/**
 * Streams a captured `.eml` file as an attachment, selected by its bare file name.
 *
 * A name carrying a path segment never resolves, so the endpoint serves only files the Mail collector stored.
 */
final readonly class DownloadMailAction implements DebugActionInterface
{
    /**
     * @param MailFileStore $files Store resolving the requested file inside the mail directory.
     * @param ResponseFactoryInterface $responseFactory Factory building the PSR-7 response.
     * @param StreamFactoryInterface $streamFactory Factory building the PSR-7 response body.
     */
    public function __construct(
        private MailFileStore $files,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    /**
     * Streams the `.eml` file the `file` query parameter names.
     *
     * @param ServerRequestInterface $request Incoming request carrying the query parameters.
     *
     * @return ResponseInterface `message/rfc822` attachment, or a `404` response when the name is missing, unsafe, or
     * unknown.
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $file = $request->getQueryParams()['file'] ?? null;
        $path = is_string($file) ? $this->files->resolve($file) : null;

        if ($path === null) {
            return $this->responseFactory
                ->createResponse(404)
                ->withHeader('Content-Type', 'text/plain; charset=UTF-8')
                ->withBody($this->streamFactory->createStream(MailMessage::FILE_NOT_FOUND->value));
        }

        return $this->responseFactory
            ->createResponse()
            ->withHeader('Content-Type', 'message/rfc822')
            ->withHeader('Content-Disposition', "attachment; filename=\"{$file}\"")
            ->withBody($this->streamFactory->createStreamFromFile($path));
    }
}
