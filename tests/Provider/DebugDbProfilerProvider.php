<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Provider;

use RuntimeException;
use Yii3\Debug\Tests\Db\DebugDbProfilerTest;
use Yiisoft\Db\Profiler\Context\{CommandContext, ConnectionContext};

/**
 * Native context forwarding and capture boundary cases for {@see DebugDbProfilerTest}.
 *
 * Covers command and connection method categories without forwarding diagnostic parameters or exceptions, and
 * profiler calls that cross a capture boundary.
 */
final class DebugDbProfilerProvider
{
    /**
     * @return array<string, array{CommandContext|ConnectionContext, string}>
     */
    public static function applicationProfilerContexts(): array
    {
        return [
            'command method without diagnostic data' => [
                (
                    new CommandContext(
                        'native.query',
                        'diagnostic marker',
                        'SELECT 1',
                        ['private' => 'diagnostic marker'],
                    )
                )->setException(new RuntimeException('command diagnostic')),
                'native.query',
            ],
            'connection method without exception' => [
                (
                    new ConnectionContext('native.open'))
                    ->setException(
                        new RuntimeException('connection diagnostic'),
                    ),
                'native.open',
            ],
        ];
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public static function inactiveBoundarySequences(): array
    {
        return [
            'begin before startup' => [['begin', 'startup', 'end']],
            'end after shutdown' => [['startup', 'begin', 'shutdown', 'end']],
        ];
    }
}
