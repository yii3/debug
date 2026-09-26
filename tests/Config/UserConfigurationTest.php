<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Config;

use PHPForge\Debug\Panel\User\UserSnapshot;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Yii3\Debug\Collector\UserCollector;
use Yii3\Debug\Tests\Support\PackageConfiguration;
use Yii3\Debug\Tests\Support\Stubs\IdentityStub;
use Yiisoft\Auth\{IdentityInterface, IdentityRepositoryInterface};
use Yiisoft\User\CurrentUser;

/**
 * Unit tests for the packaged User collector definition and its `user.identityData` parameter.
 */
final class UserConfigurationTest extends TestCase
{
    public function testConfiguredIdentityReaderReachesThePackagedCollector(): void
    {
        $user = new CurrentUser(
            self::createStub(IdentityRepositoryInterface::class),
            self::createStub(EventDispatcherInterface::class),
        );

        $user->overrideIdentity(new IdentityStub());

        $params = PackageConfiguration::params(
            [
                'user' => [
                    'identityData' => static fn(IdentityInterface $identity): array => ['login' => 'reader'],
                ],
            ],
        );

        $collector = PackageConfiguration::container(
            definitions: [
                ...PackageConfiguration::serviceDefinitions('collectors', $params),
                CurrentUser::class => $user,
            ],
        )->get(UserCollector::class);

        self::assertInstanceOf(
            UserCollector::class,
            $collector,
            'User collector must be packaged.',
        );

        $collector->startup();

        self::assertSame(
            ['login' => "'reader'"],
            UserSnapshot::fromArray($collector->capture(), '$.panels.user')->data()['identity'] ?? null,
            'Parameter reader must replace the default attribute source.',
        );
    }
}
