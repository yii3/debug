<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Data\FilterPrefix;
use PHPForge\Debug\Helper\EmptyState;
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Panel\Queue\{JobRecord, QueueCardRenderer, QueueGridRenderer, QueueSnapshot, QueueSummary};
use PHPForge\Debug\Toolbar\ToolbarItem;
use UIAwesome\Html\Flow\P;
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Phrasing\{Code, Span, Strong};
use UIAwesome\Html\Root\Header;
use Yii3\Debug\Grid\{PrefixedDropdownFilter, PrefixedTextFilter};
use Yii3\Debug\Search\QueueSearch;
use Yiisoft\Data\Reader\Sort;
use Yiisoft\Yii\DataView\GridView\Column\DataColumn;

use function array_combine;
use function count;
use function in_array;
use function is_array;
use function spl_object_id;

/**
 * Presents Yii3 queue push and worker records with the shared Queue grid and job cards.
 */
final readonly class QueuePanel implements ContextAwarePanelInterface
{
    use PanelContentTrait;

    public function __construct(private PanelGrid $grid) {}

    public function icon(): string
    {
        return 'queue';
    }

    public function id(): string
    {
        return 'queue';
    }

    public function name(): string
    {
        return 'Queue';
    }

    public function render(array $payload): string
    {
        return $this->renderPanel($payload);
    }

    public function renderWithContext(array $payload, PanelRenderContext $context): string
    {
        return $this->renderPanel($payload, $context);
    }

    public function toolbarItems(array $payload): array
    {
        $entries = $payload['entries'] ?? null;

        if (!is_array($entries) || $entries === []) {
            return [];
        }

        $snapshot = QueueSnapshot::fromArray($payload, 'panels.queue');
        $summary = QueueSummary::fromRecords($snapshot->entries());
        $items = [new ToolbarItem(value: (string) $summary->totalEvents())];

        if ($summary->hasErrors()) {
            $items[] = new ToolbarItem(
                value: (string) $summary->totalErrors(),
                label: 'Errors',
                status: 'danger',
            );
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderPanel(array $payload, PanelRenderContext|null $context = null): string
    {
        $records = QueueSnapshot::fromArray($payload, 'panels.queue')->entries();
        $summary = QueueSummary::fromRecords($records);

        $title = H1::tag()
            ->class('yii-debug-sr-only')
            ->content('Queue')
            ->render();

        $queryParams = $context === null ? [] : $context->queryParams;

        $search = QueueSearch::fromQueryParams($queryParams);

        $filtered = $search->filter($records);

        $summaryItems = [
            Span::tag()->html(
                Strong::tag()->content((string) count($filtered)),
                ' of ',
                Strong::tag()->content((string) $summary->totalEvents()),
                ' events',
            ),
            Span::tag()->class('yii-debug-grid-summary-sep')->content('·'),
            Span::tag()->html(Strong::tag()->content((string) $summary->totalPushed()), ' pushed'),
        ];

        if ($summary->totalExecuted() > 0) {
            $summaryItems[] = Span::tag()->class('yii-debug-grid-summary-sep')->content('·');
            $summaryItems[] = Span::tag()
                ->html(Strong::tag()->content((string) $summary->totalExecuted()), ' executed');
        }

        if ($summary->hasErrors()) {
            $summaryItems[] = Span::tag()->class('yii-debug-grid-summary-sep')->content('·');
            $summaryItems[] = Span::tag()
                ->class('yii-debug-grid-summary-stat-danger')
                ->html(Strong::tag()->content((string) $summary->totalErrors()), ' failed');
        }

        if ($context !== null && !$summary->isEmpty()) {
            $summaryItems[] = $this->grid->pageSizeSelector($queryParams, 25);
        }

        $header = Header::tag()->class('yii-debug-grid-summary')->html(...$summaryItems)->render();

        if ($summary->isEmpty()) {
            return $title . $header . EmptyState::card(
                'No jobs queued in this request',
                P::tag()->content('This request did not push or execute messages through a debug queue decorator.'),
                P::tag()->html(
                    'Wrap ',
                    Code::tag()->content('QueueProducerInterface'),
                    ' with ',
                    Code::tag()->content('QueueProducerDecorator'),
                    ' to populate this view.',
                ),
            );
        }

        $componentIds = $summary->componentIds();
        $driverNames = [];
        $sequences = [];

        foreach ($records as $sequence => $record) {
            $sequences[spl_object_id($record)] = $sequence;

            if ($record->driverName !== '' && !in_array($record->driverName, $driverNames, true)) {
                $driverNames[] = $record->driverName;
            }
        }

        $full = $context !== null;
        $jobUrl = static fn(JobRecord $record): string => $context === null
            ? '#'
            : $context->actionUrl('queue-job', ['seq' => $sequences[spl_object_id($record)] ?? 0]);
        $columns = [
            new DataColumn(
                property: 'jobId',
                header: 'ID',
                content: static fn(JobRecord $row): string => QueueGridRenderer::renderIdCell($row),
                encodeContent: false,
                withSorting: $full,
                filter: $full
                    ? new PrefixedTextFilter(FilterPrefix::QUEUE, ['aria-label' => 'Filter by ID'])
                    : false,
                filterEmpty: $full ? static fn(): bool => true : null,
                bodyClass: 'yii-debug-col-queue-id',
            ),
            new DataColumn(
                property: 'eventType',
                header: 'Status',
                content: static fn(JobRecord $row): string => QueueGridRenderer::renderStatusCell($row),
                encodeContent: false,
                withSorting: $full,
                filter: $full
                    ? new PrefixedDropdownFilter(
                        FilterPrefix::QUEUE,
                        ['push' => 'Queued (push)', 'exec' => 'Done (exec)', 'error' => 'Failed (error)'],
                        ['aria-label' => 'Filter by Status'],
                    )
                    : false,
                filterEmpty: $full ? static fn(): bool => true : null,
                bodyClass: 'yii-debug-cell-pill',
            ),
            new DataColumn(
                property: 'driverName',
                header: 'Driver',
                content: static fn(JobRecord $row): string => QueueGridRenderer::renderDriverCell($row),
                encodeContent: false,
                withSorting: $full,
                filter: $full
                    ? new PrefixedDropdownFilter(
                        FilterPrefix::QUEUE,
                        array_combine($driverNames, $driverNames),
                        ['aria-label' => 'Filter by Driver'],
                    )
                    : false,
                filterEmpty: $full ? static fn(): bool => true : null,
                bodyClass: 'yii-debug-cell-pill',
            ),
            new DataColumn(
                property: 'componentId',
                header: 'Component',
                content: static fn(JobRecord $row): string => QueueGridRenderer::renderComponentCell($row),
                withSorting: $full,
                filter: $full
                    ? new PrefixedDropdownFilter(
                        FilterPrefix::QUEUE,
                        array_combine($componentIds, $componentIds),
                        ['aria-label' => 'Filter by Component'],
                    )
                    : false,
                filterEmpty: $full ? static fn(): bool => true : null,
                bodyClass: 'yii-debug-cell-nowrap',
            ),
            new DataColumn(
                property: 'jobClass',
                header: 'Job',
                content: static fn(JobRecord $row): string => QueueGridRenderer::renderJobCell($row, $jobUrl($row)),
                encodeContent: false,
                withSorting: $full,
                filter: $full
                    ? new PrefixedTextFilter(FilterPrefix::QUEUE, ['aria-label' => 'Filter by Job'])
                    : false,
                filterEmpty: $full ? static fn(): bool => true : null,
            ),
            new DataColumn(
                property: 'time',
                header: 'Time',
                content: static fn(JobRecord $row): string => QueueGridRenderer::renderTimeCell($row),
                withSorting: $full,
                bodyClass: 'yii-debug-cell-nowrap yii-debug-cell-mono',
            ),
            new DataColumn(
                property: 'duration',
                header: 'Duration',
                content: static fn(JobRecord $row): string => QueueGridRenderer::renderDurationCell($row),
                withSorting: $full,
                bodyClass: 'yii-debug-cell-nowrap yii-debug-cell-numeric',
            ),
            new DataColumn(
                header: 'TTR',
                content: static fn(JobRecord $row): string => QueueGridRenderer::renderTtrCell($row),
                withSorting: false,
                bodyClass: 'yii-debug-cell-nowrap yii-debug-cell-numeric',
            ),
            new DataColumn(
                header: 'Attempt',
                content: static fn(JobRecord $row): string => QueueGridRenderer::renderAttemptCell($row),
                withSorting: false,
                bodyClass: 'yii-debug-cell-nowrap yii-debug-cell-numeric',
            ),
        ];

        $asyncHint = QueueCardRenderer::renderAsyncHint($summary)?->render() ?? '';

        if ($context === null) {
            return $title . $header . $asyncHint . $this->grid->render($columns, $filtered);
        }

        $grid = $this->grid
            ->fullForContext($context, FilterPrefix::QUEUE, 'yii-debug-queue-filters')
            ->containerAttributes(['class' => 'yii-debug-grid yii-debug-grid-queue'])
            ->bodyRowAttributes(
                static fn(array|object $row): array => $row instanceof JobRecord
                    ? ['class' => 'yii-debug-row-link', 'data-href' => $jobUrl($row)]
                    : [],
            )
            ->dataReader(
                $this->grid->paginator(
                    $filtered,
                    $queryParams,
                    Sort::only(['eventType', 'driverName', 'componentId', 'jobClass', 'jobId', 'time', 'duration'])
                        ->withoutDefaultSorting()
                        ->withOrder(['time' => 'asc']),
                    25,
                ),
            )
            ->columns(...$columns)
            ->render();

        return $title
            . $header
            . $asyncHint
            . $this->grid->activeFilterBanner($context, FilterPrefix::QUEUE, $search->activeFilters)
            . $grid;
    }
}
