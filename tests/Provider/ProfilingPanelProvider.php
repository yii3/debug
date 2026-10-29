<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Provider;

/**
 * Data provider for {@see \Yii3\Debug\Tests\Panel\ProfilingPanelTest} test cases.
 */
final class ProfilingPanelProvider
{
    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function sortAttributeProvider(): iterable
    {
        yield 'sequence' => [
            'seq',
            'SLOW application',
            ['Yii3\\Application::handle', 'Yiisoft\\Db\\Command::query', 'Yii3\\View::render'],
        ];
        yield 'category' => [
            'category',
            'SLOW application',
            ['Yii3\\Application::handle', 'Yii3\\View::render', 'Yiisoft\\Db\\Command::query'],
        ];
        yield 'info' => [
            'info',
            'SLOW application',
            ['Yii3\\View::render', 'Yiisoft\\Db\\Command::query', 'Yii3\\Application::handle'],
        ];
        yield 'info tie uses sequence' => [
            'info',
            'MIDDLE view',
            ['Yii3\\Application::handle', 'Yii3\\View::render', 'Yiisoft\\Db\\Command::query'],
        ];
    }

    /**
     * @return iterable<string, array{float, string}>
     */
    public static function timelineRulers(): iterable
    {
        yield '100 ms uses a 50 ms step' => [
            0.1,
            <<<HTML
            <header class="yii-debug-tl-axis">
            <span class="yii-debug-tl-tick" style='left: 0%;'>0 ms</span><span class="yii-debug-tl-tick" style='left: 50%;'>50 ms</span>
            </header>
            HTML,
        ];
        yield '80 ms uses a 20 ms step' => [
            0.08,
            <<<HTML
            <header class="yii-debug-tl-axis">
            <span class="yii-debug-tl-tick" style='left: 0%;'>0 ms</span><span class="yii-debug-tl-tick" style='left: 25%;'>20 ms</span><span class="yii-debug-tl-tick" style='left: 50%;'>40 ms</span><span class="yii-debug-tl-tick" style='left: 75%;'>60 ms</span>
            </header>
            HTML,
        ];
    }
}
