<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Data\{FilterPrefix, QueryInput};
use PHPForge\Debug\Helper\Tabs;
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Panel\User\{
    UserDataNormalizer,
    UserGuestRenderer,
    UserIdentityRenderer,
    UserRbacRenderer,
    UserRbacRow,
    UserSnapshot,
};
use PHPForge\Debug\Toolbar\ToolbarItem;
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Form\{Button, Form, InputHidden, InputText};
use UIAwesome\Html\Form\Values\ButtonType;
use UIAwesome\Html\Heading\{H1, H2};
use UIAwesome\Html\Phrasing\{Label, Span};
use Yii3\Debug\Grid\PrefixedTextFilter;
use Yii3\Debug\Search\UserSearch;
use Yii3\Debug\User\{IdentityListProviderInterface, UserSwitch};
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Csrf\CsrfTokenInterface;
use Yiisoft\Data\Reader\Iterable\IterableDataReader;
use Yiisoft\Data\Reader\Sort;
use Yiisoft\Yii\DataView\GridView\Column\DataColumn;

use function array_key_exists;
use function count;
use function date;
use function get_object_vars;
use function is_array;
use function is_numeric;
use function is_scalar;
use function is_string;
use function rtrim;

/**
 * Presents the shared User panel payload, the identity chip, and the user-switch controls.
 *
 * The switch controls render only when user switching is enabled and a main user is authenticated; the shared
 * `userswitch.js` runtime drives the forms through their fixed element IDs.
 */
final readonly class UserPanel implements ContextAwarePanelInterface
{
    use PanelContentTrait;

    private string $routePrefix;

    /**
     * @param PanelGrid $grid Shared debugger grid renderer.
     * @param UserSwitch|null $userSwitch Identity switch service, or `null` when unavailable.
     * @param IdentityListProviderInterface|null $identities Switchable-identity provider, or `null` to fall back to
     * the manual ID input.
     * @param CsrfTokenInterface|null $csrfToken CSRF token embedded into the switch forms, or `null` when the
     * application does not use CSRF protection.
     * @param bool $switchEnabled Whether user switching is enabled (deny by default).
     * @param string $routePrefix URL prefix serving the Yii3 debugger pages.
     */
    public function __construct(
        private PanelGrid $grid,
        private UserSwitch|null $userSwitch = null,
        private IdentityListProviderInterface|null $identities = null,
        private CsrfTokenInterface|null $csrfToken = null,
        private bool $switchEnabled = false,
        string $routePrefix = '/debug',
    ) {
        $this->routePrefix = rtrim($routePrefix, '/');
    }

    public function icon(): string
    {
        return 'user';
    }

    public function id(): string
    {
        return 'user';
    }

    public function name(): string
    {
        return 'User';
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
        $id = UserSnapshot::fromArray($payload, 'panels.user')->data()['id'] ?? null;

        if ($id === null) {
            return [new ToolbarItem(value: 'Guest')];
        }

        $idLabel = is_scalar($id) ? (string) $id : 'unknown';

        if ($this->userSwitch === null || $this->userSwitch->isMainUser()) {
            return [new ToolbarItem(value: $idLabel, status: 'info')];
        }

        return [new ToolbarItem(value: $idLabel, label: 'switching', status: 'warning')];
    }

    /**
     * Returns whether the switch controls may render for the current session.
     */
    private function canSwitchUser(): bool
    {
        return $this->switchEnabled
            && $this->userSwitch !== null
            && $this->csrfToken !== null
            && $this->userSwitch->getMainUserId() !== null;
    }

    /**
     * Renders the hidden CSRF input required by the switch forms.
     */
    private function csrfInput(): string
    {
        if ($this->csrfToken === null) {
            return '';
        }

        return InputHidden::tag()->name('_csrf')->value($this->csrfToken->getValue())->render();
    }

    /**
     * Formats a switch-grid timestamp using the Yii2 `Y-m-d H:i` vocabulary.
     */
    private static function formatTimestamp(string $value): string
    {
        return $value !== '' && is_numeric($value) ? date('Y-m-d H:i', (int) $value) : $value;
    }

    /**
     * Builds one Yii2-compatible identity-list column.
     *
     * @return DataColumn<array<array-key, mixed>>
     */
    private function identityColumn(
        string $property,
        string $header,
        bool $full,
        bool $timestamp = false,
    ): DataColumn {
        return new DataColumn(
            property: $property,
            header: $header,
            content: $timestamp
                ? static fn(array $row): string => self::formatTimestamp(self::identityRowValue($row, $property))
                : static fn(array $row): string => self::identityRowValue($row, $property),
            withSorting: $full,
            filter: $full
                ? new PrefixedTextFilter(FilterPrefix::USER, ['aria-label' => "Filter by {$header}"])
                : false,
            filterEmpty: $full ? static fn(): bool => true : null,
            bodyClass: $timestamp ? 'yii-debug-cell-mono yii-debug-nowrap' : null,
        );
    }

    /**
     * Normalizes identity objects into the configurable Yii2 switch-grid vocabulary.
     *
     * @param list<IdentityInterface> $identities Switchable identities.
     *
     * @return list<array{id:string,username:string,email:string,status:string,created_at:string,updated_at:string}>
     */
    private function identityRows(array $identities): array
    {
        $rows = [];

        foreach ($identities as $identity) {
            $values = [];

            foreach (get_object_vars($identity) as $key => $value) {
                $values[(string) $key] = $value;
            }

            $rows[] = [
                'id' => $identity->getId() ?? '',
                'username' => self::identityValue($values, 'username'),
                'email' => self::identityValue($values, 'email'),
                'status' => self::identityValue($values, 'status'),
                'created_at' => self::identityValue($values, 'created_at', 'createdAt'),
                'updated_at' => self::identityValue($values, 'updated_at', 'updatedAt'),
            ];
        }

        return $rows;
    }

    /**
     * Returns a scalar identity-grid row value as a string.
     *
     * @param array<array-key, mixed> $row Normalized identity row.
     */
    private static function identityRowValue(array $row, string $property): string
    {
        $value = $row[$property] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Returns the first scalar public identity value matching the supplied aliases.
     *
     * @param array<string, mixed> $values Public identity properties.
     */
    private static function identityValue(array $values, string ...$aliases): string
    {
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $values) && is_scalar($values[$alias])) {
                return (string) $values[$alias];
            }
        }

        return '';
    }

    /**
     * Renders the switchable-identity grid consumed by the shared user-switch runtime.
     *
     * @param list<IdentityInterface> $identities Switchable identities.
     */
    private function renderIdentityGrid(array $identities, PanelRenderContext|null $context): string
    {
        $rows = $this->identityRows($identities);

        $queryParams = $context === null ? [] : $context->queryParams;

        $search = UserSearch::fromQueryParams($queryParams);

        $filtered = $search->filter($rows);

        $full = $context !== null;

        $columns = [
            $this->identityColumn('id', 'Id', $full),
            $this->identityColumn('username', 'Username', $full),
            $this->identityColumn('email', 'Email', $full),
            $this->identityColumn('status', 'Status', $full),
            $this->identityColumn('created_at', 'Created At', $full, timestamp: true),
            $this->identityColumn('updated_at', 'Updated At', $full, timestamp: true),
        ];

        if ($context === null) {
            $grid = $this->grid->create()
                ->dataReader(new IterableDataReader($filtered));
        } else {
            $gridQuery = $queryParams;

            $userQuery = QueryInput::group($gridQuery, FilterPrefix::USER);

            $userQuery['_active'] = 'switch';
            $gridQuery[FilterPrefix::USER] = $userQuery;

            $grid = $this->grid
                ->full(
                    $context->panelUrl(queryParams: []),
                    $gridQuery,
                    FilterPrefix::USER,
                    'yii-debug-user-filters',
                )
                ->dataReader(
                    $this->grid->paginator(
                        $filtered,
                        $queryParams,
                        Sort::only(['id', 'username', 'email', 'status', 'created_at', 'updated_at'])
                            ->withOrder(['id' => 'desc']),
                        10,
                    ),
                );
        }

        $gridHtml = $grid
            ->columns(...$columns)
            ->bodyRowAttributes(
                static fn(array|object $identity): array => [
                    'data-key' => is_array($identity) ? ($identity['id'] ?? '') : '',
                ],
            )
            ->containerAttributes(['class' => 'yii-debug-grid'])
            ->tableAttributes(['class' => 'yii-debug-table yii-debug-table-pointer yii-debug-table-userswitch'])
            ->render();

        return Div::tag()
            ->id('debug-userswitch__filter')
            ->html(
                $context === null
                    ? ''
                    : $this->grid->activeFilterBanner(
                        $context,
                        FilterPrefix::USER,
                        $search->activeFilters,
                        ['_active' => 'switch'],
                    ),
                $gridHtml,
            )
            ->render();
    }

    /**
     * Renders the context-free fallback or the complete searchable identity grid used by snapshot pages.
     *
     * @param array<string, mixed> $payload Decoded User payload.
     */
    private function renderPanel(array $payload, PanelRenderContext|null $context = null): string
    {
        $data = UserSnapshot::fromArray($payload, 'panels.user')->data();

        $title = H1::tag()
            ->class('yii-debug-sr-only')
            ->content('User')
            ->render();

        if (($data['id'] ?? null) === null) {
            return $title . UserGuestRenderer::render();
        }

        $rawIdentity = is_array($data['identity'] ?? null) ? $data['identity'] : [];

        $identity = [];

        foreach ($rawIdentity as $key => $value) {
            $identity[(string) $key] = is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');
        }

        $view = UserDataNormalizer::fromIdentity($identity, null);

        $tabs = [
            [
                'label' => 'User',
                'content' => UserIdentityRenderer::render($view),
            ],
        ];

        $rolesVal = $data['roles'] ?? null;
        $permissionsVal = $data['permissions'] ?? null;

        if (is_array($rolesVal) || is_array($permissionsVal)) {
            $tabs[] = ['label' => 'Roles and Permissions', 'content' => $this->renderRbac($data)];
        }

        $switchTab = null;

        if ($this->canSwitchUser()) {
            $switchTab = count($tabs);
            $tabs[] = ['label' => 'Switch User', 'content' => $this->renderSwitch($context)];
        }

        $activeTab = 0;

        if ($context !== null && $switchTab !== null) {
            $userQuery = QueryInput::group($context->queryParams, FilterPrefix::USER);

            if ($userQuery !== []) {
                $activeTab = $switchTab;
            }
        }

        return $title . Tabs::render('user', 'User data', $tabs, $activeTab);
    }

    /**
     * Renders the Roles and Permissions section from the RBAC snapshot data.
     *
     * @param array<array-key, mixed> $data Snapshot data from {@see UserSnapshot::data()}.
     */
    private function renderRbac(array $data): string
    {
        $rolesVal = $data['roles'] ?? null;
        $permissionsVal = $data['permissions'] ?? null;

        return UserRbacRenderer::render(
            is_array($rolesVal) ? $this->renderRbacGrid($rolesVal) : null,
            is_array($permissionsVal) ? $this->renderRbacGrid($permissionsVal) : null,
        );
    }

    /**
     * Renders one RBAC category with the shared six-column vocabulary.
     *
     * @param array<array-key, mixed> $rawRows Captured role or permission rows.
     */
    private function renderRbacGrid(array $rawRows): string
    {
        $rows = [];

        foreach ($rawRows as $rawRow) {
            $rows[] = UserRbacRow::fromArray(is_array($rawRow) ? $rawRow : []);
        }

        return $this->grid->create()
            ->layout('<div class="yii-debug-table-wrap">{items}</div>')
            ->dataReader(new IterableDataReader($rows))
            ->columns(
                new DataColumn(
                    header: 'Name',
                    content: static fn(UserRbacRow $r): string => $r->name,
                    withSorting: false,
                ),
                new DataColumn(
                    header: 'Description',
                    content: static fn(UserRbacRow $r): string => $r->description,
                    withSorting: false,
                ),
                new DataColumn(
                    header: 'Rule Name',
                    content: static fn(UserRbacRow $r): string => $r->ruleName,
                    withSorting: false,
                ),
                new DataColumn(
                    header: 'Data',
                    content: static fn(UserRbacRow $r): string => $r->data,
                    withSorting: false,
                ),
                new DataColumn(
                    header: 'Created At',
                    content: static fn(UserRbacRow $r): string => $r->createdAt !== null
                        ? date('Y-m-d H:i:s', $r->createdAt)
                        : '',
                    withSorting: false,
                ),
                new DataColumn(
                    header: 'Updated At',
                    content: static fn(UserRbacRow $r): string => $r->updatedAt !== null
                        ? date('Y-m-d H:i:s', $r->updatedAt)
                        : '',
                    withSorting: false,
                ),
            )
            ->containerAttributes(['class' => 'yii-debug-grid'])
            ->tableAttributes(['class' => 'yii-debug-table'])
            ->render();
    }

    /**
     * Renders the reset-identity form shown while impersonating, or an empty string for the main user.
     */
    private function renderResetForm(): string
    {
        if ($this->userSwitch === null || $this->userSwitch->isMainUser()) {
            return '';
        }

        return Form::tag()
            ->action($this->routePrefix . '/reset-identity')
            ->id('debug-userswitch__reset-identity')
            ->method('post')
            ->html(
                $this->csrfInput(),
                Button::tag()
                    ->class('yii-debug-btn yii-debug-btn-ghost')
                    ->html(
                        'Reset to ',
                        Span::tag()
                            ->class('yii-debug-level-chip yii-debug-level-info')
                            ->content($this->userSwitch->getMainUserId() ?? ''),
                    )
                    ->id('debug-userswitch__reset-identity-button')
                    ->type(ButtonType::SUBMIT),
            )
            ->render();
    }

    /**
     * Renders the switch-user section: header with the reset action, the switch form, and the identity grid.
     */
    private function renderSwitch(PanelRenderContext|null $context): string
    {
        if (!$this->canSwitchUser()) {
            return '';
        }

        $identities = $this->identities?->identities() ?? [];

        $hasGrid = $identities !== [];

        $header = Div::tag()
            ->class('yii-debug-section-header')
            ->html(
                H2::tag()
                    ->content('Switch user'),
                $this->renderResetForm(),
            );
        $setForm = Form::tag()
            ->action($this->routePrefix . '/set-identity')
            ->class('yii-debug-stack')
            ->id('debug-userswitch__set-identity')
            ->method('post')
            ->html(
                $this->csrfInput(),
                Div::tag()
                    ->class('yii-debug-field')
                    ->html(
                        Label::tag()->class('yii-debug-label')->content('Switch User')->for('user_id'),
                        InputText::tag()->class('yii-debug-input')->id('user_id')->name('user_id'),
                    ),
                Button::tag()
                    ->class('yii-debug-btn yii-debug-btn-primary')
                    ->content('Switch')
                    ->type(ButtonType::SUBMIT),
            );

        if ($hasGrid) {
            $setForm = $setForm->style('display:none');
        }

        return $header->render()
            . $setForm->render()
            . ($hasGrid ? $this->renderIdentityGrid($identities, $context) : '');
    }
}
