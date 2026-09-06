# Theme Source Layer (V1)

Canonical theme value ownership tree for Theme Architecture V1.

## Ownership

- **Themes own values**: foundation primitives, semantic tokens, variant overrides live here.
- **Shell / Setup / App CSS own selectors**: component CSS lives in owner surface stylesheets.
- **Studio owns governed editing**: Studio tools edit source resources through approved flow.
- **Runtime consumes compiled output**: Shell/setup/apps read `var(--token)` from published artifact.

## Layer Structure

```
resources/themes/
  foundation/        — Raw reusable primitives (spacing, radius, colors, motion, typography)
  semantic/          — Intent-level tokens (surface, text, border, accent, status)
  variants/          — Theme variant overrides (light/dark x liquid-glass/paper)
  contracts/         — Three-layer contract documentation (source, compile/publish, runtime)
```

## Responsibilities

1. Foundation tokens define the system's raw design vocabulary.
2. Semantic tokens map foundation primitives into UI intent meanings.
3. Variants override selected foundation/semantic values per theme combination.
4. Layer contracts document boundaries between source, compilation, and runtime.

## Non-Goals

- This tree does not own component selectors or app/module CSS.
- This tree does not replace runtime CSS compiler logic.
- This tree is not a runtime loading path — compiled output at `public/assets/theme.css` serves that role.
- This tree does not house Studio editing drafts or approval artifacts.

## Future Migration Notes

- Existing flat source files at root (`foundation.css`, `light.css`, `dark.css`, `liquid-glass.css`, etc.) are the current compilation input. Migration slices should progressively split these into structured subdirectory files as token ownership hardens.
- `navy` and `obsidian` remain disabled compatibility entries in the manifest. Do not remove until Phase 3 confirms no `/app` defaults still reference `system-obsidian`.
- When the compiler is updated to read from subdirectories, update the manifest `sources` array accordingly.
- Each migration slice must pass `scripts/system/check_deployment_readiness.sh` and `scripts/architecture/run_architecture_gates.sh`.
