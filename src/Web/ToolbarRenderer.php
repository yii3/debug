<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use Yii3\Debug\ToolbarAsset;
use Yiisoft\Assets\AssetManager;
use Yiisoft\View\WebView;

use function htmlspecialchars;
use function strripos;
use function substr_replace;
use function trim;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Renders shared toolbar views and resolves their assets through Yii3.
 */
final readonly class ToolbarRenderer
{
    /**
     * @param WebView $view Yii3 PHP view renderer.
     * @param AssetManager $assetManager Yii3 asset publisher.
     * @param string $viewPath Resolved path containing the shared debugger templates.
     */
    public function __construct(
        private WebView $view,
        private AssetManager $assetManager,
        private string $viewPath,
    ) {}

    /**
     * Injects toolbar markup immediately before the final closing body tag.
     *
     * Usage example:
     *
     * ```php
     * $html = $renderer->inject($html, $toolbar);
     * ```
     *
     * @param string $html Response HTML.
     * @param string $toolbar Rendered toolbar markup.
     *
     * @return string HTML containing the toolbar, appended when no closing body tag exists.
     */
    public function inject(string $html, string $toolbar): string
    {
        $offset = strripos($html, '</body>');

        return $offset === false
            ? $html . $toolbar
            : substr_replace($html, $toolbar, $offset, 0);
    }

    /**
     * Renders the shared toolbar element and the Yii3-published runtime.
     *
     * Usage example:
     *
     * ```php
     * $html = $renderer->render('/debug/toolbar?tag=request-1');
     * ```
     *
     * @param string $dataUrl Toolbar payload URL.
     * @param list<string> $skipUrls Same-origin URLs excluded from AJAX tracking.
     * @param string $position Initial toolbar position.
     * @param int $height Initial drawer height percentage.
     *
     * @return string Toolbar element followed by its published runtime tag.
     */
    public function render(
        string $dataUrl,
        array $skipUrls = [],
        string $position = 'bottom',
        int $height = 50,
    ): string {
        return $this->renderElement($dataUrl, $skipUrls, $position, $height) . $this->scriptTag();
    }

    /**
     * Renders the framework-neutral toolbar template through the Yii3 view component.
     *
     * Usage example:
     *
     * ```php
     * $element = $renderer->renderElement('/debug/toolbar?tag=request-1');
     * ```
     *
     * @param string $dataUrl Toolbar payload URL.
     * @param list<string> $skipUrls Same-origin URLs excluded from AJAX tracking.
     * @param string $position Initial toolbar position.
     * @param int $height Initial drawer height percentage.
     *
     * @return string Rendered toolbar custom element.
     */
    public function renderElement(
        string $dataUrl,
        array $skipUrls = [],
        string $position = 'bottom',
        int $height = 50,
    ): string {
        return trim(
            $this->view->withClearedState()->render(
                $this->viewPath . '/toolbar.php',
                [
                    'dataUrl' => $dataUrl,
                    'height' => $height,
                    'position' => $position,
                    'skipUrls' => $skipUrls,
                ],
            ),
        );
    }

    /**
     * Renders a script tag for the Yii3-published toolbar runtime.
     *
     * Usage example:
     *
     * ```php
     * $script = $renderer->scriptTag();
     * ```
     *
     * @return string Toolbar runtime script tag.
     */
    public function scriptTag(): string
    {
        $url = $this->assetManager->getUrl(ToolbarAsset::class, 'dist/js/toolbar.min.js');

        return '<script type="module" src="' . self::escape($url) . '"></script>';
    }

    /**
     * Escapes an HTML attribute value.
     *
     * @param string $value Raw attribute value.
     *
     * @return string Escaped attribute value.
     */
    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
