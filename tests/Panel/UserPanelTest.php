<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\User\UserSnapshot;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\UserPanel;
use Yii3\Debug\Tests\Support\{FakeIdentity, GridFactory, UserFixture};

/**
 * Unit tests for {@see UserPanel} presenting the identity payload and the user-switch controls.
 */
final class UserPanelTest extends TestCase
{
    public function testMetadataIdentifiesTheUserPanel(): void
    {
        $panel = new UserPanel(GridFactory::panelGrid());

        self::assertSame('user', $panel->id(), 'Stable ID must pair with the user collector.');
        self::assertSame('user', $panel->icon(), 'Icon must use the shared user glyph.');
        self::assertSame('User', $panel->name(), 'Label must match the Yii2 panel.');
    }

    public function testRenderOmitsSwitchControlsForGuests(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1')]);

        $panel = new UserPanel(GridFactory::panelGrid(), $fixture->userSwitch, $fixture->repository, null, true);
        $payload = UserSnapshot::capture(['id' => null])->jsonSerialize();

        $html = $panel->render($payload);

        self::assertStringNotContainsString('Switch user', $html, 'Guests must never see the switch controls.');
    }

    public function testRenderOmitsSwitchControlsWhenDisabled(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1')]);

        $fixture->currentUser->login(new FakeIdentity('1'));

        $panel = new UserPanel(GridFactory::panelGrid(), $fixture->userSwitch, $fixture->repository);
        $payload = UserSnapshot::capture(['id' => '1'])->jsonSerialize();

        $html = $panel->render($payload);

        self::assertStringNotContainsString('Switch user', $html, 'Deny-by-default must hide the switch section.');
    }

    public function testRenderShowsIdentityAndSwitchControlsWhenEnabled(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1', 'admin'), new FakeIdentity('2', 'editor')]);

        $fixture->currentUser->login(new FakeIdentity('1', 'admin'));

        $panel = new UserPanel(
            GridFactory::panelGrid(),
            $fixture->userSwitch,
            $fixture->repository,
            null,
            true,
            '/debug',
        );
        $payload = UserSnapshot::capture(['id' => '1', 'identity' => ['username' => "'admin'"]])->jsonSerialize();

        $html = $panel->render($payload);

        self::assertStringContainsString('Switch user', $html, 'Switch section header must be rendered.');
        self::assertStringContainsString('debug-userswitch__set-identity', $html, 'Set-identity form must exist.');
        self::assertStringContainsString('id="user_id"', $html, 'User ID input must keep its runtime ID.');
        self::assertStringContainsString('debug-userswitch__filter', $html, 'Identity grid container must exist.');
        self::assertStringContainsString('data-key="2"', $html, 'Grid rows must carry their identity ID.');
        self::assertStringContainsString('display:none', $html, 'Manual form must hide when the grid renders.');
        self::assertStringContainsString('/debug/set-identity', $html, 'Form must target the switch endpoint.');
    }

    public function testRenderShowsResetButtonWhileImpersonating(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1'), new FakeIdentity('2')]);

        $fixture->currentUser->login(new FakeIdentity('1'));
        $fixture->userSwitch->setUser(new FakeIdentity('2'));

        $panel = new UserPanel(GridFactory::panelGrid(), $fixture->userSwitch, $fixture->repository, null, true);
        $payload = UserSnapshot::capture(['id' => '2'])->jsonSerialize();

        $html = $panel->render($payload);

        self::assertStringContainsString('Reset to', $html, 'Reset action must be rendered.');
        self::assertStringContainsString(
            'debug-userswitch__reset-identity-button',
            $html,
            'Reset button must keep its runtime ID.',
        );
        self::assertStringContainsString('/debug/reset-identity', $html, 'Reset form must target its endpoint.');
    }

    public function testToolbarItemsShowGuestChipForNullIdentity(): void
    {
        $payload = UserSnapshot::capture(['id' => null])->jsonSerialize();

        $items = (new UserPanel(GridFactory::panelGrid()))->toolbarItems($payload);

        self::assertCount(1, $items, 'Exactly one identity chip must be emitted.');
        self::assertSame('Guest', $items[0]->value, 'Guest chip must read `Guest`.');
        self::assertNull($items[0]->label, 'Panel title must identify the guest chip without a duplicate label.');
    }

    public function testToolbarItemsShowInfoChipForMainUser(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('7')]);

        $fixture->currentUser->login(new FakeIdentity('7'));

        $payload = UserSnapshot::capture(['id' => '7'])->jsonSerialize();
        $panel = new UserPanel(GridFactory::panelGrid(), $fixture->userSwitch);

        $items = $panel->toolbarItems($payload);
        $chip = $items[0] ?? null;

        self::assertNotNull($chip, 'Exactly one identity chip must be emitted.');
        self::assertSame('7', $chip->value, 'Chip value must expose the identity ID.');
        self::assertNull($chip->label, 'Panel title must identify the main-user chip without a duplicate label.');
        self::assertSame('info', $chip->status, 'Main-user chip must use info status.');
    }

    public function testToolbarItemsShowSwitchingChipWhileImpersonating(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1'), new FakeIdentity('2')]);

        $fixture->currentUser->login(new FakeIdentity('1'));
        $fixture->userSwitch->setUser(new FakeIdentity('2'));

        $payload = UserSnapshot::capture(['id' => '2'])->jsonSerialize();
        $panel = new UserPanel(GridFactory::panelGrid(), $fixture->userSwitch);

        $items = $panel->toolbarItems($payload);
        $chip = $items[0] ?? null;

        self::assertNotNull($chip, 'Exactly one identity chip must be emitted.');
        self::assertSame('2', $chip->value, 'Chip value must expose the impersonated ID.');
        self::assertSame('switching', $chip->label, 'Chip must add only the non-duplicated switching state.');
        self::assertSame('warning', $chip->status, 'Switching chip must use warning status.');
    }
}
