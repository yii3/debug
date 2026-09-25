<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support;

use function array_diff;
use function bin2hex;
use function chmod;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function unlink;

use const DIRECTORY_SEPARATOR;

/**
 * Creates and removes isolated directories under the system temporary directory.
 */
final class TemporaryDirectory
{
    public static function create(string $prefix): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(8));

        mkdir($path, 0o700, true);

        return $path;
    }

    public static function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        chmod($path, 0o700);

        $entries = scandir($path);

        foreach (array_diff($entries === false ? [] : $entries, ['.', '..']) as $entry) {
            $entryPath = $path . DIRECTORY_SEPARATOR . $entry;

            is_dir($entryPath) ? self::remove($entryPath) : unlink($entryPath);
        }

        rmdir($path);
    }
}
