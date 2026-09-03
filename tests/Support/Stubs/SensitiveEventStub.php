<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use JsonSerializable;
use RuntimeException;

/**
 * Event whose payload must never be serialized by collector instrumentation.
 */
final class SensitiveEventStub implements JsonSerializable
{
    public string $secret = '<secret-event-payload>';

    public function jsonSerialize(): mixed
    {
        throw new RuntimeException('The event payload must not be serialized.');
    }
}
