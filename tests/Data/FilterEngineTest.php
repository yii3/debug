<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Data;

use PHPForge\Debug\Data\FilterEngine as CoreFilterEngine;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Yii3\Debug\Data\FilterEngine;

/**
 * Unit tests for {@see FilterEngine} covering the exact/partial/numeric operator grammar shared by every debug-panel
 * search model.
 *
 * @since 0.1
 */
#[Group('data')]
#[Group('filter')]
final class FilterEngineTest extends TestCase
{
    public function testAddConditionIgnoresEmptyAndNonScalarValues(): void
    {
        $engine = new FilterEngine();

        $engine->addCondition('level', '');
        $engine->addCondition('level', null);
        $engine->addCondition('level', ['error']);

        $rows = [['level' => 'info'], ['level' => 'error']];

        self::assertSame($engine->filter($rows), $rows, 'Empty and non-scalar values must register no condition.');
    }

    public function testAddMinimumConditionKeepsRowsAtOrAboveTheBound(): void
    {
        $engine = new FilterEngine();

        $engine->addMinimumCondition('duration', 0.5);

        self::assertSame(
            [['duration' => 0.5], ['duration' => 2]],
            $engine->filter([['duration' => 0.1], ['duration' => 0.5], ['duration' => 2]]),
            'Inclusive lower bound must keep the boundary row.',
        );
    }

    public function testFilterComparesNonScalarCandidatesThroughDumpExport(): void
    {
        $engine = new FilterEngine();

        $engine->addCondition('payload', 'alpha', partial: true);

        self::assertCount(
            1,
            $engine->filter([['payload' => ['alpha' => 1]], ['payload' => ['beta' => 2]]]),
            'Array candidates must match through their exported representation.',
        );
    }

    public function testFilterDropsRowsMissingTheAttribute(): void
    {
        $engine = new FilterEngine();

        $engine->addCondition('level', 'error');

        self::assertSame(
            [],
            $engine->filter([['category' => 'app']]),
            'Rows without the filtered attribute must be dropped.',
        );
    }

    public function testFilterMatchesCaseInsensitiveSubstringWhenPartial(): void
    {
        $engine = new FilterEngine();

        $engine->addCondition('message', 'SESSION', partial: true);

        self::assertSame(
            [['message' => 'Session started']],
            $engine->filter([['message' => 'Session started'], ['message' => 'Connection opened']]),
            'Partial conditions must match case-insensitive substrings.',
        );
    }

    public function testFilterMatchesCaseInsensitiveWholeValueByDefault(): void
    {
        $engine = new FilterEngine();

        $engine->addCondition('level', 'ERROR');

        self::assertSame(
            [['level' => 'error']],
            $engine->filter([['level' => 'error'], ['level' => 'error-handler']]),
            'Default conditions must match the whole value case-insensitively.',
        );
    }

    public function testFilterParsesLeadingComparisonOperators(): void
    {
        $engine = new FilterEngine();

        $engine->addCondition('sqlCount', '> 5');

        self::assertSame(
            [['sqlCount' => 9]],
            $engine->filter([['sqlCount' => 3], ['sqlCount' => 9]]),
            'A leading `>` must compare numerically.',
        );

        $engine->addCondition('duration', '<0.5');

        self::assertSame(
            [['duration' => '0.25']],
            $engine->filter([['duration' => '0.25'], ['duration' => '0.75']]),
            'A leading `<` must compare numeric strings numerically.',
        );
    }

    public function testFilterReadsPublicPropertiesFromObjectRows(): void
    {
        $engine = new FilterEngine();

        $engine->addCondition('method', 'get');

        $match = new class {
            public string $method = 'GET';
        };
        $miss = new class {
            public string $method = 'POST';
        };

        self::assertSame(
            [$match],
            $engine->filter([$match, $miss]),
            'Object rows must be matched through their public properties.',
        );
    }

    public function testFilterRejectsMalformedInternalConditions(): void
    {
        $engine = new FilterEngine();
        $core = new CoreFilterEngine();

        $property = new ReflectionProperty($core, 'conditions');
        $facadeProperty = new ReflectionProperty($engine, 'engine');

        $property->setValue($core, [['attribute' => 'value', 'operator' => '>', 'value' => '5']]);
        $facadeProperty->setValue($engine, $core);

        self::assertSame(
            [],
            $engine->filter([['value' => 6]]),
            'Numeric conditions with a non-float boundary must reject the row.',
        );

        $property->setValue($core, [['attribute' => 'value', 'operator' => 'same', 'value' => 5.0]]);

        self::assertSame(
            [],
            $engine->filter([['value' => '5']]),
            'Text conditions with a non-string boundary must reject the row.',
        );
    }

    public function testFilterRejectsNonNumericCandidatesForNumericOperators(): void
    {
        $engine = new FilterEngine();

        $engine->addCondition('sqlCount', '>5');

        self::assertSame(
            [],
            $engine->filter([['sqlCount' => 'many'], ['sqlCount' => null]]),
            'Numeric operators must drop rows whose candidate is not numeric.',
        );
    }

    public function testFilterResetsConditionsAfterEachRun(): void
    {
        $engine = new FilterEngine();

        $engine->addCondition('level', 'error');

        $engine->filter([['level' => 'info']]);

        $rows = [['level' => 'info']];

        self::assertSame($rows, $engine->filter($rows), 'Conditions must reset after each filter run.');
    }
}
