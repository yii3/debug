<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Records every log call as a level, message, and context triple.
 */
final class RecordingLoggerStub extends AbstractLogger
{
    /**
     * @var list<array{mixed, string, array<array-key, mixed>}>
     */
    public array $records = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [$level, (string) $message, $context];
    }
}
