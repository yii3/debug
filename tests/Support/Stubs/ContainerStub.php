<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use Psr\Container\ContainerInterface;

use function array_key_exists;

/**
 * Resolves a fixed service map, so container-driven code runs without building a real container.
 */
final readonly class ContainerStub implements ContainerInterface
{
    /**
     * @param array<string, mixed> $services Services keyed by identifier.
     */
    public function __construct(private array $services = []) {}

    public function get(string $id): mixed
    {
        if (!array_key_exists($id, $this->services)) {
            throw new ServiceNotFoundStub(
                "Service \"{$id}\" is not registered.",
            );
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}
