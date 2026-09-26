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
     * Suffix of the Dump grid summary counter, following the number of captured dumps.
     */
    case DUMP_CAPTURED_SUFFIX = ' dumps captured';

    /**
     * Tooltip of the Dump toolbar metric, as the Yii2 host words it.
     */
    case DUMP_COUNT = 'Number of dumped variables';

    /**
     * Snippet of the Dump empty state, showing the `yiisoft/var-dumper` calls the panel records.
     */
    case DUMP_EMPTY_EXAMPLE = "VarDumper::dump(\$value);\nd(\$user, \$query);";

    /**
     * Explanation of the Dump empty state, naming the capture point that replaces Yii2's `Yii::debug()`.
     */
    case DUMP_EMPTY_EXPLANATION = 'The Dump panel records the values dumped through yiisoft/var-dumper, so nothing was '
        . 'captured here. To populate this view, dump values anywhere in the request cycle:';

    /**
     * Headline of the Dump empty state, as the Yii2 host words it.
     */
    case DUMP_EMPTY_HEADLINE = 'No variables dumped in this request';

    /**
     * Explanation shown when the active Dump filters exclude every captured dump.
     */
    case DUMP_NO_MATCH_EXPLANATION = 'Adjust or clear the filters to show the captured dumps.';

    /**
     * Headline shown when the active Dump filters exclude every captured dump.
     */
    case DUMP_NO_MATCH_HEADLINE = 'No dumps match the active filters';

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

    /**
     * Value of the User toolbar metric for a capture where nobody was signed in, as the Yii2 host words it.
     */
    case USER_GUEST = 'Guest';
}
