<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support;

use Closure;
use Stringable;
use Yiisoft\Rbac\{ManagerInterface, Permission, Role};

/**
 * Minimal in-memory RBAC manager stub for unit tests.
 */
final class FakeRbacManager implements ManagerInterface
{
    /**
     * @var array<string, Permission[]>
     */
    private array $userPermissions = [];
    /**
     * @var array<string, Role[]>
     */
    private array $userRoles = [];
    public function addChild(string $parentName, string $childName): self
    {
        return $this;
    }
    public function addPermission(Permission $permission): self
    {
        return $this;
    }
    public function addRole(Role $role): self
    {
        return $this;
    }
    public function assign(string $itemName, int|Stringable|string $userId, int|null $createdAt = null): self
    {
        return $this;
    }
    public function canAddChild(string $parentName, string $childName): bool
    {
        return false;
    }
    /**
     * @return array<string, Role>
     */
    public function getChildRoles(string $roleName): array
    {
        return [];
    }
    /**
     * @return string[]
     */
    public function getDefaultRoleNames(): array
    {
        return [];
    }
    /**
     * @return array<string, Role>
     */
    public function getDefaultRoles(): array
    {
        return [];
    }
    public function getGuestRole(): Role|null
    {
        return null;
    }
    public function getGuestRoleName(): string|null
    {
        return null;
    }
    /**
     * @return array<string, Role|Permission>
     */
    public function getItemsByUserId(int|Stringable|string $userId): array
    {
        return [];
    }
    public function getPermission(string $name): Permission|null
    {
        return null;
    }
    /**
     * @return array<string, Permission>
     */
    public function getPermissionsByRoleName(string $roleName): array
    {
        return [];
    }

    public function getPermissionsByUserId(int|Stringable|string $userId): array
    {
        $key = (string) $userId;
        $result = [];

        foreach ($this->userPermissions[$key] ?? [] as $permission) {
            $result[$permission->getName()] = $permission;
        }

        return $result;
    }
    public function getRole(string $name): Role|null
    {
        return null;
    }

    public function getRolesByUserId(int|Stringable|string $userId): array
    {
        $key = (string) $userId;
        $result = [];

        foreach ($this->userRoles[$key] ?? [] as $role) {
            $result[$role->getName()] = $role;
        }

        return $result;
    }
    /**
     * @return string[]
     */
    public function getUserIdsByRoleName(string $roleName): array
    {
        return [];
    }
    public function hasChild(string $parentName, string $childName): bool
    {
        return false;
    }
    public function hasChildren(string $parentName): bool
    {
        return false;
    }
    public function removeChild(string $parentName, string $childName): self
    {
        return $this;
    }
    public function removeChildren(string $parentName): self
    {
        return $this;
    }
    public function removePermission(string $name): self
    {
        return $this;
    }
    public function removeRole(string $name): self
    {
        return $this;
    }
    public function revoke(string $itemName, int|Stringable|string $userId): self
    {
        return $this;
    }
    public function revokeAll(int|Stringable|string $userId): self
    {
        return $this;
    }
    /**
     * @param array<string>|\Closure(): array<string> $roleNames
     * @phpstan-ignore method.childParameterType (vendor ManagerInterface ships without value-type annotations)
     */
    public function setDefaultRoleNames(array|Closure $roleNames): self
    {
        return $this;
    }
    public function setGuestRoleName(string|null $name): self
    {
        return $this;
    }
    public function updatePermission(string $name, Permission $permission): self
    {
        return $this;
    }
    public function updateRole(string $name, Role $role): self
    {
        return $this;
    }

    /**
     * @param array<array-key, mixed> $parameters
     */
    public function userHasPermission(int|Stringable|string|null $userId, string $permissionName, array $parameters = []): bool
    {
        return false;
    }

    /**
     * Seeds permissions for a user ID.
     *
     * @param string $userId User identifier.
     * @param Permission[] $permissions Permissions to return for that user.
     */
    public function withPermissionsForUser(string $userId, array $permissions): self
    {
        $clone = clone $this;
        $clone->userPermissions[$userId] = $permissions;

        return $clone;
    }

    /**
     * Seeds roles for a user ID.
     *
     * @param string $userId User identifier.
     * @param Role[] $roles Roles to return for that user.
     */
    public function withRolesForUser(string $userId, array $roles): self
    {
        $clone = clone $this;
        $clone->userRoles[$userId] = $roles;

        return $clone;
    }
}
