<?php

declare(strict_types=1);

namespace Yii3\Debug\Exception;

use function sprintf;

/**
 * Exception message templates authored by this package.
 *
 * Use {@see Message::getMessage()} to format a template with `sprintf()` arguments.
 */
enum Message: string
{
    /**
     * Indicates that an extension panel ID is registered more than once.
     *
     * Format: "Duplicate debug extension panel ID: %s."
     */
    case EXTENSION_PANEL_ID_DUPLICATE = 'Duplicate debug extension panel ID: %s.';

    /**
     * Indicates that an extension panel ID is empty.
     *
     * Format: "Debug extension panel ID must not be empty."
     */
    case EXTENSION_PANEL_ID_EMPTY = 'Debug extension panel ID must not be empty.';

    /**
     * Indicates that the requested extension panel is not registered.
     *
     * Format: "Unknown debug extension panel: %s."
     */
    case EXTENSION_PANEL_UNKNOWN = 'Unknown debug extension panel: %s.';

    /**
     * Indicates that an event does not provide the expected HTTP request.
     *
     * Format: "Expected an HTTP request."
     */
    case HTTP_REQUEST_EXPECTED = 'Expected an HTTP request.';

    /**
     * Indicates that an event provides neither an HTTP response nor `null`.
     *
     * Format: "Expected an HTTP response or null."
     */
    case HTTP_RESPONSE_EXPECTED = 'Expected an HTTP response or null.';

    /**
     * Indicates that request capture was attempted before starting the collector.
     *
     * Format: "The request collector must be started before collecting a request."
     */
    case REQUEST_COLLECTOR_NOT_STARTED = 'The request collector must be started before collecting a request.';

    /**
     * Indicates that response capture was attempted before starting the collector.
     *
     * Format: "The request collector must be started before collecting a response."
     */
    case RESPONSE_COLLECTOR_NOT_STARTED = 'The request collector must be started before collecting a response.';

    /**
     * Indicates that an extension panel returned an item other than a `ToolbarItem` instance.
     *
     * Format: "Debug toolbar extension panel %s must return only ToolbarItem instances."
     */
    case TOOLBAR_ITEM_INVALID = 'Debug toolbar extension panel %s must return only ToolbarItem instances.';

    /**
     * Indicates that an extension panel did not return a list of toolbar items.
     *
     * Format: "Debug toolbar extension panel %s must return a list of items."
     */
    case TOOLBAR_ITEMS_NOT_LIST = 'Debug toolbar extension panel %s must return a list of items.';

    /**
     * Indicates that a toolbar extension panel ID is registered more than once.
     *
     * Format: "Duplicate debug toolbar extension panel ID: %s."
     */
    case TOOLBAR_PANEL_ID_DUPLICATE = 'Duplicate debug toolbar extension panel ID: %s.';

    /**
     * Indicates that a toolbar extension panel ID is empty.
     *
     * Format: "Debug toolbar extension panel ID must not be empty."
     */
    case TOOLBAR_PANEL_ID_EMPTY = 'Debug toolbar extension panel ID must not be empty.';

    /**
     * Formats the message without changing diagnostic argument values.
     *
     * @param int|string ...$argument Values to insert into the message template.
     *
     * @return string Formatted exception message.
     */
    public function getMessage(int|string ...$argument): string
    {
        return sprintf($this->value, ...$argument);
    }
}
