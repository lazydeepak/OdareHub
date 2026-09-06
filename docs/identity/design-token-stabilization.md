# Design Token Stabilization

Status: Baseline token alignment

The shared theme keeps existing tokens for backward compatibility and adds semantic aliases for new work. New UI should consume semantic aliases first and only use legacy names when extending existing components.

## Token Families

| Family | Preferred Tokens | Notes |
|---|---|---|
| Colors | `--color-*`, `--tone-*` | `--bg`, `--card`, `--text`, and related tokens remain compatibility aliases. |
| Spacing | `--space-*` | Use consistent step spacing; avoid one-off margins unless matching an existing component. |
| Typography | `--font-*`, `--type-*` | Do not scale type directly with viewport width for compact controls. |
| Cards | `--card-*`, `--style-card-shadow` | Cards should stay restrained and reusable. |
| Chrome | `--chrome-*`, `--style-shell-bg` | Wrapper chrome is route-driven, not role-driven. |
| Icon Chips | `--icon-chip-*` | Use for compact icon badges and app/module identity markers. |

## Drift Rules

- Do not create page-local color systems.
- Do not add inline style blocks for new Shell UI without an approved exception.
- Keep `/u/*`, `/admin/*`, and `/displays/*` using shared token families.
- Prefer full-width sections or existing component classes over nested card layouts.
