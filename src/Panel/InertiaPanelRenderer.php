<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Helper\{CellMore, Coerce, Disclosure, EmptyState};
use PHPForge\Debug\Panel\Inertia\InertiaSnapshot;
use UIAwesome\Html\Flow\{Div, P, Pre};
use UIAwesome\Html\Heading\{H1, H2};
use UIAwesome\Html\Phrasing\{Code, Span, Strong};
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Table\{Table, Tbody, Td, Th, Thead, Tr};

use function count;
use function gettype;
use function htmlspecialchars;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_scalar;
use function is_string;
use function json_encode;
use function strlen;

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
            Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·'),
            Span::tag()->content($visit),
        ];

        if ($page !== null) {
            $items[] = Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·');
            $items[] = Span::tag()
                ->html(
                    Strong::tag()->content((string) count($props)),
                    count($props) === 1 ? ' prop' : ' props',
                );
        }

        return H1::tag()
            ->class('yii-debug-sr-only')
            ->content('Inertia')
            ->render()
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

        $version = is_scalar($page['version'] ?? null) ? (string) $page['version'] : '';

        $rows = [
            self::renderInformationRow('Component', $component !== '' ? $component : '—'),
            self::renderInformationRow('URL', $url !== '' ? $url : '—'),
            self::renderInformationRow('Version', $version !== '' ? $version : '—'),
            self::renderInformationRow('Visit', $visit),
            self::renderInformationRow('Status', (string) $statusCode),
        ];

        foreach ($requestHeaders as $name => $value) {
            if (!is_string($name) || !is_string($value) || $name === 'X-Inertia') {
                continue;
            }

            $rows[] = self::renderInformationRow($name, $value);
        }

        return Div::tag()
            ->class('yii-debug-table-wrap')
            ->html(
                Table::tag()
                    ->class('yii-debug-table yii-debug-table-mono')
                    ->html(Tbody::tag()->html(...$rows)),
            )
            ->render();
    }

    private static function renderInformationRow(string $name, string $value): Tr
    {
        return Tr::tag()->html(
            Th::tag()
                ->scope('row')
                ->style(
                    [
                        'max-width' => 'none',
                        'overflow-wrap' => 'normal',
                        'white-space' => 'nowrap',
                    ],
                )
                ->content($name),
            Td::tag()->content($value),
        );
    }

    private static function renderMissingPage(InertiaSnapshot $snapshot): string
    {
        if ($snapshot->statusCode === 409) {
            return EmptyState::card(
                'Version conflict interrupted this visit',
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
            'No Inertia page in this request',
            P::tag()
                ->html(
                    'This response was not produced by ',
                    Code::tag()->content('Inertia::render()'),
                    ', so there is no page object to inspect.',
                ),
            P::tag()
                ->content(
                    'Both full page loads and Inertia XHR visits populate this view; plain JSON endpoints, '
                    . 'redirects, and asset requests do not.',
                ),
        );
    }

    /**
     * @param array<array-key, mixed> $props
     * @param array<array-key, mixed> $sharedKeys
     */
    private static function renderProps(array $props, array $sharedKeys): string
    {
        if ($props === []) {
            return P::tag()->content('The page rendered without props.')->render();
        }

        $rows = [];

        foreach ($props as $key => $value) {
            $origin = in_array((string) $key, $sharedKeys, true)
                ? Span::tag()->class('yii-debug-badge yii-debug-badge-info')->content('shared')
                : Span::tag()->class('yii-debug-badge yii-debug-badge-muted')->content('page');

            $rows[] = Tr::tag()
                ->html(
                    Td::tag()->content((string) (count($rows) + 1)),
                    Td::tag()
                        ->class('yii-debug-cell-mono yii-debug-cell-nowrap')
                        ->html(Strong::tag()->content((string) $key)),
                    Td::tag()
                        ->class('yii-debug-cell-pill')
                        ->html($origin),
                    Td::tag()
                        ->class('yii-debug-cell-mono yii-debug-cell-nowrap')
                        ->content(self::typeOf($value)),
                    Td::tag()
                        ->class('yii-debug-cell-mono yii-debug-cell-payload')
                        ->html(self::previewOf($value)),
                );
        }

        $table = Div::tag()
            ->class('yii-debug-table-wrap')
            ->html(
                Table::tag()
                    ->class('yii-debug-table')
                    ->html(
                        Thead::tag()->html(
                            Tr::tag()->html(
                                Th::tag()->scope('col')->content('#'),
                                Th::tag()->scope('col')->content('Prop'),
                                Th::tag()->scope('col')->content('Origin'),
                                Th::tag()->scope('col')->content('Type'),
                                Th::tag()->scope('col')->content('Value'),
                            ),
                        ),
                        Tbody::tag()->html(...$rows),
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

    private static function typeOf(mixed $value): string
    {
        return match (true) {
            is_array($value) => 'array(' . count($value) . ')',
            is_string($value) => 'string(' . strlen($value) . ')',
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_bool($value) => 'bool',
            $value === null => 'null',
            default => gettype($value),
        };
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
