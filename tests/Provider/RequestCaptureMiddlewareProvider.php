<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Provider;

/**
 * Data provider for {@see \Yii3\Debug\Tests\Middleware\RequestCaptureMiddlewareTest} test cases.
 */
final class RequestCaptureMiddlewareProvider
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function injectedContentTypes(): iterable
    {
        yield 'upper-case HTML' => ['TEXT/HTML; charset=UTF-8'];
        yield 'XHTML' => ['application/xhtml+xml'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function passThroughRequests(): iterable
    {
        yield 'debugger path' => ['/debug/toolbar', '127.0.0.1'];
        yield 'denied client' => ['/', '203.0.113.10'];
    }

    /**
     * @return iterable<string, array{string, int, array<string, string>}>
     */
    public static function uninjectedResponses(): iterable
    {
        yield 'HEAD request' => ['HEAD', 200, []];
        yield 'lower-case HEAD request' => ['head', 200, []];
        yield 'informational status' => ['GET', 103, []];
        yield 'no content' => ['GET', 204, []];
        yield 'reset content' => ['GET', 205, []];
        yield 'not modified' => ['GET', 304, []];
        yield 'AJAX request' => ['GET', 200, ['X-Requested-With' => 'XMLHttpRequest']];
    }
}
