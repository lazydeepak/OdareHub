# Asset Tools

These scripts are System Tools for asset diagnostics and publishing workflows. They must preserve ownership boundaries:

- Apps/modules own source CSS under their own app/module directories.
- Shell owns URL composition and runtime linking conventions, not app/module CSS meaning.
- `public/assets/apps/...` is a published runtime delivery target, not the source of truth.
- Tools in this directory must not create hidden sources of truth.

## Registered CSS Publisher

Run from the repository root:

```bash
php scripts/assets/publish_registered_css.php
php scripts/assets/publish_registered_css.php --json
php scripts/assets/publish_registered_css.php --apply
php scripts/assets/publish_registered_css.php --apply --json
```

Validate PHP syntax with:

```bash
php -l scripts/assets/publish_registered_css.php
```

Dry-run is the default. Dry-run reads manifest-declared `styles` entries and prints the owner source CSS path plus the expected public asset target path. It does not copy, create, delete, or modify files.

Apply mode requires explicit `--apply`. Apply mode only copies owner CSS files into the confined delivery root `public/assets/apps/...`. It may create missing directories only under that delivery root. It must not edit source CSS, manifests, Core, Shell runtime code, app logic, or database state.

Published files under `public/assets/apps/...` are ignored by Git because they are reproducible delivery artifacts. Owner source files remain under `apps/<Owner>/styles/...` or `apps/<Owner>/modules/<Module>/styles.css`.

## First-Boot CSS Compiler

Run from the repository root:

```bash
php scripts/assets/compile_first_boot_css.php
php scripts/assets/compile_first_boot_css.php --json
php scripts/assets/compile_first_boot_css.php --apply
php scripts/assets/compile_first_boot_css.php --apply --json
```

The compiler reads `scripts/assets/first_boot_css_manifest.json` and publishes
the fixed setup and pre-auth CSS chains from Shell/Platform owner sources.
Apply mode may write only under `public/assets/system`,
`public/assets/rendering`, `public/assets/effects`, and
`public/assets/themes`.

The CLI and public missing/stale-asset recovery both use
`scripts/assets/first_boot_css_compiler.php`. Runtime recovery is in-process so
it remains available on production PHP hosts where process functions such as
`exec()` are disabled.

`theme.css` also carries a deterministic SHA-256 source fingerprint produced by
`scripts/assets/theme_source_fingerprint.php`. Every dynamic page request
compares that fingerprint with the current manifest/compiler/theme sources and
rebuilds a missing or stale runtime theme before rendering. This avoids relying
on deployment timestamps or a manual post-pull compiler command when the PHP
runtime can write the generated asset directory.

These public files are reproducible delivery output, not source truth. The
compiler does not read database state, runtime theme preferences, installed-app
state, or Studio artifacts.

## Deployment / Fresh Clone Step

After pulling code or deploying a fresh checkout, publish registered CSS assets before final validation:

```bash
php scripts/assets/publish_registered_css.php --apply
php scripts/assets/compile_first_boot_css.php --apply
scripts/architecture/run_architecture_gates.sh
```

Expected healthy state after publishing:

```text
checked style entries: 24
warnings: 0
ARCHITECTURE GATES: PASS
```

Do not use `bash -n` for PHP scripts. Use `bash -n` only for shell scripts.

## Theme Source Compiler

Run from the repository root:

```bash
php scripts/assets/compile_theme_sources.php
php scripts/assets/compile_theme_sources.php --json
php scripts/assets/compile_theme_sources.php --apply
php scripts/assets/compile_theme_sources.php --apply --json
```

Validate PHP syntax with:

```bash
php -l scripts/assets/compile_theme_sources.php
```

Current behavior:

- Reads enabled source entries from `resources/themes/theme-manifest.json`
- Publishes the runtime theme option catalog to `apps/Shell/Resources/published-theme-options.json`
- Compiles enabled source CSS into runtime `public/assets/theme.css`
- Supports migration bridge base via manifest `legacy_base` (default `public/assets/theme.legacy.css`)
- Apply is blocked unless manifest status is `runtime_wired_ready`

Boundary notes:

- Runtime consumes `public/assets/theme.css` as compiled artifact.
- Source files under `resources/themes/**` are the editable source-of-truth model.
- `public/assets/theme.legacy.css` is migration compatibility input only (not the long-term editable source).
