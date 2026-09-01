<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use PHPForge\Debug\Storage\RequestSummary;
use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Form\{Button, Form, Option, Select};
use UIAwesome\Html\Heading\{H1, H2};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Label, Span};
use UIAwesome\Html\Sectioning\{Article, Section};
use UIAwesome\Html\Table\{Caption, Table, Tbody, Td, Th, Thead, Tr};
use Yii3\Debug\Comparison\{HistoryComparison, HistoryMetricComparison, HistoryPanelComparison};

use function count;
use function date;
use function in_array;
use function preg_match;
use function rawurlencode;
use function rtrim;
use function substr;

/**
 * Renders capture-comparison selection and results with the shared Debug Core primitives.
 */
final class HistoryComparisonRenderer
{
    /**
     * @param array<string, RequestSummary> $manifest
     */
    public static function render(HistoryComparison $comparison, array $manifest, string $routePrefix): string
    {
        $baseline = $comparison->baseline->summary;
        $target = $comparison->target->summary;

        return H1::tag()
            ->class('yii-debug-hero-title')
            ->content('Compare captures')
            ->render()
            . self::section(
                'yii-debug-compare-selection',
                '01',
                'Selection',
                self::renderForm($manifest, $baseline->tag, $target->tag, $routePrefix),
            )
            . self::section(
                'yii-debug-compare-overview-title',
                '02',
                'Capture overview',
                self::renderOverview($comparison, $routePrefix),
            )
            . self::section(
                'yii-debug-compare-metrics-title',
                '03',
                'Request metrics',
                self::renderMetrics($comparison, $routePrefix),
            )
            . self::renderPanelSection($comparison, $routePrefix);
    }

    /**
     * @param array<string, RequestSummary> $manifest
     */
    public static function renderForm(
        array $manifest,
        string $baseline,
        string $target,
        string $routePrefix,
    ): string {
        $baselineSelect = self::captureSelect(
            'baseline',
            'yii-debug-compare-baseline',
            $baseline,
            $manifest,
        );
        $targetSelect = self::captureSelect(
            'target',
            'yii-debug-compare-target',
            $target,
            $manifest,
        );

        return Form::tag()
            ->action(rtrim($routePrefix, '/') . '/compare')
            ->class('yii-debug-compare-form')
            ->html(
                Div::tag()
                    ->class('yii-debug-field')
                    ->html(
                        Label::tag()
                            ->class('yii-debug-label')
                            ->content('Baseline capture')
                            ->for('yii-debug-compare-baseline'),
                        $baselineSelect,
                    ),
                Div::tag()
                    ->class('yii-debug-field')
                    ->html(
                        Label::tag()
                            ->class('yii-debug-label')
                            ->content('Target capture')
                            ->for('yii-debug-compare-target'),
                        $targetSelect,
                    ),
                Div::tag()
                    ->class('yii-debug-field')
                    ->html(
                        Button::tag()
                            ->class('yii-debug-btn yii-debug-btn-primary')
                            ->content('Compare captures')
                            ->type('submit'),
                    ),
            )
            ->method('get')
            ->render();
    }

    /**
     * @param array<string, RequestSummary> $manifest
     */
    public static function renderHistoryForm(array $manifest, string $routePrefix): string
    {
        if (count($manifest) < 2) {
            return '';
        }

        $target = '';
        $baseline = '';
        $position = 0;

        foreach ($manifest as $tag => $_summary) {
            if ($position++ === 0) {
                $target = $tag;

                continue;
            }

            $baseline = $tag;

            break;
        }

        return self::section(
            'yii-debug-history-compare-title',
            'Compare',
            'Capture changes',
            self::renderForm($manifest, $baseline, $target, $routePrefix),
        );
    }

    /**
     * @param array<string, RequestSummary> $manifest
     */
    private static function captureSelect(
        string $name,
        string $id,
        string $selected,
        array $manifest,
    ): Select {
        $select = Select::tag()
            ->class('yii-debug-select')
            ->id($id)
            ->name($name)
            ->required(true)
            ->value($selected);

        foreach ($manifest as $tag => $summary) {
            $time = $summary->time > 0 ? date('H:i:s', (int) $summary->time) : 'time unavailable';
            $method = $summary->method !== '' ? $summary->method : 'UNKNOWN';
            $url = self::truncateUrl($summary->url);
            $shortTag = substr($tag, 0, 8);

            $select = $select->option(
                Option::tag()
                    ->content("{$time} · {$method} · {$url} · {$shortTag}")
                    ->value($tag),
            );
        }

        return $select;
    }

    private static function captureUrl(string $routePrefix, string $tag): string
    {
        return rtrim($routePrefix, '/') . '/view?tag=' . rawurlencode($tag) . '&panel=config';
    }

    private static function panelSection(HistoryComparison $comparison, P|string ...$content): string
    {
        return Section::tag()
            ->addAriaAttribute('labelledby', 'yii-debug-compare-panels-title')
            ->class('yii-debug-section')
            ->html(
                H2::tag()
                    ->class('yii-debug-section-title')
                    ->id('yii-debug-compare-panels-title')
                    ->html(
                        Span::tag()
                            ->class('yii-debug-section-mark')
                            ->content('04'),
                        'Panel structure',
                        Span::tag()
                            ->class('yii-debug-section-count')
                            ->content((string) count($comparison->panels)),
                    ),
                ...$content,
            )
            ->render();
    }

    private static function panelUrl(string $routePrefix, string $tag, string $panel): string
    {
        return rtrim($routePrefix, '/')
            . '/view?tag=' . rawurlencode($tag)
            . '&panel=' . rawurlencode($panel);
    }

    private static function renderCaptureCard(string $label, RequestSummary $summary, string $routePrefix): Article
    {
        return Article::tag()
            ->class('yii-debug-readout-card')
            ->html(
                Span::tag()
                    ->class('yii-debug-readout-label')
                    ->content($label),
                A::tag()
                    ->class('yii-debug-readout-value')
                    ->content($summary->tag)
                    ->href(self::captureUrl($routePrefix, $summary->tag)),
                Span::tag()
                    ->class('yii-debug-readout-meta')
                    ->content("{$summary->method} · {$summary->statusCode}"),
                Span::tag()
                    ->class('yii-debug-muted')
                    ->content($summary->url)
                    ->title($summary->url),
            );
    }

    private static function renderMetricLabel(
        HistoryMetricComparison $metric,
        string $target,
        string $routePrefix,
    ): A|string {
        if ($metric->panelId() !== 'config') {
            return $metric->label;
        }

        return A::tag()
            ->content($metric->label)
            ->href(self::captureUrl($routePrefix, $target));
    }

    private static function renderMetrics(HistoryComparison $comparison, string $routePrefix): string
    {
        $rows = [];
        $target = $comparison->target->summary->tag;

        foreach ($comparison->metrics as $metric) {
            $rows[] = Tr::tag()
                ->html(
                    Th::tag()
                        ->scope('row')
                        ->html(self::renderMetricLabel($metric, $target, $routePrefix)),
                    Td::tag()
                        ->class('yii-debug-cell-mono')
                        ->content($metric->baseline()),
                    Td::tag()
                        ->class('yii-debug-cell-mono')
                        ->content($metric->target()),
                    Td::tag()
                        ->html(
                            Span::tag()
                                ->class('yii-debug-delta-' . self::trend($metric->trend()))
                                ->content($metric->delta()),
                        ),
                );
        }

        return self::table(
            'Request summary comparison',
            ['Metric', 'Baseline', 'Target', 'Delta'],
            $rows,
        );
    }

    private static function renderOverview(HistoryComparison $comparison, string $routePrefix): string
    {
        return Div::tag()
            ->class('yii-debug-compare-overview')
            ->html(
                self::renderCaptureCard('Baseline', $comparison->baseline->summary, $routePrefix),
                self::renderCaptureCard('Target', $comparison->target->summary, $routePrefix),
                Article::tag()
                    ->class('yii-debug-readout-card')
                    ->html(
                        Span::tag()
                            ->class('yii-debug-readout-label')
                            ->content('Result'),
                        Span::tag()
                            ->class('yii-debug-readout-value')
                            ->content($comparison->hasDifferences() ? 'Changed' : 'Identical'),
                        Span::tag()
                            ->class('yii-debug-readout-meta')
                            ->content('Summary and panel structure'),
                    ),
            )
            ->render();
    }

    private static function renderPanelSection(HistoryComparison $comparison, string $routePrefix): string
    {
        if ($comparison->panels === []) {
            return self::panelSection(
                $comparison,
                P::tag()
                    ->class('yii-debug-muted')
                    ->content('Neither capture contains panel payloads to compare.'),
            );
        }

        $rows = [];
        $baseline = $comparison->baseline->summary->tag;
        $target = $comparison->target->summary->tag;

        foreach ($comparison->panels as $panel) {
            $rows[] = Tr::tag()
                ->html(
                    Th::tag()
                        ->scope('row')
                        ->content($panel->label),
                    Td::tag()->html(self::renderPanelState($panel, $baseline, $panel->baselineState(), $routePrefix)),
                    Td::tag()->html(self::renderPanelState($panel, $target, $panel->targetState(), $routePrefix)),
                    Td::tag()->class('yii-debug-cell-numeric')->content((string) $panel->added()),
                    Td::tag()->class('yii-debug-cell-numeric')->content((string) $panel->removed()),
                    Td::tag()->class('yii-debug-cell-numeric')->content((string) $panel->changed()),
                    Td::tag()->class('yii-debug-cell-numeric')->content((string) $panel->unchanged()),
                );
        }

        return self::panelSection(
            $comparison,
            P::tag()
                ->class('yii-debug-muted')
                ->content('Counts compare typed JSON leaf paths without rendering captured values.'),
            self::table(
                'Panel structure comparison',
                ['Panel', 'Baseline', 'Target', 'Added', 'Removed', 'Changed', 'Unchanged'],
                $rows,
            ),
        );
    }

    private static function renderPanelState(
        HistoryPanelComparison $panel,
        string $tag,
        string $state,
        string $routePrefix,
    ): string {
        $variant = match ($state) {
            'Captured' => 'success',
            'Failed' => 'danger',
            default => 'muted',
        };
        $badge = Span::tag()
            ->class("yii-debug-badge yii-debug-badge-{$variant}")
            ->content($state)
            ->render();

        if ($state === 'Not captured') {
            return $badge . ' ' . Span::tag()
                ->class('yii-debug-not-set')
                ->content('—')
                ->render();
        }

        if (!in_array($panel->id, ['config', 'request'], true)) {
            return $badge;
        }

        return $badge . ' ' . A::tag()
            ->class('yii-debug-btn yii-debug-btn-ghost yii-debug-btn-sm')
            ->content('Open panel')
            ->href(self::panelUrl($routePrefix, $tag, $panel->id))
            ->render();
    }

    private static function section(string $id, string $mark, string $title, string $content): string
    {
        return Section::tag()
            ->addAriaAttribute('labelledby', $id)
            ->class('yii-debug-section')
            ->html(
                H2::tag()
                    ->class('yii-debug-section-title')
                    ->id($id)
                    ->html(
                        Span::tag()
                            ->class('yii-debug-section-mark')
                            ->content($mark),
                        $title,
                    ),
                $content,
            )
            ->render();
    }

    /**
     * @param list<string> $headers
     * @param list<Tr> $rows
     */
    private static function table(string $caption, array $headers, array $rows): string
    {
        $headerCells = [];

        foreach ($headers as $header) {
            $headerCells[] = Th::tag()
                ->scope('col')
                ->content($header);
        }

        return Div::tag()
            ->class('yii-debug-table-wrap')
            ->html(
                Table::tag()
                    ->class('yii-debug-table yii-debug-compare-grid')
                    ->html(
                        Caption::tag()
                            ->class('yii-debug-sr-only')
                            ->content($caption),
                        Thead::tag()->html(Tr::tag()->html(...$headerCells)),
                        Tbody::tag()->html(...$rows),
                    ),
            )
            ->render();
    }

    private static function trend(string $trend): string
    {
        return in_array($trend, ['up', 'down', 'neutral'], true) ? $trend : 'neutral';
    }

    private static function truncateUrl(string $url): string
    {
        if (preg_match('/\A(.{69}).{4}/us', $url, $matches) === 1) {
            return "{$matches[1]}...";
        }

        return $url;
    }
}
