<?php

declare(strict_types=1);

namespace Yii3\Debug\Data;

use PHPForge\Debug\Helper\Dump;

use function array_filter;
use function array_key_exists;
use function array_values;
use function get_object_vars;
use function is_float;
use function is_int;
use function is_numeric;
use function is_object;
use function is_scalar;
use function is_string;
use function mb_stripos;
use function mb_strtolower;
use function preg_match;

/**
 * Filters typed rows against exact, partial, and leading numeric comparison conditions.
 *
 * Framework-neutral core of the debug-panel search models: adapters read raw filter values from their request layer,
 * register them here, and filter the row set with identical semantics on every framework. A raw value starting with
 * `>` or `<` followed by a number becomes a numeric comparison; otherwise the value matches case-insensitively,
 * either as a substring (`partial`) or as a whole (`same`).
 *
 * Usage example:
 * ```php
 * $engine = new \PHPForge\Debug\Data\FilterEngine();
 * $engine->addCondition('level', 'error');
 * $engine->addCondition('message', 'sql', partial: true);
 * $rows = $engine->filter($rows);
 * ```
 */
final class FilterEngine
{
    private const string CHARSET = 'UTF-8';

    /**
     * @var list<
     *   array{attribute: string, operator: '>'|'<'|'>=', value: float}
     *   |array{attribute: string, operator: 'contains'|'same', value: string}
     * >
     */
    private array $conditions = [];

    /**
     * Registers an exact, partial, or leading numeric comparison condition.
     *
     * Empty and non-scalar raw values register nothing, so unfiltered attributes can be passed through unconditionally.
     *
     * @param string $attribute Row attribute (public property or array key) the condition applies to.
     * @param mixed $rawValue Raw filter value as read from the request; scalars are compared as strings.
     * @param bool $partial Whether a non-numeric value matches as a case-insensitive substring instead of whole-value.
     */
    public function addCondition(string $attribute, mixed $rawValue, bool $partial = false): void
    {
        $value = is_scalar($rawValue) ? (string) $rawValue : '';

        if ($value === '') {
            return;
        }

        if (preg_match('/^\s*([<>])\s*(-?(?:\d+(?:\.\d+)?|\.\d+))\s*$/D', $value, $matches) === 1) {
            $this->conditions[] = [
                'attribute' => $attribute,
                'operator' => $matches[1],
                'value' => (float) $matches[2],
            ];

            return;
        }

        $this->conditions[] = [
            'attribute' => $attribute,
            'operator' => $partial ? 'contains' : 'same',
            'value' => $value,
        ];
    }

    /**
     * Registers a numeric greater-than-or-equal condition.
     *
     * @param string $attribute Row attribute (public property or array key) the condition applies to.
     * @param float $value Inclusive lower bound the attribute value must reach.
     */
    public function addMinimumCondition(string $attribute, float $value): void
    {
        $this->conditions[] = ['attribute' => $attribute, 'operator' => '>=', 'value' => $value];
    }

    /**
     * Applies all registered conditions and resets the engine for the next run.
     *
     * @template TRow of array<string, mixed>|object
     *
     * @param array<int, TRow> $rows Rows to filter; typed row objects or string-keyed arrays.
     *
     * @return list<TRow> Rows matching every registered condition, reindexed.
     */
    public function filter(array $rows): array
    {
        $filtered = array_values(array_filter($rows, $this->matches(...)));

        $this->conditions = [];

        return $filtered;
    }

    /**
     * @param array<string, mixed>|object $row Typed row object or string-keyed array.
     */
    private function matches(array|object $row): bool
    {
        foreach ($this->conditions as $condition) {
            $attribute = $condition['attribute'];

            $values = is_object($row) ? get_object_vars($row) : $row;

            if (!array_key_exists($attribute, $values)) {
                return false;
            }

            $candidate = $values[$attribute];
            $operator = $condition['operator'];

            if ($operator === '>' || $operator === '<' || $operator === '>=') {
                $expected = $condition['value'];

                if (
                    !is_float($expected)
                    || !is_int($candidate) && !is_float($candidate) && !is_string($candidate)
                    || !is_numeric($candidate)
                ) {
                    return false;
                }

                $candidate = (float) $candidate;

                $matched = match ($operator) {
                    '>' => $candidate > $expected,
                    '<' => $candidate < $expected,
                    default => $candidate >= $expected,
                };

                if (!$matched) {
                    return false;
                }

                continue;
            }

            $candidate = is_scalar($candidate)
                ? (string) $candidate
                : Dump::export($candidate);

            $expected = $condition['value'];

            if (!is_string($expected)) {
                return false;
            }

            if ($operator === 'contains') {
                if (mb_stripos($candidate, $expected, 0, self::CHARSET) === false) {
                    return false;
                }

                continue;
            }

            if (
                mb_strtolower($candidate, self::CHARSET)
                !== mb_strtolower($expected, self::CHARSET)
            ) {
                return false;
            }
        }

        return true;
    }
}
