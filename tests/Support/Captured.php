<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support;

use PHPForge\Debug\Panel\Asset\AssetSnapshot;
use PHPForge\Debug\Panel\Db\DbSnapshot;
use PHPForge\Debug\Panel\Dump\DumpSnapshot;
use PHPForge\Debug\Panel\Event\EventSnapshot;
use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPForge\Debug\Panel\Mail\MailSnapshot;
use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;
use PHPForge\Debug\Panel\Request\RequestSnapshot;
use Yii3\Debug\Collector\{
    AssetCollector,
    DbCollector,
    DumpCollector,
    EventCollector,
    LogCollector,
    MailCollector,
    ProfilingCollector,
    RequestCollector,
};

/**
 * Reads a collector's encoded capture back through the snapshot its panel hydrates from.
 *
 * Collectors return the persisted payload, so assertions on typed rows exercise the encode and decode steps the
 * store applies between capture and presentation.
 */
final class Captured
{
    public static function asset(AssetCollector $collector): AssetSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : AssetSnapshot::fromArray($payload, '$.asset');
    }

    public static function db(DbCollector $collector): DbSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : DbSnapshot::fromArray($payload, '$.panels.db');
    }

    public static function dump(DumpCollector $collector): DumpSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : DumpSnapshot::fromArray($payload, '$.panels.dump');
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

    public static function mail(MailCollector $collector): MailSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : MailSnapshot::fromArray($payload, '$.panels.mail');
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
