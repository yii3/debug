<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Action;

use HttpSoft\Message\{ResponseFactory, StreamFactory};
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Action\DownloadMailAction;
use Yii3\Debug\Mail\MailFileStore;
use Yii3\Debug\Tests\Support\{HelperFactory, TemporaryDirectory};

use function file_put_contents;
use function mkdir;

/**
 * Unit tests for {@see DownloadMailAction} streaming stored `.eml` files and rejecting unknown or unsafe names.
 */
final class DownloadMailActionTest extends TestCase
{
    private string $root = '';

    public function testDownloadStreamsTheStoredMessageAsAnAttachment(): void
    {
        $response = $this->action()(HelperFactory::createRequest(uri: '/debug/download-mail?file=stored.eml'));

        self::assertSame(
            200,
            $response->getStatusCode(),
            'Stored file must be served.',
        );
        self::assertSame(
            'message/rfc822',
            $response->getHeaderLine('Content-Type'),
            'Body must be typed as a message.',
        );
        self::assertSame(
            'attachment; filename="stored.eml"',
            $response->getHeaderLine('Content-Disposition'),
            'File must download under its stored name.',
        );
        self::assertSame(
            "Subject: stored\r\n\r\nBody",
            (string) $response->getBody(),
            'File must be streamed as is.',
        );
    }

    public function testMissingUnsafeOrUnknownNamesAnswerNotFound(): void
    {
        $action = $this->action();

        $queries = ['', '?file=', '?file[]=stored.eml', '?file=unknown.eml', '?file=../outside.eml', '?file=..'];

        foreach ($queries as $query) {
            $response = $action(HelperFactory::createRequest(uri: "/debug/download-mail{$query}"));

            self::assertSame(404, $response->getStatusCode(), "Query '{$query}' must not be served.");
            self::assertSame(
                'text/plain; charset=UTF-8',
                $response->getHeaderLine('Content-Type'),
                "Query '{$query}' must answer in plain text.",
            );
            self::assertSame(
                'Mail file not found',
                (string) $response->getBody(),
                'Body must match the Yii2 text.',
            );
        }
    }

    protected function setUp(): void
    {
        $this->root = TemporaryDirectory::create('yii3-debug-mail-download-');

        mkdir("{$this->root}/mail");
        file_put_contents("{$this->root}/mail/stored.eml", "Subject: stored\r\n\r\nBody");
        file_put_contents("{$this->root}/outside.eml", 'outside');
    }

    protected function tearDown(): void
    {
        TemporaryDirectory::remove($this->root);
    }

    private function action(): DownloadMailAction
    {
        return new DownloadMailAction(
            new MailFileStore("{$this->root}/mail"),
            new ResponseFactory(),
            new StreamFactory(),
        );
    }
}
