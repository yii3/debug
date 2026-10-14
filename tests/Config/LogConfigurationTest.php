<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Config;

use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Panel\Log\LogRow;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Yii3\Debug\Collector\LogCollector;
use Yii3\Debug\Log\DebugLogTarget;
use Yii3\Debug\Tests\Support\{Captured, PackageConfiguration};
use Yiisoft\Log\Message;

use function array_map;

/**
 * Unit tests for the {@see LogCollector} the container builds with the application {@see CapturePolicy}.
 */
final class LogConfigurationTest extends TestCase
{
    public function testApplicationCapturePolicyReachesTheContainerCollector(): void
    {
        $container = PackageConfiguration::container(
            definitions: [
                CapturePolicy::class => new CapturePolicy(['pin']),
            ],
        );

        $collector = $container->get(LogCollector::class);

        self::assertInstanceOf(
            LogCollector::class,
            $collector,
            'Container must build the Log collector.',
        );

        $target = $container->get(DebugLogTarget::class);

        self::assertInstanceOf(
            DebugLogTarget::class,
            $target,
            'Container must share the debug log target.',
        );

        $collector->startup();
        $target->collect([new Message(LogLevel::INFO, 'signed in', ['pin' => '1234', 'password' => 'kept'])], false);

        self::assertSame(
            [['pin' => '[redacted]', 'password' => 'kept']],
            array_map(static fn(LogRow $row): array => $row->context, Captured::log($collector)?->entries() ?? []),
            'Only the keys of the application policy must be redacted.',
        );
    }
}
