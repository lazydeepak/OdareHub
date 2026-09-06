# Source Layer Contract

## Role

The source layer is the canonical authoring home for theme values. It is the input to the compile/publish pipeline.

## Location

`resources/themes/**` — structured as foundation, semantic, variants, and manifest.

## Contents

1. **Foundation tokens**: primitive, neutral, reusable design values (spacing, radius, colors, motion, typography, elevation).
2. **Semantic tokens**: intent-facing visual tokens (surface, text, border, accent, status, and control colors/artwork). Control, card, panel, and icon-chip geometry belongs to Shell's theme-independent rendering Foundation.
3. **Variant overrides**: theme combination-specific overrides on foundation and/or semantic values.
4. **Theme manifest** (`theme-manifest.json`): declares enabled sources, disabled entries, custom directory, and compilation metadata.
5. **Custom styles**: user-authored CSS files in `custom/` directory for instance-specific overrides.

## Ownership Rules

- Theme source owns values. Themes own values, Shell owns selectors, Studio owns governed editing, runtime consumes compiled output.
- Semantic theme sources must not define control height/radius/padding/spacing/field width, checkbox or radio shape, card radius, panel shape, or icon-chip dimensions.
- Source files must not contain component selectors or app/module-specific CSS.
- Source files must not reference runtime compilation artifacts.
- Source files must not bypass the manifest — all compilation inputs must be declared in `theme-manifest.json`.

## Validation

- All source CSS must parse without errors.
- Manifest entries must point to existing enabled source files.
- Disabled entry values must remain parseable but skipped during compilation.

## Source-of-Truth Rule

`resources/themes/**` is the canonical source of truth for theme values. `public/assets/theme.css` is compiled output, not authoring source. Studio editing tools must target source resources in end-state v1, not the runtime artifact.

## Migration Note

Currently, compilation reads flat root files (`foundation.css`, `light.css`, etc.). Future migration slices will progressively move token definitions into structured subdirectory files. The manifest must be updated in sync with each migration.
