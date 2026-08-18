<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Web;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Web\LocalAccessChecker;

/**
 * Unit tests for {@see LocalAccessChecker} enforcing explicit client IP allowlists.
 *
 * @since 0.1
 */
#[Group('toolbar')]
final class LocalAccessCheckerTest extends TestCase
{
    public function testAllowsConfiguredClientAddress(): void
    {
        $request = new ServerRequest('GET', '/', serverParams: ['REMOTE_ADDR' => '127.0.0.1']);

        self::assertTrue(
            (new LocalAccessChecker())->allows($request),
            'Loopback client must receive debug access.',
        );
    }

    public function testRejectsMissingOrUnknownClientAddress(): void
    {
        $checker = new LocalAccessChecker();

        self::assertFalse(
            $checker->allows(new ServerRequest('GET', '/')),
            'Missing client address must deny debug access.',
        );
        self::assertFalse(
            $checker->allows(
                new ServerRequest('GET', '/', serverParams: ['REMOTE_ADDR' => '203.0.113.10']),
            ),
            'Unknown client address must deny debug access.',
        );
    }
}
