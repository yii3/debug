<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Web;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Web\DebugUrlGenerator;

/**
 * Unit tests for debugger panel URL generation and route-prefix normalization.
 */
#[Group('routing')]
final class DebugUrlGeneratorTest extends TestCase
{
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

    public function testNormalizesTrailingSlashesInTheRoutePrefix(): void
    {
        $urls = new DebugUrlGenerator('/tools/debug/');

        self::assertSame(
            '/tools/debug',
            $urls->routePrefix(),
            'Trailing slashes must be removed from the prefix.',
        );
        self::assertSame(
            '/tools/debug/view?tag=request-1&panel=log&Debug%5BstatusCode%5D=500',
            $urls->panel('request-1', 'log', ['Debug' => ['statusCode' => 500]]),
            'Nested filters must use RFC 3986 query encoding.',
        );
    }
}
