<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use Closure;
use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\{PreserveGlobalState, RunTestsInSeparateProcesses};
use PHPUnit\Framework\TestCase;
use Yii3\Debug\ExtensionRegistry;

/**
 * Verifies optional capability absence without changing installed files or using reflection.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class OptionalProviderCompatibilityTest extends TestCase
{
    public function testAbsentOptionalPackagesDoNotActivateOrBreakTheRegistry(): void
    {
        $restore = $this->hideProviderPackages();

        try {
            self::assertFalse(class_exists('PHPForge\\Vite\\Vite'), 'Vite must stay unloadable.');
            self::assertFalse(class_exists('PHPForge\\Inertia\\Page'), 'Inertia must stay unloadable.');
            self::assertFalse(
                class_exists('PHPForge\\Vite\\Debug\\ViteCollector'),
                'The Vite collector must stay unloadable.',
            );
            self::assertFalse(
                class_exists('PHPForge\\Inertia\\Debug\\InertiaCollector'),
                'The Inertia collector must stay unloadable.',
            );
            self::assertSame([], ExtensionRegistry::create()->collectors(), 'No collector may be activated.');
            self::assertSame([], ExtensionRegistry::create()->panels(), 'No panel may be activated.');
        } finally {
            $restore();
        }
    }

    /**
     * Removes both provider packages from autoloading and returns the restore callback.
     *
     * @return Closure(): void
     */
    private function hideProviderPackages(): Closure
    {
        $loaders = ClassLoader::getRegisteredLoaders();

        foreach ($loaders as $loader) {
            $loader->unregister();
        }

        $filter = static function (string $class) use ($loaders): void {
            if (str_starts_with($class, 'PHPForge\\Vite\\') || str_starts_with($class, 'PHPForge\\Inertia\\')) {
                return;
            }

            foreach ($loaders as $loader) {
                $file = $loader->findFile($class);

                if ($file !== false) {
                    require $file;

                    return;
                }
            }
        };

        spl_autoload_register($filter);

        return static function () use ($filter, $loaders): void {
            spl_autoload_unregister($filter);

            foreach ($loaders as $loader) {
                $loader->register();
            }
        };
    }
}
