<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Web;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Web\DebugUrlGenerator;

/**
 * Unit tests for {@see DebugUrlGenerator} covering Yii3 debugger history, panel, and action links.
 *
 * @since 0.1
 */
#[Group('routing')]
final class DebugUrlGeneratorTest extends TestCase
{
    public function testBuildsActionUrlScopedToTag(): void
    {
        $urls = new DebugUrlGenerator();

        self::assertSame(
            '/debug/db-explain?tag=request-1&seq=3',
            $urls->action('/db-explain', 'request-1', ['panel' => 'db', 'seq' => 3]),
            'Action URLs must normalize the action path and retain the current tag.',
        );
    }
    public function testBuildsHistoryUrlAndNormalizesPrefix(): void
    {
        $urls = new DebugUrlGenerator('/tools/debug/');

        self::assertSame('/tools/debug', $urls->history(), 'Trailing slashes must be removed from the prefix.');
        self::assertSame(
            '/tools/debug?Debug%5BstatusCode%5D=500',
            $urls->history(['Debug' => ['statusCode' => 500]]),
            'History filters must use RFC 3986 query encoding.',
        );
    }

    public function testBuildsPanelUrlAndReplacesReservedParameters(): void
    {
        $urls = new DebugUrlGenerator();

        self::assertSame(
            '/debug/view?tag=request%201&panel=app.example&page=2',
            $urls->panel(
                'request 1',
                'app.example',
                ['tag' => 'stale', 'panel' => 'stale', 'page' => 2],
            ),
            'Panel URLs must preserve the requested target instead of stale query values.',
        );
    }
}
