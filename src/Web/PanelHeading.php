<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use PHPForge\Debug\Panel\PanelTitle;
use UIAwesome\Html\Heading\H1;

/**
 * Renders the visually hidden page heading that names a panel for assistive technology.
 */
final class PanelHeading
{
    /**
     * Returns the screen-reader-only heading carrying the panel title.
     *
     * @param PanelTitle $title Title announced as the page heading.
     */
    public static function render(PanelTitle $title): string
    {
        return H1::tag()
            ->class('yii-debug-sr-only')
            ->content($title)
            ->render();
    }
}
