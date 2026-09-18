<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs\Cache;

use function array_key_exists;

/**
 * Represents an application-owned cache service reporting every operation to {@see CacheCollector}.
 */
final class Cache
{
    /**
     * @var array<string, mixed> Stored values, keyed by cache key.
     */
    private array $items = [];

    public function __construct(private readonly CacheCollector $collector) {}

    public function get(string $key): mixed
    {
        $hit = array_key_exists($key, $this->items);

        $this->collector->record('get', $key, $hit ? 'hit' : 'miss');

        return $hit ? $this->items[$key] : null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->items[$key] = $value;

        $this->collector->record('set', $key, 'stored');
    }
}
