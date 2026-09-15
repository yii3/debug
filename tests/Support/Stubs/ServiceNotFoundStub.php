<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Reports an identifier {@see ContainerStub} does not resolve.
 */
final class ServiceNotFoundStub extends RuntimeException implements NotFoundExceptionInterface {}
