# Debug repository guide

This is the Yii 3 adapter `yii3/debug`. Before changing it, inspect `composer.json`, the current Git status and diff,
the relevant package configuration under `config/`, and the matching page under `docs/`. Preserve staged and unstaged
work. Public documentation is English.

Application integration guidance lives in `skills/yii3-debug-integration/SKILL.md`. Keep that directory canonical;
`.agents/skills/` and `.claude/skills/` contain discovery links. Installing the Composer package, synchronizing the
skill, client discovery, skill activation, and a working debugger request are separate results.

The package publishes Yii Config Plugin groups for parameters, definitions, Web definitions, service providers,
bootstrap callbacks, and Web events. A consuming application must compose those groups into every intended HTTP runner,
build its logger from `yiisoft/log.targets`, and include the merged `yiisoft/middleware-dispatcher.middlewares` entries
in each intended middleware pipeline. Do not replace application services or infer a working integration from a merge
plan alone. `APP_ENV` must be `debug`, `dev`, or `test`; keep the debugger out of production dependencies and runtime.

The framework-neutral contracts live in `php-forge/debug`; storage and the compiled UI live in
`php-forge/debug-core`. Provider packages own their collectors, panels, and events. Keep those boundaries explicit and
do not copy Yii 2 configuration or module APIs into this adapter.

Run applicable non-mutating checks from `docs/testing.md`. Commands such as `composer ecs` and `composer rector` modify
files. Report package/configuration checks, container resolution, application requests, browser checks, and native skill
activation separately. Do not stage, commit, publish, or edit generated documentation-site output unless requested.
