<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Grid;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Grid\GridUrlCreator;

/**
 * Unit tests for {@see GridUrlCreator} covering parameter merging, removal, and filter-group preservation.
 *
 * @since 0.1
 */
#[Group('grid')]
final class GridUrlCreatorTest extends TestCase
{
    public function testInvokeKeepsCurrentPageSizeWhenTheGridReportsTheDefault(): void
    {
        $creator = new GridUrlCreator('/debug', ['per-page' => 'all', 'sort' => '-time']);

        self::assertSame(
            '/debug?per-page=all&sort=time',
            $creator([], ['sort' => 'time', 'per-page' => null]),
            'A `null` per-page must preserve the current selection.',
        );
    }
    public function testInvokeMergesGridParametersOverTheCurrentQuery(): void
    {
        $creator = new GridUrlCreator('/debug', ['Debug' => ['statusCode' => '404'], 'page' => '1']);

        self::assertSame(
            '/debug?Debug%5BstatusCode%5D=404&page=2&sort=-time',
            $creator([], ['page' => '2', 'sort' => '-time']),
            'Grid parameters must override while filter groups are preserved.',
        );
    }

    public function testInvokeRemovesNullParameters(): void
    {
        $creator = new GridUrlCreator('/debug', ['page' => '3', 'per-page' => '25']);

        self::assertSame(
            '/debug?per-page=25',
            $creator([], ['page' => null]),
            'A `null` grid parameter must drop the current value.',
        );
    }

    public function testInvokeReturnsBarePathWhenNoParametersRemain(): void
    {
        $creator = new GridUrlCreator('/debug', ['page' => '3']);

        self::assertSame('/debug', $creator([], ['page' => null]), 'Empty query must yield the bare path.');
    }
}
