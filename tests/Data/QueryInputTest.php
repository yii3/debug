<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Data;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Data\QueryInput;

/**
 * Unit tests for {@see QueryInput} covering `Prefix[attribute]` group extraction and scalar top-level reads from
 * parsed query parameters.
 *
 * @since 0.1
 */
#[Group('data')]
#[Group('filter')]
final class QueryInputTest extends TestCase
{
    public function testGroupDropsEmptyNonScalarAndNonStringKeyedEntries(): void
    {
        $filters = QueryInput::group(
            [
                'Debug' => [
                    'statusCode' => '404',
                    'url' => '',
                    'nested' => ['x' => '1'],
                    0 => 'indexed',
                    'count' => 7,
                ],
            ],
            'Debug',
        );

        self::assertSame(
            ['statusCode' => '404', 'count' => '7'],
            $filters,
            'Empty strings, arrays, and integer keys must be dropped; numeric values must stringify.',
        );
    }

    public function testGroupReturnsEmptyArrayWhenPrefixIsAbsentOrNotAnArray(): void
    {
        self::assertSame([], QueryInput::group([], 'Debug'), 'A missing prefix must yield no filters.');
        self::assertSame(
            [],
            QueryInput::group(['Debug' => 'scalar'], 'Debug'),
            'A scalar under the prefix must yield no filters.',
        );
    }

    public function testScalarReadsTopLevelStringsAndNumbers(): void
    {
        self::assertSame('all', QueryInput::scalar(['per-page' => 'all'], 'per-page'), 'Strings must pass through.');
        self::assertSame('25', QueryInput::scalar(['per-page' => 25], 'per-page'), 'Integers must stringify.');
        self::assertNull(QueryInput::scalar([], 'per-page'), 'Missing parameters must yield `null`.');
        self::assertNull(
            QueryInput::scalar(['per-page' => ['25']], 'per-page'),
            'Array parameters must yield `null`.',
        );
    }
}
