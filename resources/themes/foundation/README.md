# Foundation Tokens (V1)

Canonical home for primitive, neutral, reusable design values.

## Scope

- Spacing scale (base unit, compound intervals)
- Radius scale (none, soft, medium, full)
- Typography scale (font sizes, line heights, font families, font weights)
- Color primitives (neutral palette, non-semantic base colors)
- Elevation primitives (shadow offsets, blur levels)
- Duration / easing curves
- Z-index bands
- Opacity levels
- Grid / layout primitives

## Ownership

Foundation tokens are owned by the theme source layer. They must not contain:

- Semantic intent meanings (e.g., `--text` belongs in semantic layer).
- Component selector references (e.g., `.topbar` belongs in Shell CSS).
- App/module-specific values (e.g., `--manufacturing-scan-bg` belongs in app CSS).

## Rules

1. Foundation tokens are context-neutral. They describe what a value is, not what it means.
2. Foundation tokens map one-to-one to CSS custom properties in the compiled output.
3. Semantic tokens and variants reference foundation tokens, not vice versa.
4. No component selector ownership in foundation files.

## Non-Goals

- Foundation does not define UI intent.
- Foundation does not own runtime resolution logic.
- Foundation does not own Studio editing workflows.
- Foundation does not replace existing source files at `resources/themes/*.css` — migration is incremental.

## Future Migration Notes

- Current foundation values exist in `resources/themes/foundation.css` (root-level file). Future slices should extract structured token files from that source.
- Placeholder JSON files in this directory (`colors.placeholder.json`, `spacing.placeholder.json`, etc.) are shape markers only. When the compiler reads structured files, replace these with actual token definitions.
- Preserve the `foundation.css` compilation entry in `theme-manifest.json` until the compiler is updated to read subdirectory files.
