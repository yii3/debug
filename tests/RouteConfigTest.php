<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yiisoft\Router\Route;

/**
 * Unit tests for the package route configuration exposing Yii3 debug endpoints.
 *
 * @since 0.1
 */
#[Group('toolbar')]
final class RouteConfigTest extends TestCase
{
    public function testParamsConfigureSharedViewAlias(): void
    {
        $params = require dirname(__DIR__) . '/config/params.php';
        self::assertIsArray($params, 'Parameter configuration must return an array.');

        $debug = $params['yii3/debug'] ?? null;
        $aliases = $params['yiisoft/aliases'] ?? null;

        self::assertIsArray($debug, 'Debugger parameter configuration must be present.');
        self::assertIsArray($aliases, 'Alias parameter configuration must be present.');

        self::assertSame(
            '@yii3DebugViews',
            $debug['viewPath'] ?? null,
            'Renderer configuration must reference the adapter-owned view alias.',
        );

        $aliasDefinitions = $aliases['aliases'] ?? null;

        self::assertIsArray($aliasDefinitions, 'Alias definitions must be present.');
        self::assertSame(
            '@vendor/php-forge/debug-core/resources/views',
            $aliasDefinitions['@yii3DebugViews'] ?? null,
            'The adapter-owned alias must target the shared Debug Core templates.',
        );
    }

    public function testRoutesUseConfiguredPrefix(): void
    {
        $params = ['yii3/debug' => ['routePrefix' => '/developer/debug']];
        $routes = require dirname(__DIR__) . '/config/routes.php';

        self::assertIsArray($routes, 'Route configuration must return an array.');
        self::assertCount(9, $routes, 'Configuration must expose the debug, panel-action, EXPLAIN, and identity endpoints.');

        foreach ($routes as $route) {
            self::assertInstanceOf(Route::class, $route, 'Every configured endpoint must be a router route.');
        }

        $history = $routes[0] ?? null;
        $snapshot = $routes[1] ?? null;
        $toolbar = $routes[2] ?? null;
        $phpInfo = $routes[3] ?? null;
        $dbExplain = $routes[4] ?? null;
        $downloadMail = $routes[5] ?? null;
        $queueJob = $routes[6] ?? null;
        $setIdentity = $routes[7] ?? null;
        $resetIdentity = $routes[8] ?? null;

        self::assertInstanceOf(Route::class, $history, 'History endpoint must be present.');
        self::assertInstanceOf(Route::class, $snapshot, 'Snapshot endpoint must be present.');
        self::assertInstanceOf(Route::class, $toolbar, 'Toolbar endpoint must be present.');
        self::assertInstanceOf(Route::class, $phpInfo, 'Phpinfo endpoint must be present.');
        self::assertInstanceOf(Route::class, $dbExplain, 'Database EXPLAIN endpoint must be present.');
        self::assertInstanceOf(Route::class, $downloadMail, 'Mail download endpoint must be present.');
        self::assertInstanceOf(Route::class, $queueJob, 'Queue job endpoint must be present.');
        self::assertInstanceOf(Route::class, $setIdentity, 'Set-identity endpoint must be present.');
        self::assertInstanceOf(Route::class, $resetIdentity, 'Reset-identity endpoint must be present.');
        self::assertSame('/developer/debug', $history->getData('pattern'), 'History route must use the prefix.');
        self::assertSame('/developer/debug/view', $snapshot->getData('pattern'), 'Snapshot route must use the prefix.');
        self::assertSame('/developer/debug/toolbar', $toolbar->getData('pattern'), 'Toolbar route must use the prefix.');
        self::assertSame('/developer/debug/php-info', $phpInfo->getData('pattern'), 'Phpinfo route must use the prefix.');
        self::assertSame(
            '/developer/debug/db-explain',
            $dbExplain->getData('pattern'),
            'Database EXPLAIN route must use the prefix.',
        );
        self::assertSame(
            '/developer/debug/download-mail',
            $downloadMail->getData('pattern'),
            'Mail download route must use the prefix.',
        );
        self::assertSame(
            '/developer/debug/queue-job',
            $queueJob->getData('pattern'),
            'Queue job route must use the prefix.',
        );
        self::assertSame(
            '/developer/debug/set-identity',
            $setIdentity->getData('pattern'),
            'Set-identity route must use the prefix.',
        );
        self::assertSame(['POST'], $setIdentity->getData('methods'), 'Identity switch must require POST.');
        self::assertSame(
            '/developer/debug/reset-identity',
            $resetIdentity->getData('pattern'),
            'Reset-identity route must use the prefix.',
        );
        self::assertSame(['POST'], $resetIdentity->getData('methods'), 'Identity reset must require POST.');
    }
}
