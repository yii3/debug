<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPForge\Debug\Helper\SensitiveDataRedactor;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Collector\UserCollector;
use Yii3\Debug\Tests\Support\{FakeIdentity, UserFixture};

/**
 * Unit tests for {@see UserCollector} capturing the authenticated Yii3 identity into the shared User payload.
 */
final class UserCollectorTest extends TestCase
{
    public function testCaptureRedactsSensitiveIdentityAttributes(): void
    {
        $identity = new FakeIdentity('7', 'admin', access_token: 'identity-secret');
        $fixture = UserFixture::create([$identity]);
        $fixture->currentUser->login($identity);
        $collector = new UserCollector($fixture->currentUser);

        $collector->startup();
        $data = $collector->capture()?->data() ?? [];
        $identityData = $data['identity'] ?? null;

        self::assertIsArray($identityData, 'Identity attributes must remain an array.');

        self::assertSame(
            SensitiveDataRedactor::PLACEHOLDER,
            $identityData['access_token'] ?? null,
            'Sensitive identity attributes must be irreversibly redacted before capture.',
        );
        self::assertSame(
            "'admin'",
            $identityData['username'] ?? null,
            'Non-sensitive identity attributes must remain available.',
        );
    }
    public function testCaptureReportsAuthenticatedIdentityWithDumpedAttributes(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('7', 'admin')]);

        $fixture->currentUser->login(new FakeIdentity('7', 'admin'));

        $collector = new UserCollector($fixture->currentUser);

        $collector->startup();
        $snapshot = $collector->capture();
        $collector->shutdown();

        self::assertNotNull($snapshot, 'Active collector must expose a snapshot.');

        $data = $snapshot->data();

        self::assertSame('7', $data['id'] ?? null, 'Identity ID must be captured.');
        self::assertIsArray($data['identity'] ?? null, 'Identity attributes must be captured.');
        self::assertSame("'admin'", $data['identity']['username'] ?? null, 'Attributes must be dump-exported.');
        self::assertNull($collector->capture(), 'Collector must stop exposing data after shutdown.');
    }

    public function testCaptureReportsGuestWithNullIdentity(): void
    {
        $fixture = UserFixture::create([]);
        $collector = new UserCollector($fixture->currentUser);

        $collector->startup();
        $snapshot = $collector->capture();
        $collector->shutdown();

        self::assertNotNull($snapshot, 'Active collector must expose a snapshot.');
        self::assertNull($snapshot->data()['id'] ?? null, 'Guest capture must resolve to a `null` ID.');
    }

    public function testCaptureReturnsNullWhenCollectorNeverStarted(): void
    {
        $fixture = UserFixture::create([]);

        $collector = new UserCollector($fixture->currentUser);

        self::assertNull($collector->capture(), 'Inactive collector must not expose a snapshot.');
    }
}
