<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Helper\{CellMore, Coerce, Disclosure, EmptyState, Format};
use PHPForge\Debug\Panel\Inertia\{InertiaMessage, InertiaSnapshot};
use PHPForge\Debug\Panel\PanelTitle;
use UIAwesome\Html\Flow\{Div, P, Pre};
use UIAwesome\Html\Heading\H2;
use UIAwesome\Html\Phrasing\{Code, Span, Strong};
use UIAwesome\Html\Root\Header;
use Yii3\Debug\Web\{PanelHeading, SummaryChip};
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Data\Reader\Iterable\IterableDataReader;
use Yiisoft\Yii\DataView\DetailView\{DataField, DetailView};
use Yiisoft\Yii\DataView\GridView\Column\{DataColumn, SerialColumn};
use Yiisoft\Yii\DataView\GridView\GridView;

use function count;
use function htmlspecialchars;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;

use const ENT_HTML5;
use const ENT_SUBSTITUTE;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Renders the Yii debugger detail presentation for a captured Inertia response.
 */
final class InertiaPanelRenderer
{
    public static function render(InertiaSnapshot $snapshot): string
    {
        $data = $snapshot->data();

        $page = is_array($data['page'] ?? null) ? $data['page'] : null;
        $requestHeaders = is_array($data['requestHeaders'] ?? null) ? $data['requestHeaders'] : [];
        $sharedKeys = is_array($data['sharedKeys'] ?? null) ? $data['sharedKeys'] : [];

        $component = Coerce::string($page['component'] ?? null);

        $props = is_array($page['props'] ?? null) ? $page['props'] : [];

        $visit = self::visit($snapshot->statusCode, $requestHeaders);
        $content = self::renderHeader($component, $visit, $page, $props);

        if ($page === null) {
            return $content . self::renderMissingPage($snapshot);
        }

        return $content
            . self::renderInformation($snapshot->statusCode, $component, $page, $requestHeaders, $visit)
            . H2::tag()->content('Props')->render()
            . self::renderProps($props, $sharedKeys)
            . self::renderRawPayload($page);
    }

    private static function previewOf(mixed $value): string
    {
        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return CellMore::clamp(
            htmlspecialchars($json, ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8'),
            $json,
        );
    }

    /**
     * @param array<array-key, mixed> $page
     * @param array<array-key, mixed> $props
     */
    private static function renderHeader(string $component, string $visit, array|null $page, array $props): string
    {
        $items = [
            Span::tag()
                ->html(Strong::tag()->content($component !== '' ? $component : '—')),
            SummaryChip::separator(),
            Span::tag()->content($visit),
        ];

        if ($page !== null) {
            $items[] = SummaryChip::separator();
            $items[] = SummaryChip::render(
                (string) count($props),
                count($props) === 1 ? ' prop' : ' props',
            );
        }

        return PanelHeading::render(PanelTitle::INERTIA)
            . Header::tag()
                ->class('yii-debug-grid-summary')
                ->html(...$items)
                ->render();
    }

    /**
     * @param array<array-key, mixed> $page
     * @param array<array-key, mixed> $requestHeaders
     */
    private static function renderInformation(
        int $statusCode,
        string $component,
        array $page,
        array $requestHeaders,
        string $visit,
    ): string {
        $url = Coerce::string($page['url'] ?? null);

        $version = Coerce::stringOrNull($page['version'] ?? null) ?? '';

        $fields = [
            new DataField(label: 'Component', value: $component !== '' ? $component : '—'),
            new DataField(label: 'URL', value: $url !== '' ? $url : '—'),
            new DataField(label: 'Version', value: $version !== '' ? $version : '—'),
            new DataField(label: 'Visit', value: $visit),
            new DataField(label: 'Status', value: (string) $statusCode),
        ];

        foreach ($requestHeaders as $name => $value) {
            if (!is_string($name) || !is_string($value) || $name === 'X-Inertia') {
                continue;
            }

            $fields[] = new DataField(label: $name, value: $value);
        }

        return Div::tag()
            ->class('yii-debug-table-wrap')
            ->html(
                DetailView::widget()
                    ->containerTag('table')
                    ->containerAttributes(['class' => 'yii-debug-table yii-debug-table-mono'])
                    ->listTag('tbody')
                    ->fieldTag('tr')
                    ->labelTag('th')
                    ->labelAttributes(
                        [
                            'scope' => 'row',
                            'style' => [
                                'max-width' => 'none',
                                'overflow-wrap' => 'normal',
                                'white-space' => 'nowrap',
                            ],
                        ],
                    )
                    ->valueTag('td')
                    ->fields(...$fields)
                    ->render(),
            )
            ->render();
    }

    private static function renderMissingPage(InertiaSnapshot $snapshot): string
    {
        if ($snapshot->statusCode === 409) {
            return EmptyState::card(
                InertiaMessage::VERSION_CONFLICT_HEADLINE->value,
                P::tag()
                    ->html(
                        'The client asset version sent in ',
                        Code::tag()->content('X-Inertia-Version'),
                        ' no longer matches the server version, so Inertia answered ',
                        Code::tag()->content('409'),
                        ' and asked the client to reload the full page.',
                    ),
                P::tag()
                    ->html(
                        'Reload target: ',
                        Code::tag()->content($snapshot->location ?? '—'),
                    ),
            );
        }

        return EmptyState::card(
            InertiaMessage::EMPTY_HEADLINE->value,
            P::tag()
                ->html(
                    'This response was not produced by ',
                    Code::tag()->content('Inertia::render()'),
                    ', so there is no page object to inspect.',
                ),
            P::tag()->content(InertiaMessage::EMPTY_COVERAGE),
        );
    }

    /**
     * @param array<array-key, mixed> $props
     * @param array<array-key, mixed> $sharedKeys
     */
    private static function renderProps(array $props, array $sharedKeys): string
    {
        if ($props === []) {
            return P::tag()
                ->content('The page rendered without props.')
                ->render();
        }

        $rows = [];

        foreach ($props as $key => $value) {
            $origin = in_array((string) $key, $sharedKeys, true)
                ? Span::tag()
                    ->class('yii-debug-badge yii-debug-badge-info')
                    ->content('shared')
                : Span::tag()
                    ->class('yii-debug-badge yii-debug-badge-muted')
                    ->content('page');

            $rows[] = [
                'prop' => Strong::tag()
                    ->content((string) $key)
                    ->render(),
                'origin' => $origin->render(),
                'type' => Format::typeOf($value),
                'value' => self::previewOf($value),
            ];
        }

        $table = GridView::widget()
            ->dataReader((new OffsetPaginator(new IterableDataReader($rows)))->withPageSize(count($rows)))
            ->layout('{items}')
            ->containerClass('yii-debug-table-wrap')
            ->tableClass('yii-debug-table')
            ->headerCellAttributes(['scope' => 'col'])
            ->columns(
                new SerialColumn(header: '#'),
                new DataColumn(
                    property: 'prop',
                    withSorting: false,
                    encodeContent: false,
                    bodyClass: 'yii-debug-cell-mono yii-debug-cell-nowrap',
                ),
                new DataColumn(
                    property: 'origin',
                    withSorting: false,
                    encodeContent: false,
                    bodyClass: 'yii-debug-cell-pill',
                ),
                new DataColumn(
                    property: 'type',
                    withSorting: false,
                    bodyClass: 'yii-debug-cell-mono yii-debug-cell-nowrap',
                ),
                new DataColumn(
                    property: 'value',
                    withSorting: false,
                    encodeContent: false,
                    bodyClass: 'yii-debug-cell-mono yii-debug-cell-payload',
                ),
            )
            ->render();

        return count($rows) > CellMore::ROW_THRESHOLD ? CellMore::wrap($table) : $table;
    }

    /**
     * @param array<array-key, mixed> $page
     */
    private static function renderRawPayload(array $page): string
    {
        $json = json_encode(
            $page,
            JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return Disclosure::render('Raw payload', Pre::tag()->content($json)->render());
    }

    /**
     * @param array<array-key, mixed> $requestHeaders
     */
    private static function visit(int $statusCode, array $requestHeaders): string
    {
        $isXhr = isset($requestHeaders['X-Inertia']);
        $isPartial = $isXhr
            && (isset($requestHeaders['X-Inertia-Partial-Data'])
                || isset($requestHeaders['X-Inertia-Partial-Except']));

        return match (true) {
            $statusCode === 409 => 'Version conflict',
            $isPartial => 'Partial reload',
            $isXhr => 'Inertia visit',
            default => 'Full page load',
        };
    }
}
