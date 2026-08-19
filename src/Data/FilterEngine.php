<?php

declare(strict_types=1);

namespace Yii3\Debug\Data;

use PHPForge\Debug\Data\FilterEngine as CoreFilterEngine;

/**
 * Backward-compatible Yii3 facade for the shared filter engine.
 *
 * New integrations should use {@see CoreFilterEngine} directly.
 */
final class FilterEngine
{
    private CoreFilterEngine $engine;

    public function __construct()
    {
        $this->engine = new CoreFilterEngine();
    }

    public function addCondition(string $attribute, mixed $rawValue, bool $partial = false): void
    {
        $this->engine->addCondition($attribute, $rawValue, $partial);
    }

    public function addMinimumCondition(string $attribute, float $value): void
    {
        $this->engine->addMinimumCondition($attribute, $value);
    }

    /**
     * @template TRow of array<string, mixed>|object
     *
     * @param array<int, TRow> $rows Rows to filter.
     *
     * @return list<TRow> Rows matching every registered condition.
     */
    public function filter(array $rows): array
    {
        return $this->engine->filter($rows);
    }
}
