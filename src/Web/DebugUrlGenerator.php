<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use PHPForge\Debug\Routing\DebugUrlGeneratorInterface;

use function http_build_query;
use function ltrim;
use function rtrim;

use const PHP_QUERY_RFC3986;

/**
 * Builds Yii3 debugger URLs for context-aware panel renderers.
 */
final readonly class DebugUrlGenerator implements DebugUrlGeneratorInterface
{
    private string $routePrefix;

    public function __construct(string $routePrefix = '/debug')
    {
        $this->routePrefix = rtrim($routePrefix, '/');
    }

    public function action(string $action, string $tag, array $queryParams = []): string
    {
        unset($queryParams['tag'], $queryParams['panel']);

        return $this->withQuery(
            $this->routePrefix . '/' . ltrim($action, '/'),
            ['tag' => $tag] + $queryParams,
        );
    }

    public function history(array $queryParams = []): string
    {
        unset($queryParams['tag'], $queryParams['panel']);

        return $this->withQuery($this->routePrefix, $queryParams);
    }

    public function panel(string $tag, string $panel, array $queryParams = []): string
    {
        unset($queryParams['tag'], $queryParams['panel']);

        return $this->withQuery(
            $this->routePrefix . '/view',
            ['tag' => $tag, 'panel' => $panel] + $queryParams,
        );
    }

    /**
     * Returns the normalized debugger route prefix.
     */
    public function routePrefix(): string
    {
        return $this->routePrefix;
    }

    /**
     * @param array<array-key, mixed> $queryParams
     */
    private function withQuery(string $path, array $queryParams): string
    {
        if ($queryParams === []) {
            return $path;
        }

        return "{$path}?" . http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
    }
}
