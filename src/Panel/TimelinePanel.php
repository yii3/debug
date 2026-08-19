<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;
use PHPForge\Debug\Panel\Timeline\{
    TimelineGeometry,
    TimelineMemoryRenderer,
    TimelineRenderer as CoreTimelineRenderer,
    TimelineSnapshot,
};
use UIAwesome\Html\Heading\H1;
use Yii3\Debug\Search\TimelineSearch;

use function count;

/**
 * Presents captured Yii3 profiler spans as the shared horizontal Timeline chart.
 */
final readonly class TimelinePanel implements ContextAwarePanelInterface
{
    use PanelContentTrait;

    public function icon(): string
    {
        return 'timeline';
    }

    public function id(): string
    {
        return 'timeline';
    }

    public function name(): string
    {
        return 'Timeline';
    }

    public function render(array $payload): string
    {
        return $this->renderPanel(
            $payload,
        );
    }

    public function renderWithContext(array $payload, PanelRenderContext $context): string
    {
        return $this->renderPanel(
            $payload,
            $context,
        );
    }

    public function toolbarItems(array $payload): array
    {
        return [];
    }

    /**
     * Renders the captured request geometry with sibling Profiling rows when snapshot context is available.
     *
     * @param array<string, mixed> $payload Decoded Timeline payload.
     */
    private function renderPanel(array $payload, PanelRenderContext|null $context = null): string
    {
        $timeline = TimelineSnapshot::fromArray($payload, 'panels.timeline');

        $profilingPayload = $context?->panelPayload('profiling');
        $profiling = $profilingPayload === null
            ? null
            : ProfilingSnapshot::fromArray($profilingPayload, 'panels.profiling');

        $start = $timeline->start * 1000;
        $duration = $profiling === null
            ? ($timeline->end - $timeline->start) * 1000
            : $profiling->time * 1000;

        $rows = $profiling?->entries() ?? [];

        $search = TimelineSearch::fromQueryParams($context->queryParams ?? []);

        $filtered = $search->filter($rows);

        $spans = TimelineGeometry::spans($filtered, $start, $duration);

        $memorySvg = $profiling === null
            ? ''
            : TimelineMemoryRenderer::render(
                $profiling->samples(),
                $start,
                $duration,
                $timeline->memory,
            );
        $profilingUrl = $context?->panelUrl('profiling', []) ?? '#';
        $filterForm = $context === null
            ? ''
            : CoreTimelineRenderer::renderFilterForm(
                $context->panelUrl('timeline', []),
                [
                    'tag' => $context->tag,
                    'panel' => 'timeline',
                ],
                $search->duration(),
                $search->category(),
            );

        return H1::tag()
            ->class('yii-debug-sr-only')
            ->content('Timeline')
            ->render()
            . CoreTimelineRenderer::renderSummary($duration, $timeline->memory, count($spans))
            . $filterForm
            . CoreTimelineRenderer::renderEmptyHint($spans !== [], $profilingUrl)
            . CoreTimelineRenderer::renderChart(
                $spans,
                TimelineGeometry::rulers($duration),
                $memorySvg,
                $timeline->memory,
            );
    }
}
