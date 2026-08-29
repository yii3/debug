<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use PHPUnit\Framework\TestCase;
use Yii3\Debug\ConfigDataFactory;

use const PHP_VERSION;

/**
 * Unit tests for live Configuration page data.
 */
final class ConfigDataFactoryTest extends TestCase
{
    public function testCreateUsesNeutralDefaultsForInvalidMetadata(): void
    {
        $configDataFactory = new ConfigDataFactory(
            [
                'name' => 42,
                'charset' => null,
                'debug' => 'yes',
            ],
        );

        $summary = $configDataFactory->create();

        self::assertSame(
            '',
            $summary->application->name,
            'Invalid application name must fall back to an empty string.',
        );
        self::assertSame(
            'UTF-8',
            $summary->application->charset,
            'Invalid charset must fall back to UTF-8.',
        );
        self::assertFalse(
            $summary->application->debug,
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

        $summary = $configDataFactory->create();

        self::assertSame(
            '3',
            $summary->application->yii,
            'Application metadata must identify Yii 3.',
        );
        self::assertSame(
            'Test application',
            $summary->application->name,
            'Application name must preserve typed metadata.',
        );
        self::assertSame(
            '1.2.3',
            $summary->application->version,
            'Application version must preserve typed metadata.',
        );
        self::assertSame(
            'ISO-8859-1',
            $summary->application->charset,
            'Application charset must preserve typed metadata.',
        );
        self::assertSame(
            'test',
            $summary->application->env,
            'Application environment must preserve typed metadata.',
        );
        self::assertTrue(
            $summary->application->debug,
            'Debug mode must preserve the typed `true` value.',
        );
        self::assertSame(
            PHP_VERSION,
            $summary->php->version,
            'PHP metadata must reflect the active runtime version.',
        );
        self::assertArrayHasKey(
            'yiisoft/assets',
            $summary->extensions,
            'Installed Composer packages must be exposed as extensions.',
        );
    }
}
