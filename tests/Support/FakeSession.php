<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support;

use Yiisoft\Session\SessionInterface;

use function array_key_exists;
use function bin2hex;
use function random_bytes;

/**
 * Provides an in-memory session fixture for the user-switch tests.
 */
final class FakeSession implements SessionInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    private string|null $id;

    public function __construct()
    {
        $this->id = bin2hex(random_bytes(8));
    }

    /**
     * @return array<string, mixed> Stored session data.
     */
    public function all(): array
    {
        return $this->data;
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function close(): void {}

    public function destroy(): void
    {
        $this->data = [];
        $this->id = null;
    }

    public function discard(): void {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * @return array<string, mixed> Cookie parameters.
     */
    public function getCookieParameters(): array
    {
        return [];
    }

    public function getId(): string|null
    {
        return $this->id;
    }

    public function getName(): string
    {
        return 'fake-session';
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function isActive(): bool
    {
        return true;
    }

    public function open(): void {}

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->data[$key] ?? $default;

        unset($this->data[$key]);

        return $value;
    }

    public function regenerateId(): void
    {
        $this->id = bin2hex(random_bytes(8));
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function setId(string $sessionId): void
    {
        $this->id = $sessionId;
    }
}
