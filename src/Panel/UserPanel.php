<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Panel\User\UserSnapshot;
use PHPForge\Debug\Toolbar\ToolbarItem;
use Yii3\Debug\View\ViewMessage;

/**
 * Adapts the framework-neutral PHPForge User panel to the Yii3 debugger.
 *
 * The detail view and the sidebar entry come from the shared presentation, which shows its empty state for a guest
 * capture. The toolbar metric matches the Yii2 User panel instead of the shared username metric: it names the user
 * ID, or reads `Guest` when nobody was signed in.
 */
final class UserPanel extends ProviderPanel
{
    /**
     * Binds the adapter to the shared User presentation.
     */
    public function __construct()
    {
        parent::__construct(new \PHPForge\Debug\Panel\User\UserPanel());
    }

    /**
     * Names the signed-in user by ID, or reports a guest.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @throws \PHPForge\Debug\Storage\HydrationException when the payload does not match the User snapshot schema.
     *
     * @return list<ToolbarItem> One `info` metric carrying the user ID, or a `Guest` metric when the capture holds no
     * ID.
     */
    public function toolbarItems(array $payload): array
    {
        $id = Coerce::stringOrNull(UserSnapshot::fromArray($payload, '$.panels.user')->data()['id'] ?? null);

        if ($id === null) {
            return [ToolbarItem::create(ViewMessage::USER_GUEST->value)];
        }

        return [ToolbarItem::create($id)->withStatus('info')];
    }
}
