<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Grid;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Grid\PrefixedUrlParameterProvider;
use Yiisoft\Yii\DataView\Url\UrlParameterType;

/**
 * Unit tests for {@see PrefixedUrlParameterProvider} covering reserved top-level reads and prefixed filter reads.
 *
 * @since 0.1
 */
#[Group('grid')]
final class PrefixedUrlParameterProviderTest extends TestCase
{
    public function testGetIgnoresPathParameters(): void
    {
        $provider = new PrefixedUrlParameterProvider(['page' => '2'], 'Debug');

        self::assertNull($provider->get('page', UrlParameterType::Path), 'Path parameters must not resolve.');
    }

    public function testGetReadsFilterAttributesFromThePrefixGroup(): void
    {
        $provider = new PrefixedUrlParameterProvider(['Debug' => ['statusCode' => '404']], 'Debug');

        self::assertSame(
            '404',
            $provider->get('statusCode', UrlParameterType::Query),
            'Filter attributes must resolve from the prefix group.',
        );
        self::assertNull(
            $provider->get('missing', UrlParameterType::Query),
            'Absent attributes must resolve to `null`.',
        );
    }

    public function testGetReadsReservedParametersFromTheTopLevel(): void
    {
        $provider = new PrefixedUrlParameterProvider(
            ['page' => '2', 'per-page' => '25', 'sort' => '-time', 'Debug' => ['page' => '9']],
            'Debug',
        );

        self::assertSame('2', $provider->get('page', UrlParameterType::Query), 'page must read the top level.');
        self::assertSame('25', $provider->get('per-page', UrlParameterType::Query), 'per-page must read the top level.');
        self::assertSame('-time', $provider->get('sort', UrlParameterType::Query), 'sort must read the top level.');
    }
}
