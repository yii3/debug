<?php

declare(strict_types=1);

namespace Yii3\Debug\Search;

use PHPForge\Debug\Data\{FilterEngine, FilterPrefix, QueryInput};
use PHPForge\Debug\Panel\Mail\MailMessage;

/**
 * Applies the Yii2-compatible `Mail[attribute]` filters to captured messages.
 */
final readonly class MailSearch
{
    private const array ATTRIBUTES = [
        'from',
        'to',
        'replyTo',
        'cc',
        'bcc',
        'subject',
        'body',
        'charset',
    ];

    /**
     * @param array<string, string> $activeFilters Active mail filter values.
     */
    private function __construct(public array $activeFilters) {}

    /**
     * @param list<MailMessage> $rows
     *
     * @return list<MailMessage>
     */
    public function filter(array $rows): array
    {
        $engine = new FilterEngine();

        foreach (self::ATTRIBUTES as $attribute) {
            $engine->addCondition($attribute, $this->activeFilters[$attribute] ?? null, partial: true);
        }

        return $engine->filter($rows);
    }

    /**
     * @param array<array-key, mixed> $queryParams
     */
    public static function fromQueryParams(array $queryParams): self
    {
        return new self(QueryInput::group($queryParams, FilterPrefix::MAIL));
    }
}
