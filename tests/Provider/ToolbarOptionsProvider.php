<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Provider;

/**
 * Data provider for {@see \Yii3\Debug\Tests\Middleware\ToolbarOptionsTest} test cases.
 */
final class ToolbarOptionsProvider
{
    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidParams(): iterable
    {
        yield 'database block' => [
            ['database' => 'none'],
            'Debug option "yii3/debug.database" must be an array.',
        ];
        yield 'excessive caller threshold' => [
            ['database' => ['excessiveCallerThreshold' => '3']],
            'Debug option "yii3/debug.database.excessiveCallerThreshold" must be an integer or null.',
        ];
        yield 'history size' => [
            ['historySize' => '50'],
            'Debug option "yii3/debug.historySize" must be an integer.',
        ];
        yield 'route prefix' => [
            ['routePrefix' => null],
            'Debug option "yii3/debug.routePrefix" must be a string.',
        ];
        yield 'skip URLs keyed' => [
            ['toolbar' => ['skipUrls' => ['health' => '/health']]],
            'Debug option "yii3/debug.toolbar.skipUrls" must be a list of strings.',
        ];
        yield 'skip URLs not an array' => [
            ['toolbar' => ['skipUrls' => '/health']],
            'Debug option "yii3/debug.toolbar.skipUrls" must be a list of strings.',
        ];
        yield 'skip URLs with a non-string entry' => [
            ['toolbar' => ['skipUrls' => ['/health', 42]]],
            'Debug option "yii3/debug.toolbar.skipUrls" must be a list of strings.',
        ];
        yield 'toolbar block' => [
            ['toolbar' => null],
            'Debug option "yii3/debug.toolbar" must be an array.',
        ];
        yield 'toolbar height' => [
            ['toolbar' => ['height' => 50.5]],
            'Debug option "yii3/debug.toolbar.height" must be an integer.',
        ];
        yield 'toolbar position' => [
            ['toolbar' => ['position' => ['bottom']]],
            'Debug option "yii3/debug.toolbar.position" must be a string.',
        ];
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function paths(): iterable
    {
        yield 'application root' => ['/', false];
        yield 'debugger endpoint' => ['/debug/toolbar', true];
        yield 'debugger root' => ['/debug', true];
        yield 'debugger root with trailing slash' => ['/debug/', true];
        yield 'nested application path' => ['/site/debug', false];
        yield 'path sharing the prefix' => ['/debugger', false];
    }
}
