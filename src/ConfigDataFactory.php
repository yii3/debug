<?php

declare(strict_types=1);

namespace Yii3\Debug;

use Composer\InstalledVersions;
use PHPForge\Debug\Panel\Config\ConfigSnapshot;

use function extension_loaded;
use function filter_var;
use function getenv;
use function is_bool;
use function is_scalar;
use function is_string;
use function ksort;
use function str_starts_with;

use const FILTER_VALIDATE_BOOLEAN;
use const PHP_VERSION;

/**
 * Creates the live Yii and PHP configuration shown by the Configuration page.
 *
 * Application metadata the application does not declare falls back to the runtime: the Composer root package supplies
 * the name and version, `APP_ENV` the environment, and `APP_DEBUG` the debug mode.
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
        $rootPackage = InstalledVersions::getRootPackage();

        return ConfigSnapshot::capture(
            [
                'application' => [
                    'yii' => '3',
                    'name' => $this->string('name', $rootPackage['name']),
                    'version' => $this->string('version', $rootPackage['pretty_version']),
                    'language' => $this->string('language'),
                    'sourceLanguage' => $this->string('sourceLanguage'),
                    'charset' => $this->string('charset', 'UTF-8'),
                    'env' => $this->string('env', self::environment()),
                    'debug' => $this->bool('debug', self::debugMode()),
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

    /**
     * Reads an application metadata key as a boolean.
     *
     * @param string $key Application metadata key to read.
     * @param bool $default Value returned when the key is absent or not a `bool`.
     *
     * @return bool Value when it is a `bool`; the default otherwise.
     */
    private function bool(string $key, bool $default): bool
    {
        $value = $this->application[$key] ?? null;

        return is_bool($value) ? $value : $default;
    }

    /**
     * Reads whether the application runs with debug output enabled.
     *
     * `APP_DEBUG` is the variable Yii applications expose for it, so it names the mode when the application declares
     * no `debug` metadata of its own.
     *
     * @return bool `true` when the variable holds a truthy value; `false` otherwise.
     */
    private static function debugMode(): bool
    {
        $debug = getenv('APP_DEBUG');

        if ($debug === false || $debug === '') {
            $debug = $_SERVER['APP_DEBUG'] ?? false;
        }

        return is_scalar($debug) && filter_var($debug, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Reads the environment name the application runs under.
     *
     * The module already gates itself on `APP_ENV` in `config/enabled.php`, so the same variable names the
     * environment when the application declares no `env` metadata of its own.
     *
     * @return string Environment name, or `''` when the variable is unset.
     */
    private static function environment(): string
    {
        $environment = getenv('APP_ENV');

        if ($environment === false || $environment === '') {
            $environment = $_SERVER['APP_ENV'] ?? '';
        }

        return is_string($environment) ? $environment : '';
    }

    /**
     * Lists the installed Yii packages with their resolved versions.
     *
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

    /**
     * Reads an application metadata key as a string.
     *
     * @param string $key Application metadata key to read.
     * @param string $default Value returned when the key is absent or not a `string`.
     *
     * @return string Value when it is a `string`; the default otherwise.
     */
    private function string(string $key, string $default = ''): string
    {
        $value = $this->application[$key] ?? null;

        return is_string($value) ? $value : $default;
    }
}
