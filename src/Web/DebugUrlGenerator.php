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
    /**
     * Base route used to generate debugger URLs.
     */
    private string $routePrefix;

    /**
     * @param string $routePrefix Base path every debugger URL is built on; a trailing slash is trimmed.
     */
    public function __construct(string $routePrefix = '/debug')
    {
        $this->routePrefix = rtrim($routePrefix, '/');
    }

    /**
     * Builds the URL of the query plan for one stored statement.
     *
     * @param string $tag Capture holding the statement.
     * @param int $sequence Position of the statement within that capture.
     *
     * @return string URL of the plan page.
     */
    public function dbExplain(string $tag, int $sequence): string
    {
        return "{$this->routePrefix}/db-explain?" . http_build_query(
            ['tag' => $tag, 'seq' => $sequence],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );
    }

    /**
     * Builds a panel URL while keeping the captured tag and target panel authoritative.
     *
     * @param string $tag Capture to open.
     * @param string $panel Panel to open within that capture.
     * @param array<array-key, mixed> $queryParams Extra query parameters; route-owned keys are ignored.
     *
     * @return string URL of the panel view.
     */
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
     *
     * @return string Base path without a trailing slash.
     */
    public function routePrefix(): string
    {
        return $this->routePrefix;
    }
}
