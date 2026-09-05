<?php

declare(strict_types=1);

$environment = getenv('APP_ENV');

if ($environment === false || $environment === '') {
    $environment = $_SERVER['APP_ENV'] ?? null;
}

return in_array($environment, ['debug', 'dev', 'test'], true);
