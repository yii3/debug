# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.1.0 Under development

- feat: add the Yii3 storage adapter for `php-forge/debug-core` and the official Yii debug collectors.
- feat: enhance User panel with RBAC support and add related configuration files.
- feat: consume shared UI contracts, retain data facades, and add context-aware panel rendering.
- feat(ui): align Logs, Database, Events, EXPLAIN, and toolbar links with Yii2.
- feat(ui): capture Yii3 profiler spans and add complete Profiling and Timeline panels.
- feat(ui): align User guest, RBAC, searchable switch grids, CSRF validation, and safe identity restoration.
- feat(ui): add Yii3 Dump, Mail, and Queue collectors, panels, secure actions, decorators, redaction, tests, and demo fixtures.
- fix(ui): harden response capture and ship the keyboard-resizable drawer with focus restoration.
- fix: harden CI, CSRF, persisted data, lifecycle cleanup, instrumentation, EXPLAIN, storage, mail, and toolbar routing.
- refactor: separate response capture, snapshot persistence, mail reconciliation, and fail-open mail reporting without changing behavior.
- refactor: reduce the debugger to a minimal Yii/PHP toolbar and request-summary history, removing diagnostic panels, collectors, instrumentation, optional integrations, and related dependencies.
- refactor: configure immutable debugger services and add protected History, Configuration, and phpinfo pages with persisted request summaries.
