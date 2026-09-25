<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Middleware;

use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\Storage\{SnapshotStore, StorageException};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use Yii3\Debug\Collector\MailCollector;
use Yii3\Debug\Mail\MailFileStore;
use Yii3\Debug\Middleware\ToolbarOptions;
use Yii3\Debug\Tests\Support\{HelperFactory, MiddlewareFactory, PackageConfiguration, TemporaryDirectory};
use Yiisoft\Mailer\Event\AfterSend;
use Yiisoft\Mailer\Message;

use function file_put_contents;
use function glob;
use function time;
use function touch;

/**
 * Unit tests for {@see \Yii3\Debug\Middleware\RequestCaptureMiddleware} recording the stored `.eml` files in the
 * request summary and removing them with the capture that owns them.
 */
#[Group('toolbar')]
final class RequestCaptureMiddlewareMailTest extends TestCase
{
    private MailCollector $collector;
    private string $root = '';

    public function testCaptureReconcilesAgedFilesNoRetainedCaptureReferences(): void
    {
        $store = new SnapshotStore("{$this->root}/snapshots", 0o700, 0o600);

        $first = $this->process($store, 50, sendMail: true);

        $referenced = $store->readSnapshot($first)?->summary->mailFiles[0] ?? '';

        touch("{$this->root}/mail/{$referenced}", time() - 90_000);
        file_put_contents("{$this->root}/mail/orphan.eml", 'orphan');
        touch("{$this->root}/mail/orphan.eml", time() - 90_000);

        $this->process($store, 50, sendMail: false);

        self::assertFileExists(
            "{$this->root}/mail/{$referenced}",
            'A retained capture keeps its file.',
        );
        self::assertFileDoesNotExist(
            "{$this->root}/mail/orphan.eml",
            'An aged orphan must be removed.',
        );
    }

    public function testEvictedCaptureTakesItsMailFilesAlong(): void
    {
        $store = new SnapshotStore("{$this->root}/snapshots", 0o700, 0o600);

        $post = $this->process($store, 1, sendMail: true);

        $summary = $store->readSnapshot($post)?->summary;

        self::assertNotNull(
            $summary,
            'Capture must be written.',
        );
        self::assertSame(
            1,
            $summary->mailCount,
            'History must count the stored message.',
        );
        self::assertSame(
            ["{$this->root}/mail/" . ($summary->mailFiles[0] ?? '')],
            glob("{$this->root}/mail/*.eml"),
            'History must name the stored file.',
        );

        $get = $this->process($store, 1, sendMail: false);

        self::assertSame(
            0,
            $store->readSnapshot($get)?->summary->mailCount,
            'A request without mail counts none.',
        );
        self::assertSame(
            [],
            glob("{$this->root}/mail/*.eml"),
            'Eviction must remove the file.',
        );
    }

    public function testFailedWriteRemovesTheMailFilesOfTheCapture(): void
    {
        $failure = null;

        try {
            $this->process(MiddlewareFactory::unwritableStore(), 50, sendMail: true);
        } catch (StorageException $exception) {
            $failure = $exception;
        }

        self::assertNotNull(
            $failure,
            'Write failure must propagate.',
        );
        self::assertSame(
            [],
            glob("{$this->root}/mail/*.eml"),
            'An uncommitted capture must not leave its file.',
        );
    }

    protected function setUp(): void
    {
        $this->root = TemporaryDirectory::create('yii3-debug-capture-mail-');
        $this->collector = new MailCollector(
            new MailFileStore("{$this->root}/mail"),
            PackageConfiguration::emailFactory(),
        );
    }

    protected function tearDown(): void
    {
        TemporaryDirectory::remove($this->root);
    }

    private function process(SnapshotStore $store, int $historySize, bool $sendMail): string
    {
        $handler = new readonly class ($this->collector, $sendMail) implements RequestHandlerInterface {
            public function __construct(private MailCollector $collector, private bool $sendMail) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                if ($this->sendMail) {
                    $this->collector->collect(
                        new AfterSend(new Message(from: 'a@example.com', to: 'b@example.com', textBody: 'Body')),
                    );
                }

                return HelperFactory::createResponse(204);
            }
        };

        return MiddlewareFactory::requestCapture(
            $store,
            new CollectorCoordinator([$this->collector]),
            options: new ToolbarOptions(historySize: $historySize),
        )
            ->process(
                HelperFactory::createRequest('POST', '/contact', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
                $handler,
            )
            ->getHeaderLine('X-Debug-Tag');
    }
}
