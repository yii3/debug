<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Panel\Mail\{MailMessage, MailSnapshot};
use PHPForge\Debug\Storage\{RequestSummary, SnapshotStore};
use PHPForge\Debug\Toolbar\ToolbarItem;
use Yii3\Debug\Middleware\ToolbarOptions;
use Yii3\Debug\Web\DebugUrlGenerator;

use function count;
use function is_string;
use function parse_url;
use function sprintf;

use const PHP_URL_PATH;

/**
 * Adapts the framework-neutral PHPForge Mail panel to the Yii3 debugger.
 *
 * The detail view, the sidebar entry, and the toolbar metric match the Yii2 Mail panel: a capture that sent mail counts
 * its own messages, and a capture that sent none but directly follows one that did, the redirect after a form post,
 * links the metric to that request.
 */
final class MailPanel extends ProviderPanel implements CaptureToolbarProviderInterface
{
    /**
     * Binds the adapter to the shared Mail presentation.
     *
     * @param SnapshotStore $store Store whose manifest locates the request that preceded a capture.
     * @param ToolbarOptions $options Settings carrying the route prefix the cross-request link is built on.
     */
    public function __construct(
        private readonly SnapshotStore $store,
        private readonly ToolbarOptions $options = new ToolbarOptions(),
    ) {
        parent::__construct(new \PHPForge\Debug\Panel\Mail\MailPanel());
    }

    /**
     * Reports whether the Mail collector captured the request.
     *
     * A capture without messages stays listed and shows the panel empty state, as the Yii2 sidebar does.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return bool `true` when the collector produced a payload; `false` otherwise.
     */
    public function hasContent(array $payload): bool
    {
        return $payload !== [];
    }

    /**
     * Counts the messages the capture sent.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @throws \PHPForge\Debug\Storage\HydrationException when the payload does not match the Mail snapshot schema.
     *
     * @return list<ToolbarItem> One metric carrying the message count, or an empty list when none was sent.
     */
    public function toolbarItems(array $payload): array
    {
        $count = count(MailSnapshot::fromArray($payload, '$.panels.mail')->entries());

        return $count === 0 ? [] : [ToolbarItem::create((string) $count)];
    }

    /**
     * Counts the messages the capture sent, or links the previous request when it sent the mail instead.
     *
     * The previous request is the manifest entry right after the capture, newest first; a capture not listed yet falls
     * back to the newest entry.
     *
     * @param string $tag Tag of the capture the toolbar describes.
     * @param array<string, mixed> $payload Serialized panel payload of that capture.
     *
     * @throws \PHPForge\Debug\Storage\HydrationException when the payload does not match the Mail snapshot schema.
     *
     * @return list<ToolbarItem> The own count, a `cross-request` metric linking the previous request, or an empty list
     * when neither sent mail.
     */
    public function toolbarItemsForCapture(string $tag, array $payload): array
    {
        $items = $this->toolbarItems($payload);

        return $items === [] ? $this->previousRequestItems($tag) : $items;
    }

    /**
     * Links the metric to the request right before the capture when that request sent mail.
     *
     * @param string $tag Tag of the capture the toolbar describes.
     *
     * @return list<ToolbarItem> One `cross-request` metric carrying the previous request's message count, a tooltip
     * naming it, and its panel URL; an empty list when there is no previous request or it sent no mail.
     */
    private function previousRequestItems(string $tag): array
    {
        $manifest = $this->store->loadManifest();

        $found = !isset($manifest[$tag]);

        foreach ($manifest as $previous) {
            if ($found === false) {
                $found = $previous->tag === $tag;

                continue;
            }

            if ($previous->mailCount === 0) {
                return [];
            }

            return [
                ToolbarItem::create((string) $previous->mailCount)
                    ->withStatus('cross-request')
                    ->withTitle(
                        sprintf(MailMessage::PREVIOUS_REQUEST->value, $previous->method, self::shortUrl($previous)),
                    )
                    ->withUrl(
                        (new DebugUrlGenerator($this->options->routePrefix))->panel($previous->tag, $this->id()),
                    ),
            ];
        }

        return [];
    }

    /**
     * Returns the path of a captured request URL, or the whole URL when it has no path.
     *
     * @param RequestSummary $summary Manifest entry of the request.
     *
     * @return string Path shown in the metric tooltip.
     */
    private static function shortUrl(RequestSummary $summary): string
    {
        $path = parse_url($summary->url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : $summary->url;
    }
}
