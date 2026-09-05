<?php

declare(strict_types=1);

use PHPForge\Vite\Manifest\ManifestLoader;
use Yii3\Debug\Collector\{InertiaCollector, ViteCollector};
use Yii3\Debug\ExtensionRegistry;
use Yii3\Debug\Panel\{InertiaPanel, VitePanel};
use Yii3\Inertia\{ResolvedPageObserver, ResolvedPageObserverInterface};
use Yiisoft\Definitions\{Reference, ReferencesArray};

if (!(require __DIR__ . '/enabled.php')) {
    return [];
}

/** @var array<string, mixed> $params */
$extensions = $params['yii3/debug']['extensions'];
$collectors = [];
$panels = [];
$definitions = [];

if ($extensions['inertia']) {
    $collectors[] = InertiaCollector::class;
    $panels[] = InertiaPanel::class;
    $definitions[ResolvedPageObserverInterface::class] = static fn(
        InertiaCollector $collector,
    ): ResolvedPageObserverInterface => new ResolvedPageObserver($collector->observe(...));
}

if ($extensions['vite']) {
    $collectors[] = ViteCollector::class;
    $panels[] = VitePanel::class;
    $definitions[ViteCollector::class] = [
        '__construct()' => [
            ...$params['php-forge/vite'],
            'manifestLoader' => Reference::optional(ManifestLoader::class),
        ],
    ];
}

$definitions[ExtensionRegistry::class] = [
    '__construct()' => [
        'collectors' => ReferencesArray::from($collectors),
        'panels' => ReferencesArray::from($panels),
    ],
];

return $definitions;
