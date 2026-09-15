<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use Closure;
use Psr\Http\Message\StreamInterface;

/**
 * Wraps a stream whose content is produced on the first read, like a view rendered lazily by the response.
 */
final class LazyBodyStreamStub implements StreamInterface
{
    /**
     * @param StreamInterface $stream Stream carrying the already rendered content.
     * @param Closure(): void $onRead Side effect the rendering performs before the content is returned.
     */
    public function __construct(private readonly StreamInterface $stream, private readonly Closure $onRead) {}

    public function __toString(): string
    {
        ($this->onRead)();

        return (string) $this->stream;
    }

    public function close(): void
    {
        $this->stream->close();
    }

    public function detach()
    {
        return $this->stream->detach();
    }

    public function eof(): bool
    {
        return $this->stream->eof();
    }

    public function getContents(): string
    {
        ($this->onRead)();

        return $this->stream->getContents();
    }

    public function getMetadata(string|null $key = null): mixed
    {
        return $this->stream->getMetadata($key);
    }

    public function getSize(): int|null
    {
        return $this->stream->getSize();
    }

    public function isReadable(): bool
    {
        return $this->stream->isReadable();
    }

    public function isSeekable(): bool
    {
        return $this->stream->isSeekable();
    }

    public function isWritable(): bool
    {
        return $this->stream->isWritable();
    }

    public function read(int $length): string
    {
        return $this->stream->read($length);
    }

    public function rewind(): void
    {
        $this->stream->rewind();
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->stream->seek($offset, $whence);
    }

    public function tell(): int
    {
        return $this->stream->tell();
    }

    public function write(string $string): int
    {
        return $this->stream->write($string);
    }
}
