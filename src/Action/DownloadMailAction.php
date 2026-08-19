<?php

declare(strict_types=1);

namespace Yii3\Debug\Action;

use PHPForge\Debug\Panel\Mail\MailSnapshot;
use PHPForge\Debug\Storage\SnapshotStore;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Throwable;
use Yii3\Debug\Collector\MailCollector;
use Yii3\Debug\Web\{LocalAccessChecker, ResponseBuilder};

use function basename;
use function ctype_digit;
use function file_get_contents;
use function is_file;
use function is_string;
use function str_contains;

use const DIRECTORY_SEPARATOR;

/**
 * Streams a captured `.eml` file selected through its owning snapshot and message sequence.
 */
final readonly class DownloadMailAction
{
    public function __construct(
        private SnapshotStore $store,
        private MailCollector $collector,
        private LocalAccessChecker $accessChecker,
        private ResponseBuilder $responseBuilder,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->accessChecker->allows($request)) {
            return $this->responseBuilder->text('Forbidden', 403);
        }

        $query = $request->getQueryParams();

        $tag = $query['tag'] ?? null;
        $seq = $query['seq'] ?? null;

        if (!is_string($tag) || $tag === '' || !is_string($seq) || !ctype_digit($seq)) {
            return $this->responseBuilder->text('A valid debug tag and mail sequence are required.', 400);
        }

        $snapshot = $this->store->readSnapshot($tag);

        if ($snapshot === null || !isset($snapshot->panels['mail'])) {
            return $this->responseBuilder->text('Captured mail message not found.', 404);
        }

        try {
            $messages = MailSnapshot::fromArray($snapshot->panels['mail'], 'panels.mail')->entries();
        } catch (Throwable) {
            return $this->responseBuilder->text('Captured mail message not found.', 404);
        }

        $sequence = (int) $seq;

        if (!isset($messages[$sequence])) {
            return $this->responseBuilder->text('Captured mail message not found.', 404);
        }

        $file = $messages[$sequence]->file;

        if (
            $file === ''
            || basename($file) !== $file
            || str_contains($file, '/')
            || str_contains($file, '\\')
        ) {
            return $this->responseBuilder->text('Captured mail message not found.', 404);
        }

        $path = $this->collector->mailPath() . DIRECTORY_SEPARATOR . $file;

        if (!is_file($path)) {
            return $this->responseBuilder->text('Captured mail file not found.', 404);
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return $this->responseBuilder->text('Captured mail file could not be read.', 500);
        }

        return $this->responseBuilder->download($contents, $file, 'message/rfc822');
    }
}
