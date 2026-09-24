<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Middleware;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Middleware\ToolbarOptions;
use Yii3\Debug\Tests\Provider\ToolbarOptionsProvider;
use Yii3\Debug\Tests\Support\PackageConfiguration;

/**
 * Unit tests for {@see ToolbarOptions} reading and validating the `yii3/debug` parameter block.
 *
 * {@see ToolbarOptionsProvider} for test case data providers.
 */
final class ToolbarOptionsTest extends TestCase
{
    public function testConstructorTrimsTheTrailingSlashFromTheRoutePrefix(): void
    {
        self::assertSame(
            '/developer/debug',
            (new ToolbarOptions(routePrefix: '/developer/debug/'))->routePrefix,
            'Trailing slash must be trimmed.',
        );
    }

    public function testFromParamsAcceptsAnExplicitNullThreshold(): void
    {
        self::assertNull(
            ToolbarOptions::fromParams(['database' => ['excessiveCallerThreshold' => null]])->excessiveCallerThreshold,
            'An explicit `null` must disable the check.',
        );
    }

    public function testFromParamsKeepsTheDefaultsForOmittedKeys(): void
    {
        self::assertEquals(
            new ToolbarOptions(),
            ToolbarOptions::fromParams([]),
            'Omitted keys must keep the constructor defaults.',
        );
        self::assertEquals(
            new ToolbarOptions(),
            ToolbarOptions::fromParams(['database' => [], 'toolbar' => []]),
            'Empty blocks must keep the constructor defaults.',
        );
    }

    public function testFromParamsMatchesTheConstructorDefaultsForThePackagedParameters(): void
    {
        $debug = PackageConfiguration::params()['yii3/debug'] ?? null;

        self::assertIsArray(
            $debug,
            'Packaged parameters must declare the `yii3/debug` block.',
        );
        self::assertEquals(
            new ToolbarOptions(),
            ToolbarOptions::fromParams($debug),
            'Packaged parameters and constructor defaults must agree.',
        );
    }

    public function testFromParamsReadsEveryDeclaredOption(): void
    {
        $options = ToolbarOptions::fromParams(
            [
                'routePrefix' => '/developer/debug/',
                'historySize' => 25,
                'database' => ['excessiveCallerThreshold' => 3],
                'toolbar' => [
                    'skipUrls' => ['/health'],
                    'position' => 'top',
                    'height' => 65,
                ],
            ],
        );

        self::assertSame(
            [
                'routePrefix' => '/developer/debug',
                'historySize' => 25,
                'excessiveCallerThreshold' => 3,
                'skipUrls' => ['/health'],
                'position' => 'top',
                'height' => 65,
            ],
            [
                'routePrefix' => $options->routePrefix,
                'historySize' => $options->historySize,
                'excessiveCallerThreshold' => $options->excessiveCallerThreshold,
                'skipUrls' => $options->skipUrls,
                'position' => $options->position,
                'height' => $options->height,
            ],
            'Every declared key must reach its option.',
        );
    }

    /**
     * @param string $path Request path to classify.
     * @param bool $expected Whether the path targets the debugger.
     */
    #[DataProviderExternal(ToolbarOptionsProvider::class, 'paths')]
    public function testIsDebugPathMatchesWholePathSegments(string $path, bool $expected): void
    {
        self::assertSame(
            $expected,
            (new ToolbarOptions())->isDebugPath($path),
            'Only the prefix segment and paths below it may match.',
        );
    }

    /**
     * @param array<string, mixed> $params Parameter block holding one value of the wrong type.
     * @param string $message Expected failure message.
     */
    #[DataProviderExternal(ToolbarOptionsProvider::class, 'invalidParams')]
    public function testThrowInvalidArgumentExceptionForInvalidOption(array $params, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            $message,
        );

        ToolbarOptions::fromParams($params);
    }
}
