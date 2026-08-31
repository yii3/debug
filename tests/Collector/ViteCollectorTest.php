<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPForge\Debug\Panel\Vite\ViteComponent;
use PHPForge\Vite\Configuration\{DevelopmentConfiguration, ProductionConfiguration};
use PHPForge\Vite\Exception\{InvalidEntrypointException, ManifestNotFoundException};
use PHPForge\Vite\Manifest\ManifestLoader;
use PHPForge\Vite\Vite;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Collector\ViteCollector;

use function array_map;
use function file_put_contents;
use function is_file;
use function json_encode;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Unit tests for native PHP Forge Vite configuration and manifest capture.
 */
#[Group('vite')]
final class ViteCollectorTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $paths = [];

    public function testCaptureNormalizesDevelopmentConfiguration(): void
    {
        $collector = new ViteCollector(
            DevelopmentConfiguration::create(
                devServerUrl: 'https://vite.example.test/',
                includeViteClient: false,
            ),
            [' /resources/js/app.ts ', 'resources/js/admin.ts', 'resources/js/app.ts'],
        );

        self::assertSame(
            'vite',
            $collector->id(),
            'Collector ID must match the Vite panel payload key.',
        );
        self::assertNull(
            $collector->capture(),
            'An inactive Vite collector must not produce a snapshot.',
        );

        $collector->startup();
        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'An active development integration must produce a Vite snapshot.',
        );
        self::assertSame(
            [
                'components' => [
                    [
                        'id' => 'vite',
                        'class' => Vite::class,
                        'implementation' => ViteComponent::IMPLEMENTATION_MODERN,
                        'inspectionAvailable' => true,
                        'mode' => ViteComponent::MODE_DEVELOPMENT,
                        'entrypoints' => ['resources/js/app.ts', 'resources/js/admin.ts'],
                        'baseUrl' => '',
                        'devServerUrl' => 'https://vite.example.test',
                        'manifestPath' => '',
                        'includeViteClient' => false,
                        'modulePreload' => null,
                        'chunks' => [],
                    ],
                ],
            ],
            $snapshot->jsonSerialize(),
            'Development capture must expose normalized entrypoints and public dev-server settings.',
        );

        $collector->shutdown();

        self::assertNull(
            $collector->capture(),
            'Shutdown must make the Vite collector inactive.',
        );

        $collector->startup();

        self::assertNotNull(
            $collector->capture(),
            'A new lifecycle must reactivate the Vite collector.',
        );
    }

    public function testCapturePermitsEmptyDefaultEntrypoints(): void
    {
        $collector = new ViteCollector(
            DevelopmentConfiguration::create('http://127.0.0.1:5173'),
        );
        $collector->startup();
        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'Vite may defer entrypoint selection until each resolve call.',
        );
        self::assertSame(
            [[]],
            array_map(static fn(ViteComponent $component): array => $component->entrypoints, $snapshot->components()),
            'The collector must preserve an intentionally empty default entrypoint list.',
        );
    }

    public function testCaptureUsesTypedProductionManifestData(): void
    {
        $manifestPath = $this->manifestPath(
            [
                'resources/js/app.ts' => [
                    'file' => 'assets/app.js',
                    'src' => 'resources/js/app.ts',
                    'isEntry' => true,
                    'css' => ['assets/app.css', 'assets/theme.css'],
                    'imports' => ['_shared.js'],
                ],
                '_shared.js' => [
                    'file' => 'assets/shared.js',
                ],
            ],
        );
        $collector = new ViteCollector(
            ProductionConfiguration::create(
                manifestPath: $manifestPath,
                assetBaseUrl: '/build/',
                modulePreload: false,
            ),
            ['/resources/js/app.ts'],
            new ManifestLoader(),
        );

        $collector->startup();
        $snapshot = $collector->capture();

        self::assertNotNull($snapshot, 'An active production integration must produce a Vite snapshot.');
        self::assertSame(
            [
                'components' => [
                    [
                        'id' => 'vite',
                        'class' => Vite::class,
                        'implementation' => ViteComponent::IMPLEMENTATION_MODERN,
                        'inspectionAvailable' => true,
                        'mode' => ViteComponent::MODE_PRODUCTION,
                        'entrypoints' => ['resources/js/app.ts'],
                        'baseUrl' => '/build',
                        'devServerUrl' => null,
                        'manifestPath' => $manifestPath,
                        'includeViteClient' => null,
                        'modulePreload' => false,
                        'chunks' => [
                            [
                                'name' => 'resources/js/app.ts',
                                'file' => 'assets/app.js',
                                'cssCount' => 2,
                                'imports' => 1,
                                'isEntry' => true,
                            ],
                            [
                                'name' => '_shared.js',
                                'file' => 'assets/shared.js',
                                'cssCount' => 0,
                                'imports' => 0,
                                'isEntry' => false,
                            ],
                        ],
                    ],
                ],
            ],
            $snapshot->jsonSerialize(),
            'Production capture must map the native typed manifest without reparsing its JSON.',
        );
    }

    public function testInvalidEntrypointsRetainTheNativeViteException(): void
    {
        $this->expectException(InvalidEntrypointException::class);
        $this->expectExceptionMessage(
            'Each Vite entrypoint must be a non-empty relative source path.',
        );

        new ViteCollector(
            DevelopmentConfiguration::create('http://127.0.0.1:5173'),
            ['resources/js/app.ts', ''],
        );
    }

    public function testMissingProductionManifestRetainsTheNativeViteException(): void
    {
        $manifestPath = sys_get_temp_dir() . '/yii3-debug-vite-missing-' . uniqid('', true) . '.json';

        $collector = new ViteCollector(
            ProductionConfiguration::create($manifestPath, '/build'),
            ['resources/js/app.ts'],
        );

        $collector->startup();

        $this->expectException(ManifestNotFoundException::class);
        $this->expectExceptionMessage(
            $manifestPath,
        );

        $collector->capture();
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->paths = [];
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function manifestPath(array $manifest): string
    {
        $path = sys_get_temp_dir() . '/yii3-debug-vite-' . uniqid('', true) . '.json';

        $this->paths[] = $path;

        self::assertNotFalse(
            file_put_contents(
                $path,
                json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ),
            'The Vite manifest fixture must be writable.',
        );

        return $path;
    }
}
