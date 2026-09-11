<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support;

use PHPForge\Debug\Panel\Db\DbSnapshot;
use PHPForge\Debug\Panel\Event\EventSnapshot;
use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;
use PHPForge\Debug\Panel\Request\RequestSnapshot;
use Yii3\Debug\Collector\{DbCollector, EventCollector, LogCollector, ProfilingCollector, RequestCollector};

/**
 * Reads a collector's encoded capture back through the snapshot its panel hydrates from.
 *
 * Collectors return the persisted payload, so assertions on typed rows exercise the encode and decode steps the
 * store applies between capture and presentation.
 */
final class Captured
{
    public static function db(DbCollector $collector): DbSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : DbSnapshot::fromArray($payload, '$.panels.db');
    }

    public static function event(EventCollector $collector): EventSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : EventSnapshot::fromArray($payload, '$.panels.event');
    }

    public static function log(LogCollector $collector): LogSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : LogSnapshot::fromArray($payload, '$.panels.log');
    }

    public static function profiling(ProfilingCollector $collector): ProfilingSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : ProfilingSnapshot::fromArray($payload, '$.panels.profiling');
    }

    public static function request(RequestCollector $collector): RequestSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : RequestSnapshot::fromArray($payload, '$.panels.request');
    }
}
