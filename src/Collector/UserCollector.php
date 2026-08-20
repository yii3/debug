<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Helper\Dump;
use PHPForge\Debug\Helper\SensitiveDataRedactor;
use PHPForge\Debug\Panel\User\UserSnapshot;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\User\CurrentUser;

use function get_object_vars;

/**
 * Captures the authenticated Yii3 identity for the User panel, with optional RBAC roles and permissions.
 *
 * Snapshots the identity's ID and public attributes; guests capture a `null` ID, so the toolbar shows the Guest
 * chip in parity with the Yii2 adapter. When a {@see ManagerInterface} is wired, also captures the roles and
 * permissions assigned to the authenticated user.
 *
 * Usage example:
 *
 * ```php
 * $snapshot = (new \Yii3\Debug\Collector\UserCollector($currentUser))->capture();
 * ```
 */
final class UserCollector implements CollectorInterface
{
    private bool $active = false;
    private readonly CapturePolicy $capturePolicy;

    /**
     * @param CurrentUser $currentUser Authenticated-user service supplying the identity.
     * @param ManagerInterface|null $rbacManager RBAC manager used to fetch roles and permissions, or `null` when
     * the `yiisoft/rbac` package is not installed or not wired.
     * @param CapturePolicy|null $capturePolicy Persistent-capture policy, or `null` to use secure defaults.
     */
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly ManagerInterface|null $rbacManager = null,
        CapturePolicy|null $capturePolicy = null,
    ) {
        $this->capturePolicy = $capturePolicy ?? new CapturePolicy();
    }

    /**
     * Snapshots the authenticated identity in the shared User payload shape.
     *
     * Usage example:
     *
     * ```php
     * $snapshot = $collector->capture();
     * ```
     *
     * @return UserSnapshot|null Captured identity payload; `null` when the collector never started.
     */
    public function capture(): UserSnapshot|null
    {
        if (!$this->active) {
            return null;
        }

        if ($this->currentUser->isGuest()) {
            return UserSnapshot::capture(
                [
                    'id' => null,
                    'identity' => [],
                    'attributes' => null,
                    'roles' => null,
                    'permissions' => null,
                ],
            );
        }

        $identity = $this->currentUser->getIdentity();
        $identityData = [];

        foreach (get_object_vars($identity) as $key => $value) {
            $identityData[(string) $key] = $this->capturePolicy->isSensitiveKey((string) $key)
                ? SensitiveDataRedactor::PLACEHOLDER
                : Dump::export($value);
        }

        $userId = $identity->getId();
        $roles = null;
        $permissions = null;

        if ($this->rbacManager !== null && $userId !== null) {
            $roles = $this->normalizeRbacItems($this->rbacManager->getRolesByUserId($userId));
            $permissions = $this->normalizeRbacItems($this->rbacManager->getPermissionsByUserId($userId));
        }

        return UserSnapshot::capture(
            [
                'id' => $userId,
                'identity' => $identityData,
                'attributes' => null,
                'roles' => $roles,
                'permissions' => $permissions,
            ],
        );
    }

    /**
     * Returns the stable ID pairing this collector with the User panel.
     *
     * Usage example:
     *
     * ```php
     * $id = $collector->id();
     * ```
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'user';
    }

    /**
     * Deactivates the collector, so a reused worker process starts clean.
     */
    public function shutdown(): void
    {
        $this->active = false;
    }

    /**
     * Activates the collector for the current request cycle.
     */
    public function startup(): void
    {
        $this->active = true;
    }

    /**
     * Normalizes an RBAC item map into the shared array shape expected by `UserSnapshot`.
     *
     * @param array<string, \Yiisoft\Rbac\Permission|\Yiisoft\Rbac\Role> $items Items indexed by name.
     *
     * @return list<array{name:string,description:string,ruleName:string,data:string,createdAt:int|null,updatedAt:int|null}>
     */
    private function normalizeRbacItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $normalized[] = [
                'name' => $item->getName(),
                'description' => $item->getDescription(),
                'ruleName' => $item->getRuleName() ?? '',
                'data' => '',
                'createdAt' => $item->getCreatedAt(),
                'updatedAt' => $item->getUpdatedAt(),
            ];
        }

        return $normalized;
    }
}
