<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use PHPForge\Debug\Panel\Config\ConfigSnapshot;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\ConfigDataFactory;

use function array_keys;
use function is_array;
use function sort;

use const PHP_VERSION;

/**
 * Unit tests for live Configuration page data.
 */
final class ConfigDataFactoryTest extends TestCase
{
    public function testCreateUsesNeutralDefaultsForInvalidMetadata(): void
    {
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
            '',
            $application['name'] ?? null,
            'Invalid application name must fall back to an empty string.',
        );
        self::assertSame(
            'UTF-8',
            $application['charset'] ?? null,
            'Invalid charset must fall back to UTF-8.',
        );
        self::assertFalse(
            $application['debug'] ?? null,
            'Invalid debug metadata must fall back to `false`.',
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
