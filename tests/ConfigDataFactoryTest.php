<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use Composer\InstalledVersions;
use PHPForge\Debug\Panel\Config\ConfigSnapshot;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\ConfigDataFactory;

use function array_keys;
use function getenv;
use function is_array;
use function putenv;
use function sort;

use const PHP_VERSION;

/**
 * Unit tests for live Configuration page data.
 */
final class ConfigDataFactoryTest extends TestCase
{
    public function testCreateFallsBackToTheEnvironmentNameSources(): void
    {
        self::assertSame(
            'staging',
            self::applicationWith('APP_ENV', 'staging', null)['env'] ?? null,
            '`APP_ENV` must name the environment.',
        );
        self::assertSame(
            'staging',
            self::applicationWith('APP_ENV', '', 'staging')['env'] ?? null,
            'An empty process variable must defer to the server variable.',
        );
        self::assertSame(
            '',
            self::applicationWith('APP_ENV', '', ['dev'])['env'] ?? null,
            'A value that is no `string` must leave the environment unnamed.',
        );
    }

    public function testCreateFallsBackToTheProcessEnvironmentForDebugMode(): void
    {
        self::assertTrue(
            self::applicationWith('APP_DEBUG', '1', null)['debug'] ?? null,
            '`APP_DEBUG` must enable debug mode.',
        );
        self::assertTrue(
            self::applicationWith('APP_DEBUG', '', 'true')['debug'] ?? null,
            'An empty process variable must defer to the server variable.',
        );
    }

    public function testCreateFallsBackToTheRootPackageAndTheEnvironment(): void
    {
        $rootPackage = InstalledVersions::getRootPackage();

        $application = self::applicationWith('APP_DEBUG', null, null);

        self::assertSame(
            $rootPackage['name'],
            $application['name'] ?? null,
            'Name must default to the Composer root package.',
        );
        self::assertSame(
            $rootPackage['pretty_version'],
            $application['version'] ?? null,
            'Version must default to the Composer root package.',
        );
        self::assertFalse(
            $application['debug'] ?? null,
            'Debug mode must stay `false` without `APP_DEBUG`.',
        );
    }

    public function testCreateKeepsDebugDisabledForFalsyAndNonScalarValues(): void
    {
        self::assertFalse(
            self::applicationWith('APP_DEBUG', null, 'false')['debug'] ?? null,
            'A falsy value must keep debug mode off.',
        );
        self::assertFalse(
            self::applicationWith('APP_DEBUG', null, ['on'])['debug'] ?? null,
            'A value that is no scalar must keep debug mode off.',
        );
    }

    public function testCreateUsesNeutralDefaultsForInvalidMetadata(): void
    {
        $rootPackage = InstalledVersions::getRootPackage();

        $application = self::slice(
            new ConfigDataFactory(
                [
                    'name' => 42,
                    'charset' => null,
                    'debug' => 'yes',
                ],
            ),
            'application',
        );

        self::assertSame(
            $rootPackage['name'],
            $application['name'] ?? null,
            'An invalid name must fall back to the Composer root package.',
        );
        self::assertSame(
            'UTF-8',
            $application['charset'] ?? null,
            'An invalid charset must fall back to UTF-8.',
        );
        self::assertFalse(
            $application['debug'] ?? null,
            'Invalid debug metadata must fall back to the environment.',
        );
    }

    public function testCreateUsesTypedApplicationMetadataAndRuntimeValues(): void
    {
        $configDataFactory = new ConfigDataFactory(
            [
                'name' => 'Test application',
                'version' => '1.2.3',
                'language' => 'en',
                'sourceLanguage' => 'en-US',
                'charset' => 'ISO-8859-1',
                'env' => 'test',
                'debug' => true,
            ],
        );

        $application = self::slice($configDataFactory, 'application');

        self::assertSame(
            '3',
            $application['yii'] ?? null,
            'Application metadata must identify Yii 3.',
        );
        self::assertSame(
            'Test application',
            $application['name'] ?? null,
            'Application name must preserve typed metadata.',
        );
        self::assertSame(
            '1.2.3',
            $application['version'] ?? null,
            'Application version must preserve typed metadata.',
        );
        self::assertSame(
            'en',
            $application['language'] ?? null,
            'Current language must preserve typed metadata.',
        );
        self::assertSame(
            'en-US',
            $application['sourceLanguage'] ?? null,
            'Source language must stay distinct from the current language.',
        );
        self::assertSame(
            'ISO-8859-1',
            $application['charset'] ?? null,
            'Application charset must preserve typed metadata.',
        );
        self::assertSame(
            'test',
            $application['env'] ?? null,
            'Application environment must preserve typed metadata.',
        );
        self::assertTrue(
            $application['debug'] ?? null,
            'Debug mode must preserve the typed `true` value.',
        );
        self::assertSame(
            PHP_VERSION,
            self::slice($configDataFactory, 'php')['version'] ?? null,
            'PHP metadata must reflect the active runtime version.',
        );

        $extensions = self::slice($configDataFactory, 'extensions');

        $names = array_keys($extensions);

        $sorted = $names;

        sort($sorted);

        self::assertSame(
            $sorted,
            $names,
            'The roster must be ordered by package name.',
        );

        $assets = $extensions['yiisoft/assets'] ?? null;

        self::assertIsArray(
            $assets,
            'Installed Composer packages must be exposed as extensions.',
        );
        self::assertSame(
            'yiisoft/assets',
            $assets['name'] ?? null,
            'Each roster entry must carry its package name.',
        );
        self::assertArrayHasKey(
            'version',
            $assets,
            'Each roster entry must carry its installed version.',
        );
    }

    /**
     * Reads the application slice with one environment variable bound to the given sources for the call.
     *
     * @param string $variable Environment variable to bind.
     * @param string|null $process Process environment value, or `null` to leave the variable unset.
     * @param mixed $server Server environment value, or `null` to leave the variable unset.
     *
     * @return array<array-key, mixed> Decoded application slice.
     */
    private static function applicationWith(string $variable, string|null $process, mixed $server): array
    {
        $previousProcess = getenv($variable);
        $previousServer = $_SERVER[$variable] ?? null;

        self::bind($variable, $process, $server);

        try {
            return self::slice(new ConfigDataFactory(), 'application');
        } finally {
            self::bind($variable, $previousProcess === false ? null : $previousProcess, $previousServer);
        }
    }

    /**
     * Binds one environment variable to the process and server environments.
     *
     * @param string $variable Environment variable to bind.
     * @param string|null $process Process environment value, or `null` to unset the variable.
     * @param mixed $server Server environment value, or `null` to unset the variable.
     */
    private static function bind(string $variable, string|null $process, mixed $server): void
    {
        putenv($process === null ? $variable : "{$variable}={$process}");

        if ($server === null) {
            unset($_SERVER[$variable]);

            return;
        }

        $_SERVER[$variable] = $server;
    }

    /**
     * Decodes one slice of the payload the factory produces.
     *
     * @param ConfigDataFactory $factory Factory under test.
     * @param string $key Slice to read.
     *
     * @return array<array-key, mixed> Decoded slice.
     */
    private static function slice(ConfigDataFactory $factory, string $key): array
    {
        $slice = ConfigSnapshot::fromArray($factory->create(), '$')->data()[$key] ?? null;

        return is_array($slice) ? $slice : self::fail("The payload must carry the {$key} slice.");
    }
}
