<?php

declare(strict_types=1);

namespace Yii3\Debug\User;

use RuntimeException;
use Yiisoft\Auth\{IdentityInterface, IdentityRepositoryInterface};
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;

use function is_string;

/**
 * Switches the authenticated Yii3 identity while tracking the original (main) user in the session.
 *
 * The session key `main_user` holds the pre-switch user ID; access decisions keep evaluating against that main
 * user, so an impersonated identity can never escalate the switch itself.
 *
 * Usage example:
 *
 * ```php
 * $userSwitch = new \Yii3\Debug\User\UserSwitch($currentUser, $identityRepository, $session);
 * $userSwitch->setUser($identity);
 * ```
 */
final readonly class UserSwitch
{
    /**
     * Session key holding the main user ID while impersonating.
     */
    public const string SESSION_KEY = 'main_user';

    /**
     * @param CurrentUser $currentUser Authenticated-user service whose identity is switched.
     * @param IdentityRepositoryInterface $identityRepository Repository resolving identities by ID.
     * @param SessionInterface $session Session tracking the main user while impersonating.
     */
    public function __construct(
        private CurrentUser $currentUser,
        private IdentityRepositoryInterface $identityRepository,
        private SessionInterface $session,
    ) {}

    /**
     * Returns the ID of the main (pre-switch) user.
     *
     * Usage example:
     *
     * ```php
     * $id = $userSwitch->getMainUserId();
     * ```
     *
     * @return string|null Main user ID, or `null` when no user is authenticated.
     */
    public function getMainUserId(): string|null
    {
        $stored = $this->session->get(self::SESSION_KEY);

        return is_string($stored) ? $stored : $this->currentUser->getId();
    }

    /**
     * Returns whether the current identity is the main user (not impersonating).
     *
     * Usage example:
     *
     * ```php
     * $isMain = $userSwitch->isMainUser();
     * ```
     */
    public function isMainUser(): bool
    {
        $stored = $this->session->get(self::SESSION_KEY);

        return !is_string($stored) || $stored === $this->currentUser->getId();
    }

    /**
     * Restores the main user identity and clears the impersonation marker.
     *
     * Usage example:
     *
     * ```php
     * $userSwitch->reset();
     * ```
     *
     * @throws RuntimeException When the stored main identity can no longer be resolved.
     */
    public function reset(): void
    {
        $stored = $this->session->get(self::SESSION_KEY);

        if (!is_string($stored)) {
            return;
        }

        $identity = $this->identityRepository->findIdentity($stored);

        if ($identity === null) {
            throw new RuntimeException('The main user identity could not be restored.');
        }

        $this->session->remove(self::SESSION_KEY);
        $this->currentUser->login($identity);
    }

    /**
     * Switches to the given identity, recording the main user on the first switch.
     *
     * Usage example:
     *
     * ```php
     * $userSwitch->setUser($identity);
     * ```
     *
     * @param IdentityInterface $identity Identity to impersonate.
     */
    public function setUser(IdentityInterface $identity): void
    {
        $mainId = $this->getMainUserId();

        if ($mainId !== null && $identity->getId() === $mainId) {
            $this->session->remove(self::SESSION_KEY);
            $this->currentUser->login($identity);

            return;
        }

        if ($mainId !== null && !is_string($this->session->get(self::SESSION_KEY))) {
            $this->session->set(self::SESSION_KEY, $mainId);
        }

        $this->currentUser->login($identity);
    }
}
