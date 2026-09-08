<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use PHPForge\Debug\Data\QueryInput;
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\View\Grid\ActiveFilterBanner;

use function array_keys;

/**
 * Rewrites a panel filter group in the query parameters and renders the banner dropping single filters: replaces the
 * group with the active filters or removes selected attributes while resetting pagination, without changing unrelated
 * navigation parameters.
 */
final class FilterRemoval
{
    /**
     * Renders the active-filter banner whose links remove single filters from the panel query.
     *
     * The clear-all link drops the union of the submitted group keys and the active ones, so values the panel search
     * rejected are cleared together with the filters they left out.
     *
     * @param array<string, string> $activeFilters Attribute-to-value map of the currently applied filters.
     * @param PanelRenderContext $context Context resolving the panel URL and exposing the submitted query.
     * @param array<array-key, mixed> $queryParams Query parameters already normalized by the panel.
     * @param string $prefix Filter group the removed attributes belong to.
     */
    public static function banner(
        array $activeFilters,
        PanelRenderContext $context,
        array $queryParams,
        string $prefix,
    ): string {
        return ActiveFilterBanner::render(
            $activeFilters,
            static fn(array $without): string => $context->panelUrl(
                queryParams: self::queryParams($queryParams, $prefix, $without),
            ),
            array_keys(QueryInput::group($context->queryParams, $prefix) + $activeFilters),
        );
    }

    /**
     * @param array<array-key, mixed> $queryParams Query parameters already normalized by the panel.
     * @param string $prefix Filter group to update.
     * @param list<string> $without Attributes to remove from that group.
     *
     * @return array<array-key, mixed>
     */
    public static function queryParams(array $queryParams, string $prefix, array $without): array
    {
        $filters = QueryInput::group($queryParams, $prefix);

        foreach ($without as $attribute) {
            unset($filters[$attribute]);
        }

        return self::withGroup($queryParams, $prefix, $filters, ['page']);
    }

    /**
     * Replaces the filter group with the active filters, or removes it when no filter is active.
     *
     * The group keeps its current position in the query, so link query strings preserve their parameter order.
     *
     * @param array<array-key, mixed> $queryParams Query parameters already normalized by the panel.
     * @param string $prefix Filter group to replace.
     * @param array<string, string> $filters Active filters; an empty set removes the group.
     * @param list<string> $drop Unrelated parameters to remove, such as `view`.
     *
     * @return array<array-key, mixed>
     */
    public static function withGroup(array $queryParams, string $prefix, array $filters, array $drop = []): array
    {
        foreach ($drop as $name) {
            unset($queryParams[$name]);
        }

        if ($filters === []) {
            unset($queryParams[$prefix]);
        } else {
            $queryParams[$prefix] = $filters;
        }

        return $queryParams;
    }
}
