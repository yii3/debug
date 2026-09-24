<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Provider;

/**
 * Data provider for {@see \Yii3\Debug\Tests\Middleware\RequestCaptureMiddlewareTest} test cases.
 */
final class RequestCaptureMiddlewareProvider
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function passThroughRequests(): iterable
    {
        yield 'debugger path' => ['/debug/toolbar', '127.0.0.1'];
        yield 'denied client' => ['/', '203.0.113.10'];
    }
}
