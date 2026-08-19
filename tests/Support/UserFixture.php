<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support;

use Psr\EventDispatcher\EventDispatcherInterface;
use Yii3\Debug\User\UserSwitch;
use Yiisoft\User\CurrentUser;

/**
 * Builds a wired current-user, repository, session, and switch fixture for the user-switch tests.
 */
final readonly class UserFixture
{
    private function __construct(
        public CurrentUser $currentUser,
        public InMemoryIdentityRepository $repository,
        public FakeSession $session,
        public UserSwitch $userSwitch,
    ) {}

    /**
     * Creates a fixture seeded with the given identities.
     *
     * @param list<FakeIdentity> $identities Available identities.
     *
     * @return self Wired fixture.
     */
    public static function create(array $identities, EventDispatcherInterface|null $dispatcher = null): self
    {
        $repository = new InMemoryIdentityRepository($identities);
        $session = new FakeSession();
        $dispatcher ??= new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                return $event;
            }
        };
        $currentUser = (new CurrentUser($repository, $dispatcher))->withSession($session);

        return new self(
            $currentUser,
            $repository,
            $session,
            new UserSwitch($currentUser, $repository, $session),
        );
    }
}
