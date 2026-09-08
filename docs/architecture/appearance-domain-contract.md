# Appearance Domain Contract

Status: Architecture checkpoint. Vocabulary and ownership only; no runtime behavior changes.

Appearance is the future canonical umbrella domain for the visual state of OdareHub. This contract names the dimensions that future persistence, tooling, publishing, and runtime contracts will use. It does not replace any current setting, selector, compiler input, browser-storage key, or runtime value.

## Canonical vocabulary

1. **Appearance** — the umbrella domain containing color, surface, effects, and motion choices.
2. **Color mode** — user or system intent: `system`, `light`, or `dark`.
3. **Color scheme** — the effective rendered result: `light` or `dark`. When color mode is `system`, environment resolution determines the color scheme.
4. **Palette** — the owner of semantic color values, including background, text, accent, status, and related color tokens.
5. **Surface profile** — the owner of material treatment: opacity, borders, elevation, and surface composition.
6. **Effect profile** — the selected optional visual-effect treatment, such as `none` or `liquid-glass`.
7. **Effects enabled** — an independent switch controlling whether the selected effect profile may be applied. Selecting a profile does not itself enable effects.
8. **Motion mode** — an independent motion preference: `system`, `reduced`, or `off`.
9. **Appearance preset** — a named reference to a structured combination of the dimensions above. A preset references catalog entries and values; it does not duplicate or independently own their tokens.

## Required domain decisions

- Appearance is the umbrella domain.
- Palette ownership excludes blur, translucency, motion, material treatment, and decorative effects.
- Liquid Glass belongs to Special Effects and is an effect profile, not a theme or palette.
- Paper is expected to become a surface profile. Its current warm colors will later become a separate palette.
- Surface profiles own material, opacity, border, and elevation treatment; they do not own semantic palette colors.
- Effect profile, effects enabled, and motion mode remain separate dimensions.
- Appearance presets are references, not independent token sources.
- Complete light and dark coverage is a future catalog validation concern, not part of this checkpoint.

## Legacy compatibility terminology

The terms `theme`, `color style`, and `palette mode` when used to mean `system`/`light`/`dark` are legacy compatibility terminology. Combined values such as `system-liquid-glass`, `light-paper`, and `dark-liquid-glass` are also legacy compatibility representations.

Existing uses remain valid during migration. This checkpoint does not rename theme CSS files, selectors, HTML attributes, settings such as `ui.theme` or `system.theme`, compiler inputs, persistence fields, or browser local-storage keys. Compatibility adapters will project legacy values into the future structured Appearance model only in a later governed migration.

## Ownership

### Shell

Shell owns resolution and presentation of the effective runtime Appearance state for its surfaces. Shell consumes published, authorized capability and preference contracts; it does not consume Theme Doctor draft JSON as runtime truth.

### Studio authoring and diagnostic tools

Studio tools may inspect, preview, diagnose, and eventually propose governed Appearance changes. Authoring requires a future preview, diff, approval, snapshot, apply, and rollback workflow. Theme Doctor remains diagnostic-only at this checkpoint.

### Special Effects

Special Effects owns effect-profile requests and policy, the independent effects-enabled state, effect fallbacks, and effect/motion readiness. Liquid Glass belongs here. Special Effects does not own semantic palette colors.

### Compiler and publisher

The compiler/publisher will eventually validate and publish structured Appearance capabilities from approved owner artifacts. It does not gain new inputs or behavior in this checkpoint, and presets must not become a second token source.

### Runtime consumers

Runtime consumers apply only resolved, published Appearance state. They must not infer authoring truth from Studio draft inventory or bypass Shell and platform ownership contracts.

## Checkpoint boundary

This contract freezes vocabulary and ownership only. It introduces no runtime behavior change and makes no CSS, selector, settings, persistence, compiler, browser-storage, published-options, or visual-output change.
