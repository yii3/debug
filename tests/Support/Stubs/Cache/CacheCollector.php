<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs\Cache;

use PHPForge\Debug\CollectorInterface;

/**
 * Buffers cache operations recorded by {@see Cache} while the request runs.
 *
 * The constructor takes no argument, so a container autowires it from the class name alone.
 */
final class CacheCollector implements CollectorInterface
{
    /**
     * @var list<array{string, string, string}> Recorded operation, key, and result triples in call order.
     */
    private array $operations = [];
    /**
     * Indicates whether request-scoped collection is running.
     */
    private bool $started = false;

    public function capture(): array|null
    {
        return $this->started ? ['operations' => $this->operations] : null;
    }

    public function id(): string
    {
        return 'cache';
    }

    public function record(string $operation, string $key, string $result): void
    {
        if ($this->started) {
            $this->operations[] = [$operation, $key, $result];
        }
    }

    public function shutdown(): void
    {
        $this->started = false;
        $this->operations = [];
    }

    public function startup(): void
    {
        $this->started = true;
    }
}
