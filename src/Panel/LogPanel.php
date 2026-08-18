<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Helper\EmptyState;
use PHPForge\Debug\Panel\Log\{LogCellRenderer, LogCounts, LogRow, LogSnapshot};
use PHPForge\Debug\Toolbar\ToolbarItem;
use UIAwesome\Html\Flow\P;
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use Yiisoft\Yii\DataView\GridView\Column\DataColumn;

use function htmlspecialchars;
use function is_int;
use function is_string;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Presents the shared Logs panel payload and contributes the message-count toolbar chips.
 */
final readonly class LogPanel implements PanelInterface
{
    use PanelContentTrait;

    /**
     * @param PanelGrid $grid Shared debugger grid renderer.
     */
    public function __construct(private PanelGrid $grid) {}

    public function icon(): string
    {
        return 'logs';
    }

    public function id(): string
    {
        return 'log';
    }

    public function name(): string
    {
        return 'Logs';
    }

    public function render(array $payload): string
    {
        $entries = LogSnapshot::fromArray($payload, 'panels.log')->entries();
        $title = H1::tag()->class('yii-debug-sr-only')->content('Logs')->render();

        if ($entries === []) {
            return $title
                . EmptyState::card(
                    'No log messages captured',
                    P::tag()->content('This request did not emit log messages through the debug log target.'),
                );
        }

        $counts = LogCounts::fromRows($entries);
        $header = Header::tag()
            ->class('yii-debug-grid-summary')
            ->html(
                Span::tag()->html(Strong::tag()->content((string) $counts->total), ' messages'),
                Span::tag()->class('yii-debug-grid-summary-sep')->content('·'),
                Span::tag()->html(Strong::tag()->content((string) $counts->errors), ' errors'),
                Span::tag()->class('yii-debug-grid-summary-sep')->content('·'),
                Span::tag()->html(Strong::tag()->content((string) $counts->warnings), ' warnings'),
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
                content: static fn(LogRow $row): string => LogCellRenderer::renderTimeCell($row),
                encodeContent: false,
                withSorting: false,
                bodyClass: 'yii-debug-cell-num',
            ),
            new DataColumn(
                header: 'Level',
                content: static fn(LogRow $row): string => LogCellRenderer::renderLevelCell($row),
                encodeContent: false,
                withSorting: false,
            ),
            new DataColumn(
                header: 'Category',
                content: static fn(LogRow $row): string => LogCellRenderer::renderCategoryCell($row),
                encodeContent: false,
                withSorting: false,
            ),
            new DataColumn(
                header: 'Message',
                content: static fn(LogRow $row): string => LogCellRenderer::renderMessageCell($row, $traceLine),
                encodeContent: false,
                withSorting: false,
            ),
        ];

        return $title . $header->render() . $this->grid->render($columns, $entries);
    }

    public function toolbarItems(array $payload): array
    {
        $counts = LogCounts::fromRows(LogSnapshot::fromArray($payload, 'panels.log')->entries());
        $items = [new ToolbarItem(value: (string) $counts->total)];

        if ($counts->errors > 0) {
            $items[] = new ToolbarItem(value: (string) $counts->errors, label: 'Errors', status: 'danger');
        }

        if ($counts->warnings > 0) {
            $items[] = new ToolbarItem(value: (string) $counts->warnings, label: 'Warnings', status: 'warning');
        }

        return $items;
    }
}
