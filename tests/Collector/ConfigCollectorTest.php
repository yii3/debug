<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPUnit\Framework\TestCase;
use Yii3\Debug\Collector\ConfigCollector;

use const PHP_VERSION;

/**
 * Unit tests for {@see ConfigCollector} capturing the Yii3 runtime identity into the shared Configuration payload.
 */
final class ConfigCollectorTest extends TestCase
{
    public function testCaptureListsInstalledYiisoftPackagesWithSharedEntryShape(): void
    {
        $collector = new ConfigCollector();

        $collector->startup();
        $snapshot = $collector->capture();
        $collector->shutdown();

        self::assertNotNull($snapshot, 'Active collector must expose a snapshot.');

        $extensions = $snapshot->data()['extensions'] ?? null;

        self::assertIsArray($extensions, 'Payload must carry the extensions roster.');
        self::assertNotEmpty($extensions, 'Roster must list installed yiisoft packages.');

        $entry = $extensions['yiisoft/assets'] ?? null;

        self::assertIsArray($entry, 'Direct dependency must appear in the roster.');
        self::assertSame('yiisoft/assets', $entry['name'] ?? null, 'Entry must repeat its package name.');
        self::assertIsString($entry['version'] ?? null, 'Entry must carry a version.');
    }
    public function testCaptureReturnsNullWhenCollectorNeverStarted(): void
    {
        self::assertNull((new ConfigCollector())->capture(), 'Inactive collector must not expose a snapshot.');
    }

    public function testCaptureReturnsSharedConfigurationPayloadDuringActiveLifecycle(): void
    {
        $collector = new ConfigCollector(
            [
                'name' => 'Test App',
                'version' => '1.2.3',
                'language' => 'en',
                'charset' => 'ISO-8859-1',
                'env' => 'dev',
                'debug' => true,
            ],
        );

        $collector->startup();
        $snapshot = $collector->capture();
        $collector->shutdown();

        self::assertNotNull($snapshot, 'Active collector must expose a snapshot.');

        $data = $snapshot->data();
        $application = $data['application'] ?? null;
        $php = $data['php'] ?? null;

        self::assertIsArray($application, 'Payload must carry the application slice.');
        self::assertIsArray($php, 'Payload must carry the PHP slice.');
        self::assertSame(PHP_VERSION, $data['phpVersion'] ?? null, 'PHP version must match the runtime.');
        self::assertSame('3', $data['yiiVersion'] ?? null, 'Framework generation must be reported.');
        self::assertSame('Test App', $application['name'] ?? null, 'Configured name must be captured.');
        self::assertSame('1.2.3', $application['version'] ?? null, 'Configured version must be captured.');
        self::assertSame('ISO-8859-1', $application['charset'] ?? null, 'Configured charset must win the default.');
        self::assertSame('dev', $application['env'] ?? null, 'Configured environment must be captured.');
        self::assertTrue($application['debug'] ?? null, 'Configured debug flag must be captured.');
        self::assertSame(PHP_VERSION, $php['version'] ?? null, 'PHP slice must repeat the runtime version.');
        self::assertNull($collector->capture(), 'Collector must stop exposing data after shutdown.');
    }

    public function testCaptureUsesNeutralDefaultsWhenApplicationMetadataIsMistyped(): void
    {
        $collector = new ConfigCollector(['name' => 42, 'charset' => null, 'debug' => 'yes']);

        $collector->startup();
        $snapshot = $collector->capture();
        $collector->shutdown();

        self::assertNotNull($snapshot, 'Active collector must expose a snapshot.');

        $application = $snapshot->data()['application'] ?? null;

        self::assertIsArray($application, 'Payload must carry the application slice.');
        self::assertSame('', $application['name'] ?? null, 'Mistyped name must fall back to an empty string.');
        self::assertSame('UTF-8', $application['charset'] ?? null, 'Mistyped charset must fall back to `UTF-8`.');
        self::assertFalse($application['debug'] ?? null, 'Mistyped debug flag must fall back to `false`.');
    }
}
