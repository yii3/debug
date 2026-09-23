<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function file_get_contents;
use function str_contains;
use function strlen;
use function substr;

/**
 * Guards the host against naming any provider package: Vite and Inertia register through `yii3/debug.collectors`,
 * `yii3/debug.panels`, and the application's `events-web` group like every other extension, so neither the sources
 * nor the packaged configuration may reference their namespaces.
 */
final class ProviderNeutralityTest extends TestCase
{
    /**
     * Namespaces owned by provider packages the host must never name.
     */
    private const array PROVIDER_NAMESPACES = [
        'PHPForge\\Inertia',
        'PHPForge\\Vite',
        'yii\\inertia',
    ];

    public function testSourcesAndPackagedConfigurationNameNoProviderPackage(): void
    {
        $root = dirname(__DIR__);

        $offenders = [];
        $directories = ['src', 'config'];

        foreach ($directories as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$directory}"));

            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                if ($file->isFile() === false || $file->getExtension() !== 'php') {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());

                foreach (self::PROVIDER_NAMESPACES as $namespace) {
                    if (str_contains($source, $namespace)) {
                        $offenders[] = substr($file->getPathname(), strlen($root) + 1) . " ({$namespace})";
                    }
                }
            }
        }

        self::assertSame(
            [],
            $offenders,
            'Files naming a provider namespace.',
        );
    }
}
