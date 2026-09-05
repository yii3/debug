# Yii Debug Request and Router Redesign — Session Handoff

Date: 2026-09-04

## Scope

The work spans these repositories:

- `/home/terabytesoftw/Github/yii3/debug`
- `/home/terabytesoftw/Github/yii2-extensions/debug`
- `/home/terabytesoftw/Github/php-forge/debug-core`

The Yii 2 debugger at `http://localhost:8080` is the compatibility reference. The Yii 3 debugger runs at `http://localhost:8081`. The shared presentation and framework-neutral contracts live in Debug Core.

## Product decisions

- Routing belongs in the Request panel because every captured request has routing context.
- The toolbar Request item combines the resolved route and HTTP status, for example `Request site/index 200`.
- Yii 2 no longer needs a duplicate standalone Router toolbar/sidebar entry, although its capture and direct-detail compatibility are preserved.
- The Request panel uses the common tab order: Input, Headers, Session, Routes, Server.
- Populated diagnostic sections should be immediately useful instead of looking like an unstructured data dump.
- Original diagnostic values are preserved. No redaction or collector-schema change was introduced for Headers or Server.

## Implemented work

### Request and routing

- Integrated routing diagnostics into Request for both Yii 2 and Yii 3.
- Added route/action summaries, request metadata, route configuration badges, registered-route inventory, matched-route state, and resolution trace where the adapter can provide them.
- Moved framework-neutral routing view models and renderers into Debug Core, with Yii-specific factories and collectors remaining in their adapters.
- Updated navigation and toolbar mapping to avoid duplicate Yii 2 Router items.

### Input

- GET, POST, FILES, COOKIES, and Request Body now share the same disclosure treatment and typography.
- Populated sections open by default; empty sections stay collapsed.
- All disclosures use the shared `CLICK TO EXPAND` / `CLICK TO COLLAPSE` affordance.

### Headers

- Replaced the raw table dump with a single Header Exchange surface.
- Split the exchange into Inbound Request and Outbound Response lanes, with counts and one live filter.
- Preserved repeated header values and their order.
- Removed PHP-style quote noise from displayed strings while retaining the original diagnostic content.
- Covered empty, long, malformed, escaped HTML, invalid UTF-8, and raw integer response-line cases.

### Server

- Added a Server Environment surface with an Execution Context summary for protocol, host, runtime, and entry point.
- Grouped values into Request Context, Network & Transport, Runtime & Paths, Header Mirrors, and Environment & Other.
- Populated primary groups default to open and remain individually collapsible.
- Header Mirrors defaults to collapsed because it duplicates CGI/header information.
- Every group displays `CLICK TO EXPAND` / `CLICK TO COLLAPSE` and a value count.
- One filter searches all groups, temporarily opens matching collapsed groups, and restores the user's previous disclosure state when cleared.
- Added responsive container-query behavior, touch-friendly controls, and light/dark theme coverage.
- Correctly formats IPv6 host summaries such as `[::1]:8081`.

### Shared frontend and asset delivery

- Fixed live-filter target discovery for tables nested inside disclosure bodies.
- Attached live status to the mini toolbar rather than the input element.
- Added a Vite `closeBundle` step that refreshes the asset-root modification time. This makes Yii 2 AssetManager publish a new hash when nested built assets change, preventing stale CSS/JavaScript.
- Rebuilt and verified the shared asset bundle in both adapters.
- Applied the `frontend-design` skill to keep the debugger compact, diagnostic, consistent, and non-generic.

## Important implementation areas

### Debug Core

- `src/Panel/Request/RequestRenderer.php`
- `src/Panel/Request/RequestHeadersRenderer.php`
- `src/Panel/Request/RequestServerRenderer.php`
- `src/Panel/Request/RequestDiagnosticValueRenderer.php`
- `src/Panel/Request/ServerVariableGrouper.php`
- `src/Panel/Request/ServerVariableGroup.php`
- `src/Panel/Request/RequestToolbarItemFactory.php`
- `src/Panel/Request/Routing/`
- `src/Helper/Disclosure.php`
- `resources/src/core/live-filter.js`
- `resources/src/core/debug.js`
- `resources/src/styles/main.css`
- `resources/src/styles/primitives.css`
- `vite.config.js`
- `resources/assets/dist/`

### Yii 3 adapter

- `src/Panel/RequestPanel.php`
- `src/Collector/RequestCollector.php`
- `src/Collector/RouteActionResolver.php`
- `src/Routing/`
- Associated Request, routing, toolbar, and page-renderer tests.

### Yii 2 adapter

- `src/panels/RequestPanel.php`
- `src/panels/request/RequestRoutingViewFactory.php`
- `src/views/default/panels/request/detail.php`
- Router panel/collector compatibility, module panel registration, toolbar mapping, and sidebar rendering.
- Associated Request, routing, toolbar, asset, and page-renderer tests.

## Verification completed

- Debug Core PHP suite: 945 tests passed.
- Yii 3 suite: 278 tests, 1,280 assertions passed.
- Yii 2 suite: 1,189 tests, 2,901 assertions passed.
- JavaScript suite: 181 tests passed with 100% statements, branches, functions, and lines.
- PHPStan passed in all three repositories.
- ECS passed in all three repositories. A transient parallel cache collision in Core passed when rerun alone.
- JavaScript/CSS linting, Prettier, asset-size, and contrast checks passed.
- `git diff --check` passed in all three repositories.
- Live visual and accessibility-tree checks passed on both `localhost:8080` and `localhost:8081`, including the final Server disclosure defaults.

## Repository state and continuation notes

- All implementation remains uncommitted. Do not assume a commit exists.
- `CHANGELOG.md` was updated in all three repositories for the user-visible work.
- Pre-existing untracked Debug Core files `index.json` and `index.lock` were intentionally left untouched.
- Debug tags and saved view URLs are ephemeral. Generate a fresh request before future browser verification.
- Before continuing, inspect `git status` and the diffs in all three repositories, then rerun the narrow tests related to any additional change.
