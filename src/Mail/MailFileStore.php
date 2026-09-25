<?php

declare(strict_types=1);

namespace Yii3\Debug\Mail;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Yii3\Debug\Exception\Message;

use function array_flip;
use function basename;
use function bin2hex;
use function chmod;
use function date;
use function file_put_contents;
use function filemtime;
use function glob;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function time;
use function unlink;

use const DIRECTORY_SEPARATOR;
use const LOCK_EX;

/**
 * Stores the `.eml` files of captured mail messages and removes them once no retained capture refers to them.
 *
 * Only bare file names are resolved, so a name read back from a manifest or a request cannot reach outside the mail
 * directory.
 */
final readonly class MailFileStore
{
    /**
     * Age, in seconds, below which an unreferenced `.eml` file is kept, so a message persisted by a request still in
     * flight is not deleted.
     */
    private const int ORPHAN_GRACE_PERIOD = 86_400;

    /**
     * @param string $path Directory holding the `.eml` files, already resolved from any alias.
     * @param int $dirMode Permission bits applied to the directory when it is created.
     * @param int|null $fileMode Permission bits applied to every written file, or `null` to keep the process umask.
     * @param LoggerInterface|null $logger Logger receiving a warning when a file cannot be written, or `null` to report
     * the failure only through the empty file name.
     */
    public function __construct(
        private string $path,
        private int $dirMode = 0o700,
        private int|null $fileMode = 0o600,
        private LoggerInterface|null $logger = null,
    ) {}

    /**
     * Deletes aged `.eml` files no retained capture refers to.
     *
     * The grace period keeps a file written by a concurrent request whose capture is not committed yet. A file that
     * cannot be deleted stays for the next reconciliation.
     *
     * @param iterable<string> $referencedFiles File names referenced by the committed manifest.
     */
    public function reconcile(iterable $referencedFiles): void
    {
        $referenced = array_flip([...$referencedFiles]);
        $paths = glob($this->path . DIRECTORY_SEPARATOR . '*.eml');
        $cutoff = time() - self::ORPHAN_GRACE_PERIOD;

        foreach ($paths === false ? [] : $paths as $path) {
            if (!isset($referenced[basename($path)]) && @filemtime($path) <= $cutoff) {
                @unlink($path);
            }
        }
    }

    /**
     * Deletes the `.eml` files of a capture that was evicted from history or never committed.
     *
     * Unsafe or unknown names are skipped. A file that cannot be deleted is left for {@see reconcile()}.
     *
     * @param iterable<string> $files File names recorded by the capture.
     */
    public function remove(iterable $files): void
    {
        foreach ($files as $file) {
            $path = $this->resolve($file);

            if ($path !== null) {
                @unlink($path);
            }
        }
    }

    /**
     * Returns the absolute path of a stored `.eml` file.
     *
     * @param string $file File name as recorded by the capture or requested by the client.
     *
     * @return string|null Path of the stored file, or `null` when the name carries a path segment or no such file
     * exists.
     */
    public function resolve(string $file): string|null
    {
        if (basename($file) !== $file) {
            return null;
        }

        $path = $this->path . DIRECTORY_SEPARATOR . $file;

        return is_file($path) ? $path : null;
    }

    /**
     * Writes one message to the mail directory under a new unique name.
     *
     * A failure is logged and leaves no partial file behind, so the capture keeps the message without a stored file.
     *
     * @param string $content Message content, normally the RFC 5322 source of the sent message.
     *
     * @return string Name of the written file, or `''` when it could not be written.
     */
    public function write(string $content): string
    {
        $file = date('Ymd-His-') . bin2hex(random_bytes(4)) . '.eml';

        $path = $this->path . DIRECTORY_SEPARATOR . $file;

        try {
            if (
                !is_dir($this->path)
                && (!@mkdir($this->path, $this->dirMode, true) || !@chmod($this->path, $this->dirMode))
            ) {
                throw new RuntimeException(
                    Message::CAPTURED_MAIL_DIRECTORY_CREATE_FAILED->getMessage($this->path),
                );
            }

            if (
                @file_put_contents($path, $content, LOCK_EX) === false
                || ($this->fileMode !== null && !@chmod($path, $this->fileMode))
            ) {
                @unlink($path);

                throw new RuntimeException(
                    Message::CAPTURED_MAIL_FILE_PERSIST_FAILED->getMessage($path),
                );
            }
        } catch (RuntimeException $failure) {
            $this->logger?->warning($failure->getMessage(), ['category' => self::class]);

            return '';
        }

        return $file;
    }
}
