<?php

declare(strict_types=1);

use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Inertia\Debug\InertiaCollector;
use Psr\Container\ContainerInterface;
use Yii3\Debug\ExtensionRegistry;

if (!(require __DIR__ . '/enabled.php')) {
    return [];
}

/** @var array<string, mixed> $params */
$debug = $params['yii3/debug'];

$definitions = [
    ExtensionRegistry::class => static fn(
        ContainerInterface $container,
    ): ExtensionRegistry => ExtensionRegistry::fromParams(
        $debug['collectors'] ?? [],
        $debug['panels'] ?? [],
        $container,
    ),
];

// The packaged Inertia collector redacts page props and URLs with the same policy the Request panel applies.
if (class_exists(InertiaCollector::class)) {
    $definitions[InertiaCollector::class] = static fn(CapturePolicy $capturePolicy): InertiaCollector => new InertiaCollector(
        $capturePolicy->redact(...),
        $capturePolicy->redactUrl(...),
    );
}

return $definitions;
