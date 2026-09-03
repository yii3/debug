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
}
