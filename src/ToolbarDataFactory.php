<?php

declare(strict_types=1);

namespace Yii3\Debug;

use InvalidArgumentException;
use PHPForge\Debug\Panel\Config\ConfigSnapshot;
use PHPForge\Debug\Storage\{DebugSnapshot, PanelFailure};
use PHPForge\Debug\Toolbar\{ToolbarData, ToolbarItem, ToolbarPanel};
use Throwable;
use Yii3\Debug\Panel\PanelInterface;
use Yii3\Debug\Web\DebugUrlGenerator;
use Yiisoft\Assets\AssetManager;

use function array_key_exists;
use function is_string;
use function strlen;
use function substr;
use function trim;

use const PHP_VERSION;

/**
 * Converts a Yii3 core snapshot into the payload consumed by the shared toolbar runtime.
 *
 * Builds one toolbar panel per registered presentation panel from its stored payload, mirroring the Yii2 adapter
 * aggregation: panels contribute their own metric chips, capture failures surface as danger chips, and the PHP and
 * Yii versions come from the Configuration payload.
 */
final readonly class ToolbarDataFactory
{
    /**
     * @var array<string, PanelInterface> Presentation panels indexed by stable ID.
     */
    private array $panels;

    private string $routePrefix;
    private DebugUrlGenerator $urls;

    /**
     * @param AssetManager $assetManager Yii3 asset publisher.
     * @param iterable<PanelInterface> $panels Presentation panels in toolbar order.
     * @param string $routePrefix URL prefix serving the Yii3 debugger pages.
     * @param string $position Initial toolbar position.
     * @param int $height Initial drawer height percentage.
     */
    public function __construct(
        private AssetManager $assetManager,
        iterable $panels = [],
        string $routePrefix = '/debug',
        private string $position = 'bottom',
        private int $height = 50,
    ) {
        $this->urls = new DebugUrlGenerator($routePrefix);

        $this->routePrefix = $this->urls->routePrefix();

        $resolvedPanels = [];

        foreach ($panels as $panel) {
            $id = $panel->id();

            if (trim($id) === '') {
                throw new InvalidArgumentException('Yii3 debug panel ID must not be empty.');
            }

            if (isset($resolvedPanels[$id])) {
                throw new InvalidArgumentException("Duplicate Yii3 debug panel ID: {$id}.");
            }

            $resolvedPanels[$id] = $panel;
        }

        $this->panels = $resolvedPanels;
    }

    /**
     * Creates toolbar data for a captured Yii3 request.
     *
     * Usage example:
     *
     * ```php
     * $data = $factory->create($snapshot);
     * ```
     *
     * @param DebugSnapshot $snapshot Captured Yii3 request snapshot.
     *
     * @return ToolbarData Portable toolbar payload.
     */
    public function create(DebugSnapshot $snapshot): ToolbarData
    {
        $tag = $snapshot->summary->tag;
        $items = [];

        foreach ($this->panels as $id => $panel) {
            $failure = $snapshot->failures[$id] ?? null;

            if ($failure !== null) {
                $items[] = $this->failurePanel($tag, $panel, $failure);

                continue;
            }

            if (!array_key_exists($id, $snapshot->panels)) {
                continue;
            }

            try {
                $chips = $panel->toolbarItems($snapshot->panels[$id]);
            } catch (Throwable $throwable) {
                $items[] = $this->failurePanel(
                    $tag,
                    $panel,
                    PanelFailure::fromThrowable(PanelFailure::HYDRATE, $throwable),
                );

                continue;
            }

            if ($chips === []) {
                continue;
            }

            $items[] = new ToolbarPanel(
                id: $id,
                title: $id === 'profiling' ? '' : $panel->name(),
                url: $this->viewUrl($tag, $id),
                icon: $panel->icon(),
                items: $chips,
            );
        }

        $logo = $this->assetManager->getUrl(ToolbarAsset::class, 'svg/yii.svg');
        $iconBaseUrl = $this->assetManager->getUrl(ToolbarAsset::class, 'svg/ajax.svg');
        $iconBaseUrl = substr($iconBaseUrl, 0, -strlen('ajax.svg'));

        return new ToolbarData(
            tag: $tag,
            title: 'Yii Debugger',
            indexUrl: $this->routePrefix,
            configUrl: $this->configUrl($snapshot),
            items: $items,
            position: $this->position,
            defaultHeight: $this->height,
            iconBaseUrl: $iconBaseUrl,
            logo: $logo,
            logoFallback: $logo,
            phpInfoUrl: $this->routePrefix . '/php-info',
            phpVersion: self::configVersion($snapshot, 'phpVersion') ?? PHP_VERSION,
            yiiVersion: self::configVersion($snapshot, 'yiiVersion') ?? '3',
        );
    }

    /**
     * Builds the URL behind the Yii brand chip.
     *
     * @param DebugSnapshot $snapshot Captured request snapshot.
     *
     * @return string Configuration panel URL when captured; request summary URL otherwise.
     */
    private function configUrl(DebugSnapshot $snapshot): string
    {
        $panel = array_key_exists('config', $snapshot->panels) ? 'config' : 'summary';

        return $this->viewUrl($snapshot->summary->tag, $panel);
    }

    /**
     * Reads a version string stored in the Configuration payload.
     *
     * @param DebugSnapshot $snapshot Captured request snapshot.
     * @param string $key Version key (`phpVersion` or `yiiVersion`).
     *
     * @return string|null Stored version, or `null` when the Configuration payload does not provide one.
     */
    private static function configVersion(DebugSnapshot $snapshot, string $key): string|null
    {
        $payload = $snapshot->panels['config'] ?? null;

        if ($payload === null) {
            return null;
        }

        try {
            $data = ConfigSnapshot::fromArray($payload, 'panels.config')->data();
        } catch (Throwable) {
            return null;
        }

        $version = $data[$key] ?? null;

        return is_string($version) && $version !== '' ? $version : null;
    }

    /**
     * Builds the danger envelope for a panel whose collector failed.
     *
     * @param string $tag Captured request tag.
     * @param PanelInterface $panel Presentation panel registered for the failed collector.
     * @param PanelFailure $failure Stored capture or hydration failure.
     */
    private function failurePanel(string $tag, PanelInterface $panel, PanelFailure $failure): ToolbarPanel
    {
        return new ToolbarPanel(
            id: $panel->id(),
            title: $panel->name(),
            url: $this->viewUrl($tag, $panel->id()),
            icon: $panel->icon(),
            items: [
                new ToolbarItem(
                    value: 'error',
                    label: $panel->name(),
                    status: 'danger',
                    title: $failure->exception->getMessage(),
                ),
            ],
        );
    }

    /**
     * Builds a snapshot panel URL.
     *
     * @param string $tag Captured request tag.
     * @param string $panel Panel identifier.
     *
     * @return string Snapshot panel URL.
     */
    private function viewUrl(string $tag, string $panel): string
    {
        return $this->urls->panel($tag, $panel);
    }
}
