<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Yii3\Debug\Extension\ProviderCatalog;
use Yii3\Debug\ExtensionRegistry;

if (!(require __DIR__ . '/enabled.php')) {
    return [];
}

/** @var array<string, mixed> $params */
$debug = $params['yii3/debug'];

return [
    ExtensionRegistry::class => static fn(
        ContainerInterface $container,
    ): ExtensionRegistry => ExtensionRegistry::fromParams(
        $debug['collectors'] ?? [],
        $debug['panels'] ?? [],
        $container,
    ),
    ...ProviderCatalog::packaged()->definitions(),
];
