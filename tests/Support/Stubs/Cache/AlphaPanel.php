<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs\Cache;

use PHPForge\Debug\{Panel, PanelView};

/**
 * Represents a portable panel whose only purpose is pinning the registered extension order.
 */
final class AlphaPanel extends Panel
{
    protected const string ICON = 'db';
    protected const string ID = 'alpha';
    protected const string TITLE = 'Alpha';

    public function present(array $data): PanelView
    {
        return PanelView::create()->toolbar('Alpha', 1);
    }
}
