<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Panel\Config\{ConfigCardRenderer, ConfigDataNormalizer, ConfigSnapshot};

use function is_array;
use function is_string;
use function ksort;
use function rtrim;

/**
 * Presents the shared Configuration panel payload with the framework-neutral readout and roster sections.
 *
 * Contributes no toolbar chip; the configuration surfaces through the Yii and PHP brand chips instead.
 */
final readonly class ConfigPanel implements PanelInterface
{
    use PanelContentTrait;

    private string $routePrefix;

    /**
     * @param string $routePrefix URL prefix serving the Yii3 debugger pages.
     */
    public function __construct(string $routePrefix = '/debug')
    {
        $this->routePrefix = rtrim($routePrefix, '/');
    }

    public function icon(): string
    {
        return 'config';
    }

    public function id(): string
    {
        return 'config';
    }

    public function name(): string
    {
        return 'Configuration';
    }

    public function render(array $payload): string
    {
        $data = ConfigSnapshot::fromArray($payload, 'panels.config')->data();
        $summary = (new ConfigDataNormalizer())->normalize($data, self::extensions($data));

        return ConfigCardRenderer::renderReadoutGrid($summary)
            . ConfigCardRenderer::renderPhpExtensionsSection($summary->php)
            . ConfigCardRenderer::renderApplicationDetailsSection($summary->application)
            . (string) ConfigCardRenderer::renderInstalledExtensionsSection($summary)
            . ConfigCardRenderer::renderPhpInfoCta($this->routePrefix . '/php-info');
    }

    public function toolbarItems(array $payload): array
    {
        return [];
    }

    /**
     * Returns the installed-extensions roster as a sorted `name => version` map.
     *
     * @param array<array-key, mixed> $data Decoded configuration payload.
     *
     * @return array<string, string> Extension versions keyed by package name, sorted alphabetically.
     */
    private static function extensions(array $data): array
    {
        $roster = [];
        $extensions = is_array($data['extensions'] ?? null) ? $data['extensions'] : [];

        foreach ($extensions as $extension) {
            if (!is_array($extension)) {
                continue;
            }

            $name = $extension['name'] ?? null;
            $version = $extension['version'] ?? null;

            if (is_string($name) && is_string($version)) {
                $roster[$name] = $version;
            }
        }

        ksort($roster);

        return $roster;
    }
}
