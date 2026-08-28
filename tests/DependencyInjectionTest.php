<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use PHPUnit\Framework\TestCase;
use Yii3\Debug\Middleware\ToolbarMiddleware;
use Yii3\Debug\ToolbarDataFactory;
use Yii3\Debug\Web\ToolbarRenderer;

/**
 * Unit tests for the package DI configuration.
 */
final class DependencyInjectionTest extends TestCase
{
    public function testComposerPublishesOnlyFoundationalConfigFiles(): void
    {
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__) . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($composer, 'Composer manifest must decode to an array.');
        $extra = $composer['extra'] ?? null;
        self::assertIsArray($extra, 'Composer extra configuration must be present.');

        self::assertSame(
            ['params' => 'params.php', 'di' => 'di.php', 'routes' => 'routes.php'],
            $extra['config-plugin'] ?? null,
        );
    }

    public function testConfigurationPublishesOnlyToolbarServices(): void
    {
        $params = require dirname(__DIR__) . '/config/params.php';
        $definitions = require dirname(__DIR__) . '/config/di.php';

        self::assertIsArray($params, 'Package parameters must return an array.');
        self::assertIsArray($definitions, 'Package DI configuration must return an array.');
        self::assertSame(
            [ToolbarDataFactory::class, ToolbarMiddleware::class, ToolbarRenderer::class],
            array_keys($definitions),
            'DI must contain only toolbar services.',
        );
    }
}
