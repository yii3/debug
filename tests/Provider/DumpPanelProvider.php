<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Provider;

/**
 * Sorting and filtering cases for {@see \Yii3\Debug\Tests\Panel\DumpPanelTest}.
 */
final class DumpPanelProvider
{
    /**
     * @return iterable<string, array{array<array-key, mixed>, list<string>}>
     */
    public static function views(): iterable
    {
        yield 'capture time ascending by default' => [[], ['beta', 'gamma', 'alpha']];
        yield 'capture time descending' => [['sort' => '-time'], ['alpha', 'gamma', 'beta']];
        yield 'category ascending' => [['sort' => 'category'], ['gamma', 'alpha', 'beta']];
        yield 'category filter' => [['Log' => ['category' => 'app.c2']], ['alpha']];
        yield 'message ascending' => [['sort' => 'message'], ['alpha', 'beta', 'gamma']];
        yield 'message filter' => [['Log' => ['message' => 'AMM']], ['gamma']];
        yield 'second page' => [['per-page' => '1', 'page' => '2'], ['gamma']];
    }
}
