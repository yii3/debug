<?php

declare(strict_types=1);

namespace Yii3\Debug;

use Composer\InstalledVersions;
use PHPForge\Debug\Panel\Config\ConfigSnapshot;

use function extension_loaded;
use function is_bool;
use function is_string;
use function ksort;
use function str_starts_with;

use const PHP_VERSION;

/**
 * Creates the live Yii and PHP configuration shown by the Configuration page.
 */
final readonly class ConfigDataFactory
{
    /**
     * @param array<string, mixed> $application Optional application metadata.
     */
    public function __construct(private array $application = []) {}

    /**
     * Builds the Configuration panel payload from the live application and PHP runtime.
     *
     * @return array<string, mixed> Payload accepted by the shared Configuration presenter.
     */
    public function create(): array
    {
        return ConfigSnapshot::capture(
            [
                'application' => [
                    'yii' => '3',
                    'name' => $this->string('name'),
                    'version' => $this->string('version'),
                    'language' => $this->string('language'),
                    'sourceLanguage' => $this->string('sourceLanguage'),
                    'charset' => $this->string('charset', 'UTF-8'),
                    'env' => $this->string('env'),
                    'debug' => $this->bool('debug'),
                ],
                'php' => [
                    'version' => PHP_VERSION,
                    'xdebug' => extension_loaded('xdebug'),
                    'apcu' => extension_loaded('apcu'),
                    'memcache' => extension_loaded('memcache'),
                    'memcached' => extension_loaded('memcached'),
                ],
                'extensions' => self::installedPackages(),
            ],
        )->jsonSerialize();
    }

    private function bool(string $key): bool
    {
        $value = $this->application[$key] ?? null;

        return is_bool($value) ? $value : false;
    }

    /**
     * @return array<string, array{name: string, version: string}> Installed Yii packages keyed by package name.
     */
    private static function installedPackages(): array
    {
        $packages = [];

        foreach (InstalledVersions::getInstalledPackages() as $package) {
            if (!str_starts_with($package, 'yiisoft/')) {
                continue;
            }

            $version = InstalledVersions::getPrettyVersion($package);

            if (is_string($version)) {
                $packages[$package] = ['name' => $package, 'version' => $version];
            }
        }

        ksort($packages);

        return $packages;
    }

    private function string(string $key, string $default = ''): string
    {
        $value = $this->application[$key] ?? null;

        return is_string($value) ? $value : $default;
    }
}
