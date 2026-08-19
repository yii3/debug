<?php

declare(strict_types=1);

namespace Yii3\Debug\Search;

use PHPForge\Debug\Data\{FilterEngine, FilterPrefix, QueryInput};

/**
 * Applies the Yii2-compatible `User[attribute]` filters to switchable identity rows.
 */
final readonly class UserSearch
{
    /**
     * @param array<string, string> $activeFilters Active identity filters.
     */
    private function __construct(public array $activeFilters) {}

    /**
     * Filters switchable identities by ID, username, email, status, and timestamps.
     *
     * @param list<array{id:string,username:string,email:string,status:string,created_at:string,updated_at:string}> $rows
     * Switchable identity rows.
     *
     * @return list<array{id:string,username:string,email:string,status:string,created_at:string,updated_at:string}>
     * Rows matching every active filter.
     */
    public function filter(array $rows): array
    {
        $engine = new FilterEngine();

        $engine->addCondition('id', $this->activeFilters['id'] ?? null);
        $engine->addCondition('username', $this->activeFilters['username'] ?? null, partial: true);
        $engine->addCondition('email', $this->activeFilters['email'] ?? null, partial: true);
        $engine->addCondition('status', $this->activeFilters['status'] ?? null);
        $engine->addCondition('created_at', $this->activeFilters['created_at'] ?? null, partial: true);
        $engine->addCondition('updated_at', $this->activeFilters['updated_at'] ?? null, partial: true);

        return $engine->filter($rows);
    }

    /**
     * Builds the search from parsed debugger query parameters.
     *
     * @param array<array-key, mixed> $queryParams Parsed query parameters.
     */
    public static function fromQueryParams(array $queryParams): self
    {
        $filters = QueryInput::group($queryParams, FilterPrefix::USER);

        unset($filters['_active']);

        return new self($filters);
    }
}
