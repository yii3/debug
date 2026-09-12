<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\{Panel, PanelView};
use PHPForge\Debug\Toolbar\ToolbarItem;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii3\Debug\Panel\ProviderPanel;

/**
 * Unit tests for {@see ProviderPanel} adapting a provider-owned declarative panel to the Yii3 debugger.
 */
final class ProviderPanelTest extends TestCase
{
    public function testCompletePayloadAndToolbarArePreserved(): void
    {
        $data = ['hits' => 1, 'misses' => 2];

        $provider = new class extends Panel {
            protected const string ICON = 'inertia';
            protected const string ID = 'custom';
            protected const string TITLE = 'Custom';

            public int $calls = 0;

            /**
             * @var array<string, mixed>
             */
            public array $received = [];

            public function present(array $data): PanelView
            {
                ++$this->calls;
                $this->received = $data;

                return PanelView::create()
                    ->toolbar('Hits', 1)
                    ->toolbar('Misses', 2);
            }
        };

        $panel = new ProviderPanel($provider);

        self::assertEquals(
            [
                ToolbarItem::create('1')->withTitle('Hits'),
                ToolbarItem::create('2')->withTitle('Misses'),
            ],
            $panel->toolbarItems($data),
            'Every captured field and toolbar metric must be preserved.',
        );
        self::assertSame(
            1,
            $provider->calls,
            'The provider must be asked exactly once.',
        );
        self::assertSame(
            $data,
            $provider->received,
            'The complete payload must reach the provider.'
        );
    }

    public function testExternalPanelNeedsNoFrameworkSpecificPresentation(): void
    {
        $panel = new ProviderPanel($this->provider());

        self::assertSame(
            'custom',
            $panel->id(),
            'Provider ID must be preserved.'
        );
        self::assertSame(
            'Custom',
            $panel->name(),
            'Provider title must be preserved.'
        );
        self::assertSame(
            'inertia',
            $panel->icon(),
            'Provider icon must be preserved.'
        );
        self::assertFalse(
            $panel->hasContent([]),
            'Empty navigation state must be preserved.'
        );
        self::assertTrue(
            $panel->hasContent(['hits' => 1]),
            'Captured activity must be exposed.'
        );
        self::assertStringContainsString(
            'yii-debug-table',
            $panel->render(['hits' => 1]),
            'External panels must use the shared frontend.'
        );

        $items = $panel->toolbarItems(['hits' => 1]);

        self::assertArrayHasKey(
            0,
            $items,
            'A captured metric must create a toolbar item.'
        );
        self::assertSame(
            '1',
            $items[0]->jsonSerialize()['value'],
            'Provider metrics must reach the toolbar.'
        );
    }

    public function testHydrationHookRemainsAvailableToCompatibilityFacades(): void
    {
        $panel = new class ($this->provider()) extends ProviderPanel {
            /** @param array<string, mixed> $payload
             * @return array<string, mixed> */
            public function decode(array $payload): array
            {
                return $this->data($payload);
            }
        };

        self::assertSame(
            ['first' => 1, 'second' => 2],
            $panel->decode(['first' => 1, 'second' => 2]),
            'Compatibility facades must retain access to complete decoded data.'
        );
    }

    public function testThrowRuntimeExceptionWhenPreparedPresentationFailed(): void
    {
        $provider = new class extends Panel {
            protected const string ICON = 'inertia';
            protected const string ID = 'custom';
            protected const string TITLE = 'Custom';

            public function present(array $data): PanelView
            {
                throw new RuntimeException(
                    'Presentation is unavailable.',
                );
            }
        };

        $panel = (new ProviderPanel($provider))->forPayload(['hits' => 1]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Presentation is unavailable.',
        );

        $panel->hasContent(['hits' => 1]);
    }

    private function provider(): Panel
    {
        return new class extends Panel {
            protected const string ICON = 'inertia';
            protected const string ID = 'custom';
            protected const string TITLE = 'Custom';

            public function present(array $data): PanelView
            {
                return PanelView::create()
                    ->summary('', 1)
                    ->overview(['Driver' => 'local'])
                    ->toolbar('Hits', 1)
                    ->active($data !== []);
            }
        };
    }
}
