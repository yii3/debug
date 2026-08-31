<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Panel\Asset\ViteChunk;
use PHPForge\Debug\Panel\Vite\{ViteComponent, ViteSnapshot};
use PHPForge\Vite\Configuration\{DevelopmentConfiguration, ProductionConfiguration};
use PHPForge\Vite\Manifest\ManifestLoader;
use PHPForge\Vite\Support\EntrypointNormalizer;
use PHPForge\Vite\Vite;

use function count;

/**
 * Captures one explicitly configured PHP Forge Vite integration.
 */
final class ViteCollector implements CollectorInterface
{
    /**
     * @var list<string>
     */
    private readonly array $entrypoints;
    private readonly ManifestLoader $manifestLoader;
    private bool $started = false;

    /**
     * @param list<mixed> $entrypoints Configured Vite entrypoints.
     */
    public function __construct(
        private readonly DevelopmentConfiguration|ProductionConfiguration $configuration,
        array $entrypoints = [],
        ManifestLoader|null $manifestLoader = null,
    ) {
        $this->entrypoints = EntrypointNormalizer::normalize($entrypoints, false);
        $this->manifestLoader = $manifestLoader ?? new ManifestLoader();
    }

    public function capture(): ViteSnapshot|null
    {
        if ($this->started === false) {
            return null;
        }

        return new ViteSnapshot([$this->component()]);
    }

    public function id(): string
    {
        return 'vite';
    }

    public function shutdown(): void
    {
        $this->started = false;
    }

    public function startup(): void
    {
        $this->started = true;
    }

    /**
     * @return list<ViteChunk> Captured production-manifest chunks in manifest order.
     */
    private function chunks(string $manifestPath): array
    {
        $chunks = [];

        foreach ($this->manifestLoader->load($manifestPath)->chunks() as $chunk) {
            $chunks[] = new ViteChunk(
                name: $chunk->key,
                file: $chunk->file,
                cssCount: count($chunk->css()),
                imports: count($chunk->imports()),
                isEntry: $chunk->isEntry(),
            );
        }

        return $chunks;
    }

    private function component(): ViteComponent
    {
        if ($this->configuration instanceof DevelopmentConfiguration) {
            return new ViteComponent(
                id: $this->id(),
                class: Vite::class,
                implementation: ViteComponent::IMPLEMENTATION_MODERN,
                inspectionAvailable: true,
                mode: ViteComponent::MODE_DEVELOPMENT,
                entrypoints: $this->entrypoints,
                baseUrl: '',
                devServerUrl: $this->configuration->devServerUrl,
                manifestPath: '',
                includeViteClient: $this->configuration->includeViteClient,
                modulePreload: null,
                chunks: [],
            );
        }

        return new ViteComponent(
            id: $this->id(),
            class: Vite::class,
            implementation: ViteComponent::IMPLEMENTATION_MODERN,
            inspectionAvailable: true,
            mode: ViteComponent::MODE_PRODUCTION,
            entrypoints: $this->entrypoints,
            baseUrl: $this->configuration->assetBaseUrl,
            devServerUrl: null,
            manifestPath: $this->configuration->manifestPath,
            includeViteClient: null,
            modulePreload: $this->configuration->modulePreload,
            chunks: $this->chunks($this->configuration->manifestPath),
        );
    }
}
