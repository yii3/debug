<?php

declare(strict_types=1);

use Yii3\Debug\DebugServiceProvider;

if (!(require __DIR__ . '/enabled.php')) {
    return [];
}

return [
    DebugServiceProvider::class,
];
