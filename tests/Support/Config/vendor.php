<?php

declare(strict_types=1);

use Psr\EventDispatcher\EventDispatcherInterface;
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;

return [
    EventDispatcherInterface::class => Dispatcher::class,
];
