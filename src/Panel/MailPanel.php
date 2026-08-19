<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Data\{FilterPrefix, PageSize, QueryInput};
use PHPForge\Debug\Helper\EmptyState;
use PHPForge\Debug\Panel\Mail\{MailCardRenderer, MailMessage, MailSnapshot};
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Toolbar\ToolbarItem;
use UIAwesome\Html\Flow\P;
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Phrasing\{Code, Span, Strong};
use UIAwesome\Html\Root\Header;
use Yii3\Debug\Search\MailSearch;

use function array_slice;
use function ceil;
use function count;
use function htmlspecialchars;
use function is_array;
use function max;
use function min;
use function parse_url;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const PHP_URL_PATH;

/**
 * Presents messages captured through the Yii3 mailer decorator with secure `.eml` download links.
 */
final readonly class MailPanel implements ContextAwarePanelInterface
{
    use PanelContentTrait;

    public function __construct(private PanelGrid $grid) {}

    public function icon(): string
    {
        return 'mail';
    }

    public function id(): string
    {
        return 'mail';
    }

    public function name(): string
    {
        return 'Mail';
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

        return is_array($entries) && $entries !== []
            ? [new ToolbarItem(value: (string) count($entries))]
            : [];
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array<string, string> $values
     */
    private static function filterForm(PanelRenderContext $context, array $values): string
    {
        $action = (string) parse_url($context->panelUrl(queryParams: []), PHP_URL_PATH);

        $labels = [
            'from' => 'From',
            'to' => 'To',
            'replyTo' => 'Reply',
            'cc' => 'Copy receiver',
            'bcc' => 'Hidden copy receiver',
            'charset' => 'Charset',
            'subject' => 'Subject',
            'body' => 'Body',
        ];
        $fields = '';

        foreach ($labels as $attribute => $label) {
            $id = "mail-{$attribute}";
            $value = self::escape($values[$attribute] ?? '');
            $fields .= '<div class="yii-debug-field"><label for="' . $id . '">' . self::escape($label) . '</label>'
                . '<input class="yii-debug-input" id="' . $id . '" name="Mail[' . $attribute . ']" value="'
                . $value . '"></div>';
        }

        return '<div id="email-form" class="yii-debug-collapsible"><form class="yii-debug-stack" action="'
            . self::escape($action) . '" method="get">'
            . '<input type="hidden" name="tag" value="' . self::escape($context->tag) . '">'
            . '<input type="hidden" name="panel" value="mail">'
            . '<div class="yii-debug-field-grid">' . $fields . '</div>'
            . '<div><button class="yii-debug-btn yii-debug-btn-primary" type="submit">Apply filters</button></div>'
            . '</form></div>';
    }

    /**
     * @param array<array-key, mixed> $queryParams
     */
    private static function pager(
        PanelRenderContext $context,
        array $queryParams,
        int $page,
        int $pages,
    ): string {
        if ($pages <= 1) {
            return '';
        }

        $items = '';

        for ($number = 1; $number <= $pages; $number++) {
            $params = $queryParams;
            $params['page'] = $number;
            $class = $number === $page ? 'yii-debug-pager-item is-active' : 'yii-debug-pager-item';
            $items .= '<li class="' . $class . '"><a class="yii-debug-pager-link" href="'
                . self::escape($context->panelUrl(queryParams: $params)) . '">' . $number . '</a></li>';
        }

        return '<div class="yii-debug-mail-pager"><ul class="yii-debug-pager">' . $items . '</ul></div>';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderPanel(array $payload, PanelRenderContext|null $context = null): string
    {
        $entries = MailSnapshot::fromArray($payload, 'panels.mail')->entries();

        $queryParams = $context === null ? [] : $context->queryParams;

        $search = MailSearch::fromQueryParams($queryParams);

        $filtered = $search->filter($entries);

        $summaryItems = [
            Span::tag()->html(
                Strong::tag()->content((string) count($filtered)),
                count($filtered) === 1 ? ' message' : ' messages',
            ),
        ];
        $failed = MailMessage::failedCount($entries);

        if ($failed > 0) {
            $summaryItems[] = Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·');
            $summaryItems[] = Span::tag()
                ->class('yii-debug-grid-summary-stat-danger')
                ->html(Strong::tag()->content((string) $failed), ' failed');
        }

        if ($context !== null && $entries !== []) {
            $summaryItems[] = '<button aria-controls="email-form" aria-expanded="false" data-target="#email-form" '
                . 'data-yii-debug-toggle="collapse" class="yii-debug-btn yii-debug-btn-ghost '
                . 'yii-debug-mail-filter-toggle" type="button">Filter</button>';
            $summaryItems[] = $this->grid->pageSizeSelector($queryParams, 20);
        }

        $title = H1::tag()->class('yii-debug-sr-only')->content('Email messages')->render();
        $header = Header::tag()->class('yii-debug-grid-summary')->html(...$summaryItems)->render();

        if ($entries === []) {
            return $title . $header . EmptyState::card(
                'No emails sent in this request',
                P::tag()->content(
                    'This request did not dispatch messages through the Yii3 mailer debug decorator.',
                ),
                P::tag()->html(
                    'Wrap ',
                    Code::tag()->content('MailerInterface'),
                    ' with ',
                    Code::tag()->content('MailerInterfaceProxy'),
                    ' to populate this view.',
                ),
            );
        }

        $pageSize = PageSize::resolve(QueryInput::scalar($queryParams, 'per-page'), 20) ?? max(1, count($filtered));

        $pages = max(1, (int) ceil(count($filtered) / $pageSize));
        $page = min($pages, max(1, (int) (QueryInput::scalar($queryParams, 'page') ?? '1')));
        $visible = array_slice($filtered, ($page - 1) * $pageSize, $pageSize);

        $indexes = [];

        foreach ($entries as $index => $message) {
            $indexes[$message->file] = $index;
        }

        $items = '';

        foreach ($visible as $message) {
            $items .= '<li class="yii-debug-mail-list-item">'
                . MailCardRenderer::renderItem(
                    $message,
                    $context === null
                        ? static fn(string $file): string => '#'
                        : static fn(string $file): string => $context->actionUrl(
                            'download-mail',
                            ['seq' => $indexes[$file] ?? -1],
                        ),
                )
                . '</li>';
        }

        return $title
            . $header
            . ($context === null ? '' : self::filterForm($context, $search->activeFilters))
            . ($context === null ? '' : $this->grid->activeFilterBanner($context, FilterPrefix::MAIL, $search->activeFilters))
            . '<ol class="yii-debug-mail-list">' . $items . '</ol>'
            . ($context === null ? '' : self::pager($context, $queryParams, $page, $pages));
    }
}
