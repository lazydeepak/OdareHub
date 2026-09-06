# Theme Architecture V1 Production Readiness Verification (2026-06-05)

Status: Verification audit only (no runtime behavior changes)
Scope: Source layer, compile/publish layer, runtime consumption layer

## 1. Architecture Reality

Theme Architecture V1 is partially enforced in production runtime, but not exclusively enforced.

Observed runtime model today:

resources/themes/**
-> compiler (manual or runtime-triggered)
-> public/assets/theme.css
-> runtime consumption

plus an active bypass path:

CSS Token Editor save
-> direct write to public/assets/theme.css
-> runtime consumption

Evidence:

- Contract declares source as canonical and theme.css as runtime artifact/output:
  - docs/architecture/theme-source-compilation-migration.md
- Runtime serves /assets/theme.css from public/assets/theme.css and can auto-compile before serving:
  - public/index.php (route /assets/theme.css, shouldRecompileThemeCss(), maybeRecompileThemeCss())
- Global style registry loads /assets/theme.css as global theme stylesheet:
  - apps/Shell/Services/StyleRegistryService.php
- Compiler builds runtime artifact from manifest sources and marks output as generated:
  - scripts/assets/compile_theme_sources.php
  - public/assets/theme.css ("GENERATED FILE" header)
- CSS Token Editor save writes directly to runtime artifact:
  - apps/Studio/Tools/CssTokenEditor/Services/CssTokenEditorSaveService.php (THEME_CSS_PATH '/public/assets/theme.css', file_put_contents)

Conclusion:

- Running system truth is currently dual-path: source+compiler path exists and is active, but runtime artifact can also be mutated directly.

## 2. Compliance Findings

### 2.1 Source of truth verification

Finding: Effective source of truth is mixed.

- Architecturally intended source of truth: resources/themes/** + manifest.
- Operational runtime truth: public/assets/theme.css is what runtime actually consumes.
- Because CSS Token Editor can directly mutate public/assets/theme.css, runtime artifact can become de-facto immediate source of truth.

Assessment: both are active in practice.

### 2.2 Runtime consumption verification

Finding: Runtime consumes compiled artifact path /assets/theme.css backed by public/assets/theme.css.

Evidence:

- /assets/theme.css handler serves public/assets/theme.css:
  - public/index.php
- Stylesheet registry references /assets/theme.css globally:
  - apps/Shell/Services/StyleRegistryService.php

Can runtime bypass compiler output?

- Yes. If public/assets/theme.css is directly edited, runtime serves edited content unless a recompile trigger condition is met.

### 2.3 Compiler verification

Is compiler mandatory?

- As a mechanism: yes (it is the only builder from resources/themes to runtime artifact).
- As a manual operator step: not always, because runtime can invoke compiler on-demand for /assets/theme.css.

Can source changes reach runtime without manual compiler execution?

- Yes. Runtime path can auto-run compiler when shouldRecompileThemeCss() detects stale/missing artifact.

Can runtime changes bypass source files?

- Yes. Direct edits to public/assets/theme.css (including CSS Token Editor save path) bypass source files.

### 2.4 Studio tool verification

#### CSS Token Editor

Reads:

- Selector model from resources/themes sources first, with fallback to public/assets/theme.css:
  - apps/Studio/Controllers/StudioController.php (buildCssTokenEditorModel(), resolveThemeSourceFiles(), readThemeSourceTokenSelectors(), readThemeTokenSelectors())
- Live fetch target for UI refresh is /assets/theme.css:
  - apps/Studio/Tools/CssTokenEditor/Views/preview.php
  - apps/Studio/Tools/CssTokenEditor/assets/css_token_editor.js

Writes:

- Writes directly to public/assets/theme.css with backup:
  - apps/Studio/Tools/CssTokenEditor/Services/CssTokenEditorSaveService.php

Classification: C. Violates architecture.

Reason:

- V1 contract states runtime artifact must not be canonical authoring source; this tool persists edits directly to runtime artifact.

#### Theme Tool

Reads:

- Runtime selector snapshot from public/assets/theme.css for preview model:
  - apps/Studio/Controllers/StudioController.php (buildThemeToolPreviewModel(), readThemeTokenSelectors())
- Registry/draft metadata from storage and draft JSON:
  - apps/Studio/Tools/ThemeTool/Services/ThemeRegistryReaderService.php

Writes:

- Draft JSON only under apps/Studio/Tools/ThemeTool/Themes/*.json:
  - apps/Studio/Tools/ThemeTool/Services/ThemeDraftMutationService.php

Classification: B. Transitional.

Reason:

- Does not write runtime artifact or source theme files, but still relies on runtime artifact for token preview context.

#### Visual Customizer

Reads/Writes relevant to theme artifact path:

- No evidence of direct public/assets/theme.css writes.
- Draft storage write exists for Visual Customizer draft artifacts, not theme.css:
  - apps/Studio/Tools/CustomizationStudio/Services/VisualCustomizerDraftStorageService.php

Classification: B. Transitional (adjacent chain, not direct theme-source compiler consumer).

### 2.5 Runtime artifact protection verification

Can users/tools directly edit theme.css?

- Yes (tool-mediated): CSS Token Editor save endpoint writes it directly.

Is theme.css treated as generated output?

- Yes by compiler and artifact marker comments.
- But not fully protected from becoming write target because CSS Token Editor still writes to it.

Is generated output protected from source-of-truth drift?

- Partially. Runtime has auto-compile bridge and generated markers, but direct-write path remains.

### 2.6 Theme layer verification

Requested layers verified:

- Foundation layer active: source comment and tokens present in public/assets/theme.css.
- Semantic layer active: source comment and tokens present.
- Variant layers active: light/dark plus style variants liquid-glass/paper present.

Evidence:

- public/assets/theme.css contains source blocks:
  - source: foundation (foundation.css)
  - source: semantic (semantic/semantic.css)
  - source: light (light.css)
  - source: dark (dark.css)
  - source: liquid-glass (liquid-glass.css)
  - source: paper (paper.css)
- Manifest enables these sources and disables navy/obsidian/custom.my-theme:
  - resources/themes/theme-manifest.json

Any decorative/unused layer?

- Root-level active layers are used by compiler/runtime.
- Structured subdirectories (foundation/, variants/, contracts/) appear largely contractual/migration scaffolding in this phase; active compilation input is manifest-listed paths.

### 2.7 Failure scenario behavior (logical verification)

If public/assets/theme.css is deleted:

- /assets/theme.css request triggers maybeRecompileThemeCss(); shouldRecompileThemeCss() returns true when runtime artifact missing; compiler runs with --apply and attempts to regenerate.
- If compile succeeds, runtime serves regenerated file.
- If compile fails, runtime logs error and does not serve theme CSS from this route.

If source token changes (resources/themes/**):

- shouldRecompileThemeCss() compares source/manifest/compiler mtimes with runtime artifact and triggers compile when source is newer.
- Runtime request then serves updated compiled artifact.

If compiler does not run:

- With existing artifact present, runtime continues serving current public/assets/theme.css.
- Source changes do not become visible until compiler executes (manual or runtime-triggered).

If CSS Token Editor saves:

- Service writes directly to public/assets/theme.css and creates backup.
- Runtime immediately consumes changed artifact via /assets/theme.css, independent of source files.

## 3. Violations

1. CSS Token Editor direct runtime artifact mutation violates V1 end-state boundary.
   - Evidence: apps/Studio/Tools/CssTokenEditor/Services/CssTokenEditorSaveService.php

2. Runtime artifact can become operational source-of-truth through direct-write path.
   - Evidence: direct file_put_contents to /public/assets/theme.css and runtime serving that path.

## 4. Transitional Areas

1. Runtime auto-compile bridge in public/index.php is transitional support that reduces manual publish burden but keeps runtime coupled to compilation triggers.
2. Theme Tool is draft-oriented and non-runtime-authoritative (good), but reads runtime artifact for preview model context.
3. Style customization chain docs still describe CSS Token Editor as standalone runtime-artifact editor.

## 5. Production Readiness Classification

Classification: C. Architecture Correct But Operational Debt Exists.

Why:

- Correct:
  - Source->compile->runtime path is implemented and active.
  - Runtime does consume /assets/theme.css artifact.
  - Foundation/semantic/variant layering is active in compiled output.
- Operational debt:
  - Direct-write path to runtime artifact remains active via CSS Token Editor.
  - This prevents exclusive enforcement of source-only authoring contract.

## 6. Recommended Next Action

Single next action (no implementation in this audit):

- Align CSS Token Editor persistence boundary to Theme Architecture V1 so save path updates source-layer artifacts (or governed source mutation flow) instead of direct public/assets/theme.css writes, while preserving current safety checks and backup semantics.

This would eliminate dual-truth drift and move classification from C toward B/A.
