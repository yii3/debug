<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPForge\Debug\Helper\SensitiveDataRedactor;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Collector\InertiaCollector;
use Yii3\Debug\Tests\Support\HelperFactory;

/**
 * Unit tests for {@see InertiaCollector} request and page capture.
 */
#[Group('inertia')]
final class InertiaCollectorTest extends TestCase
{
    public function testCaptureRecordsExternalLocationAndConflictStatus(): void
    {
        $collector = new InertiaCollector();

        $collector->startup();
        $collector->collectRequest(
            HelperFactory::createRequest(
                'GET',
                'https://example.test/account',
                ['X-Inertia' => 'true'],
            ),
        );
        $collector->collectResponse(
            HelperFactory::createResponse(
                409,
                ['X-Inertia-Location' => 'https://example.test/sign-in'],
            ),
        );

        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'An Inertia external redirect must produce a snapshot without an observed page.',
        );
        self::assertSame(
            409,
            $snapshot->statusCode,
            'External redirect snapshots must retain the conflict status.',
        );
        self::assertSame(
            'https://example.test/sign-in',
            $snapshot->location,
            'External redirect snapshots must retain the response location.',
        );
        self::assertSame(
            [
                'location' => 'https://example.test/sign-in',
                'page' => null,
                'requestHeaders' => ['X-Inertia' => 'true'],
                'sharedKeys' => [],
                'statusCode' => 409,
            ],
            $snapshot->data(),
            'An external redirect without a resolved page must retain a `null` page.',
        );
    }

    public function testCaptureRecordsFullPageAndNegotiationMetadata(): void
    {
        $collector = new InertiaCollector();
        $page = [
            'component' => 'Dashboard/Index',
            'props' => ['appName' => 'My Application', 'auth' => ['user' => null]],
            'url' => '/dashboard',
            'version' => 'asset-version-1',
        ];

        $collector->startup();
        $collector->collectRequest(
            HelperFactory::createRequest(
                'GET',
                'https://example.test/dashboard',
                [
                    'Accept' => 'text/html, application/xhtml+xml',
                    'X-Inertia' => 'true',
                    'X-Inertia-Version' => 'asset-version-1',
                ],
            ),
        );
        $collector->observe($page, ['appName', 'auth']);
        $collector->collectResponse(
            HelperFactory::createResponse(
                200,
                ['Content-Type' => 'application/json'],
            ),
        );

        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'An observed Inertia page must produce a snapshot.',
        );
        self::assertSame(
            [
                'location' => null,
                'page' => $page,
                'requestHeaders' => [
                    'X-Inertia' => 'true',
                    'X-Inertia-Version' => 'asset-version-1',
                ],
                'sharedKeys' => ['appName', 'auth'],
                'statusCode' => 200,
            ],
            $snapshot->data(),
            'Full-page snapshots must retain the page, shared keys, and Inertia negotiation metadata.',
        );
    }

    public function testCaptureRecordsPartialReloadMetadata(): void
    {
        $collector = new InertiaCollector();
        $page = [
            'component' => 'Users/Index',
            'props' => ['users' => [['id' => 42]]],
            'url' => '/users?page=2',
            'version' => 'asset-version-2',
        ];

        $collector->startup();
        $collector->collectRequest(
            HelperFactory::createRequest(
                'GET',
                'https://example.test/users?page=2',
                [
                    'X-Inertia' => 'true',
                    'X-Inertia-Partial-Component' => 'Users/Index',
                    'X-Inertia-Partial-Data' => 'users,filters',
                    'X-Inertia-Partial-Except' => 'statistics',
                    'X-Inertia-Reset' => 'flash',
                    'X-Inertia-Error-Bag' => 'createUser',
                    'X-Inertia-Except-Once-Props' => 'announcements',
                    'X-Inertia-Infinite-Scroll-Merge-Intent' => 'append',
                    'X-Inertia-Version' => 'asset-version-2',
                ],
            ),
        );
        $collector->observe($page, ['auth']);
        $collector->collectResponse(HelperFactory::createResponse());

        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'A partial Inertia reload must produce a snapshot.',
        );
        self::assertSame(
            [
                'location' => null,
                'page' => $page,
                'requestHeaders' => [
                    'X-Inertia' => 'true',
                    'X-Inertia-Partial-Component' => 'Users/Index',
                    'X-Inertia-Partial-Data' => 'users,filters',
                    'X-Inertia-Partial-Except' => 'statistics',
                    'X-Inertia-Reset' => 'flash',
                    'X-Inertia-Error-Bag' => 'createUser',
                    'X-Inertia-Except-Once-Props' => 'announcements',
                    'X-Inertia-Infinite-Scroll-Merge-Intent' => 'append',
                    'X-Inertia-Version' => 'asset-version-2',
                ],
                'sharedKeys' => ['auth'],
                'statusCode' => 200,
            ],
            $snapshot->data(),
            'Partial reload snapshots must retain the resolved page and every supported request header.',
        );
    }

    public function testCaptureRedactsNestedSecretsAndPageUrlQueryValues(): void
    {
        $collector = new InertiaCollector();

        $collector->startup();
        $collector->collectRequest(
            HelperFactory::createRequest(
                'GET',
                'https://example.test/account',
                ['X-Inertia' => 'true'],
            ),
        );
        $collector->observe(
            [
                'component' => 'Account/Index',
                'props' => [
                    'profile' => [
                        'email' => 'person@example.test',
                        'password' => 'page-password',
                        'sessions' => [
                            ['label' => 'Browser', 'token' => 'session-token'],
                        ],
                    ],
                ],
                'url' => '/account?token=page-token&filter=active#details',
                'version' => 'asset-version-3',
            ],
            ['profile'],
        );
        $collector->collectResponse(HelperFactory::createResponse());

        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'A sensitive Inertia page must still produce a sanitized snapshot.',
        );
        self::assertSame(
            [
                'location' => null,
                'page' => [
                    'component' => 'Account/Index',
                    'props' => [
                        'profile' => [
                            'email' => 'person@example.test',
                            'password' => SensitiveDataRedactor::PLACEHOLDER,
                            'sessions' => [
                                [
                                    'label' => 'Browser',
                                    'token' => SensitiveDataRedactor::PLACEHOLDER,
                                ],
                            ],
                        ],
                    ],
                    'url' => '/account?token=%5Bredacted%5D&filter=active#details',
                    'version' => 'asset-version-3',
                ],
                'requestHeaders' => ['X-Inertia' => 'true'],
                'sharedKeys' => ['profile'],
                'statusCode' => 200,
            ],
            $snapshot->data(),
            'Nested secrets and page URL query values must be redacted while safe values remain visible.',
        );
    }
    public function testCaptureReturnsNullWhileInactiveAndRetainsPlainRequestState(): void
    {
        $collector = new InertiaCollector();

        self::assertNull(
            $collector->capture(),
            'An inactive collector must not produce a snapshot.',
        );

        $collector->startup();
        $collector->collectRequest(
            HelperFactory::createRequest(
                'GET',
                'https://example.test/dashboard',
            ),
        );
        $collector->collectResponse(HelperFactory::createResponse());

        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'An active Inertia integration must retain an empty snapshot for direct panel diagnostics.',
        );
        self::assertSame(
            [
                'location' => null,
                'page' => null,
                'requestHeaders' => [],
                'sharedKeys' => [],
                'statusCode' => 200,
            ],
            $snapshot->data(),
            'A plain request must retain only its response status and no fabricated Inertia activity.',
        );
    }

    public function testShutdownAndStartupClearCapturedState(): void
    {
        $collector = new InertiaCollector();

        $collector->startup();
        $collector->collectRequest(
            HelperFactory::createRequest(
                'GET',
                'https://example.test/first',
                ['X-Inertia' => 'true'],
            ),
        );
        $collector->observe(
            ['component' => 'First', 'props' => [], 'url' => '/first', 'version' => 'one'],
            ['auth'],
        );
        $collector->collectResponse(HelperFactory::createResponse());

        self::assertNotNull(
            $collector->capture(),
            'The first lifecycle must capture its observed page.',
        );

        $collector->shutdown();

        self::assertNull(
            $collector->capture(),
            'Shutdown must make the collector inactive and discard captured state.',
        );

        $collector->startup();
        $collector->collectRequest(
            HelperFactory::createRequest(
                'GET',
                'https://example.test/second',
                ['X-Inertia' => 'true'],
            ),
        );
        $collector->collectResponse(HelperFactory::createResponse(204));

        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'A new lifecycle must capture new Inertia negotiation metadata.',
        );
        self::assertSame(
            [
                'location' => null,
                'page' => null,
                'requestHeaders' => ['X-Inertia' => 'true'],
                'sharedKeys' => [],
                'statusCode' => 204,
            ],
            $snapshot->data(),
            'A new lifecycle must retain only the new request and response state.',
        );
    }

    public function testStartupIsIdempotentWithinTheActiveLifecycle(): void
    {
        $collector = new InertiaCollector();

        $collector->startup();
        $collector->collectRequest(
            HelperFactory::createRequest(
                'GET',
                'https://example.test/dashboard',
                ['X-Inertia' => 'true'],
            ),
        );
        $collector->observe(
            ['component' => 'Dashboard/Index', 'props' => [], 'url' => '/dashboard', 'version' => 'one'],
            ['auth'],
        );
        $collector->collectResponse(HelperFactory::createResponse());

        $collector->startup();

        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'Repeated startup calls must keep the current lifecycle active.',
        );

        $page = $snapshot->data()['page'] ?? null;

        self::assertIsArray(
            $page,
            'An observed Inertia page must retain its array shape.',
        );
        self::assertSame(
            'Dashboard/Index',
            $page['component'] ?? null,
            'Repeated startup calls must not discard an observed Inertia page.',
        );
    }
}
