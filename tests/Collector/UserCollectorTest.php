<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use JsonSerializable;
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Helper\SensitiveDataRedactor;
use PHPForge\Debug\Panel\User\UserSnapshot;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use stdClass;
use Yii3\Debug\Collector\UserCollector;
use Yii3\Debug\Tests\Support\HelperFactory;
use Yii3\Debug\Tests\Support\Stubs\{ContainerStub, IdentityStub};
use Yiisoft\Auth\{IdentityInterface, IdentityRepositoryInterface};
use Yiisoft\Rbac\{Manager, ManagerInterface, Permission, Role, SimpleAssignmentsStorage, SimpleItemsStorage};
use Yiisoft\User\CurrentUser;

/**
 * Unit tests for {@see UserCollector} identity, attribute, and RBAC capture across the request lifecycle.
 */
final class UserCollectorTest extends TestCase
{
    public function testCaptureIsNullOutsideTheCaptureWindow(): void
    {
        $collector = new UserCollector(new ContainerStub([CurrentUser::class => self::currentUser(null)]));

        self::assertNull(
            $collector->capture(),
            'Idle collector must record nothing.',
        );

        $collector->startup();

        self::assertNotNull(
            $collector->capture(),
            'Started collector must record the user.',
        );

        $collector->shutdown();

        self::assertNull(
            $collector->capture(),
            'Stopped collector must record nothing.',
        );
    }

    public function testCaptureIsNullWithoutCurrentUser(): void
    {
        $collector = new UserCollector(new ContainerStub());

        $collector->startup();

        self::assertNull(
            $collector->capture(),
            'Missing `yiisoft/user` must disable the panel.',
        );
    }

    public function testCaptureKeepsRolesWhenPermissionLookupFails(): void
    {
        $manager = self::createStub(ManagerInterface::class);

        $manager
            ->method('getRolesByUserId')
            ->willReturn([(new Role('admin'))->withCreatedAt(1)->withUpdatedAt(2)]);
        $manager
            ->method('getPermissionsByUserId')
            ->willThrowException(new RuntimeException('Storage is offline.'));

        $data = self::capture(
            new UserCollector(
                new ContainerStub(
                    [
                        CurrentUser::class => self::currentUser(new IdentityStub()),
                        ManagerInterface::class => $manager,
                    ],
                ),
            ),
        );

        self::assertSame(
            [
                [
                    'name' => 'admin',
                    'description' => '',
                    'ruleName' => null,
                    'data' => '',
                    'createdAt' => 1,
                    'updatedAt' => 2,
                ],
            ],
            $data['roles'] ?? null,
            'Roles read before the failure must be kept.',
        );
        self::assertArrayHasKey(
            'permissions',
            $data,
            'Payload must keep the permissions key.',
        );
        self::assertNull(
            $data['permissions'],
            'Failed lookup must leave permissions `null`.',
        );
        self::assertSame(
            '100',
            $data['id'] ?? null,
            'Identity must survive the RBAC failure.',
        );
    }

    public function testCaptureReadsIdentityAttributesFromTheFirstAvailableSource(): void
    {
        $arrayable = new class implements IdentityInterface, JsonSerializable {
            public string $name = 'public';

            public function getId(): string
            {
                return '1';
            }

            /**
             * @return array<string, string>
             */
            public function jsonSerialize(): array
            {
                return ['name' => 'json'];
            }

            /**
             * @return array<string, string>
             */
            public function toArray(): array
            {
                return ['name' => 'array'];
            }
        };
        $serializable = new class implements IdentityInterface, JsonSerializable {
            public string $name = 'public';

            public function getId(): string
            {
                return '2';
            }

            /**
             * @return array<string, string>
             */
            public function jsonSerialize(): array
            {
                return ['name' => 'json'];
            }
        };
        $scalar = new class implements IdentityInterface, JsonSerializable {
            public string $name = 'public';

            public function getId(): string
            {
                return '3';
            }

            public function jsonSerialize(): string
            {
                return 'json';
            }
        };

        self::assertSame(
            ['name' => "'array'"],
            self::capture(self::collector($arrayable))['identity'] ?? null,
            '`toArray()` must win over `jsonSerialize()`.',
        );
        self::assertSame(
            ['name' => "'json'"],
            self::capture(self::collector($serializable))['identity'] ?? null,
            '`jsonSerialize()` must win over public properties.',
        );
        self::assertSame(
            ['name' => "'public'"],
            self::capture(self::collector($scalar))['identity'] ?? null,
            'Non-array serialization must fall back to public properties.',
        );
        self::assertSame(
            ['email' => "'reader@example.com'", 7 => "'badge'"],
            self::capture(
                new UserCollector(
                    new ContainerStub([CurrentUser::class => self::currentUser($arrayable)]),
                    identityData: static fn(IdentityInterface $identity): array => [
                        'email' => 'reader@example.com',
                        7 => 'badge',
                    ],
                ),
            )['identity'] ?? null,
            'Configured reader must win, numeric keys included.',
        );
    }

    public function testCaptureReadsNoRbacManagerTheContainerDoesNotDefine(): void
    {
        $manager = self::createStub(ManagerInterface::class);

        $manager
            ->method('getRolesByUserId')
            ->willReturn([new Role('admin')]);
        $manager
            ->method('getPermissionsByUserId')
            ->willReturn([new Permission('createPost')]);

        $container = self::createStub(ContainerInterface::class);

        $container
            ->method('has')
            ->willReturnCallback(static fn(string $id): bool => $id === CurrentUser::class);
        $container
            ->method('get')
            ->willReturnMap(
                [
                    [CurrentUser::class, self::currentUser(new IdentityStub())],
                    [ManagerInterface::class, $manager],
                ],
            );

        $data = self::capture(new UserCollector($container));

        self::assertNull(
            $data['roles'] ?? null,
            'Undeclared manager must not list roles.',
        );
        self::assertNull(
            $data['permissions'] ?? null,
            'Undeclared manager must not list permissions.',
        );
    }

    public function testCaptureReadsTheIdentityWhenTheResponseLeaves(): void
    {
        $user = self::currentUser(new IdentityStub('7'));

        $collector = new UserCollector(new ContainerStub([CurrentUser::class => $user]));

        $collector->startup();

        $collector->collectRequest(HelperFactory::createRequest());
        $collector->collectResponse(HelperFactory::createResponse());

        $user->clearIdentityOverride();
        $collector->startup();

        self::assertSame(
            '7',
            self::data($collector)['id'] ?? null,
            'Identity must be the one present when the response left.',
        );

        $collector->shutdown();
        $collector->startup();

        self::assertSame(
            [
                'id' => null,
                'identity' => null,
                'attributes' => null,
                'roles' => null,
                'permissions' => null,
            ],
            self::data($collector),
            'New request must discard the previous identity.',
        );
    }

    public function testCaptureRecordsGuestPayload(): void
    {
        self::assertSame(
            [
                'id' => null,
                'identity' => null,
                'attributes' => null,
                'roles' => null,
                'permissions' => null,
            ],
            self::capture(self::collector(null)),
            'Guest payload must match the Yii2 shape.',
        );
    }

    public function testCaptureRecordsIdentityRolesAndPermissions(): void
    {
        $manager = new Manager(new class extends SimpleItemsStorage {}, new class extends SimpleAssignmentsStorage {});

        $manager
            ->addRole(
                (new Role('admin'))
                    ->withDescription('Administrator')
                    ->withRuleName('isOwner')
                    ->withCreatedAt(1_700_000_000)
                    ->withUpdatedAt(1_700_000_100),
            )
            ->addPermission((new Permission('createPost'))->withCreatedAt(1_700_000_200)->withUpdatedAt(1_700_000_300))
            ->addPermission((new Permission('deletePost'))->withCreatedAt(1_700_000_400)->withUpdatedAt(1_700_000_500))
            ->addChild('admin', 'createPost')
            ->addChild('admin', 'deletePost')
            ->assign('admin', '100');

        self::assertSame(
            [
                'id' => '100',
                'identity' => [
                    'email' => "'admin@example.com'",
                    'password_hash' => SensitiveDataRedactor::PLACEHOLDER,
                    'username' => "'admin'",
                    'id' => "'100'",
                ],
                'attributes' => null,
                'roles' => [
                    [
                        'name' => 'admin',
                        'description' => 'Administrator',
                        'ruleName' => 'isOwner',
                        'data' => '',
                        'createdAt' => 1_700_000_000,
                        'updatedAt' => 1_700_000_100,
                    ],
                ],
                'permissions' => [
                    [
                        'name' => 'createPost',
                        'description' => '',
                        'ruleName' => null,
                        'data' => '',
                        'createdAt' => 1_700_000_200,
                        'updatedAt' => 1_700_000_300,
                    ],
                    [
                        'name' => 'deletePost',
                        'description' => '',
                        'ruleName' => null,
                        'data' => '',
                        'createdAt' => 1_700_000_400,
                        'updatedAt' => 1_700_000_500,
                    ],
                ],
            ],
            self::capture(
                new UserCollector(
                    new ContainerStub(
                        [
                            CurrentUser::class => self::currentUser(new IdentityStub()),
                            ManagerInterface::class => $manager,
                        ],
                    ),
                ),
            ),
            'Payload must carry public attributes and the assigned RBAC items.',
        );
    }

    public function testCaptureRedactsNestedSensitiveKeys(): void
    {
        $user = new stdClass();

        $user->login = 'admin';
        $user->password_hash = 'hash';

        $identity = self::capture(
            new UserCollector(
                new ContainerStub([CurrentUser::class => self::currentUser(new IdentityStub())]),
                identityData: static fn(IdentityInterface $identity): array => [
                    'profile' => ['auth_key' => 'secret', 'theme' => 'dark'],
                    'user' => $user,
                ],
            ),
        )['identity'] ?? null;

        self::assertSame(
            [
                'profile' => "[\n    'auth_key' => '[redacted]'\n    'theme' => 'dark'\n]",
                'user' => "[\n    'login' => 'admin'\n    'password_hash' => '[redacted]'\n]",
            ],
            $identity,
            'Sensitive keys must be redacted inside arrays and objects too.',
        );
    }

    public function testCaptureRedactsTheKeysOfTheConfiguredPolicy(): void
    {
        $identity = self::capture(
            new UserCollector(
                new ContainerStub([CurrentUser::class => self::currentUser(new IdentityStub())]),
                new CapturePolicy(sensitiveKeys: ['email']),
            ),
        )['identity'] ?? null;

        self::assertIsArray(
            $identity,
            'Identity must be captured.',
        );
        self::assertSame(
            SensitiveDataRedactor::PLACEHOLDER,
            $identity['email'] ?? null,
            'Policy key must be redacted.',
        );
        self::assertSame(
            "'\$2y\$13\$secret'",
            $identity['password_hash'] ?? null,
            'Keys outside the policy must be kept.',
        );
    }

    public function testCollectResponseIsIgnoredOutsideTheCaptureWindow(): void
    {
        $read = false;

        $container = self::createStub(ContainerInterface::class);

        $container
            ->method('has')
            ->willReturnCallback(
                static function () use (&$read): bool {
                    $read = true;

                    return true;
                },
            );

        (new UserCollector($container))->collectResponse(HelperFactory::createResponse());

        self::assertFalse(
            $read,
            'Idle collector must not read the container.',
        );
    }

    public function testIdIsUser(): void
    {
        self::assertSame(
            'user',
            (new UserCollector(new ContainerStub()))->id(),
            'ID must pair the collector with the User panel.',
        );
    }

    public function testThrowRuntimeExceptionFromCaptureWhenTheIdentityReaderFailsForTheResponse(): void
    {
        $failure = new RuntimeException('Identity reader failed.');

        $calls = 0;

        $collector = new UserCollector(
            new ContainerStub([CurrentUser::class => self::currentUser(new IdentityStub())]),
            identityData: static function (IdentityInterface $identity) use (&$calls, $failure): array {
                if (++$calls === 1) {
                    throw $failure;
                }

                return ['retried' => 'yes'];
            },
        );

        $collector->startup();

        $collector->collectResponse(HelperFactory::createResponse());

        $this->expectExceptionObject($failure);

        $collector->capture();
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function capture(UserCollector $collector): array
    {
        $collector->startup();

        return self::data($collector);
    }

    private static function collector(IdentityInterface|null $identity): UserCollector
    {
        return new UserCollector(new ContainerStub([CurrentUser::class => self::currentUser($identity)]));
    }

    private static function currentUser(IdentityInterface|null $identity): CurrentUser
    {
        $user = new CurrentUser(
            self::createStub(IdentityRepositoryInterface::class),
            self::createStub(EventDispatcherInterface::class),
        );

        if ($identity !== null) {
            $user->overrideIdentity($identity);
        }

        return $user;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function data(UserCollector $collector): array
    {
        return UserSnapshot::fromArray($collector->capture(), '$.panels.user')->data();
    }
}
