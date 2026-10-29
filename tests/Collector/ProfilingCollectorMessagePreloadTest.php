<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPUnit\Framework\Attributes\{Group, PreserveGlobalState, RunTestsInSeparateProcesses};
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Yii3\Debug\Collector\ProfilingCollector;
use Yiisoft\Profiler\{Message, Profiler};

use function class_exists;
use function error_reporting;
use function restore_error_handler;
use function set_error_handler;
use function spl_autoload_register;
use function spl_autoload_unregister;

use const E_ALL;
use const E_DEPRECATED;

/**
 * Tests the {@see ProfilingCollector} preload of the Yii profiler message class on startup.
 *
 * The tests run isolated so the message class is still unloaded when the collector starts.
 */
#[Group('collector')]
#[Group('profile')]
#[PreserveGlobalState(false)]
#[RunTestsInSeparateProcesses]
final class ProfilingCollectorMessagePreloadTest extends TestCase
{
    public function testStartupPreloadsTheMessageClassWithoutReportingItsDeprecation(): void
    {
        self::assertFalse(
            class_exists(Message::class, false),
            'The isolated process must start with the message class unloaded.',
        );

        $deprecations = [];

        error_reporting(E_ALL);
        set_error_handler(
            static function (int $level, string $message) use (&$deprecations): bool {
                if ((error_reporting() & $level) !== 0) {
                    $deprecations[] = $message;
                }

                return true;
            },
            E_DEPRECATED,
        );

        try {
            (new ProfilingCollector(new Profiler(new NullLogger())))->startup();
        } finally {
            restore_error_handler();
        }

        self::assertTrue(
            class_exists(Message::class, false),
            'Startup must load the message class.',
        );
        self::assertSame(
            [],
            $deprecations,
            'Loading the message class must not report its deprecation.',
        );
        self::assertSame(
            E_ALL,
            error_reporting(),
            'The previous error reporting level must be restored.',
        );
    }

    public function testStartupRestoresErrorReportingWhenTheMessageClassFailsToLoad(): void
    {
        $autoloader = static function (string $class): void {
            if ($class === Message::class) {
                throw new RuntimeException('Message class unavailable.');
            }
        };

        error_reporting(E_ALL);
        spl_autoload_register($autoloader, true, true);

        try {
            (new ProfilingCollector(new Profiler(new NullLogger())))->startup();

            self::fail(
                'The autoloader failure must reach the caller.',
            );
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Message class unavailable.',
                $exception->getMessage(),
                'The autoloader failure must be preserved.',
            );
        } finally {
            spl_autoload_unregister($autoloader);
        }

        self::assertSame(
            E_ALL,
            error_reporting(),
            'The previous error reporting level must be restored after a failed load.',
        );
    }
}
