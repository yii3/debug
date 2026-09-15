<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Provider;

/**
 * Data provider for {@see \Yii3\Debug\Tests\Web\DebugRequestHandlerTest} test cases.
 */
final class DebugRequestHandlerProvider
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function paths(): iterable
    {
        yield 'comparison' => ['/debug/compare', 'compare'];
        yield 'configuration' => ['/debug/view', 'config'];
        yield 'database explain' => ['/debug/db-explain', 'db-explain'];
        yield 'history with query' => ['/debug?page=2', 'history'];
        yield 'history with trailing slash' => ['/debug/', 'history'];
        yield 'history' => ['/debug', 'history'];
        yield 'php info' => ['/debug/php-info', 'php-info'];
        yield 'toolbar data' => ['/debug/toolbar', 'toolbar'];
    }
}
