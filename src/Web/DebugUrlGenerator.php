<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use PHPForge\Debug\Routing\DebugUrlGeneratorInterface;

use function http_build_query;
use function ltrim;
use function rtrim;

use const PHP_QUERY_RFC3986;

/**
 * Builds Yii3 debugger URLs without coupling shared panel renderers to PSR-7 requests or router services.
 */
final readonly class DebugUrlGenerator implements DebugUrlGeneratorInterface
{
    private string $routePrefix;

    /**
     * @param string $routePrefix URL prefix serving the Yii3 debugger pages.
     */
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
     * @param array<array-key, mixed> $queryParams Query parameters appended to the path.
     */
    private function withQuery(string $path, array $queryParams): string
    {
        if ($queryParams === []) {
            return $path;
        }

        return $path . '?' . http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
    }
}
