<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use PHPForge\Debug\CollectorInterface;

/**
 * Represents an explicitly registered extension collector with an arbitrary ID.
 */
final class ExtensionCollectorStub implements CollectorInterface
{
    public function __construct(private readonly string $id = 'extension') {}

    public function capture(): array|null
    {
        return null;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function shutdown(): void {}

    public function startup(): void {}
}
