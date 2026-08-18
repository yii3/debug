<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Helper\{EmptyState, Format};
use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;
use PHPForge\Debug\Toolbar\ToolbarItem;
use UIAwesome\Html\Flow\P;
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;

use function count;
use function is_float;
use function is_int;
use function number_format;
use function sprintf;

/**
 * Presents the shared Profiling panel payload and contributes the time and memory toolbar chips.
 */
final readonly class ProfilingPanel implements PanelInterface
{
    use PanelContentTrait;

    public function icon(): string
    {
        return 'profiling';
    }

    public function id(): string
    {
        return 'profiling';
    }

    public function name(): string
    {
        return 'Profiling';
    }

    public function render(array $payload): string
    {
        $snapshot = ProfilingSnapshot::fromArray($payload, 'panels.profiling');
        $entries = $snapshot->entries();
        $header = Header::tag()
            ->class('yii-debug-grid-summary')
            ->html(
                Span::tag()->html(Strong::tag()->content(self::formatTime($snapshot->time)), ' total'),
                Span::tag()->class('yii-debug-grid-summary-sep')->content('·'),
                Span::tag()->html(Strong::tag()->content(Format::bytesToMb($snapshot->memory, 3)), ' peak'),
            );
        $body = $entries === []
            ? EmptyState::card(
                'No profile blocks captured',
                P::tag()->content('This request did not record profile blocks, so the timing table is empty.'),
            )
            : (string) P::tag()->content(sprintf('%d profile blocks captured.', count($entries)));

        return H1::tag()->class('yii-debug-sr-only')->content('Performance Profiling')->render()
            . $header->render()
            . $body;
    }

    public function toolbarItems(array $payload): array
    {
        $time = $payload['time'] ?? null;
        $memory = $payload['memory'] ?? null;

        if ((!is_float($time) && !is_int($time)) || !is_int($memory)) {
            return [];
        }

        return [
            new ToolbarItem(value: self::formatTime((float) $time), title: 'Total processing time'),
            new ToolbarItem(value: Format::bytesToMb($memory, 3), title: 'Peak memory'),
        ];
    }

    /**
     * Formats a duration in seconds as a millisecond readout.
     *
     * @param float $seconds Duration in seconds.
     */
    private static function formatTime(float $seconds): string
    {
        return number_format($seconds * 1000) . ' ms';
    }
}
