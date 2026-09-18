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
     * Indicates that a collector registration key does not match the ID the collector declares.
     *
     * Format: "Debug collector registered as \"%s\" must match its ID \"%s\"."
     */
    case COLLECTOR_ID_MISMATCH = 'Debug collector registered as "%s" must match its ID "%s".';

    /**
     * Indicates that the requested capture holds no payload for the requested panel.
     *
     * Format: "Debug panel was not captured."
     */
    case DEBUG_PANEL_NOT_CAPTURED = 'Debug panel was not captured.';

    /**
     * Indicates that the requested panel cannot be served.
     *
     * Format: "The requested debug panel is not available."
     */
    case DEBUG_PANEL_UNAVAILABLE = 'The requested debug panel is not available.';

    /**
     * Indicates that the container resolved a debugger service to an unexpected type.
     *
     * Format: "The debug service %s is not available."
     */
    case DEBUG_SERVICE_UNAVAILABLE = 'The debug service %s is not available.';

    /**
     * Indicates that the requested capture is no longer retained.
     *
     * Format: "Debug snapshot not found."
     */
    case DEBUG_SNAPSHOT_NOT_FOUND = 'Debug snapshot not found.';

    /**
     * Indicates that a collector entry declares a non-boolean "enabled" option.
     *
     * Format: "Debug collector option \"enabled\" for \"%s\" must be a boolean."
     */
    case EXTENSION_COLLECTOR_ENABLED_INVALID = 'Debug collector option "enabled" for "%s" must be a boolean.';

    /**
     * Indicates that a collector entry declares an option other than "enabled".
     *
     * Format: "Unknown debug collector option \"%s\" for \"%s\". Available option: enabled."
     */
    case EXTENSION_COLLECTOR_OPTION_UNKNOWN
        = 'Unknown debug collector option "%s" for "%s". Available option: enabled.';

    /**
     * Indicates that a registration entry is neither a class string nor an array declaring a class string.
     *
     * Format: "Debug registration \"%s\" must be a class string or an array declaring a \"class\" string."
     */
    case EXTENSION_ENTRY_INVALID
        = 'Debug registration "%s" must be a class string or an array declaring a "class" string.';

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
     * Indicates that a panel registration key does not match the ID the panel declares.
     *
     * Format: "Debug panel registered as \"%s\" must match its ID \"%s\"."
     */
    case PANEL_ID_MISMATCH = 'Debug panel registered as "%s" must match its ID "%s".';

    /**
     * Indicates that the configuration overrides the title or icon of a panel rendering its own presentation.
     *
     * Format: "Panel %s renders its own title and icon, which configuration cannot override."
     */
    case PANEL_METADATA_UNSUPPORTED = 'Panel %s renders its own title and icon, which configuration cannot override.';

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
     * Indicates that the captured route metadata does not carry the expected shape.
     *
     * Format: "Captured route metadata must be an array or null."
     */
    case ROUTE_METADATA_INVALID = 'Captured route metadata must be an array or null.';

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
