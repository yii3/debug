<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Panel\User\UserSnapshot;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\UserPanel;
use Yii3\Debug\Tests\Support\{FakeIdentity, GridFactory, UserFixture};
use Yii3\Debug\Web\DebugUrlGenerator;
use Yiisoft\Csrf\StubCsrfToken;

use function array_map;
use function range;
use function substr_count;

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

        self::assertSame(
            <<<HTML
            <h1 class="yii-debug-sr-only">
            User
            </h1><div class="yii-debug-empty-state">
            <h2>
            No user authenticated in this request
            </h2><p>
            The request was served to a guest, so there are no identity attributes, roles, or permissions to inspect.
            </p><p>
            Sign in and reload to inspect the identity. User switching remains unavailable to guests.
            </p>
            </div>
            HTML,
            $html,
            'Guests must render the shared complete empty state without authenticated-user tabs.',
        );
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

    public function testRenderRbacUsesSharedHeadingsAndCompleteColumns(): void
    {
        $payload = UserSnapshot::capture(
            [
                'id' => '1',
                'identity' => ['id' => "'1'", 'username' => "'admin'"],
                'roles' => [
                    [
                        'name' => 'admin',
                        'description' => 'Administrator',
                        'ruleName' => 'isAdmin',
                        'data' => '{"scope":"all"}',
                        'createdAt' => 1_700_000_000,
                        'updatedAt' => 1_700_000_001,
                    ],
                ],
                'permissions' => [],
            ],
        )->jsonSerialize();

        $html = (new UserPanel(GridFactory::panelGrid()))->render($payload);

        self::assertStringContainsString(
            '>Roles and Permissions</a>',
            $html,
            'RBAC tab must use the complete Yii2 label.',
        );
        self::assertSame(2, substr_count($html, '>Data<'), 'Both RBAC grids must expose the Data column.');
        self::assertStringContainsString('{"scope":"all"}', $html, 'RBAC data must reach the role grid.');
        self::assertStringContainsString("\nRoles\n</h2>", $html, 'Roles heading must come from the shared renderer.');
        self::assertStringContainsString(
            "\nPermissions\n</h2>",
            $html,
            'Captured empty permissions must retain the shared heading.',
        );
    }

    public function testRenderShowsIdentityAndSwitchControlsWhenEnabled(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1', 'admin'), new FakeIdentity('2', 'editor')]);

        $fixture->currentUser->login(new FakeIdentity('1', 'admin'));

        $panel = new UserPanel(
            GridFactory::panelGrid(),
            $fixture->userSwitch,
            $fixture->repository,
            new StubCsrfToken('valid'),
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
        self::assertStringContainsString('>User</a>', $html, 'Identity tab must use the Yii2 panel label.');
        self::assertStringContainsString('>Switch User</a>', $html, 'Switch tab must use the Yii2 panel label.');
    }

    public function testRenderShowsResetButtonWhileImpersonating(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1'), new FakeIdentity('2')]);

        $fixture->currentUser->login(new FakeIdentity('1'));
        $fixture->userSwitch->setUser(new FakeIdentity('2'));

        $panel = new UserPanel(
            GridFactory::panelGrid(),
            $fixture->userSwitch,
            $fixture->repository,
            new StubCsrfToken('valid'),
            true,
        );
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

    public function testRenderSwitchControlsFailClosedWithoutCsrfToken(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1'), new FakeIdentity('2')]);

        $fixture->currentUser->login(new FakeIdentity('1'));

        $panel = new UserPanel(GridFactory::panelGrid(), $fixture->userSwitch, $fixture->repository, null, true);
        $payload = UserSnapshot::capture(['id' => '1'])->jsonSerialize();

        $html = $panel->render($payload);

        self::assertStringNotContainsString(
            'debug-userswitch__set-identity',
            $html,
            'Switch controls must stay hidden without a CSRF token service.',
        );
    }

    public function testRenderSwitchFormIncludesTheConfiguredCsrfToken(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1')]);

        $fixture->currentUser->login(new FakeIdentity('1'));

        $panel = new UserPanel(
            GridFactory::panelGrid(),
            $fixture->userSwitch,
            null,
            new StubCsrfToken('csrf-value'),
            true,
        );
        $payload = UserSnapshot::capture(['id' => '1', 'identity' => ['username' => "'admin'"]])->jsonSerialize();

        $html = $panel->render($payload);

        self::assertStringContainsString(
            '<input name="_csrf" type="hidden" value="csrf-value">',
            $html,
            'The switch form must submit the configured CSRF token.',
        );
    }

    public function testRenderWithContextFiltersSortsAndPaginatesSwitchableIdentities(): void
    {
        $identities = array_map(
            static fn(int $id): FakeIdentity => new FakeIdentity(
                (string) $id,
                "user-{$id}",
                "user-{$id}@example.com",
                '10',
                (string) (1_700_000_000 + $id),
                (string) (1_700_000_100 + $id),
            ),
            range(1, 12),
        );
        $fixture = UserFixture::create($identities);

        $fixture->currentUser->login($identities[0]);

        $panel = new UserPanel(
            GridFactory::panelGrid(),
            $fixture->userSwitch,
            $fixture->repository,
            new StubCsrfToken('valid'),
            true,
        );
        $payload = UserSnapshot::capture(['id' => '1', 'identity' => ['username' => "'user-1'"]])->jsonSerialize();
        $context = new PanelRenderContext(
            'request-1',
            'user',
            ['User' => ['_active' => 'switch']],
            'light',
            new DebugUrlGenerator(),
        );

        $html = $panel->renderWithContext($payload, $context);

        self::assertSame(10, substr_count($html, 'data-key="'), 'The first page must retain Yii2\'s ten-user page size.');
        self::assertStringContainsString('class="yii-debug-grid-footer"', $html, 'Full grid pagination must render.');
        self::assertStringContainsString('name="User[username]"', $html, 'Username filter must use the shared prefix.');
        self::assertStringContainsString('name="User[email]"', $html, 'Email filter must use the shared prefix.');
        self::assertStringContainsString('>Created At<', $html, 'Created timestamp column must render.');
        self::assertStringContainsString('>Updated At<', $html, 'Updated timestamp column must render.');
        self::assertStringContainsString(
            'class="yii-debug-tab-link is-active" id="user-tab-1"',
            $html,
            'A switch-grid request must reopen the Switch User tab.',
        );

        $filtered = $panel->renderWithContext(
            $payload,
            new PanelRenderContext(
                'request-1',
                'user',
                ['User' => ['username' => 'user-12']],
                'light',
                new DebugUrlGenerator(),
            ),
        );

        self::assertSame(1, substr_count($filtered, 'data-key="12"'), 'The matching identity must remain visible.');
        self::assertSame(0, substr_count($filtered, 'data-key="11"'), 'Unmatched identities must be filtered out.');
        self::assertStringContainsString('class="yii-debug-active-filters"', $filtered, 'Active filters must render.');
        self::assertStringContainsString(
            'User%5B_active%5D=switch',
            $filtered,
            'Removing the last filter must keep the Switch User tab active.',
        );
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
