<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Helper\Dump;
use PHPForge\Debug\Panel\User\UserSnapshot;
use Yiisoft\User\CurrentUser;

use function get_object_vars;

/**
 * Captures the authenticated Yii3 identity for the User panel.
 *
 * Snapshots the identity's ID and public attributes; guests capture a `null` ID, so the toolbar shows the Guest
 * chip in parity with the Yii2 adapter.
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

    /**
     * @param CurrentUser $currentUser Authenticated-user service supplying the identity.
     */
    public function __construct(private readonly CurrentUser $currentUser) {}

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
            $identityData[(string) $key] = Dump::export($value);
        }

        return UserSnapshot::capture(
            [
                'id' => $identity->getId(),
                'identity' => $identityData,
                'attributes' => null,
                'roles' => null,
                'permissions' => null,
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
}
