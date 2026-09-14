<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

/**
 * Defines a panel-specific toolbar title without changing its sidebar name.
 */
interface ToolbarTitleProviderInterface
{
    /**
     * Returns the toolbar title. An empty string keeps the icon while hiding the text label.
     *
     * @return string Toolbar title, or `''` to show the icon alone.
     */
    public function toolbarTitle(): string;
}
