<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use JsonSerializable;
use RuntimeException;

/**
 * Event whose payload and lifecycle-like accessor must never be read by collector instrumentation.
 */
final class SensitiveEventStub implements JsonSerializable
{
    public string $secret = '<secret-event-payload>';

    /**
     * Mimics the lifecycle accessor, which the collector must only call on whitelisted dispatcher events.
     */
    public function getMiddleware(): never
    {
        throw new RuntimeException('The event must not be inspected as a lifecycle event.');
    }

    public function jsonSerialize(): mixed
    {
        throw new RuntimeException('The event payload must not be serialized.');
    }
}
