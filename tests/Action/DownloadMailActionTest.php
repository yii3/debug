<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Action;

use GuzzleHttp\Psr7\{HttpFactory, ServerRequest};
use PHPForge\Debug\Panel\Mail\{MailMessage, MailSnapshot};
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary, SnapshotStore};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Action\DownloadMailAction;
use Yii3\Debug\Collector\MailCollector;
use Yii3\Debug\Web\{LocalAccessChecker, ResponseBuilder};

use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Unit tests for {@see DownloadMailAction} enforcing snapshot ownership and safe mail-file resolution.
 */
#[Group('mail')]
final class DownloadMailActionTest extends TestCase
{
    private string $mailPath = '';
    private string $storagePath = '';

    public function testInvokeDownloadsFileOwnedByTheSelectedSnapshot(): void
    {
        mkdir($this->mailPath, 0o777, true);
        file_put_contents($this->mailPath . '/message.eml', 'Subject: Phase 5');
        $store = new SnapshotStore($this->storagePath, 0o777, null);
        $store->writeSnapshot($this->snapshot('message.eml'), 10);
        $request = (new ServerRequest(
            'GET',
            'https://example.test/debug/download-mail',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        ))->withQueryParams(['tag' => 'request-1', 'seq' => '0']);

        $response = $this->action($store)($request);

        self::assertSame(200, $response->getStatusCode(), 'Owned captured file must be downloadable.');
        self::assertSame('message/rfc822', $response->getHeaderLine('Content-Type'), 'Download must use the RFC-822 media type.');
        self::assertSame('attachment; filename="message.eml"', $response->getHeaderLine('Content-Disposition'), 'Safe file name must be used as attachment metadata.');
        self::assertSame('Subject: Phase 5', (string) $response->getBody(), 'Captured file content must stream unchanged.');
    }

    public function testInvokeRejectsTraversalAndMalformedRequests(): void
    {
        $store = new SnapshotStore($this->storagePath, 0o777, null);
        $store->writeSnapshot($this->snapshot('../outside.eml'), 10);
        $base = new ServerRequest(
            'GET',
            'https://example.test/debug/download-mail',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        );

        self::assertSame(
            404,
            $this->action($store)($base->withQueryParams(['tag' => 'request-1', 'seq' => '0']))->getStatusCode(),
            'Snapshot file names containing traversal must be rejected.',
        );
        self::assertSame(
            400,
            $this->action($store)($base->withQueryParams(['tag' => 'request-1', 'seq' => '-1']))->getStatusCode(),
            'Invalid sequences must be rejected before storage access.',
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $root = sys_get_temp_dir() . '/yii3-debug-mail-action-' . uniqid('', true);
        $this->mailPath = $root . '/mail';
        $this->storagePath = $root . '/storage';
    }

    protected function tearDown(): void
    {
        foreach ([$this->mailPath, $this->storagePath] as $path) {
            $files = glob($path . '/*');

            foreach ($files === false ? [] : $files as $file) {
                unlink($file);
            }

            if (is_dir($path)) {
                rmdir($path);
            }
        }

        $root = dirname($this->mailPath);

        if (is_dir($root)) {
            rmdir($root);
        }

        parent::tearDown();
    }

    private function action(SnapshotStore $store): DownloadMailAction
    {
        $factory = new HttpFactory();

        return new DownloadMailAction(
            $store,
            new MailCollector($this->mailPath, 0o777, 0o666),
            new LocalAccessChecker(),
            new ResponseBuilder($factory, $factory),
        );
    }

    private function snapshot(string $file): DebugSnapshot
    {
        return new DebugSnapshot(
            new RequestSummary(
                'request-1',
                'https://example.test/',
                false,
                'GET',
                '127.0.0.1',
                1.0,
                200,
                0,
                0,
                1,
                [$file],
                0.01,
                1024,
            ),
            [
                'mail' => (new MailSnapshot([
                    new MailMessage(
                        'sender@example.test',
                        ['recipient@example.test'],
                        [],
                        [],
                        [],
                        'Subject',
                        'Body',
                        '',
                        'UTF-8',
                        $file,
                        true,
                        1,
                    ),
                ]))->jsonSerialize(),
            ],
            [],
        );
    }
}
