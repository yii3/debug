<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use PHPForge\Debug\Comparison\PanelComparison;
use PHPForge\Debug\Panel\PanelTitle;
use PHPForge\Debug\Storage\RequestSummary;
use PHPForge\Debug\View\History\CaptureLabel;
use PHPForge\Debug\View\ViewMessage;
use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Form\{Button, Form, Option, Select};
use UIAwesome\Html\Heading\{H1, H2};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Label, Span};
use UIAwesome\Html\Sectioning\{Article, Section};
use Yii3\Debug\Comparison\{HistoryComparison, HistoryMetricComparison, HistoryPanelComparison};
use Yii3\Debug\View\ViewMessage as AdapterMessage;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Data\Reader\Iterable\IterableDataReader;
use Yiisoft\Yii\DataView\GridView\GridView;

use function count;
use function in_array;
use function max;
use function rawurlencode;
use function rtrim;

/**
 * Renders capture-comparison selection and results with the shared Debug Core primitives.
 */
final class HistoryComparisonRenderer
{
    /**
     * Renders the comparison page for two captures.
     *
     * @param HistoryComparison $comparison Differences between the two captures.
     * @param array<string, RequestSummary> $manifest Captured request summaries keyed by tag.
     * @param string $routePrefix Base route used to generate debugger URLs.
     *
     * @return string Rendered comparison page.
     */
    public static function render(HistoryComparison $comparison, array $manifest, string $routePrefix): string
    {
        $panelLabels = [];

        foreach ($comparison->panels as $panel) {
            if (in_array($panel->id, ['config', 'request'], true)) {
                $panelLabels[$panel->id] = $panel->label;
            }
        }

        return self::renderWithPanels($comparison, $manifest, $routePrefix, $panelLabels);
    }

    /**
     * Renders the capture-selection form of the comparison page.
     *
     * @param array<string, RequestSummary> $manifest Captured request summaries keyed by tag.
     * @param string $baseline Tag currently selected as the baseline.
     * @param string $target Tag currently selected as the target.
     * @param string $routePrefix Base route used to generate debugger URLs.
     *
     * @return string Rendered form.
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
                            ->content(ViewMessage::BASELINE)
                            ->for('yii-debug-compare-baseline'),
                        $baselineSelect,
                    ),
                Div::tag()
                    ->class('yii-debug-field')
                    ->html(
                        Label::tag()
                            ->class('yii-debug-label')
                            ->content(ViewMessage::TARGET)
                            ->for('yii-debug-compare-target'),
                        $targetSelect,
                    ),
                Div::tag()
                    ->class('yii-debug-field')
                    ->html(
                        Button::tag()
                            ->class('yii-debug-btn yii-debug-btn-primary')
                            ->content(PanelTitle::COMPARE)
                            ->type('submit'),
                    ),
            )
            ->method('get')
            ->render();
    }

    /**
     * Renders the compact comparison form embedded in the history page.
     *
     * @param array<string, RequestSummary> $manifest Captured request summaries keyed by tag.
     * @param string $routePrefix Base route used to generate debugger URLs.
     *
     * @return string Rendered form, or `''` when fewer than two captures are available.
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
     * Renders links only for panels registered by the current adapter.
     *
     * @param HistoryComparison $comparison Differences between the two captures.
     * @param array<string, RequestSummary> $manifest Captured request summaries keyed by tag.
     * @param string $routePrefix Base route used to generate debugger URLs.
     * @param array<string, string> $panelLabels Registered display names indexed by panel ID.
     *
     * @return string Rendered comparison page.
     */
    public static function renderWithPanels(
        HistoryComparison $comparison,
        array $manifest,
        string $routePrefix,
        array $panelLabels,
    ): string {
        $labels = [];

        foreach ($comparison->panels as $panel) {
            $labels[$panel->id] = $panelLabels[$panel->id] ?? $panel->label;
        }

        $comparison = HistoryComparison::fromSnapshots(
            $comparison->baseline,
            $comparison->target,
            $labels
        );

        $baseline = $comparison->baseline->summary;
        $target = $comparison->target->summary;

        return H1::tag()
            ->class('yii-debug-hero-title')
            ->content(PanelTitle::COMPARE)
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
                self::renderMetrics($comparison),
            )
            . self::renderPanelSection($comparison, $routePrefix, $panelLabels);
    }

    /**
     * Renders one capture selector, listing every retained capture.
     *
     * @param string $name Form field name.
     * @param string $id Element id the label points at.
     * @param string $selected Tag currently selected.
     * @param array<string, RequestSummary> $manifest Captured request summaries keyed by tag.
     *
     * @return Select Rendered selector.
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
            $select = $select->option(
                Option::tag()
                    ->content(CaptureLabel::fromSummary($summary))
                    ->value($tag),
            );
        }

        return $select;
    }

    /**
     * Builds the URL opening one capture.
     *
     * @param string $routePrefix Base route used to generate debugger URLs.
     * @param string $tag Capture to open.
     *
     * @return string URL of that capture.
     */
    private static function captureUrl(string $routePrefix, string $tag): string
    {
        return rtrim($routePrefix, '/') . '/view?tag=' . rawurlencode($tag) . '&panel=config';
    }

    /**
     * Renders a comparison table as a GridView with a visually hidden caption.
     *
     * @template TRow of object
     *
     * @param string $caption Visually hidden caption naming the table.
     * @param list<TRow> $rows Rows to render.
     * @param list<GridColumn<TRow>> $columns Columns in display order.
     *
     * @return string Rendered table.
     */
    private static function grid(string $caption, array $rows, array $columns): string
    {
        /** @var GridView<TRow> $grid */
        $grid = PanelGrid::create(
            (new OffsetPaginator(new IterableDataReader($rows)))->withPageSize(max(1, count($rows))),
        );

        return $grid
            ->caption($caption, ['class' => 'yii-debug-sr-only'])
            ->columns(...$columns)
            ->tableClass('yii-debug-table', 'yii-debug-compare-grid')
            ->render();
    }

    /**
     * Wraps the panel comparison content in its section, adding the verdict heading.
     *
     * @param HistoryComparison $comparison Differences between the two captures.
     * @param P|string ...$content Section content in display order.
     *
     * @return string Rendered section.
     */
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

    /**
     * Builds the URL opening one panel of a capture.
     *
     * @param string $routePrefix Base route used to generate debugger URLs.
     * @param string $tag Capture to open.
     * @param string $panel Panel to open within that capture.
     *
     * @return string URL of the panel view.
     */
    private static function panelUrl(string $routePrefix, string $tag, string $panel): string
    {
        return rtrim($routePrefix, '/')
            . '/view?tag=' . rawurlencode($tag)
            . '&panel=' . rawurlencode($panel);
    }

    /**
     * Renders the card describing one side of the comparison.
     *
     * @param string $label Side heading, such as the baseline or the target.
     * @param RequestSummary $summary Summary of that capture.
     * @param string $routePrefix Base route used to generate debugger URLs.
     *
     * @return Article Rendered card.
     */
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

    /**
     * Renders the request-summary metric table.
     *
     * @param HistoryComparison $comparison Differences between the two captures.
     *
     * @return string Rendered table.
     */
    private static function renderMetrics(HistoryComparison $comparison): string
    {
        return self::grid(
            ViewMessage::COMPARISON_METRICS_CAPTION->value,
            $comparison->metrics,
            [
                new GridColumn(
                    header: 'Metric',
                    content: static fn(HistoryMetricComparison $metric): string => $metric->label,
                    encodeContent: true,
                ),
                new GridColumn(
                    header: 'Baseline',
                    content: static fn(HistoryMetricComparison $metric): string => $metric->baseline(),
                    encodeContent: true,
                    bodyClass: 'yii-debug-cell-mono',
                ),
                new GridColumn(
                    header: 'Target',
                    content: static fn(HistoryMetricComparison $metric): string => $metric->target(),
                    encodeContent: true,
                    bodyClass: 'yii-debug-cell-mono',
                ),
                new GridColumn(
                    header: 'Delta',
                    content: static fn(HistoryMetricComparison $metric): string => Span::tag()
                        ->class('yii-debug-delta-' . self::trend($metric->trend()))
                        ->content($metric->delta())
                        ->render(),
                ),
            ],
        );
    }

    /**
     * Renders the overview section with both capture cards and the overall verdict.
     *
     * @param HistoryComparison $comparison Differences between the two captures.
     * @param string $routePrefix Base route used to generate debugger URLs.
     *
     * @return string Rendered section.
     */
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
                            ->content(
                                $comparison->hasDifferences()
                                    ? ViewMessage::CHANGED->value
                                    : ViewMessage::IDENTICAL->value,
                            ),
                        Span::tag()
                            ->class('yii-debug-readout-meta')
                            ->content(ViewMessage::COMPARISON_SCOPE),
                    ),
            )
            ->render();
    }

    /**
     * Renders the per-panel structural comparison table.
     *
     * @param HistoryComparison $comparison Differences between the two captures.
     * @param string $routePrefix Base route used to generate debugger URLs.
     * @param array<string, string> $panelLabels Registered display names indexed by panel ID.
     *
     * @return string Rendered section.
     */
    private static function renderPanelSection(HistoryComparison $comparison, string $routePrefix, array $panelLabels): string
    {
        if ($comparison->panels === []) {
            return self::panelSection(
                $comparison,
                P::tag()
                    ->class('yii-debug-muted')
                    ->content(AdapterMessage::COMPARISON_PANELS_EMPTY),
            );
        }

        $baseline = $comparison->baseline->summary->tag;
        $target = $comparison->target->summary->tag;

        return self::panelSection(
            $comparison,
            P::tag()
                ->class('yii-debug-muted')
                ->content(AdapterMessage::COMPARISON_COUNTS_SCOPE),
            self::grid(
                ViewMessage::COMPARISON_PANELS_CAPTION->value,
                $comparison->panels,
                [
                    new GridColumn(
                        header: ViewMessage::PANEL->value,
                        content: static fn(HistoryPanelComparison $panel): string => $panel->label,
                        encodeContent: true,
                    ),
                    new GridColumn(
                        header: 'Baseline',
                        content: static fn(HistoryPanelComparison $panel): string => self::renderPanelState(
                            $panel,
                            $baseline,
                            $panel->baselineState(),
                            $routePrefix,
                            isset($panelLabels[$panel->id]),
                        ),
                    ),
                    new GridColumn(
                        header: 'Target',
                        content: static fn(HistoryPanelComparison $panel): string => self::renderPanelState(
                            $panel,
                            $target,
                            $panel->targetState(),
                            $routePrefix,
                            isset($panelLabels[$panel->id]),
                        ),
                    ),
                    new GridColumn(
                        header: 'Added',
                        content: static fn(HistoryPanelComparison $panel): string => (string) $panel->added(),
                        encodeContent: true,
                        bodyClass: 'yii-debug-cell-numeric',
                    ),
                    new GridColumn(
                        header: 'Removed',
                        content: static fn(HistoryPanelComparison $panel): string => (string) $panel->removed(),
                        encodeContent: true,
                        bodyClass: 'yii-debug-cell-numeric',
                    ),
                    new GridColumn(
                        header: ViewMessage::CHANGED->value,
                        content: static fn(HistoryPanelComparison $panel): string => (string) $panel->changed(),
                        encodeContent: true,
                        bodyClass: 'yii-debug-cell-numeric',
                    ),
                    new GridColumn(
                        header: 'Unchanged',
                        content: static fn(HistoryPanelComparison $panel): string => (string) $panel->unchanged(),
                        encodeContent: true,
                        bodyClass: 'yii-debug-cell-numeric',
                    ),
                ],
            ),
        );
    }

    /**
     * Renders one panel state cell, linking it to the panel when that capture is still retained.
     *
     * @param HistoryPanelComparison $panel Panel the cell describes.
     * @param string $tag Capture the cell refers to.
     * @param string $state State of the panel in that capture.
     * @param string $routePrefix Base route used to generate debugger URLs.
     * @param bool $available Whether the panel is registered by the current adapter.
     *
     * @return string Rendered cell.
     */
    private static function renderPanelState(
        HistoryPanelComparison $panel,
        string $tag,
        string $state,
        string $routePrefix,
        bool $available,
    ): string {
        $variant = match ($state) {
            PanelComparison::STATE_CAPTURED => 'success',
            PanelComparison::STATE_FAILED => 'danger',
            default => 'muted',
        };
        $badge = Span::tag()
            ->class("yii-debug-badge yii-debug-badge-{$variant}")
            ->content($state)
            ->render();

        if ($state === PanelComparison::STATE_NOT_CAPTURED) {
            return $badge . ' ' . Span::tag()
                ->class('yii-debug-not-set')
                ->content('—')
                ->render();
        }

        if (!$available) {
            return $badge;
        }

        return $badge . ' ' . A::tag()
            ->class('yii-debug-btn yii-debug-btn-ghost yii-debug-btn-sm')
            ->content(ViewMessage::OPEN_PANEL)
            ->href(self::panelUrl($routePrefix, $tag, $panel->id))
            ->render();
    }

    /**
     * Wraps content in a titled comparison section.
     *
     * @param string $id Element id the section heading anchors.
     * @param string $mark Decorative marker shown before the title.
     * @param string $title Section title.
     * @param string $content Rendered section content.
     *
     * @return string Rendered section.
     */
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
     * Returns the glyph representing the direction a metric moved in.
     *
     * @param string $trend Directional vocabulary: `'up'`, `'down'`, or `'neutral'`.
     *
     * @return string Glyph representing that direction.
     */
    private static function trend(string $trend): string
    {
        return in_array($trend, ['up', 'down', 'neutral'], true) ? $trend : 'neutral';
    }
}
