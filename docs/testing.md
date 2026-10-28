# Testing

This package provides a consistent set of [Composer](https://getcomposer.org/) scripts for local validation. Use a
source checkout with PHP 8.3 or later and development dependencies installed. Record the PHP version, resolved
dependency revisions, and any symlinked package path before interpreting results.

Tool references:

- [Actionlint](https://github.com/rhysd/actionlint) for GitHub Actions workflow linting.
- [Codespell](https://github.com/codespell-project/codespell) for spelling checks.
- [Composer Require Checker](https://github.com/maglnet/ComposerRequireChecker) for dependency definition checks.
- [Easy Coding Standard (ECS)](https://github.com/easy-coding-standard/easy-coding-standard) for coding standards.
- [EditorConfig Checker](https://github.com/editorconfig-checker/editorconfig-checker) for EditorConfig compliance.
- [Gitleaks](https://github.com/gitleaks/gitleaks) for secret scanning.
- [Infection](https://infection.github.io/) for mutation testing.
- [Markdownlint-cli2](https://github.com/DavidAnson/markdownlint-cli2) for Markdown linting.
- [PHPStan](https://phpstan.org/) for static analysis.
- [PHPUnit](https://phpunit.de/) for unit tests.
- [Prettier](https://prettier.io/) for Markdown and YAML formatting checks.
- [Rector](https://github.com/rectorphp/rector) for automated refactoring.
- [Yamllint](https://github.com/adrienverge/yamllint) for YAML linting.
- [Zizmor](https://github.com/zizmorcore/zizmor) for GitHub Actions security audits.

## Install and validate

```bash
composer install
composer validate --strict
composer audit
```

Do not bypass security advisories to obtain a successful install. A consuming application should commit its lock file;
this library's local lock file is ignored.

## Automated refactoring (Rector)

Run Rector to apply automated code refactoring. This command modifies files.

```bash
composer rector
```

Preview the refactoring without modifying files.

```bash
composer rector-check
```

## Coding standards (ECS)

Check coding standards without modifying files.

```bash
composer ecs-check
```

Apply coding standard fixes. Review the changes before keeping them.

```bash
composer ecs
```

## Dependency definition check

Verify that runtime dependencies are correctly declared in `composer.json`.

```bash
composer check-dependencies
```

## Mutation testing (Infection)

Run mutation testing.

```bash
composer mutation
```

Run mutation testing with PHPStan static analysis enabled.

```bash
composer mutation-static
```

Both scripts disable Composer's process timeout, set PHP's memory limit to unlimited, use four workers, ignore the MSI
when there are no generated mutations, and require 100% MSI and covered MSI. `composer mutation-static` additionally
enables PHPStan with an unlimited memory limit. `infection.json5` defines the source directory, per-test timeout, and
reporting; it does not contain the command-line thresholds. The shared CI workflow receives the same worker and MSI
options. Infection requires an available coverage driver, and a failed or skipped initial suite is not a passing run.

## Repository quality checks

Make the CLI tools used by the shared quality workflow available on `PATH`: actionlint with its ShellCheck helper,
codespell, EditorConfig Checker, markdownlint-cli2, Prettier, and yamllint. Run all checks:

```bash
composer quality
```

Each tool also has its own script:

```bash
composer actionlint
composer codespell
composer editorconfig-checker
composer markdownlint
composer prettier
composer yamllint
```

The linter configuration lives in `.github/linters`, and the Prettier configuration lives in `.prettierrc.json` and
`.prettierignore`. The shared quality workflow uses those exact files.

## Static analysis (PHPStan)

Run static analysis.

```bash
composer static
```

## Unit tests (PHPUnit)

Run the full test suite.

```bash
composer tests
```

## Documentation and application verification

In an application, also verify that every intended runner consumes the required groups, every intended middleware
pipeline contains both debugger middleware IDs in package order, an allowed HTML request receives the toolbar and is
retained in History, a known log entry appears in Logs with sensitive keys redacted, an unlisted client is denied, and
a production process has no debugger integration. Trigger the real application behavior for optional Mail, User, RBAC,
Inertia, or Vite panels; panel registration alone does not prove that an event reached its collector.

## Skill distribution

Validate `skills/yii3-debug-integration/SKILL.md` with a compatible Agent Skills validator. Build a Composer archive and
inspect its file list: the skill must be included, while `.agents/`, `.claude/`, `AGENTS.md`, `docs/`, and any future
`examples/` directory must be absent. Test `composer skills:show` and synchronization in a disposable application with
`llm/skills` configured. Native client discovery and activation are separate checks from installation and
synchronization.

## Workflow security checks

Make zizmor and Gitleaks available on `PATH`. Audit the current workflow files with the same pedantic persona used by
the shared security workflow:

```bash
composer zizmor
```

Scan Git history for secrets with the repository's Gitleaks configuration:

```bash
composer gitleaks
```

Run both checks. Findings make the command fail; inspect them rather than disabling the checks.

```bash
composer security
```

Zizmor needs GitHub API access for its online audits. If the tool runs offline, report that limit separately. To request
an offline audit explicitly:

```bash
composer zizmor -- --offline
```

Report hosted CI execution and its PHP/OS matrix separately from these local commands. Keep generated reports in
ignored `internal/`.

## Passing extra arguments

Composer scripts support forwarding additional arguments using `--`.

Run PHPUnit with code coverage report generation.

```bash
composer tests -- --coverage-html internal/coverage
```

Run PHPStan with a different memory limit.

```bash
composer static -- --memory-limit=512M
```

## Next steps

- 📚 [Getting started](index.md)
- 💡 [Application integration examples](examples.md)
- 🤖 [AI assistance](agents.md)

[Back to documentation](../README.md#documentation)
