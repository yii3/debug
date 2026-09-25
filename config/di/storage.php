<?php

declare(strict_types=1);

use PHPForge\Debug\Storage\SnapshotStore;
use Yii3\Debug\Mail\MailFileStore;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Definitions\DynamicReference;

if (!(require dirname(__DIR__) . '/enabled.php')) {
    return [];
}

/** @var array<string, mixed> $params */
$config = $params['yii3/debug'];

return [
    MailFileStore::class => [
        '__construct()' => [
            'path' => DynamicReference::to(
                static fn(Aliases $aliases): string => $aliases->get($config['storage']['mailPath']),
            ),
            'dirMode' => $config['storage']['dirMode'],
            'fileMode' => $config['storage']['fileMode'],
        ],
    ],
    SnapshotStore::class => [
        '__construct()' => [
            'path' => DynamicReference::to(
                static fn(Aliases $aliases): string => $aliases->get($config['storage']['path']),
            ),
            'dirMode' => $config['storage']['dirMode'],
            'fileMode' => $config['storage']['fileMode'],
        ],
    ],
];
