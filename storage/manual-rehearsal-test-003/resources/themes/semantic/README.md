# Semantic Tokens (V1)

Canonical home for intent-facing tokens consumed by runtime surfaces.

## Scope

- Surface tokens (background, panel, card surface colors)
- Text tokens (primary text, secondary/muted text)
- Border tokens (default border, subtle border, strong border)
- Accent tokens (primary accent, secondary accent)
- Status tokens (success, warning, danger, info colors)
- Notification tokens (chip backgrounds, text)
- Control tokens (control background, control radius)
- Focus / interaction tokens (focus ring, hover state)

## Ownership

Semantic tokens are owned by the theme source layer. They must:

- Map from foundation primitives (e.g., `--text` uses a foundation neutral color).
- Define meaning, not component selectors (e.g., `--text` means "primary text color", not `.sidebar-link`).

They must not:

- Contain component selector CSS.
- Duplicate foundation primitive definitions.
- Reference app/module business context.

## Rules

1. Semantic values reference foundation token variables where possible.
2. Each semantic token has a single meaning that is consistent across all themes.
3. Variant overrides may change a semantic token's value for a specific theme combination.
4. Semantic layer is the primary consumption layer for Shell/app component CSS (`var(--token)` in component stylesheets).

## Non-Goals

- Semantic layer does not own component selectors.
- Semantic layer does not manage runtime theme preference resolution.
- Semantic layer does not store Studio editing drafts or approval artifacts.
- Semantic tokens are not a replacement for app/module-local CSS variables.

## Future Migration Notes

- Current semantic values are embedded across `resources/themes/light.css`, `resources/themes/dark.css`, and style variant files. Future slices should extract a canonical semantic token set.
- `resources/themes/semantic/semantic-tokens.placeholder.json` is a shape marker. Replace with actual token definitions when the compiler supports structured semantic input.
- After semantic token extraction, component CSS should reference `var(--semantic-token)` instead of hardcoded values.
