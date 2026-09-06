# First-Boot CSS Safety Contract

Status: Active, Slice 3.

## Protected Law

Setup rendering must remain usable before database setup completes. Theme source
truth is file/resource based; the database may select runtime preferences only
after setup.

For `/setup`, `/setup/*`, `/login`, `/2fa`, `/forgot-password`,
`/reset-password`, `/account/setup`, `/recovery/*`, and `/maintenance/*`:

- no `ThemePreferenceService`
- no `StyleRegistryService`
- no installed-app registry lookup
- no database-backed theme selection
- no Studio or customization dependency

## Ownership

- Shell owns first-boot structural safety, rendering foundation, and setup CSS.
- Platform owns the built-in theme identity, effects-none profile, and semantic aliases.
- System Tools publishes deterministic runtime copies.
- `public/assets/**` remains generated delivery output, never editable source truth.

This is platform boot safety, not sample-app presentation work.

## Static Load Order

The setup auth layout emits exactly this ordered chain:

```text
/assets/system/shell-essential.css
/assets/rendering/foundation.css
/assets/effects/effects-none.css
/assets/themes/liquid-glass-system.css
/assets/system/semantic-aliases.css
/assets/system/setup.css
```

Pre-auth login and recovery layouts emit the same first five assets followed by:

```text
/assets/system/auth.css
```

`effects-none.css` is mandatory for first boot. Setup must not depend on glass,
blur, saturation, or other expensive effects to remain readable.

## Source And Publication

The publication map is:

```text
scripts/assets/first_boot_css_manifest.json
```

Publish with:

```bash
php scripts/assets/compile_first_boot_css.php --apply
```

The publisher may read only Shell/Platform owner CSS and may write only:

```text
public/assets/system/
public/assets/rendering/
public/assets/effects/
public/assets/themes/
```

The focused gate enforces the exact ordered target-to-owner source map and
requires every published target to match compiler output with its generated
file marker intact.

## Token Definition Ownership

- Shell essential may define only `--sys-*`.
- Rendering foundation may define only `--render-*`.
- Theme-independent control geometry, native-choice shapes, shared surface/card radii, card padding, and icon-chip dimensions are `--render-*` values owned by Rendering Foundation.
- Effect profiles may define only `--fx-*`.
- Built-in theme color sources may define only `--color-*`.
- Built-in theme typography/mode sources may define only `--theme-*`.
- Semantic aliases and setup/auth component CSS must not redefine raw
  `--sys-*`, `--render-*`, `--fx-*`, `--color-*`, or `--theme-*` tokens.

## Runtime Boundary

Authenticated runtime also loads the same versioned rendering Foundation before
the composed theme, then maps legacy Shell geometry names back to those
Foundation primitives, so structural control and basic surface geometry cannot drift with theme
selection. Theme Manager, Customization Studio, CSS Live Editor, owner-app CSS,
and database schema remain outside this contract.

The generated `public/assets/theme.css` records a deterministic source
fingerprint covering the theme manifest, compiler/fingerprint implementation,
enabled sources, auto-discovered sources, and any enabled legacy base. Before a
dynamic page renders, `public/index.php` compares that value with current source
content and rebuilds missing or stale output. Content fingerprints, not file
timestamps, are the deployment freshness authority. Dry-run compiler paths must
report required work without creating or changing generated assets.

## Validation

```bash
bash scripts/architecture/check_first_boot_css_safety.sh
bash scripts/system/check_deployment_readiness.sh
```

The focused diagnostic renders setup and each pre-auth header with forbidden
theme services guarded by a failing autoloader, verifies both six-link orders,
validates token-definition ownership, checks the exact compiler source map, and
requires current generated publication output.
