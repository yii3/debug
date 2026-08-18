<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Panel\User\{UserDataNormalizer, UserIdentityRenderer, UserSnapshot};
use PHPForge\Debug\Toolbar\ToolbarItem;
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Form\{Button, Form, InputHidden, InputText};
use UIAwesome\Html\Form\Values\ButtonType;
use UIAwesome\Html\Heading\{H1, H2};
use UIAwesome\Html\Phrasing\{Label, Span};
use Yii3\Debug\User\{IdentityListProviderInterface, UserSwitch};
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Csrf\CsrfTokenInterface;
use Yiisoft\Data\Reader\Iterable\IterableDataReader;
use Yiisoft\Yii\DataView\GridView\Column\DataColumn;

use function is_array;
use function is_scalar;
use function is_string;
use function rtrim;

/**
 * Presents the shared User panel payload, the identity chip, and the user-switch controls.
 *
 * The switch controls render only when user switching is enabled and a main user is authenticated; the shared
 * `userswitch.js` runtime drives the forms through their fixed element IDs.
 */
final readonly class UserPanel implements PanelInterface
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
        $data = UserSnapshot::fromArray($payload, 'panels.user')->data();
        $rawIdentity = is_array($data['identity'] ?? null) ? $data['identity'] : [];
        $identity = [];

        foreach ($rawIdentity as $key => $value) {
            if (is_string($value)) {
                $identity[(string) $key] = $value;
            }
        }

        $view = UserDataNormalizer::fromIdentity($identity, null);

        return H1::tag()->class('yii-debug-sr-only')->content('User')->render()
            . UserIdentityRenderer::render($view)
            . $this->renderSwitch();
    }

    public function toolbarItems(array $payload): array
    {
        $id = UserSnapshot::fromArray($payload, 'panels.user')->data()['id'] ?? null;

        if ($id === null) {
            return [new ToolbarItem(value: 'Guest', label: 'User')];
        }

        $idLabel = is_scalar($id) ? (string) $id : 'unknown';

        if ($this->userSwitch === null || $this->userSwitch->isMainUser()) {
            return [new ToolbarItem(value: $idLabel, label: $this->name(), status: 'info')];
        }

        return [new ToolbarItem(value: $idLabel, label: $this->name() . ' switching', status: 'warning')];
    }

    /**
     * Returns whether the switch controls may render for the current session.
     */
    private function canSwitchUser(): bool
    {
        return $this->switchEnabled
            && $this->userSwitch !== null
            && $this->userSwitch->getMainUserId() !== null;
    }

    /**
     * Renders a hidden CSRF input for the switch forms, or an empty string without CSRF protection.
     */
    private function csrfInput(): string
    {
        if ($this->csrfToken === null) {
            return '';
        }

        return InputHidden::tag()->name('_csrf')->value($this->csrfToken->getValue())->render();
    }

    /**
     * Renders the switchable-identity grid consumed by the shared user-switch runtime.
     *
     * @param list<IdentityInterface> $identities Switchable identities.
     */
    private function renderIdentityGrid(array $identities): string
    {
        $gridView = $this->grid->create()
            ->dataReader(new IterableDataReader($identities))
            ->columns(
                new DataColumn(
                    header: 'ID',
                    content: static fn(IdentityInterface $identity): string => $identity->getId() ?? '',
                    withSorting: false,
                ),
                new DataColumn(
                    header: 'Identity',
                    content: static fn(IdentityInterface $identity): string => $identity::class,
                    withSorting: false,
                ),
            )
            ->bodyRowAttributes(
                static fn(array|object $identity): array => [
                    'data-key' => $identity instanceof IdentityInterface ? ($identity->getId() ?? '') : '',
                ],
            )
            ->containerAttributes(['class' => 'yii-debug-grid'])
            ->tableAttributes(['class' => 'yii-debug-table yii-debug-table-pointer yii-debug-table-userswitch']);

        return Div::tag()
            ->id('debug-userswitch__filter')
            ->html($gridView->render())
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
    private function renderSwitch(): string
    {
        if (!$this->canSwitchUser()) {
            return '';
        }

        $identities = $this->identities?->identities() ?? [];
        $hasGrid = $identities !== [];
        $header = Div::tag()
            ->class('yii-debug-section-header')
            ->html(H2::tag()->content('Switch user'), $this->renderResetForm());
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
            . ($hasGrid ? $this->renderIdentityGrid($identities) : '');
    }
}
