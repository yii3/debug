<?php

declare(strict_types=1);

use PHPForge\Debug\Helper\Trace;

if (!(require dirname(__DIR__) . '/enabled.php')) {
    return [];
}

/** @var array<string, mixed> $params */
$config = $params['yii3/debug'];

return [
    Trace::class => static function () use ($config): Trace {
        $trace = Trace::create();

        if ($config['traceLine'] !== null) {
            $trace = $trace->withTemplate($config['traceLine']);
        }

        return $trace->withPathMappings($config['tracePathMappings']);
    },
];
