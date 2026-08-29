<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\Inertia\InertiaSnapshot;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\InertiaPanel;

/**
 * Unit tests for the stateless Inertia extension-panel presentation.
 */
final class InertiaPanelTest extends TestCase
{
    private InertiaPanel $panel;

    public function testCapturedPageHeadersPropsAndRawPayloadAreEscaped(): void
    {
        $html = $this->panel->render(
            $this->payload(
                [
                    'component' => '<script>alert("component")</script>',
                    'props' => [
                        '<img src=x onerror=alert(1)>' => '</textarea><script>alert("value")</script>',
                    ],
                    'url' => '/?<svg onload=alert(1)>',
                    'version' => '<b>v1</b>',
                ],
                [
                    'X-Inertia' => 'true',
                    'X-Inertia-Partial-Data' => '<iframe src=javascript:alert(1)>',
                ],
            ),
        );

        self::assertStringNotContainsString(
            '<script>',
            $html,
            'Captured page data must not introduce script tags.',
        );
        self::assertStringNotContainsString(
            '<img ',
            $html,
            'Captured prop names must not introduce image tags.',
        );
        self::assertStringNotContainsString(
            '<svg ',
            $html,
            'Captured URLs must not introduce SVG tags.',
        );
        self::assertStringNotContainsString(
            '<iframe ',
            $html,
            'Captured headers must not introduce iframe tags.',
        );
        self::assertStringContainsString(
            '&lt;script&gt;alert("component")&lt;/script&gt;',
            $html,
            'Encoded component content must remain inspectable.',
        );
        self::assertStringContainsString(
            '&lt;img src=x onerror=alert(1)&gt;',
            $html,
            'Encoded prop names must remain inspectable.',
        );
        self::assertStringContainsString(
            '&lt;iframe src=javascript:alert(1)&gt;',
            $html,
            'Encoded negotiation-header content must remain inspectable.',
        );
    }

    public function testContractMetadataAndPerCaptureVisibility(): void
    {
        self::assertSame(
            'inertia',
            $this->panel->id(),
            'Panel ID must match the persisted payload key.',
        );
        self::assertSame(
            'Inertia',
            $this->panel->name(),
            'Panel name must match the sidebar label.',
        );
        self::assertSame(
            'inertia',
            $this->panel->icon(),
            'Panel icon must use the shared Inertia glyph.',
        );
        self::assertFalse(
            $this->panel->hasContent($this->payload(null)),
            'A plain capture without a page or Inertia request header must stay out of the sidebar.',
        );
        self::assertTrue(
            $this->panel->hasContent(
                $this->payload(['component' => 'Site/Index', 'props' => [], 'url' => '/', 'version' => 'v1']),
            ),
            'A captured page must expose the panel.',
        );
        self::assertTrue(
            $this->panel->hasContent($this->payload(null, ['X-Inertia' => 'true'])),
            'An Inertia XHR without a page must still expose the panel.',
        );
    }

    public function testFullPageLoadRendersSummaryInformationPropsAndRawPayload(): void
    {
        $html = $this->panel->render(
            $this->payload(
                [
                    'component' => 'Site/Index',
                    'props' => ['user' => ['id' => 1]],
                    'url' => '/dashboard',
                    'version' => 42,
                ],
                statusCode: 201,
            ),
        );

        self::assertStringContainsString(
            'Site/Index',
            $html,
            'Component name must surface in the detail.',
        );
        self::assertStringContainsString(
            'Full page load',
            $html,
            'A non-XHR page must be classified as a full load.',
        );
        self::assertMatchesRegularExpression(
            '/<strong>\s*1\s*<\/strong>\s*prop/s',
            $html,
            'Summary must expose the captured prop count.',
        );
        self::assertStringContainsString('/dashboard', $html, 'Page URL must surface in the information table.');
        self::assertMatchesRegularExpression(
            '/>\s*42\s*</s',
            $html,
            'Scalar asset versions must surface in the information table.',
        );
        self::assertMatchesRegularExpression(
            '/>\s*201\s*</s',
            $html,
            'Response status must surface in the information table.',
        );
        self::assertMatchesRegularExpression(
            '/<h2>\s*Props/s',
            $html,
            'Props section heading must be present.',
        );
        self::assertMatchesRegularExpression(
            '/>\s*user\s*<\/strong>/s',
            $html,
            'Prop keys must surface in the table.',
        );
        self::assertStringContainsString(
            'Raw payload',
            $html,
            'The complete page must remain inspectable.',
        );
        self::assertStringContainsString(
            '"user"',
            $html,
            'Raw payload must contain the serialized prop key.',
        );
    }

    public function testMissingPageAndEmptyPropsRenderDistinctEmptyStates(): void
    {
        $missingPage = $this->panel->render($this->payload(null, ['X-Inertia' => 'true']));
        $emptyProps = $this->panel->render(
            $this->payload(['component' => 'Site/Index', 'props' => [], 'url' => '/', 'version' => 'v1']),
        );

        self::assertStringContainsString(
            'yii-debug-empty-state',
            $missingPage,
            'An Inertia request without a page must render the shared empty-state card.',
        );
        self::assertStringContainsString(
            'No Inertia page in this request',
            $missingPage,
            'The no-page state must explain what is absent.',
        );
        self::assertStringContainsString(
            'The page rendered without props.',
            $emptyProps,
            'A valid page with no props must render its narrower empty message.',
        );
        self::assertStringContainsString(
            'Raw payload',
            $emptyProps,
            'An empty props collection must not hide the rest of the captured page.',
        );
    }

    public function testPartialReloadRendersVisitAndNegotiationHeaders(): void
    {
        $html = $this->panel->render(
            $this->payload(
                [
                    'component' => 'Users/Index',
                    'props' => ['users' => [['id' => 7]]],
                    'url' => '/users',
                    'version' => 'v2',
                ],
                [
                    'X-Inertia' => 'true',
                    'X-Inertia-Partial-Component' => 'Users/Index',
                    'X-Inertia-Partial-Data' => 'users',
                    'X-Inertia-Version' => 'v2',
                ],
            ),
        );

        self::assertStringContainsString(
            'Partial reload',
            $html,
            'Partial headers must classify the Inertia visit.',
        );
        self::assertStringContainsString(
            'X-Inertia-Partial-Component',
            $html,
            'The targeted component header must surface in the information table.',
        );
        self::assertStringContainsString(
            'X-Inertia-Partial-Data',
            $html,
            'The selected-props header must surface in the information table.',
        );
        self::assertStringContainsString(
            'X-Inertia-Version',
            $html,
            'The client asset version header must surface in the information table.',
        );
        self::assertMatchesRegularExpression(
            '/<th[^>]*style=[\'\"][^\'\"]*white-space:\s*nowrap[^\'\"]*[\'\"][^>]*>'
            . '\s*X-Inertia-Partial-Component\s*<\/th>/s',
            $html,
            'Long Inertia metadata labels must remain on one row.',
        );
    }

    public function testPropsExposeSharedAndPageOriginsAndScalarTypes(): void
    {
        $html = $this->panel->render(
            $this->payload(
                [
                    'component' => 'Site/Index',
                    'props' => [
                        'auth' => ['isGuest' => true],
                        'title' => 'Welcome',
                        'count' => 7,
                        'ratio' => 1.5,
                        'enabled' => true,
                        'missing' => null,
                    ],
                    'url' => '/',
                    'version' => 'v1',
                ],
                sharedKeys: ['auth'],
            ),
        );

        self::assertMatchesRegularExpression(
            '/>\s*auth\s*<.*yii-debug-badge-info[^>]*>shared<.*>\s*array\(1\)\s*</s',
            $html,
            'Configured shared props must carry the shared badge and array cardinality.',
        );
        self::assertMatchesRegularExpression(
            '/>\s*title\s*<.*yii-debug-badge-muted[^>]*>page<.*>\s*string\(7\)\s*</s',
            $html,
            'Page-specific props must carry the page badge and string length.',
        );
        self::assertMatchesRegularExpression(
            '/>\s*count\s*<.*>\s*int\s*</s',
            $html,
            'Integer props must expose their type.',
        );
        self::assertMatchesRegularExpression(
            '/>\s*ratio\s*<.*>\s*float\s*</s',
            $html,
            'Float props must expose their type.',
        );
        self::assertMatchesRegularExpression(
            '/>\s*enabled\s*<.*>\s*bool\s*</s',
            $html,
            'Boolean props must expose their type.',
        );
        self::assertMatchesRegularExpression(
            '/>\s*missing\s*<.*>\s*null\s*</s',
            $html,
            'Null props must expose their type.',
        );
    }

    public function testToolbarItemsExposeTheCapturedComponent(): void
    {
        $items = $this->panel->toolbarItems(
            $this->payload(
                [
                    'component' => 'Site/Index',
                    'props' => [],
                    'url' => '/',
                    'version' => 'v1',
                ],
            ),
        );

        self::assertCount(
            1,
            $items,
            'A captured component must expose one toolbar metric.',
        );
        self::assertSame(
            [
                'value' => 'Site/Index',
                'status' => 'default',
                'title' => 'Inertia component',
            ],
            $items[0]->jsonSerialize(),
            'The toolbar metric must match the Yii2 Inertia component chip.',
        );
    }

    public function testToolbarItemsStayEmptyWithoutACapturedComponent(): void
    {
        foreach (
            [
                $this->payload(null, ['X-Inertia' => 'true']),
                $this->payload(['component' => '', 'props' => [], 'url' => '/', 'version' => 'v1']),
                $this->payload(['props' => [], 'url' => '/', 'version' => 'v1']),
            ] as $payload
        ) {
            self::assertSame(
                [],
                $this->panel->toolbarItems($payload),
                'A capture without a component must stay out of the toolbar.',
            );
        }
    }

    public function testVersionConflictRendersReloadExplanationAndEscapedLocation(): void
    {
        $html = $this->panel->render(
            $this->payload(
                null,
                ['X-Inertia' => 'true', 'X-Inertia-Version' => 'stale'],
                statusCode: 409,
                location: 'https://example.test/<script>alert(1)</script>',
            ),
        );

        self::assertStringContainsString(
            'Version conflict interrupted this visit',
            $html,
            'A 409 response without a page must explain the interrupted visit.',
        );
        self::assertStringContainsString(
            'Reload target:',
            $html,
            'The version-conflict state must label the browser reload target.',
        );
        self::assertStringContainsString(
            'https://example.test/&lt;script&gt;alert(1)&lt;/script&gt;',
            $html,
            'The reload target must remain visible and HTML-escaped.',
        );
        self::assertStringNotContainsString('<script>', $html, 'Captured locations must not introduce markup.');
    }

    protected function setUp(): void
    {
        $this->panel = new InertiaPanel();
    }

    /**
     * @param array<array-key, mixed>|null $page
     * @param array<array-key, mixed> $requestHeaders
     * @param array<array-key, mixed> $sharedKeys
     *
     * @return array<string, mixed>
     */
    private function payload(
        array|null $page,
        array $requestHeaders = [],
        array $sharedKeys = [],
        int $statusCode = 200,
        string|null $location = null,
    ): array {
        return InertiaSnapshot::capture(
            $location,
            $page,
            $requestHeaders,
            $sharedKeys,
            $statusCode,
        )->jsonSerialize();
    }
}
