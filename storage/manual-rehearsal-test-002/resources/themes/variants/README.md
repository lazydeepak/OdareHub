# Theme Variants (V1)

Canonical home for theme variant overrides.

## Scope

Each variant subdirectory contains overrides for a specific theme combination:

- `light-paper/` — light mode with Paper style
- `light-liquid-glass/` — light mode with Liquid Glass style
- `dark-paper/` — dark mode with Paper style
- `dark-liquid-glass/` — dark mode with Liquid Glass style

## Ownership

Variants are owned by the theme source layer. Each variant:

- Overrides selected foundation and/or semantic values only.
- Must not duplicate the full foundation or semantic token set.
- Must not contain component selector CSS.
- Must not introduce independent theme-specific semantics.

## Rules

1. Variants override only what differs from the base semantic layer.
2. Base variants (`light.css`, `dark.css`) provide mode-level defaults. Style variants (`paper.css`, `liquid-glass.css`) provide style-level overrides.
3. `system-*` resolves to light/dark at runtime and is not a separate source variant tree.
4. Disabled styles (`navy`, `obsidian`) remain as compatibility entries only. Do not add new sources to them.
5. Custom styles placed in `resources/themes/custom/` follow the same override-only contract.

## Non-Goals

- Variants are not independent token universes.
- Variants do not own component selectors or runtime resolution logic.
- Variant files are not a replacement for the manifest-driven compile pipeline.

## Future Migration Notes

- Current variant definitions live in flat root files (`light.css`, `dark.css`, `liquid-glass.css`, `paper.css`). Future migration should progressively move variant-specific overrides into these subdirectories.
- The primary variant matrix is: `{light, dark} × {paper, liquid-glass}`. All four combinations must be kept in sync.
- When the compiler is updated to read from subdirectory variant files, update the manifest `sources` to point to the structured paths.
- Each variant subdirectory currently contains a placeholder README. Replace with actual override CSS when the compiler supports subdirectory variant input.
