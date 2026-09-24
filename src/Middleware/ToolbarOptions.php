<?php

declare(strict_types=1);

namespace Yii3\Debug\Middleware;

use InvalidArgumentException;
use Yii3\Debug\Exception\Message;

use function array_is_list;
use function array_key_exists;
use function is_array;
use function is_int;
use function is_string;
use function rtrim;
use function str_starts_with;

/**
 * Carries the `yii3/debug` settings shared by the debugger middlewares, the debugger pages, and the toolbar payload.
 *
 * One instance built from the application parameters reaches every consumer through the container, so the route
 * prefix and the toolbar presentation cannot drift apart between the services that read them.
 */
final readonly class ToolbarOptions
{
    /**
     * Base path the debugger endpoints are served under, without a trailing slash.
     */
    public string $routePrefix;

    /**
     * @param string $routePrefix Base path the debugger endpoints are served under; a trailing slash is trimmed.
     * @param int $historySize Captures kept before the oldest are rotated out.
     * @param int|null $excessiveCallerThreshold Statements per call site that flag it as excessive, or `null` to
     * disable the check.
     * @param list<string> $skipUrls Same-origin URLs excluded from AJAX tracking.
     * @param string $position Edge the toolbar docks to.
     * @param int $height Collapsed toolbar height, in pixels.
     */
    public function __construct(
        string $routePrefix = '/debug',
        public int $historySize = 50,
        public int|null $excessiveCallerThreshold = null,
        public array $skipUrls = [],
        public string $position = 'bottom',
        public int $height = 50,
    ) {
        $this->routePrefix = rtrim($routePrefix, '/');
    }

    /**
     * Creates the options from the `yii3/debug` parameter block.
     *
     * A key the block omits keeps its default; a key it declares must hold a value of the expected type, because the
     * parameters are merged from several packages and a wrong type would otherwise surface far from its source.
     *
     * @param array<array-key, mixed> $params The `yii3/debug` parameter block.
     *
     * @throws InvalidArgumentException when a declared key holds a value of the wrong type.
     *
     * @return self Options carrying the declared values.
     */
    public static function fromParams(array $params): self
    {
        $database = self::section($params, 'database');
        $toolbar = self::section($params, 'toolbar');

        $routePrefix = self::value($params, 'routePrefix', '/debug');
        $historySize = self::value($params, 'historySize', 50);
        $excessiveCallerThreshold = self::value($database, 'excessiveCallerThreshold', null);
        $skipUrls = self::value($toolbar, 'skipUrls', []);
        $position = self::value($toolbar, 'position', 'bottom');
        $height = self::value($toolbar, 'height', 50);

        if (is_string($routePrefix) === false) {
            throw self::invalid('routePrefix', 'a string');
        }

        if (is_int($historySize) === false) {
            throw self::invalid('historySize', 'an integer');
        }

        if ($excessiveCallerThreshold !== null && is_int($excessiveCallerThreshold) === false) {
            throw self::invalid('database.excessiveCallerThreshold', 'an integer or null');
        }

        if (self::isStringList($skipUrls) === false) {
            throw self::invalid('toolbar.skipUrls', 'a list of strings');
        }

        if (is_string($position) === false) {
            throw self::invalid('toolbar.position', 'a string');
        }

        if (is_int($height) === false) {
            throw self::invalid('toolbar.height', 'an integer');
        }

        return new self($routePrefix, $historySize, $excessiveCallerThreshold, $skipUrls, $position, $height);
    }

    /**
     * Returns whether a request path targets the debugger itself.
     *
     * The prefix must match a whole path segment, so `/debugger` stays an application path under the `/debug` prefix.
     *
     * @param string $path Request path, without the query string.
     *
     * @return bool `true` when the path is the prefix or lies under it; `false` otherwise.
     */
    public function isDebugPath(string $path): bool
    {
        return $path === $this->routePrefix || str_starts_with($path, $this->routePrefix . '/');
    }

    /**
     * Builds the failure raised for a parameter holding a value of the wrong type.
     *
     * @param string $key Parameter path below `yii3/debug`.
     * @param string $expected Type the parameter must hold, as a noun phrase.
     *
     * @return InvalidArgumentException failure naming the parameter and the expected type.
     */
    private static function invalid(string $key, string $expected): InvalidArgumentException
    {
        return new InvalidArgumentException(Message::TOOLBAR_OPTION_INVALID->getMessage($key, $expected));
    }

    /**
     * Returns whether a value is a list holding only strings.
     *
     * @param mixed $value Value to check.
     *
     * @return bool `true` when the value is a list of strings; `false` otherwise.
     *
     * @phpstan-assert-if-true list<string> $value
     */
    private static function isStringList(mixed $value): bool
    {
        if (is_array($value) === false || array_is_list($value) === false) {
            return false;
        }

        foreach ($value as $item) {
            if (is_string($item) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns a nested parameter block, or an empty block when the parameters omit it.
     *
     * @param array<array-key, mixed> $params The `yii3/debug` parameter block.
     * @param string $key Key of the nested block.
     *
     * @throws InvalidArgumentException when the key holds something other than an array.
     *
     * @return array<array-key, mixed> Nested block.
     */
    private static function section(array $params, string $key): array
    {
        $section = self::value($params, $key, []);

        if (is_array($section) === false) {
            throw self::invalid($key, 'an array');
        }

        return $section;
    }

    /**
     * Returns a declared parameter, or the default when the block omits the key.
     *
     * An explicit `null` counts as declared, so it is validated instead of silently replaced by the default.
     *
     * @param array<array-key, mixed> $params Parameter block.
     * @param string $key Parameter key.
     * @param mixed $default Value used when the key is absent.
     *
     * @return mixed Declared value, or the default.
     */
    private static function value(array $params, string $key, mixed $default): mixed
    {
        return array_key_exists($key, $params) ? $params[$key] : $default;
    }
}
