<?php

declare(strict_types=1);

use Yii3\Debug\Db\ConnectionInstrumentation;

if (!(require __DIR__ . '/enabled.php')) {
    return [];
}

return [
    ConnectionInstrumentation::apply(...),
];
