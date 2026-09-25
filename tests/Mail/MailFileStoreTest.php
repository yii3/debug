<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Mail;

use ArrayIterator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Yii3\Debug\Exception\Message;
use Yii3\Debug\Mail\MailFileStore;
use Yii3\Debug\Tests\Support\Stubs\RecordingLoggerStub;
use Yii3\Debug\Tests\Support\TemporaryDirectory;

use function chmod;
use function clearstatcache;
use function file_get_contents;
use function file_put_contents;
use function fileperms;
use function function_exists;
use function mkdir;
use function posix_geteuid;
use function scandir;
use function time;
use function touch;
use function umask;

use const DIRECTORY_SEPARATOR;

/**
 * Unit tests for {@see MailFileStore} writing, resolving, removing, and reconciling captured `.eml` files.
 */
final class MailFileStoreTest extends TestCase
{
    private string $root = '';

    public function testReconcileKeepsReferencedAndFreshFilesAndRemovesAgedOrphans(): void
    {
        $mail = "{$this->root}/mail";

        mkdir($mail);
        file_put_contents("{$this->root}/outside.eml", 'outside');

        do {
            $now = time();

            foreach (['referenced', 'orphan', 'boundary', 'fresh'] as $name) {
                file_put_contents("{$mail}/{$name}.eml", $name);
            }

            file_put_contents("{$mail}/aged.txt", 'aged');

            touch("{$mail}/referenced.eml", $now - 90_000);
            touch("{$mail}/orphan.eml", $now - 90_000);
            touch("{$mail}/boundary.eml", $now - 86_400);
            touch("{$mail}/fresh.eml", $now - 86_399);
            touch("{$mail}/aged.txt", $now - 90_000);

            (new MailFileStore($mail))->reconcile(new ArrayIterator(['referenced.eml', '../outside.eml']));
        } while (time() !== $now);

        clearstatcache();

        self::assertFileExists(
            "{$mail}/referenced.eml",
            'Referenced mail must survive.',
        );
        self::assertFileDoesNotExist(
            "{$mail}/orphan.eml",
            'Aged orphan must be removed.',
        );
        self::assertFileDoesNotExist(
            "{$mail}/boundary.eml",
            'Mail exactly at the cutoff must be removed.',
        );
        self::assertFileExists(
            "{$mail}/fresh.eml",
            'Mail inside the grace period must survive.',
        );
        self::assertFileExists(
            "{$mail}/aged.txt",
            'Only `.eml` files are reconciled.',
        );
        self::assertFileExists(
            "{$this->root}/outside.eml",
            'Files outside the mail directory are never touched.',
        );
    }

    public function testReconcileWithoutMailDirectoryDoesNothing(): void
    {
        $mail = "{$this->root}/missing";

        (new MailFileStore($mail))->reconcile([]);

        self::assertDirectoryDoesNotExist(
            $mail,
            'Reconciliation must not create the directory.',
        );
    }

    public function testRemoveDeletesListedFilesAndSkipsUnsafeOrUnknownNames(): void
    {
        $mail = "{$this->root}/mail";

        mkdir($mail);
        file_put_contents("{$mail}/first.eml", 'first');
        file_put_contents("{$mail}/second.eml", 'second');
        file_put_contents("{$this->root}/outside.eml", 'outside');

        (new MailFileStore($mail))->remove(['first.eml', '../outside.eml', 'unknown.eml']);

        self::assertFileDoesNotExist(
            "{$mail}/first.eml",
            'Listed file must be removed.',
        );
        self::assertFileExists(
            "{$mail}/second.eml",
            'Unlisted file must survive.',
        );
        self::assertFileExists(
            "{$this->root}/outside.eml",
            'Path traversal must not reach outside files.',
        );
    }

    public function testResolveReturnsPathOfStoredFileOnly(): void
    {
        $mail = "{$this->root}/mail";

        mkdir($mail);
        file_put_contents("{$mail}/stored.eml", 'stored');
        file_put_contents("{$this->root}/outside.eml", 'outside');

        $store = new MailFileStore($mail);

        self::assertSame(
            $mail . DIRECTORY_SEPARATOR . 'stored.eml',
            $store->resolve('stored.eml'),
            'Stored file must resolve inside the mail directory.',
        );
        self::assertNull(
            $store->resolve('../outside.eml'),
            'Traversal must not resolve.',
        );
        self::assertNull(
            $store->resolve('unknown.eml'),
            'Unknown file must not resolve.',
        );
        self::assertNull(
            $store->resolve('..'),
            'Parent directory must not resolve.',
        );
    }

    public function testWriteAppliesConfiguredModesDespiteRestrictiveUmask(): void
    {
        $mail = "{$this->root}/nested/mail";

        $previous = umask(0o077);

        try {
            $file = (new MailFileStore($mail, 0o750, 0o640))->write('content');
        } finally {
            umask($previous);
        }

        clearstatcache();

        self::assertSame(
            0o750,
            fileperms($mail) & 0o777,
            'Directory mode must be applied.',
        );
        self::assertSame(
            0o640,
            fileperms("{$mail}/{$file}") & 0o777,
            'File mode must be applied.',
        );
    }

    public function testWriteKeepsUmaskModeWhenFileModeIsNull(): void
    {
        $mail = "{$this->root}/mail";

        $previous = umask(0o022);

        try {
            $file = (new MailFileStore($mail, fileMode: null))->write('content');
        } finally {
            umask($previous);
        }

        clearstatcache();

        self::assertSame(
            0o700,
            fileperms($mail) & 0o777,
            'Default directory mode must be `0700`.',
        );
        self::assertSame(
            0o644,
            fileperms("{$mail}/{$file}") & 0o777,
            'Umask must decide the file mode.',
        );
    }

    public function testWriteLogsAndReturnsEmptyNameWhenDirectoryCannotBeCreated(): void
    {
        $blocker = "{$this->root}/blocker";

        file_put_contents($blocker, 'not a directory');

        $logger = new RecordingLoggerStub();

        $file = (new MailFileStore("{$blocker}/mail", logger: $logger))->write('content');

        self::assertSame(
            '',
            $file,
            'Failed write must yield no file name.',
        );
        self::assertSame(
            [
                [
                    LogLevel::WARNING,
                    Message::CAPTURED_MAIL_DIRECTORY_CREATE_FAILED->getMessage("{$blocker}/mail"),
                    ['category' => MailFileStore::class],
                ],
            ],
            $logger->records,
            'Failure must be logged once as a warning.',
        );
    }

    public function testWriteLogsAndReturnsEmptyNameWhenFileCannotBeWritten(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('Directory permissions are not enforced for the root user.');
        }

        $mail = "{$this->root}/mail";

        mkdir($mail);
        chmod($mail, 0o500);

        $logger = new RecordingLoggerStub();

        try {
            $file = (new MailFileStore($mail, fileMode: null, logger: $logger))->write('content');
            $silent = (new MailFileStore($mail))->write('content');
        } finally {
            chmod($mail, 0o700);
        }

        self::assertSame(
            '',
            $file,
            'Failed write must yield no file name.',
        );
        self::assertSame(
            '',
            $silent,
            'Failure without a logger must also yield no file name.',
        );
        self::assertSame(
            ['.', '..'],
            scandir($mail),
            'No partial file may remain.',
        );
        self::assertCount(
            1,
            $logger->records,
            'Failure must be logged once.',
        );
        self::assertSame(
            LogLevel::WARNING,
            $logger->records[0][0],
            'Failure must be logged as a warning.',
        );
        self::assertStringStartsWith(
            'Unable to persist captured mail file: ' . $mail . DIRECTORY_SEPARATOR,
            $logger->records[0][1],
            'Warning must name the file path.',
        );
    }

    public function testWriteStoresContentUnderGeneratedName(): void
    {
        $mail = "{$this->root}/mail";

        $previous = umask(0o022);

        try {
            $file = (new MailFileStore($mail))->write('Subject: hello');
        } finally {
            umask($previous);
        }

        clearstatcache();

        self::assertMatchesRegularExpression(
            '/^\d{8}-\d{6}-[0-9a-f]{8}\.eml$/',
            $file,
            'Name must carry the send time and an eight-digit random suffix.',
        );
        self::assertSame(
            'Subject: hello',
            file_get_contents("{$mail}/{$file}"),
            'Content must be written verbatim.',
        );
        self::assertSame(
            0o600,
            fileperms("{$mail}/{$file}") & 0o777,
            'Default file mode must be `0600`.',
        );
    }

    protected function setUp(): void
    {
        $this->root = TemporaryDirectory::create('yii3-debug-mail-store-');
    }

    protected function tearDown(): void
    {
        TemporaryDirectory::remove($this->root);
    }
}
