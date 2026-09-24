<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use InvalidArgumentException;
use Yii3\Debug\Exception\Message;

use function implode;

/**
 * Assembles the built-in panels in the display order {@see BuiltInPanels::IDS} declares.
 *
 * `IDS` is the single order the sidebar and the toolbar both follow: the list takes the panels keyed by ID and orders
 * them from `IDS` alone, so adding a built-in means one entry there and one panel in the map.
 */
final readonly class BuiltInPanelList
{
    /**
     * @param list<ExtensionPanelInterface> $panels Built-in panels in display order.
     */
    private function __construct(private array $panels) {}

    /**
     * Creates the list from the built-in panels keyed by ID, in the order {@see BuiltInPanels::IDS} declares.
     *
     * The order of the map is irrelevant; every ID of `IDS` must have a panel, and every panel must be keyed by the ID
     * it declares.
     *
     * @param array<string, ExtensionPanelInterface> $panelsById Built-in panels keyed by their ID.
     *
     * @throws InvalidArgumentException when a key is not a built-in ID, a panel declares an ID other than its key, or a
     * built-in ID has no panel.
     *
     * @return self List holding the built-in panels in display order.
     */
    public static function fromMap(array $panelsById): self
    {
        foreach ($panelsById as $id => $panel) {
            if (BuiltInPanels::isBuiltIn($id) === false) {
                throw new InvalidArgumentException(
                    Message::BUILT_IN_PANEL_UNKNOWN->getMessage($id, implode(', ', BuiltInPanels::IDS)),
                );
            }

            if ($panel->id() !== $id) {
                throw new InvalidArgumentException(
                    Message::PANEL_ID_MISMATCH->getMessage($id, $panel->id()),
                );
            }
        }

        $panels = [];

        foreach (BuiltInPanels::IDS as $id) {
            $panels[] = $panelsById[$id] ?? throw new InvalidArgumentException(
                Message::BUILT_IN_PANEL_MISSING->getMessage($id),
            );
        }

        return new self($panels);
    }

    /**
     * Returns the built-in panels every host navigation starts from.
     *
     * @return list<ExtensionPanelInterface> Built-in panels in display order.
     */
    public function panels(): array
    {
        return $this->panels;
    }
}
