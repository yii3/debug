<?php

declare(strict_types=1);

namespace Yii3\Debug\Asset;

use Yii3\Debug\DebugAsset;
use Yiisoft\Aliases\Aliases;

use function file_get_contents;
use function is_file;
use function preg_match;

/**
 * Resolves SVG icons through the Yii3 vendor alias.
 */
final readonly class Icon
{
    public function __construct(private Aliases $aliases) {}

    /**
     * Returns trusted markup for one packaged SVG icon.
     *
     * Usage example:
     *
     * ```php
     * $svg = $icon->render('request');
     * ```
     *
     * @param string $name Icon basename without the `.svg` extension.
     *
     * @return string SVG markup or an empty string when unavailable.
     */
    public function render(string $name): string
    {
        if (preg_match('/\A[a-z0-9][a-z0-9-]*\z/', $name) !== 1) {
            return '';
        }

        $path = $this->aliases->get(DebugAsset::SOURCE_PATH . '/svg/' . $name . '.svg');

        if (!is_file($path)) {
            return '';
        }

        $svg = file_get_contents($path);

        return $svg === false ? '' : $svg;
    }
}
