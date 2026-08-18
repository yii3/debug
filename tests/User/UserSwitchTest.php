<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\User;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii3\Debug\Tests\Support\{FakeIdentity, UserFixture};
use Yii3\Debug\User\UserSwitch;

/**
 * Unit tests for {@see UserSwitch} tracking the main user while impersonating.
 */
final class UserSwitchTest extends TestCase
{
    public function testGetMainUserIdReturnsNullForGuests(): void
    {
        $fixture = UserFixture::create([]);

        self::assertNull($fixture->userSwitch->getMainUserId(), 'Guests must have no main user.');
        self::assertTrue($fixture->userSwitch->isMainUser(), 'Guests must count as main.');
    }

    public function testResetRestoresTheMainIdentity(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1'), new FakeIdentity('2')]);

        $fixture->currentUser->login(new FakeIdentity('1'));
        $fixture->userSwitch->setUser(new FakeIdentity('2'));
        $fixture->userSwitch->reset();

        self::assertSame('1', $fixture->currentUser->getId(), 'Main identity must be restored.');
        self::assertFalse($fixture->session->has(UserSwitch::SESSION_KEY), 'Marker must be removed.');
        self::assertTrue($fixture->userSwitch->isMainUser(), 'Main user must be detected after reset.');
    }

    public function testResetWithoutImpersonationIsANoOp(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1')]);

        $fixture->currentUser->login(new FakeIdentity('1'));
        $fixture->userSwitch->reset();

        self::assertSame('1', $fixture->currentUser->getId(), 'Identity must stay unchanged.');
    }

    public function testSetUserBackToMainClearsTheMarker(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1'), new FakeIdentity('2')]);

        $fixture->currentUser->login(new FakeIdentity('1'));
        $fixture->userSwitch->setUser(new FakeIdentity('2'));
        $fixture->userSwitch->setUser(new FakeIdentity('1'));

        self::assertSame('1', $fixture->currentUser->getId(), 'Current identity must be the main user again.');
        self::assertFalse($fixture->session->has(UserSwitch::SESSION_KEY), 'Marker must be removed.');
        self::assertTrue($fixture->userSwitch->isMainUser(), 'Main user must be detected.');
    }
    public function testSetUserRecordsMainUserAndSwitchesIdentity(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1'), new FakeIdentity('2')]);

        $fixture->currentUser->login(new FakeIdentity('1'));
        $fixture->userSwitch->setUser(new FakeIdentity('2'));

        self::assertSame('2', $fixture->currentUser->getId(), 'Current identity must be the impersonated user.');
        self::assertSame('1', $fixture->session->get(UserSwitch::SESSION_KEY), 'Main user must be tracked.');
        self::assertFalse($fixture->userSwitch->isMainUser(), 'Impersonation must be detected.');
        self::assertSame('1', $fixture->userSwitch->getMainUserId(), 'Main user ID must stay the original.');
    }

    public function testThrowRuntimeExceptionWhenMainIdentityCannotBeRestored(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('2')]);

        $fixture->session->set(UserSwitch::SESSION_KEY, 'missing');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The main user identity could not be restored.');

        $fixture->userSwitch->reset();
    }
}
