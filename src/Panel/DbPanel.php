<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Helper\EmptyState;
use PHPForge\Debug\Panel\Db\{DbQueryRenderer, DbSnapshot, QueryRow};
use PHPForge\Debug\Toolbar\ToolbarItem;
use UIAwesome\Html\Flow\P;
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use Yiisoft\Yii\DataView\GridView\Column\DataColumn;

use function count;
use function htmlspecialchars;
use function is_int;
use function is_string;
use function number_format;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Presents the shared Database panel payload and contributes the query-count and total-time toolbar chips.
 */
final readonly class DbPanel implements PanelInterface
{
    use PanelContentTrait;

    /**
     * @param PanelGrid $grid Shared debugger grid renderer.
     */
    public function __construct(private PanelGrid $grid) {}

    public function icon(): string
    {
        return 'db';
    }

    public function id(): string
    {
        return 'db';
    }

    public function name(): string
    {
        return 'Database';
    }

    public function render(array $payload): string
    {
        $entries = DbSnapshot::fromArray($payload, 'panels.db')->entries();
        $title = H1::tag()->class('yii-debug-sr-only')->content('Database Queries')->render();

        if ($entries === []) {
            return $title
                . EmptyState::card(
                    'No database queries captured',
                    P::tag()->content('This request did not profile queries through the debug DB profiler.'),
                );
        }

        $header = Header::tag()
            ->class('yii-debug-grid-summary')
            ->html(
                Span::tag()->html(Strong::tag()->content((string) count($entries)), ' queries'),
                Span::tag()->class('yii-debug-grid-summary-sep')->content('·'),
                Span::tag()->html(Strong::tag()->content(self::totalTime($entries)), ' total'),
            );
        $traceLine = static function (array $frame): string {
            $file = $frame['file'] ?? '';
            $line = $frame['line'] ?? '';
            $text = is_string($file) ? $file . (is_string($line) || is_int($line) ? ":{$line}" : '') : '';

            return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $columns = [
            new DataColumn(
                header: 'Time',
                content: static fn(QueryRow $row): string => DbQueryRenderer::renderTimeCell($row),
                encodeContent: false,
                withSorting: false,
                bodyClass: 'yii-debug-cell-num',
            ),
            new DataColumn(
                header: 'Type',
                content: static fn(QueryRow $row): string => DbQueryRenderer::renderTypeCell($row),
                encodeContent: false,
                withSorting: false,
            ),
            new DataColumn(
                header: 'Duration',
                content: static fn(QueryRow $row): string => DbQueryRenderer::renderDurationCell($row),
                encodeContent: false,
                withSorting: false,
                bodyClass: 'yii-debug-cell-num',
            ),
            new DataColumn(
                header: 'Query',
                content: static fn(QueryRow $row): string => DbQueryRenderer::renderQueryCell(
                    $row,
                    $traceLine,
                    false,
                    static fn(int $seq): string => '',
                ),
                encodeContent: false,
                withSorting: false,
            ),
        ];

        return $title . $header->render() . $this->grid->render($columns, $entries);
    }

    public function toolbarItems(array $payload): array
    {
        $entries = DbSnapshot::fromArray($payload, 'panels.db')->entries();
        $queryCount = count($entries);

        if ($queryCount === 0) {
            return [];
        }

        return [
            new ToolbarItem(
                value: (string) $queryCount,
                status: 'info',
                title: "Executed {$queryCount} database queries.",
            ),
            new ToolbarItem(value: self::totalTime($entries), title: 'Total query time'),
        ];
    }

    /**
     * Formats the summed query duration as a millisecond readout.
     *
     * @param list<QueryRow> $entries Captured query rows.
     */
    private static function totalTime(array $entries): string
    {
        $total = 0.0;

        foreach ($entries as $entry) {
            $total += $entry->duration;
        }

        return number_format($total) . ' ms';
    }
}
