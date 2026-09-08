<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use PHPForge\Debug\Routing\DebugUrlGeneratorInterface;

use function http_build_query;
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

    public function panel(string $tag, string $panel, array $queryParams = []): string
    {
        unset($queryParams['tag'], $queryParams['panel']);

        $query = http_build_query(
            ['tag' => $tag, 'panel' => $panel] + $queryParams,
            '',
            '&',
            PHP_QUERY_RFC3986,
        );

        return "{$this->routePrefix}/view?{$query}";
    }

    /**
     * Returns the normalized debugger route prefix.
     */
    public function routePrefix(): string
    {
        return $this->routePrefix;
    }
}
