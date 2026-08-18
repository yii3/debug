<?php

declare(strict_types=1);

namespace Yii3\Debug\User;

use Yiisoft\Auth\IdentityInterface;

/**
 * Supplies the switchable identities listed by the User panel switch grid.
 */
interface IdentityListProviderInterface
{
    /**
     * Returns every identity the debugger may switch to.
     *
     * Usage example:
     *
     * ```php
     * $identities = $provider->identities();
     * ```
     *
     * @return list<IdentityInterface> Switchable identities.
     */
    public function identities(): array;
}
