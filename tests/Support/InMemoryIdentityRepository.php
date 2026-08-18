<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support;

use Yii3\Debug\User\IdentityListProviderInterface;
use Yiisoft\Auth\{IdentityInterface, IdentityRepositoryInterface};

use function array_values;

/**
 * Provides an in-memory identity roster for the user-switch tests.
 */
final class InMemoryIdentityRepository implements IdentityListProviderInterface, IdentityRepositoryInterface
{
    /**
     * @var array<string, IdentityInterface> Identities indexed by ID.
     */
    private array $identities = [];

    /**
     * @param list<IdentityInterface> $identities Available identities.
     */
    public function __construct(array $identities = [])
    {
        foreach ($identities as $identity) {
            $this->identities[(string) $identity->getId()] = $identity;
        }
    }

    public function findIdentity(string $id): IdentityInterface|null
    {
        return $this->identities[$id] ?? null;
    }

    public function identities(): array
    {
        return array_values($this->identities);
    }
}
