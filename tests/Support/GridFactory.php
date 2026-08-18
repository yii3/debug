<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support;

use Yii3\Debug\Panel\PanelGrid;
use Yiisoft\Di\{Container, ContainerConfig};
use Yiisoft\Validator\{Validator, ValidatorInterface};

/**
 * Provides a data-view grid renderer backed by a minimal container for panel tests.
 */
final class GridFactory
{
    /**
     * Creates a grid renderer whose container resolves the data-view column renderers.
     *
     * @return PanelGrid Ready-to-render grid helper.
     */
    public static function panelGrid(): PanelGrid
    {
        $container = new Container(
            ContainerConfig::create()->withDefinitions([ValidatorInterface::class => Validator::class]),
        );

        return new PanelGrid($container);
    }
}
