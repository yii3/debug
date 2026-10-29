# AI assistance

The canonical source is [`skills/yii3-debug-integration/SKILL.md`](../skills/yii3-debug-integration/SKILL.md). It helps
agents install the development dependency, connect every Yii Config Plugin and DI integration point, and verify a real
request. It is separate from the `yii2-debug-inspection` skill and uses no Yii 2 module configuration.

## Install in an application

### Automatic Composer installation

Bootstrap `llm/skills` 1.15.0 once in the application. Create `skills.json` with an explicit trust policy:

```json
{
    "target": ".agents/skills",
    "aliases": [".claude/skills"],
    "auto-sync": true,
    "vendor-sources": false,
    "dependencies": {
        "composer": {
            "trusted-replace": true,
            "trusted": ["yii3/debug"]
        }
    }
}
```

Then authorize the plugin and install both development dependencies:

```bash
composer config allow-plugins.llm/skills true
composer require --dev llm/skills:1.15.0 "yii3/debug:^0.1@dev"
composer skills:show
```

The package must contain this bundle and its `composer.json` must declare `extra.skills.source`. With `auto-sync`
enabled, later installs and updates synchronize trusted bundles. If it is disabled, run `composer skills:update` after
dependency changes. Installing `yii3/debug` alone leaves a bundle inside `vendor/`; clients opened at the application
root do not discover it automatically.

The plugin creates `.claude/skills` only as a link to `.agents/skills`. If that path already exists as a real directory,
synchronization still copies trusted bundles to `.agents/skills`, then refuses to replace the directory and exits with
an error, so Claude Code does not discover them. Back up the existing directory, move each skill it contains into
`.agents/skills` without replacing a skill of the same name, remove the emptied directory, and run
`composer skills:update`.

### Optional npx installation

From the application root, list a local checkout before installing its unpublished work:

```bash
npx skills@1.7.2 add /absolute/path/to/yii3-debug --list
npx skills@1.7.2 add /absolute/path/to/yii3-debug \
  --skill yii3-debug-integration --agent claude-code codex opencode
```

A Composer-installed package is also a valid source when that installed revision includes the skill directory. For a
remote source, list it first and verify that the selected release or default branch contains the bundle. Installer links
and copies are generated outputs; edit only the canonical `skills/` source.

## Discovery and verification

This checkout's `.agents/skills/` and `.claude/skills/` entries link to the canonical bundle. For manual installation,
copy or link the complete `yii3-debug-integration` directory into the application's client skill directory.

Open a fresh client session at the application root and verify discovery. Activate `$yii3-debug-integration` in Codex,
`/yii3-debug-integration` in Claude Code, or ask OpenCode to load it through its skill tool. Package installation,
skill synchronization, discovery, native activation, container resolution, and a successful debugger request are
separate outcomes; report each one honestly.

Use a realistic acceptance task: inspect all HTTP runners, connect their groups and middleware intentionally, resolve
the logger and decorated services, make an allowed HTML request, find it in History, inspect a known non-sensitive entry
in its Logs panel, then verify denied access and production disablement. Static configuration inspection does not prove
browser rendering or event capture.

## Next steps

- 📚 [Getting started](index.md)
- 💡 [Application integration examples](examples.md)
- ⚙️ [Configuration reference](configuration.md)
- 🧪 [Testing guide](testing.md)

[Back to documentation](../README.md#documentation)
