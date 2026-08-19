<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\User;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Yii3\Debug\Tests\Support\{FakeIdentity, UserFixture};
use Yii3\Debug\User\UserSwitch;
use Yiisoft\User\Event\BeforeLogin;

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

    public function testRejectedLoginDoesNotCreateAnImpersonationMarker(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
            public bool $reject = false;

            public function dispatch(object $event): object
            {
                if ($this->reject && $event instanceof BeforeLogin) {
                    $event->invalidate();
                }

                return $event;
            }
        };
        $fixture = UserFixture::create([new FakeIdentity('1'), new FakeIdentity('2')], $dispatcher);

        $fixture->currentUser->login(new FakeIdentity('1'));
        $dispatcher->reject = true;

        try {
            $fixture->userSwitch->setUser(new FakeIdentity('2'));
            self::fail('Rejected login must stop the identity switch.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'The requested user identity could not be authenticated.',
                $exception->getMessage(),
                'Rejected target identity must report the stable switch failure.',
            );
        }

        self::assertSame('1', $fixture->currentUser->getId(), 'Rejected login must retain the main identity.');
        self::assertFalse(
            $fixture->session->has(UserSwitch::SESSION_KEY),
            'Rejected login must not leave an impersonation marker.',
        );
    }

    public function testRejectedRestoreRetainsTheImpersonationMarker(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
            public bool $reject = false;

            public function dispatch(object $event): object
            {
                if ($this->reject && $event instanceof BeforeLogin) {
                    $event->invalidate();
                }

                return $event;
            }
        };
        $fixture = UserFixture::create([new FakeIdentity('1'), new FakeIdentity('2')], $dispatcher);

        $fixture->currentUser->login(new FakeIdentity('1'));
        $fixture->userSwitch->setUser(new FakeIdentity('2'));
        $dispatcher->reject = true;

        try {
            $fixture->userSwitch->reset();
            self::fail('Rejected login must stop the main-user restoration.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'The main user identity could not be restored.',
                $exception->getMessage(),
                'Rejected main identity must report the stable restoration failure.',
            );
        }

        self::assertSame('2', $fixture->currentUser->getId(), 'Rejected restoration must keep the impersonated user.');
        self::assertSame(
            '1',
            $fixture->session->get(UserSwitch::SESSION_KEY),
            'Rejected restoration must retain the main-user marker for a later retry.',
        );
    }

    public function testResetRestoresTheMainIdentity(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1'), new FakeIdentity('2')]);

        $fixture->currentUser->login(new FakeIdentity('1'));
        $fixture->userSwitch->setUser(new FakeIdentity('2'));
        $switchedSessionId = $fixture->session->getId();
        $fixture->userSwitch->reset();

        self::assertSame('1', $fixture->currentUser->getId(), 'Main identity must be restored.');
        self::assertFalse($fixture->session->has(UserSwitch::SESSION_KEY), 'Marker must be removed.');
        self::assertTrue($fixture->userSwitch->isMainUser(), 'Main user must be detected after reset.');
        self::assertNotSame(
            $switchedSessionId,
            $fixture->session->getId(),
            'Restoration must regenerate the session ID through CurrentUser login.',
        );
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
        $mainSessionId = $fixture->session->getId();
        $fixture->userSwitch->setUser(new FakeIdentity('2'));

        self::assertSame('2', $fixture->currentUser->getId(), 'Current identity must be the impersonated user.');
        self::assertSame('1', $fixture->session->get(UserSwitch::SESSION_KEY), 'Main user must be tracked.');
        self::assertFalse($fixture->userSwitch->isMainUser(), 'Impersonation must be detected.');
        self::assertSame('1', $fixture->userSwitch->getMainUserId(), 'Main user ID must stay the original.');
        self::assertNotSame(
            $mainSessionId,
            $fixture->session->getId(),
            'Impersonation must regenerate the session ID through CurrentUser login.',
        );
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
