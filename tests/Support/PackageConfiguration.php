<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support;

use PHPForge\Debug\Capture\CapturePolicy;
use RuntimeException;
use Yii3\Debug\Tests\Support\Stubs\Cache\{AlphaPanel, BetaPanel, CacheCollector, CachePanel};
use Yiisoft\Di\{Container, ContainerConfig};

use function dirname;
use function is_array;
use function is_string;

/**
 * Loads the packaged configuration files the way `yiisoft/config` does, with application-modified params.
 */
final class PackageConfiguration
{
    /**
     * Autowired definitions an application would declare for the fixtures it registers.
     *
     * @var array<string, string>
     */
    public const array FIXTURES = [
        AlphaPanel::class => AlphaPanel::class,
        BetaPanel::class => BetaPanel::class,
        CacheCollector::class => CacheCollector::class,
        CachePanel::class => CachePanel::class,
        CapturePolicy::class => CapturePolicy::class,
    ];

    /**
     * Builds a container from the packaged web definitions and the fixture definitions.
     *
     * @param array<string, mixed> $debug Entries merged into `params['yii3/debug']`.
     * @param array<string, mixed> $definitions Extra definitions appended after the fixtures.
     *
     * @return Container Container serving the packaged services.
     */
    public static function container(array $debug = [], array $definitions = []): Container
    {
        return new Container(
            ContainerConfig::create()->withDefinitions(
                [
                    ...self::definitions(self::params($debug)),
                    ...self::FIXTURES,
                    ...$definitions,
                ],
            ),
        );
    }

    /**
     * Loads `config/di-web.php` with the params in scope.
     *
     * @param array<string, mixed> $params Complete params array.
     *
     * @throws RuntimeException When the packaged file does not return a string-keyed array.
     *
     * @return array<string, mixed> Definitions the package declares for a web request.
     */
    public static function definitions(array $params): array
    {
        $file = dirname(__DIR__, 2) . '/config/di-web.php';

        return self::stringKeyed(
            (
                static function (array $params) use ($file): array {
                    $definitions = require $file;

                    if (is_array($definitions) === false) {
                        throw new RuntimeException("Packaged configuration \"{$file}\" must return an array.");
                    }

                    return $definitions;
                }
            )($params),
            $file,
        );
    }

    /**
     * Loads `config/events-web.php` with the params in scope.
     *
     * @param array<string, mixed> $params Complete params array.
     *
     * @throws RuntimeException When the packaged file does not return a string-keyed array.
     *
     * @return array<string, mixed> Listeners the package declares for a web request.
     */
    public static function events(array $params): array
    {
        $file = dirname(__DIR__, 2) . '/config/events-web.php';

        return self::stringKeyed(
            (
                static function (array $params) use ($file): array {
                    $listeners = require $file;

                    if (is_array($listeners) === false) {
                        throw new RuntimeException("Packaged configuration \"{$file}\" must return an array.");
                    }

                    return $listeners;
                }
            )($params),
            $file,
        );
    }

    /**
     * Loads `config/params.php` and merges the application additions into `yii3/debug`.
     *
     * @param array<string, mixed> $debug Entries merged into `params['yii3/debug']`.
     *
     * @return array<string, mixed> Complete params array.
     */
    public static function params(array $debug = []): array
    {
        /** @var array<string, mixed> $params */
        $params = require dirname(__DIR__, 2) . '/config/params.php';

        /** @var array<string, mixed> $packaged */
        $packaged = $params['yii3/debug'] ?? [];

        $params['yii3/debug'] = [...$packaged, ...$debug];

        return $params;
    }

    /**
     * Rejects a packaged configuration whose entries are not indexed by service ID.
     *
     * @param array<array-key, mixed> $entries Entries the packaged file returned.
     * @param string $file Absolute path of the packaged file, named in the failure.
     *
     * @throws RuntimeException When an entry is not indexed by a `string` key.
     *
     * @return array<string, mixed> Entries indexed by service ID.
     */
    private static function stringKeyed(array $entries, string $file): array
    {
        $keyed = [];

        foreach ($entries as $id => $entry) {
            if (is_string($id) === false) {
                throw new RuntimeException("Packaged configuration \"{$file}\" must use string keys.");
            }

            $keyed[$id] = $entry;
        }

        return $keyed;
    }
}
