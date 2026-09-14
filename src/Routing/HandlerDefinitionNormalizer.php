<?php

declare(strict_types=1);

namespace Yii3\Debug\Routing;

use function is_array;
use function is_object;
use function is_string;
use function strpos;
use function substr;

/**
 * Converts Yii router middleware definitions into safe, stable scalar labels.
 */
final class HandlerDefinitionNormalizer
{
    /**
     * Formats an action or middleware definition without retaining its object graph or configuration values.
     *
     * @param mixed $definition Handler as a class name, a `['class' => ...]` array, a callable pair, or any
     * other value.
     *
     * @return string|null Scalar label, or `null` when the definition carries no recognizable name.
     */
    public static function describe(mixed $definition): string|null
    {
        if (is_string($definition)) {
            return self::normalizeClassLabel($definition);
        }

        if (is_array($definition) && is_string($definition['class'] ?? null)) {
            return self::normalizeClassLabel($definition['class']);
        }

        if (
            is_array($definition)
            && (is_string($definition[0] ?? null) || is_object($definition[0] ?? null))
            && is_string($definition[1] ?? null)
        ) {
            $class = is_object($definition[0]) ? $definition[0]::class : $definition[0];

            return self::normalizeClassLabel($class) . '::' . $definition[1] . '()';
        }

        if (is_object($definition)) {
            return self::normalizeClassLabel($definition::class);
        }

        return null;
    }

    /**
     * Formats every handler definition, dropping the ones that carry no recognizable name.
     *
     * @param array<array-key, mixed> $definitions Handler definitions in declaration order.
     *
     * @return list<string> Scalar labels in the same order, unnamed handlers omitted.
     */
    public static function describeAll(array $definitions): array
    {
        $descriptions = [];

        foreach ($definitions as $definition) {
            $description = self::describe($definition);

            if ($description !== null) {
                $descriptions[] = $description;
            }
        }

        return $descriptions;
    }

    /**
     * Removes PHP's NUL-delimited source suffix from anonymous class labels.
     *
     * @param string $label Raw class label, possibly carrying the anonymous-class suffix.
     *
     * @return string Label truncated at the NUL separator when present.
     */
    private static function normalizeClassLabel(string $label): string
    {
        $separator = strpos($label, "\0");

        return $separator === false ? $label : substr($label, 0, $separator);
    }
}
