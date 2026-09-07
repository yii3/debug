# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.1.0 Under development

- feat: add the Yii3 storage adapter for `php-forge/debug-core` and the official Yii debug collectors.
- feat: enhance User panel with RBAC support and add related configuration files.
- feat: consume shared UI contracts, retain data facades, and add context-aware panel rendering.
- feat(ui): align Logs, Database, Events, EXPLAIN, and toolbar links with Yii2.
- feat(ui): capture Yii3 profiler spans and add the Profiling panel with Timeline and detailed views.
- feat(ui): align User guest, RBAC, searchable switch grids, CSRF validation, and safe identity restoration.
- feat(ui): add Yii3 Dump, Mail, and Queue collectors, panels, secure actions, decorators, redaction, tests, and demo fixtures.
- fix(ui): harden response capture and ship the keyboard-resizable drawer with focus restoration.
- fix: harden CI, CSRF, persisted data, lifecycle cleanup, instrumentation, EXPLAIN, storage, mail, and toolbar routing.
- refactor: separate response capture, snapshot persistence, mail reconciliation, and fail-open mail reporting without changing behavior.
- refactor: reduce the debugger to a minimal Yii/PHP toolbar and request-summary history, removing diagnostic panels, collectors, instrumentation, optional integrations, and related dependencies.
- refactor: configure immutable debugger services and add protected History, Configuration, and phpinfo pages with persisted request summaries.
- feat(ui): add protected capture comparison with metric deltas and privacy-preserving panel-structure counts.
- feat(ui): add registered extension navigation, a toolbar chip, and the Inertia panel; fix metadata layout and compose app-owned extensions.
- feat(ui): add native PHP Forge Vite configuration and manifest capture with a toolbar mode chip and detail panel.
- feat(ui): add the built-in Yii3 Request collector, align its toolbar, detail view, navigation, and security with Yii2, and keep Request history navigation compatible with captures created before the built-in panel.
- refactor: keep service constructors focused on essential dependencies; migrate removed optional arguments to `withExtensionPanels()`, `withCollectorCoordinator()`, `withCapturePolicy()`, and the existing immutable configuration methods.
- feat(ui): add the built-in Yii3 Profiling collector, time and peak-memory toolbar metrics, and the Yii2-compatible filterable timing panel.
- fix(ui): unify Profiling filters and terminology, shorten Timeline labels with full hover text, align chart colors and duration gauges with Yii2, and preserve request-scoped timing and post-flush spans.
- feat(ui): add filterable Yii3 Logs with severity and Trace shortcuts, logger integration, Delta column, and ordered navigation.
- feat(ui): add metadata-only PSR-14 Events capture with useful source labels, a non-redundant filterable grid, transparent dispatcher decoration, toolbar count, and primary navigation.
- refactor(ui): consolidate Request, Server, Session, Flashes, routing, headers, and input diagnostics into searchable, compact views.
- refactor(history): share typed comparison with Debug Core and add `HistoryMetricComparison::create()` for immutable panel-link configuration.
- refactor: delegate history metric calculations and formatting to Debug Core while preserving adapter models, public contracts, and exact comparison output.
- refactor: delegate panel comparison to Debug Core while preserving public history models, exact ordering, capture states, difference counts, and diagnostic values.
- refactor: delegate captured URL-to-path display conversion to Debug Core while preserving original diagnostic URLs, public models, navigation, and rendered output.
- feat: merge Yii Config integrations, retain empty Logs navigation, and use the named Yii3 Inertia observer.
- refactor(ui): share pagination bounds and footer markup across the manual History, Logs, Events, and Profiling grids while preserving panel-specific HTML, filtering, sorting, and navigation.
- refactor(ui): share filter removal and page reset across manual Logs, Events, and Profiling banners, reusing normalized queries while preserving panel-specific navigation.
- refactor(ui): reuse normalized grid queries for header and pager links in Logs, Events, and Profiling, keeping sort links local to each header and resolving their active direction once per header row.
- refactor(ui): reuse active search filters in manual Logs, Events, and Profiling grids and the normalized Logs query in severity shortcuts, preserving filter replacement, page reset, and query ordering.
- refactor(events): replace the duplicate execution-flow list with full-width diagnostics without repeating row fields in the primary table, retaining column filters, sorting, pagination, and original observation identities.
- refactor: centralize panel icons, titles, and exception messages; document enum cases, literal formats, and formatting examples.

### Added

- feat(events): add the shared execution inspector, identity-based middleware correlation, and opt-in bounded lifecycle context and source traces.
