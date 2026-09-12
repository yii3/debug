<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Web;

use PHPUnit\Framework\TestCase;
use Yii3\Debug\Web\{GridColumn, PanelGrid};
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Data\Reader\Iterable\IterableDataReader;
use Yiisoft\Yii\DataView\GridView\GridView;

/**
 * Unit tests for {@see \Yii3\Debug\Web\GridColumnRenderer} spreading the column class across every cell section.
 */
final class GridColumnRendererTest extends TestCase
{
    public function testColumnGroupingCarriesTheColumnClassToTheColElement(): void
    {
        $html = $this->grid()
            ->columnGrouping()
            ->render();

        self::assertStringContainsString(
            "<colgroup>\n<col class=\"yii-debug-col-num\">",
            $html,
            'Column class must reach the `col` element.',
        );
    }

    public function testEmptyBodyCellKeepsTheColumnClass(): void
    {
        $html = $this->grid()->render();

        self::assertStringContainsString(
            '<td class="yii-debug-col-num"></td>',
            $html,
            'Content-free cell must keep the column class.',
        );
    }

    public function testFooterCellCarriesNoColumnMarkup(): void
    {
        $html = $this->grid()
            ->enableFooter()
            ->render();

        self::assertStringContainsString(
            "<tfoot>\n<tr>\n<td>&nbsp;</td>",
            $html,
            'Footer cell must stay empty.',
        );
    }

    /**
     * Returns a grid of one row whose single column renders no content, so every cell section can be inspected.
     *
     * @return GridView<array{value: string}>
     */
    private function grid(): GridView
    {
        /** @var GridView<array{value: string}> $grid */
        $grid = PanelGrid::create(new OffsetPaginator(new IterableDataReader([['value' => '']])));

        return $grid->columns(
            new GridColumn(
                header: 'Value',
                content: self::value(...),
                class: 'yii-debug-col-num',
            ),
        );
    }

    /**
     * @param array{value: string} $row Row whose single field carries the rendered content.
     */
    private static function value(array $row): string
    {
        return $row['value'];
    }
}
