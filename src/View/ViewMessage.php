<?php

declare(strict_types=1);

namespace Yii3\Debug\View;

/**
 * Presentation text specific to this adapter, kept out of Debug Core because it names Yii3 concepts.
 *
 * Framework-neutral chrome text lives in {@see \PHPForge\Debug\View\ViewMessage} and the per-panel Debug Core catalogs;
 * only wording that would be wrong for another framework belongs here.
 */
enum ViewMessage: string
{
    /**
     * Explanation shown above the panel comparison table, stating what the counts do and do not reveal.
     */
    case COMPARISON_COUNTS_SCOPE = 'Counts compare typed JSON leaf paths without rendering captured values.';

    /**
     * Explanation shown when neither capture retained a panel payload to compare.
     */
    case COMPARISON_PANELS_EMPTY = 'Neither capture contains panel payloads to compare.';

    /**
     * Caption of the Events grid, naming the dispatcher the panel observes.
     */
    case EVENT_CAPTURE_SCOPE = 'Observed through the decorated PSR-14 dispatcher. Direct calls to other dispatchers '
        . 'are not captured.';

    /**
     * Label of the captured request method in the Events context readout.
     */
    case EVENT_CONTEXT_REQUEST_METHOD = 'Request method';

    /**
     * Label of the captured request path in the Events context readout, stated without its query.
     */
    case EVENT_CONTEXT_REQUEST_PATH = 'Request path (no query)';

    /**
     * Empty state of the history grid when nothing has been captured yet.
     */
    case HISTORY_EMPTY = 'No requests have been captured.';

    /**
     * Empty state of the history grid when the active filters exclude every capture.
     */
    case HISTORY_NO_MATCH = 'No requests match the current filters.';

    /**
     * Tooltip of the sidebar entry leading to the request history.
     */
    case HISTORY_TOOLTIP = 'View request history';

    /**
     * Tooltip of the sidebar entry for a captured panel that no registered presenter can render.
     */
    case RAW_PANEL_TOOLTIP = 'View captured data without an installed presenter';
}
