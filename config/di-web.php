<?php

declare(strict_types=1);

use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Inertia\Debug\{InertiaCollector, InertiaPanel};
use PHPForge\Inertia\Event\ProtocolResultCreated;
use PHPForge\Vite\Debug\{ViteCollector, VitePanel};
use PHPForge\Vite\Event\AssetsResolved;
use Psr\Container\ContainerInterface;
use Yii3\Debug\ExtensionRegistry;
use Yiisoft\Definitions\ReferencesArray;
use Yiisoft\EventDispatcher\Provider\ListenerCollection;

if (!(require __DIR__ . '/enabled.php')) {
    return [];
}

/** @var array<string, mixed> $params */
$extensions = $params['yii3/debug']['extensions'];
$collectors = [];
$panels = [];
$definitions = [];

/** @var array<class-string, class-string> $listeners */
$listeners = [];

if ($extensions['inertia']) {
    $collectors[] = InertiaCollector::class;
    $panels[] = InertiaPanel::class;
    $definitions[InertiaCollector::class] = static fn(
        CapturePolicy $capturePolicy,
    ): InertiaCollector => new InertiaCollector($capturePolicy->redact(...), $capturePolicy->redactUrl(...));
    $listeners[ProtocolResultCreated::class] = InertiaCollector::class;
}

if ($extensions['vite']) {
    $collectors[] = ViteCollector::class;
    $panels[] = VitePanel::class;
    $listeners[AssetsResolved::class] = ViteCollector::class;
}

if ($listeners !== []) {
    $definitions[ListenerCollection::class] = static function (
        ContainerInterface $container,
    ) use ($listeners): ListenerCollection {
        $collection = new ListenerCollection();

        foreach ($listeners as $event => $class) {
            $listener = $container->get($class);

            if (is_callable($listener)) {
                $collection = $collection->add($listener, $event);
            }
        }

        return $collection;
    };
}

$definitions[ExtensionRegistry::class] = [
    '__construct()' => [
        'collectors' => ReferencesArray::from($collectors),
        'panels' => ReferencesArray::from($panels),
    ],
];

return $definitions;
