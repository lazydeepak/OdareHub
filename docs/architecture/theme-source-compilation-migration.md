# OdareHub Theme Architecture Contract v1

Status: Locked architecture v1 (governed migration in progress)
Date: 2026-06-05
Owner: Studio + Asset Publisher + Shell runtime contract

## 1. Architecture Statement

Theme architecture is three-layered:

1. **Source layer**: canonical authoring source for theme values.
2. **Compile/publish layer**: deterministic compilation from source to runtime artifact.
3. **Runtime consumption layer**: consumers read variables from compiled output only.

Canonical rule:

**Themes own values, Shell owns selectors, Studio owns governed editing, and runtime consumes compiled results.**

## 2. Layer Contracts

### 2.1 Source layer

Source authoring lives under `resources/themes/**` and manifest metadata.

Contains:

1. Foundation/global tokens (neutral primitives).
2. Semantic/system tokens (UI intent tokens).
3. Theme variant overrides.

`system-*` behavior is runtime resolution of light/dark mode and must not duplicate separate source variant files.

### 2.2 Compile/publish layer

Compiler reads source + manifest and publishes runtime artifact.

Responsibilities:

1. Validate source shape/existence.
2. Resolve inheritance/ordering.
3. Merge foundation + semantic + variant override.
4. Publish runtime output artifact(s).
5. Optionally emit compatibility artifact for legacy consumers.

### 2.3 Runtime consumption layer

Shell/Setup/Login/Studio/apps consume compiled variables; they do not own theme truth.

Rules:

1. Component CSS stays in owner surface stylesheets.
2. Component styles consume `var(--token-name)`.
3. Runtime loads active theme preference and reads compiled output.
4. Runtime must not invent/persist theme values independently.

## 3. Ownership Separation

### A. Theme source owns values

Examples: token values, semantic aliases, shadows, blur intensity, variant-specific overrides.

### B. Shell/setup/component styles own selectors

Examples: `.topbar`, `.sidebar`, `.auth-shell`, `.workspace-surface`.

### C. Studio owns governed editing workflow

Studio tools edit source resources or governed drafts and publish via approved flow.
Studio tools must not treat `public/assets/theme.css` as permanent source-of-truth.

## 4. Runtime Artifact Rule

`public/assets/theme.css` may remain permanently only as:

1. compiled runtime artifact
2. compatibility artifact output
3. published cache/output

It must not remain canonical authoring source in v1 end-state.

## 5. Target Structure (v1)

```text
resources/
  themes/
    foundation.css
    light.css
    dark.css
    liquid-glass.css
    paper.css
    theme-manifest.json
    custom/
      *.css

public/
  assets/
    theme.css            # compiled output only
    theme-legacy.css     # optional compatibility output

apps/
  Shell/
    styles/
      *.css              # selector/component ownership
```

## 6. Token Hierarchy (v1)

### 6.1 Foundation tokens

Raw reusable primitives such as spacing, radius, durations, neutral colors, blur levels.

### 6.2 Semantic tokens

Intent-level UI tokens such as surface/text/border/accent/status meanings.

### 6.3 Theme variant overrides

Variant files override only what is needed (not full redefinition).

Primary variant matrix:

1. light-paper
2. light-liquid-glass
3. dark-paper
4. dark-liquid-glass

## 7. Migration Plan (Fast-Track Safe Order)

### Phase 1

Keep runtime behavior unchanged and lock source-of-truth contract.

### Phase 2

Extract non-token selector/component CSS from runtime theme artifact into owner CSS.

### Phase 3

Harden source theme resources and manifest-driven compile contract.

### Phase 4

Retarget Studio editors to source resources only (compatibility bridge allowed during migration).

### Phase 5

Enforce runtime artifact as published output only; deprecate and remove direct editing path.

## 8. Validation Requirements

Required for each migration slice:

1. `git diff --check`
2. `bash scripts/system/check_deployment_readiness.sh`
3. `bash scripts/architecture/run_architecture_gates.sh`
4. Runtime parity verification for extracted selector moves

## 9. Non-Goals in this contract update

This document update does not itself authorize:

1. direct runtime behavior changes
2. immediate selector extraction in this same slice
3. Core changes
4. ungated Studio runtime edits
