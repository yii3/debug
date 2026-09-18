<?php

declare(strict_types=1);

namespace Yii3\Debug;

use InvalidArgumentException;
use PHPForge\Debug\{CollectorInterface, Panel as PortablePanel};
use PHPForge\Debug\Registration\{PanelOverride, PanelRegistration, PanelRegistry};
use Psr\Container\ContainerInterface;
use Yii3\Debug\Exception\Message;
use Yii3\Debug\Panel\{BuiltInPanels, ExtensionPanelInterface, ProviderPanel};

use function array_keys;
use function array_unique;
use function array_values;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;
use function sort;
use function trim;

/**
 * Holds the collectors and panels an application registered, ordered by the shared registration policy.
 *
 * Panels reach {@see self::panels()} in the order {@see PanelRegistry} computes: built-in IDs keep their registration
 * order and every extension follows by position, then by effective title, then by ID.
 */
final readonly class ExtensionRegistry
{
    /**
     * @var list<CollectorInterface> Enabled collectors in capture order.
     */
    private array $collectors;
    /**
     * @var list<string> IDs the configuration disabled, sorted alphabetically.
     */
    private array $disabled;
    /**
     * @var array<string, PanelOverride> Application overrides indexed by panel ID.
     */
    private array $overrides;
    /**
     * @var list<ExtensionPanelInterface> Enabled panels in navigation order.
     */
    private array $panels;

    /**
     * Validates the registration keys, applies the configured metadata, and computes the navigation order.
     *
     * @param iterable<CollectorInterface> $collectors Enabled collectors in capture order.
     * @param iterable<ExtensionPanelInterface|PortablePanel> $panels Enabled panels, in registration order.
     * @param iterable<string, PanelOverride> $overrides Application overrides indexed by panel ID.
     * @param iterable<string> $disabled IDs the configuration disabled, so the host tells them from missing ones.
     *
     * @throws InvalidArgumentException When an ID is empty or duplicated, a string key does not match the ID the
     * entry declares, or an override renames a panel rendering its own title and icon.
     */
    public function __construct(
        iterable $collectors = [],
        iterable $panels = [],
        iterable $overrides = [],
        iterable $disabled = [],
    ) {
        $collectorList = [];

        foreach ($collectors as $key => $collector) {
            $id = $collector->id();

            if (trim($id) === '' || isset($collectorList[$id])) {
                throw new InvalidArgumentException(
                    "Empty or duplicate debug collector ID: {$id}",
                );
            }

            if (is_string($key) && $key !== $id) {
                throw new InvalidArgumentException(
                    Message::COLLECTOR_ID_MISMATCH->getMessage($key, $id),
                );
            }

            $collectorList[$id] = $collector;
        }

        $panelList = [];

        foreach ($panels as $key => $panel) {
            $adapted = $panel instanceof PortablePanel ? new ProviderPanel($panel) : $panel;
            $id = $adapted->id();

            if (trim($id) === '' || isset($panelList[$id])) {
                throw new InvalidArgumentException(
                    "Empty or duplicate debug panel ID: {$id}",
                );
            }

            if (is_string($key) && $key !== $id) {
                throw new InvalidArgumentException(
                    Message::PANEL_ID_MISMATCH->getMessage($key, $id),
                );
            }

            $panelList[$id] = $adapted;
        }

        $overrideMap = [];

        foreach ($overrides as $id => $override) {
            $overrideMap[$id] = $override;
        }

        $defaults = [];

        foreach ($panelList as $id => $panel) {
            $defaults[] = BuiltInPanels::isBuiltIn($id)
                ? PanelRegistration::builtIn($id, $panel->name(), $panel->icon())
                : PanelRegistration::extension($id, $panel->name(), $panel->icon());
        }

        $registry = PanelRegistry::resolve($defaults, $overrideMap);

        $ordered = [];

        foreach ($registry->enabled() as $registration) {
            // Every enabled registration comes from `$panelList`, so this guard is unreachable.
            // @infection-ignore-all
            $registered = $panelList[$registration->id] ?? throw new InvalidArgumentException(
                Message::EXTENSION_PANEL_UNKNOWN->getMessage($registration->id),
            );

            $ordered[] = self::withMetadata($registered, $registration);
        }

        $disabledIds = $registry->disabled();

        foreach ($disabled as $id) {
            $disabledIds[] = $id;
        }

        $disabledIds = array_unique($disabledIds);

        sort($disabledIds, SORT_STRING);

        $this->collectors = array_values($collectorList);
        $this->disabled = $disabledIds;
        $this->overrides = $overrideMap;
        $this->panels = $ordered;
    }

    /**
     * Returns the collectors the application enabled.
     *
     * @return list<CollectorInterface> Enabled collectors in capture order.
     */
    public function collectors(): array
    {
        return $this->collectors;
    }

    /**
     * Returns the built-in collector first, unless the application explicitly registered an override with the same ID.
     *
     * A built-in whose ID the configuration disabled is dropped instead of placed first.
     *
     * @param CollectorInterface $builtIn Built-in collector to place first.
     *
     * @return list<CollectorInterface> Built-in and enabled collectors in capture order.
     */
    public function collectorsWithBuiltIn(CollectorInterface $builtIn): array
    {
        return $this->collectorsWithBuiltIns([$builtIn]);
    }

    /**
     * Returns built-in collectors first, replacing each one with an explicitly registered collector of the same ID.
     *
     * A built-in whose ID the configuration disabled is dropped instead of placed first.
     *
     * @param iterable<CollectorInterface> $builtIns Built-in collectors in capture order.
     *
     * @return list<CollectorInterface> Built-in and enabled collectors in capture order.
     */
    public function collectorsWithBuiltIns(iterable $builtIns): array
    {
        return self::compose($this->collectors, $builtIns, $this->disabled);
    }

    /**
     * Creates a registry from explicitly enabled collectors and panels.
     *
     * @param iterable<CollectorInterface> $collectors Enabled collectors in capture order.
     * @param iterable<ExtensionPanelInterface|PortablePanel> $panels Enabled panels, in registration order.
     * @param iterable<string, PanelOverride> $overrides Application overrides indexed by panel ID.
     * @param iterable<string> $disabled IDs the configuration disabled.
     *
     * @throws InvalidArgumentException When the registration is invalid.
     *
     * @return self Registry holding the enabled collectors and panels.
     */
    public static function create(
        iterable $collectors = [],
        iterable $panels = [],
        iterable $overrides = [],
        iterable $disabled = [],
    ): self {
        return new self($collectors, $panels, $overrides, $disabled);
    }

    /**
     * Returns the IDs the configuration removed, which a host tells apart from an ID it never knew.
     *
     * @return list<string> Disabled IDs, sorted alphabetically.
     */
    public function disabled(): array
    {
        return $this->disabled;
    }

    /**
     * Creates a registry from the `collectors` and `panels` entries of the application configuration.
     *
     * Every entry is a class string, or an array declaring a `class` string plus options. A collector accepts the
     * `enabled` option alone; a panel accepts the keys {@see PanelOverride::KEYS}. An entry the configuration
     * disabled is reported by {@see self::disabled()} and its class is never resolved.
     *
     * @param iterable<array-key, mixed> $collectors Collector entries indexed by configuration ID.
     * @param iterable<array-key, mixed> $panels Panel entries indexed by configuration ID.
     * @param ContainerInterface $container Container the enabled classes are resolved from.
     *
     * @throws InvalidArgumentException When an entry, an option, or the resulting registration is invalid.
     *
     * @return self Registry holding the enabled collectors and panels.
     */
    public static function fromParams(iterable $collectors, iterable $panels, ContainerInterface $container): self
    {
        $collectorList = [];
        $disabled = [];
        $overrides = [];
        $panelList = [];

        foreach ($collectors as $key => $value) {
            $id = (string) $key;
            [$class, $options] = self::entry($value, $id);

            if (self::collectorEnabled($options, $id) === false) {
                $disabled[] = $id;

                continue;
            }

            /** @var CollectorInterface $collector */
            $collector = $container->get($class);

            $collectorList[$id] = $collector;
        }

        foreach ($panels as $key => $value) {
            $id = (string) $key;
            [$class, $options] = self::entry($value, $id);
            $override = PanelOverride::fromArray($options);

            if ($override->enabled === false) {
                $disabled[] = $id;

                continue;
            }

            /** @var ExtensionPanelInterface|PortablePanel $panel */
            $panel = $container->get($class);

            $overrides[$id] = $override;
            $panelList[$id] = $panel;
        }

        return new self($collectorList, $panelList, $overrides, $disabled);
    }

    /**
     * Returns the panels the application enabled.
     *
     * @return list<ExtensionPanelInterface> Enabled panels in navigation order.
     */
    public function panels(): array
    {
        return $this->panels;
    }

    /**
     * Returns the built-in panel first, unless the application explicitly registered an override with the same ID.
     *
     * A built-in whose ID the configuration disabled is dropped instead of placed first.
     *
     * @param ExtensionPanelInterface $builtIn Built-in panel to place first.
     *
     * @return list<ExtensionPanelInterface> Built-in and enabled panels in navigation order.
     */
    public function panelsWithBuiltIn(ExtensionPanelInterface $builtIn): array
    {
        return $this->panelsWithBuiltIns([$builtIn]);
    }

    /**
     * Returns built-in panels first, replacing each one with an explicitly registered panel of the same ID.
     *
     * A built-in whose ID the configuration disabled is dropped instead of placed first.
     *
     * @param iterable<ExtensionPanelInterface> $builtIns Built-in panels in navigation order.
     *
     * @return list<ExtensionPanelInterface> Built-in and enabled panels in navigation order.
     */
    public function panelsWithBuiltIns(iterable $builtIns): array
    {
        return self::compose($this->panels, $builtIns, $this->disabled);
    }

    /**
     * Returns a copy with one more enabled collector appended.
     *
     * @param CollectorInterface $collector Collector to enable.
     *
     * @throws InvalidArgumentException When the collector ID is empty or already registered.
     *
     * @return self Registry including the collector.
     */
    public function withCollector(CollectorInterface $collector): self
    {
        return new self([...$this->collectors, $collector], $this->panels, $this->overrides, $this->disabled);
    }

    /**
     * Returns a copy with one more enabled panel appended.
     *
     * @param ExtensionPanelInterface|PortablePanel $panel Panel to enable; a portable panel is adapted.
     *
     * @throws InvalidArgumentException When the panel ID is empty or already registered.
     *
     * @return self Registry including the panel.
     */
    public function withPanel(ExtensionPanelInterface|PortablePanel $panel): self
    {
        return new self($this->collectors, [...$this->panels, $panel], $this->overrides, $this->disabled);
    }

    /**
     * Resolves the effective `enabled` flag of one collector entry.
     *
     * @param array<array-key, mixed> $options Options the entry declares beside its class.
     * @param string $id Configuration ID naming the entry in a failure.
     *
     * @throws InvalidArgumentException When the entry declares an unknown option or a non-`bool` `enabled` value.
     *
     * @return bool Effective flag, `true` when the entry omits the option.
     */
    private static function collectorEnabled(array $options, string $id): bool
    {
        foreach (array_keys($options) as $option) {
            if ($option !== 'enabled') {
                throw new InvalidArgumentException(
                    Message::EXTENSION_COLLECTOR_OPTION_UNKNOWN->getMessage($option, $id),
                );
            }
        }

        $enabled = $options['enabled'] ?? true;

        if (is_bool($enabled) === false) {
            throw new InvalidArgumentException(
                Message::EXTENSION_COLLECTOR_ENABLED_INVALID->getMessage($id),
            );
        }

        return $enabled;
    }

    /**
     * Places every built-in first, replacing it with a registered entry of the same ID, and appends the remaining
     * registrations.
     *
     * A built-in no registered entry replaced is skipped once the configuration disabled its ID; one disabled list
     * serves collectors and panels alike, because the same ID names both here and the renderer already skips a panel
     * whose capture is absent.
     *
     * @template TEntry of CollectorInterface|ExtensionPanelInterface
     *
     * @param array<int, TEntry> $registered Registered entries in registration order.
     * @param iterable<TEntry> $builtIns Built-in entries in display order.
     * @param list<string> $disabled IDs the configuration disabled.
     *
     * @return list<TEntry> Built-in and registered entries in display order.
     */
    private static function compose(array $registered, iterable $builtIns, array $disabled): array
    {
        $resolved = [];

        foreach ($builtIns as $builtIn) {
            $match = null;

            foreach ($registered as $index => $entry) {
                if ($entry->id() !== $builtIn->id()) {
                    continue;
                }

                $match = $entry;

                unset($registered[$index]);

                // Registered IDs are unique, so the remaining entries cannot match; the guard is a shortcut only.
                // @infection-ignore-all
                break;
            }

            if ($match === null) {
                if (in_array($builtIn->id(), $disabled, true)) {
                    continue;
                }

                $match = $builtIn;
            }

            $resolved[] = $match;
        }

        return [
            ...$resolved,
            ...$registered,
        ];
    }

    /**
     * Splits one registration entry into the class to resolve and the options left to validate.
     *
     * @param mixed $value Entry the configuration declares: a class string, or an array declaring a `class` string.
     * @param string $id Configuration ID naming the entry in a failure.
     *
     * @throws InvalidArgumentException When the entry declares no class string.
     *
     * @return array{string, array<array-key, mixed>} Class name and remaining options.
     */
    private static function entry(mixed $value, string $id): array
    {
        if (is_string($value)) {
            return [$value, []];
        }

        if (is_array($value) === false || is_string($value['class'] ?? null) === false) {
            throw new InvalidArgumentException(
                Message::EXTENSION_ENTRY_INVALID->getMessage($id),
            );
        }

        /** @var string $class */
        $class = $value['class'];

        unset($value['class']);

        return [$class, $value];
    }

    /**
     * Applies the effective title and icon the policy resolved for a panel.
     *
     * @param ExtensionPanelInterface $panel Registered panel.
     * @param PanelRegistration $registration Effective registration of that panel.
     *
     * @throws InvalidArgumentException When the panel renders its own title and icon, so it carries no override.
     *
     * @return ExtensionPanelInterface Panel presenting the effective metadata.
     */
    private static function withMetadata(
        ExtensionPanelInterface $panel,
        PanelRegistration $registration,
    ): ExtensionPanelInterface {
        if ($registration->title === $panel->name() && $registration->icon === $panel->icon()) {
            return $panel;
        }

        if ($panel instanceof ProviderPanel) {
            return $panel->withMetadata($registration->title, $registration->icon);
        }

        throw new InvalidArgumentException(
            Message::PANEL_METADATA_UNSUPPORTED->getMessage($registration->id),
        );
    }
}
