<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support;

use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Storage\PanelSnapshot;
use RuntimeException;

/**
 * Provides an application-defined collector for Yii3 integration tests.
 */
final class CustomCollector implements CollectorInterface
{
    public int $shutdownCount = 0;
    public int $startupCount = 0;

    public function __construct(
        private readonly string $collectorId = 'app.example',
        private readonly bool $failCapture = false,
    ) {}

    public function capture(): PanelSnapshot
    {
        if ($this->failCapture) {
            throw new RuntimeException('Custom collector capture failed.');
        }

        return new CustomSnapshot('custom payload');
    }

    public function id(): string
    {
        return $this->collectorId;
    }

    public function shutdown(): void
    {
        $this->shutdownCount++;
    }

    public function startup(): void
    {
        $this->startupCount++;
    }
}
