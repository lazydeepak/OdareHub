# Runtime Layer Contract

## Role

The runtime layer consumes compiled theme variables. It reads `public/assets/theme.css` (or equivalent published output) and renders CSS custom properties for Shell/setup/app component CSS to consume via `var(--token-name)`.

## Components

1. **Compiled artifact**: `public/assets/theme.css` — the output of the compile/publish pipeline.
2. **Theme preference service**: `apps/Shell/Services/ThemePreferenceService.php` — resolves the active theme preference and serves style discovery.
3. **Runtime loading**: `public/index.php` — serves `/assets/theme.css` and handles on-demand recompilation.
4. **Component stylesheets**: Shell/app CSS files that reference `var(--token-name)`.

## Contract Rules

1. Runtime must consume compiled output only. It must not read source files directly.
2. Runtime must not invent or persist theme values independently.
3. Runtime must not circumvent the compile/publish pipeline.
4. Runtime must load active theme preference and serve the corresponding compiled output.
5. Runtime must not contain theme token definitions — those belong in the source layer.
6. Component CSS must stay in owner surface stylesheets, not in the runtime theme artifact.
7. Component CSS must consume `var(--token-name)` for all theme-dependent values.

## Ownership Separation at Runtime

| Owner | Runtime Responsibility |
|---|---|
| Theme source layer | Provides token values via compiled output |
| Shell | Owns topbar, sidebar, workspace chrome selectors |
| Setup/Auth | Owns login, registration page selectors |
| App modules | Own business-domain selectors |
| Studio | Owns editor tooling selectors |

## Non-Goals

- Runtime does not own theme value truth.
- Runtime does not validate or enforce accessibility.
- Runtime does not generate theme variants dynamically.
- Runtime does not provide a write path back to source files.

## Future Migration Notes

- As selector CSS is extracted from the runtime theme artifact into owner stylesheets, runtime parity must be verified: selectors must behave identically while replacing hardcoded values with `var(--token)`.
- After migration, `public/assets/theme.css` should contain only token declarations (no component selectors).
- Shell must eventually consume from Platform ApprovedStyleRegistry for governed style values, not directly from the compiled artifact. This remains intentionally disconnected until the consumption boundary plan is implemented.
