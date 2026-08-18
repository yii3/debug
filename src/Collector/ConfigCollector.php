<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use Composer\InstalledVersions;
use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Panel\Config\ConfigSnapshot;

use function extension_loaded;
use function is_bool;
use function is_string;
use function str_starts_with;

use const PHP_VERSION;

/**
 * Captures the Yii3 application identity and runtime environment for the Configuration panel.
 *
 * Snapshots the framework / PHP identity, the adapter-configured application metadata, and the installed
 * `yiisoft/*` package roster resolved through the Composer runtime API.
 *
 * Usage example:
 *
 * ```php
 * $snapshot = (new \Yii3\Debug\Collector\ConfigCollector(['name' => 'My Project']))->capture();
 * ```
 */
final class ConfigCollector implements CollectorInterface
{
    /**
     * Framework generation reported to the toolbar brand chip.
     */
    public const string YII_VERSION = '3';

    private bool $active = false;

    /**
     * @param array<string, mixed> $application Application metadata overrides (`name`, `version`, `language`,
     * `sourceLanguage`, `charset`, `env`, `debug`) merged over neutral defaults.
     */
    public function __construct(private readonly array $application = []) {}

    /**
     * Snapshots the framework/PHP/application identity and the installed-packages roster.
     *
     * Usage example:
     *
     * ```php
     * $snapshot = $collector->capture();
     * ```
     *
     * @return ConfigSnapshot|null Captured configuration payload; `null` when the collector never started.
     */
    public function capture(): ConfigSnapshot|null
    {
        if (!$this->active) {
            return null;
        }

        return ConfigSnapshot::capture(
            [
                'phpVersion' => PHP_VERSION,
                'yiiVersion' => self::YII_VERSION,
                'application' => [
                    'yii' => self::YII_VERSION,
                    'name' => $this->applicationString('name'),
                    'version' => $this->applicationString('version'),
                    'language' => $this->applicationString('language'),
                    'sourceLanguage' => $this->applicationString('sourceLanguage'),
                    'charset' => $this->applicationString('charset', 'UTF-8'),
                    'env' => $this->applicationString('env'),
                    'debug' => $this->applicationBool('debug'),
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
        );
    }

    /**
     * Returns the stable ID pairing this collector with the Configuration panel.
     *
     * Usage example:
     *
     * ```php
     * $id = $collector->id();
     * ```
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'config';
    }

    /**
     * Deactivates the collector, so a reused worker process starts clean.
     */
    public function shutdown(): void
    {
        $this->active = false;
    }

    /**
     * Activates the collector for the current request cycle.
     */
    public function startup(): void
    {
        $this->active = true;
    }

    /**
     * Returns a boolean application metadata value, falling back to `false` when missing or mistyped.
     *
     * @param string $key Metadata key.
     */
    private function applicationBool(string $key): bool
    {
        $value = $this->application[$key] ?? null;

        return is_bool($value) ? $value : false;
    }

    /**
     * Returns a string application metadata value, falling back to the given default when missing or mistyped.
     *
     * @param string $key Metadata key.
     * @param string $default Fallback value.
     */
    private function applicationString(string $key, string $default = ''): string
    {
        $value = $this->application[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    /**
     * Returns the installed `yiisoft/*` packages resolved through the Composer runtime API.
     *
     * Entries mirror the shared Configuration payload shape (`name` and `version` keys per package), so the
     * Configuration panel renders identically across adapters.
     *
     * @return array<string, array{name: string, version: string}> Package entries indexed by package name.
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

        return $packages;
    }
}
