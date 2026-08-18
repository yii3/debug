<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPUnit\Framework\TestCase;
use Yii3\Debug\Collector\UserCollector;
use Yii3\Debug\Tests\Support\{FakeIdentity, FakeRbacManager, UserFixture};
use Yiisoft\Rbac\{Permission, Role};

/**
 * Unit tests for {@see UserCollector} RBAC roles and permissions capture via an optional RBAC manager.
 *
 * @since 0.1
 */
final class UserCollectorRbacTest extends TestCase
{
    public function testCaptureEmitsNullRolesAndPermissionsWhenNoManagerIsWired(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('2', 'editor')]);
        $fixture->currentUser->login(new FakeIdentity('2', 'editor'));

        $collector = new UserCollector($fixture->currentUser);
        $collector->startup();
        $snapshot = $collector->capture();
        $collector->shutdown();

        self::assertNotNull($snapshot, 'Snapshot must not be `null`.');
        $data = $snapshot->data();

        self::assertArrayHasKey('roles', $data, 'Snapshot must contain a `roles` key.');
        self::assertNull($data['roles'], 'Roles must remain `null` without a manager.');
        self::assertArrayHasKey('permissions', $data, 'Snapshot must contain a `permissions` key.');
        self::assertNull($data['permissions'], 'Permissions must remain `null` without a manager.');
    }

    public function testCaptureEmitsNullRolesForGuestEvenWithManagerWired(): void
    {
        $fixture = UserFixture::create([]);
        $collector = new UserCollector($fixture->currentUser, new FakeRbacManager());
        $collector->startup();
        $snapshot = $collector->capture();
        $collector->shutdown();

        self::assertNotNull($snapshot, 'Guest snapshot must not be `null`.');
        $data = $snapshot->data();
        self::assertArrayHasKey('roles', $data, 'Snapshot must contain a `roles` key.');
        self::assertNull($data['roles'], 'Guest roles must be `null`.');
        self::assertArrayHasKey('permissions', $data, 'Snapshot must contain a `permissions` key.');
        self::assertNull($data['permissions'], 'Guest permissions must be `null`.');
    }
    public function testCaptureIncludesRolesAndPermissionsWhenManagerIsWired(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1', 'admin')]);
        $fixture->currentUser->login(new FakeIdentity('1', 'admin'));

        $manager = (new FakeRbacManager())
            ->withRolesForUser('1', [new Role('admin')])
            ->withPermissionsForUser('1', [(new Permission('manage-users'))->withDescription('Manage users')]);

        $collector = new UserCollector($fixture->currentUser, $manager);
        $collector->startup();
        $snapshot = $collector->capture();
        $collector->shutdown();

        self::assertNotNull($snapshot, 'Snapshot must not be `null`.');
        $data = $snapshot->data();

        self::assertArrayHasKey('roles', $data, 'Snapshot must contain a `roles` key.');
        $roles = $data['roles'];
        self::assertIsArray($roles, 'Roles must be captured as an array.');
        self::assertCount(1, $roles, 'Exactly one role must be present.');
        self::assertArrayHasKey(0, $roles, 'First role must be present at index 0.');
        self::assertIsArray($roles[0], 'Each role must be an array row.');
        $role0 = $roles[0];
        self::assertArrayHasKey('name', $role0, 'Role row must contain a `name` key.');
        self::assertSame('admin', $role0['name'], 'Role name must match.');

        self::assertArrayHasKey('permissions', $data, 'Snapshot must contain a `permissions` key.');
        $permissions = $data['permissions'];
        self::assertIsArray($permissions, 'Permissions must be captured as an array.');
        self::assertCount(1, $permissions, 'Exactly one permission must be present.');
        self::assertArrayHasKey(0, $permissions, 'First permission must be present at index 0.');
        self::assertIsArray($permissions[0], 'Each permission must be an array row.');
        $perm0 = $permissions[0];
        self::assertArrayHasKey('name', $perm0, 'Permission row must contain a `name` key.');
        self::assertSame('manage-users', $perm0['name'], 'Permission name must match.');
        self::assertArrayHasKey('description', $perm0, 'Permission row must contain a `description` key.');
        self::assertSame('Manage users', $perm0['description'], 'Description must be captured.');
    }

    public function testNormalizedRowIncludesAllSixFields(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('3', 'viewer')]);
        $fixture->currentUser->login(new FakeIdentity('3', 'viewer'));

        $role = (new Role('read-only'))
            ->withDescription('Read-only access')
            ->withRuleName('ipRule')
            ->withCreatedAt(1700000000)
            ->withUpdatedAt(1700000001);

        $manager = (new FakeRbacManager())->withRolesForUser('3', [$role]);

        $collector = new UserCollector($fixture->currentUser, $manager);
        $collector->startup();
        $snapshot = $collector->capture();
        $collector->shutdown();

        self::assertNotNull($snapshot, 'Snapshot must not be `null`.');
        $data = $snapshot->data();

        self::assertArrayHasKey('roles', $data, 'Snapshot must contain a `roles` key.');
        $roles = $data['roles'];
        self::assertIsArray($roles, 'Roles must be captured as an array.');
        self::assertCount(1, $roles, 'One role must be present.');

        self::assertArrayHasKey(0, $roles, 'First role must be present at index 0.');
        self::assertIsArray($roles[0], 'Role row must be an array.');
        $row = $roles[0];
        self::assertArrayHasKey('name', $row, 'Row must contain a `name` key.');
        self::assertSame('read-only', $row['name'], 'Name must match.');
        self::assertArrayHasKey('description', $row, 'Row must contain a `description` key.');
        self::assertSame('Read-only access', $row['description'], 'Description must match.');
        self::assertArrayHasKey('ruleName', $row, 'Row must contain a `ruleName` key.');
        self::assertSame('ipRule', $row['ruleName'], 'Rule name must match.');
        self::assertArrayHasKey('data', $row, 'Row must contain a `data` key.');
        self::assertSame('', $row['data'], 'Data must be empty string.');
        self::assertArrayHasKey('createdAt', $row, 'Row must contain a `createdAt` key.');
        self::assertSame(1700000000, $row['createdAt'], 'Created-at timestamp must match.');
        self::assertArrayHasKey('updatedAt', $row, 'Row must contain an `updatedAt` key.');
        self::assertSame(1700000001, $row['updatedAt'], 'Updated-at timestamp must match.');
    }
}
