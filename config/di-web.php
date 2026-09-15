<?php

declare(strict_types=1);

use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Inertia\Debug\{InertiaCollector, InertiaPanel};
use PHPForge\Vite\Debug\{ViteCollector, VitePanel};
use Yii3\Debug\ExtensionRegistry;
use Yiisoft\Definitions\ReferencesArray;

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
    $definitions[InertiaCollector::class] = static fn(
        CapturePolicy $capturePolicy,
    ): InertiaCollector => new InertiaCollector(
        $capturePolicy->redact(...),
        $capturePolicy->redactUrl(...),
    );
}

if ($extensions['vite']) {
    $collectors[] = ViteCollector::class;
    $panels[] = VitePanel::class;
}

$definitions[ExtensionRegistry::class] = [
    '__construct()' => [
        'collectors' => ReferencesArray::from($collectors),
        'panels' => ReferencesArray::from($panels),
    ],
];

return $definitions;
