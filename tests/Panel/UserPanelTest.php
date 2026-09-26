<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\User\{UserMessage, UserSnapshot};
use PHPForge\Debug\Toolbar\ToolbarItem;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\UserPanel;
use Yii3\Debug\Tests\Support\HelperFactory;

use function array_map;
use function dirname;
use function file_get_contents;
use function json_decode;
use function rtrim;

use const JSON_THROW_ON_ERROR;

/**
 * Unit tests for {@see UserPanel} guest listing, the Yii2 toolbar metric, and rendering of a capture written by the
 * Yii2 host.
 */
final class UserPanelTest extends TestCase
{
    public function testGuestCaptureStaysListedWithTheSharedEmptyState(): void
    {
        $panel = new UserPanel();

        $payload = self::payload(null);

        self::assertTrue(
            $panel->hasContent($payload),
            'Guest capture must stay listed, as in Yii2.',
        );
        self::assertStringContainsString(
            UserMessage::EMPTY_HEADLINE->value,
            $panel->render(HelperFactory::createPanelRenderInput($payload)),
            'Guest detail must show the shared empty state.',
        );
    }

    public function testRenderMatchesTheYii2DetailForAYii2Capture(): void
    {
        self::assertSame(
            rtrim((string) file_get_contents(dirname(__DIR__) . '/Support/Fixture/yii2-user-detail.html'), "\n"),
            (new UserPanel())->render(HelperFactory::createPanelRenderInput(self::yii2Capture()['panels']['user'])),
            'Detail must be byte-identical to the Yii2 panel.',
        );
    }

    public function testToolbarItemsNameTheUserIdOrGuest(): void
    {
        $panel = new UserPanel();

        self::assertSame(
            [['value' => 'Guest', 'status' => 'default']],
            self::items($panel, self::payload(null)),
            'Guest capture must read `Guest`.',
        );
        self::assertSame(
            [['value' => '7', 'status' => 'info']],
            self::items($panel, self::payload('7')),
            'Signed-in capture must name the ID.',
        );
        self::assertSame(
            [['value' => '1', 'status' => 'info']],
            self::items($panel, self::yii2Capture()['panels']['user']),
            'Integer ID from a Yii2 capture must be named too.',
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array<string, mixed>>
     */
    private static function items(UserPanel $panel, array $payload): array
    {
        return array_map(
            static fn(ToolbarItem $item): array => $item->jsonSerialize(),
            $panel->toolbarItems($payload),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(string|null $id): array
    {
        return UserSnapshot::capture(
            [
                'id' => $id,
                'identity' => $id === null ? null : ['id' => "'{$id}'", 'username' => "'admin'"],
                'attributes' => null,
                'roles' => null,
                'permissions' => null,
            ],
        )->jsonSerialize();
    }

    /**
     * @return array{summary: array<string, mixed>, panels: array{user: array<string, mixed>}}
     */
    private static function yii2Capture(): array
    {
        /** @var array{summary: array<string, mixed>, panels: array{user: array<string, mixed>}} */
        return json_decode(
            (string) file_get_contents(dirname(__DIR__) . '/Support/Fixture/yii2-user-capture.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
