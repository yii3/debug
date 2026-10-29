<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Provider;

use PHPForge\Debug\Helper\SensitiveDataRedactor;

/**
 * Data provider for {@see \Yii3\Debug\Tests\Collector\RequestCollectorTest} test cases.
 */
final class RequestCollectorProvider
{
    /**
     * @return iterable<string, array{array<string, string>, array<string, bool|string>}>
     */
    public static function clientFlagHeaders(): iterable
    {
        yield 'browser' => [
            ['User-Agent' => 'Mozilla/5.0'],
            [
                'isAjax' => false,
                'isFlash' => false,
                'isPjax' => false,
                'isSecureConnection' => false,
                'method' => 'GET',
            ],
        ];
        yield 'flash PJAX header' => [
            [
                'User-Agent' => 'Flash client',
                'X-Pjax' => 'true',
            ],
            [
                'isAjax' => false,
                'isFlash' => true,
                'isPjax' => false,
                'isSecureConnection' => false,
                'method' => 'GET',
            ],
        ];
        yield 'shockwave AJAX' => [
            [
                'User-Agent' => 'Shockwave player',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
            [
                'isAjax' => true,
                'isFlash' => true,
                'isPjax' => false,
                'isSecureConnection' => false,
                'method' => 'GET',
            ],
        ];
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function redactedHeaderValues(): iterable
    {
        yield 'text header' => [
            'X-Note',
            'token=note-secret',
            'token=' . SensitiveDataRedactor::PLACEHOLDER,
        ];
        yield 'URI header' => [
            'X-Callback-Uri',
            'https://example.test/callback?token=uri-secret&mode=fast',
            'https://example.test/callback?token=%5Bredacted%5D&mode=fast',
        ];
    }
}
