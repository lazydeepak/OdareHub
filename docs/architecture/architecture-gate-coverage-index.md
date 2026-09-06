# Architecture Gate Coverage Index / Diagnostics Map

Status: Active read-only architecture diagnostics coverage index.

Purpose: Provide one canonical map of architecture laws to enforcement gates so future hardening stays targeted, non-duplicative, and ownership-correct.

This index is read-only governance guidance. It does not authorize runtime behavior changes, route redesign, Core edits, migrations, or sample-app polish.

Checkpoint debt register and safe-work map:

- `docs/architecture/known-architecture-debt-and-next-safe-work.md`

## Aggregate Gate Order

Aggregate runner:

- `scripts/architecture/run_architecture_gates.sh`

Declared order contract:

- `scripts/architecture/gate-runner-contract.md`

Current aggregate gates:

1. `scripts/architecture/check_core_lock_scope.sh`
2. `scripts/architecture/check_system_app_contracts.sh`
3. `scripts/architecture/check_shell_runtime_menu_boundary.sh`
4. `scripts/architecture/check_shell_css_ownership.sh`
5. `scripts/architecture/check_shell_rendering_contract.sh`
6. `scripts/architecture/check_shell_sidebar_content_geometry_contract.sh`
7. `scripts/architecture/check_asset_registry_integrity.sh`
8. `scripts/architecture/check_operator_confinement.sh`
9. `scripts/architecture/check_display_readonly.sh`
10. `scripts/architecture/check_admin_route_contract.sh`
11. `scripts/architecture/check_studio_boundary.sh`
12. `scripts/architecture/check_studio_enforcement_readiness.sh`
13. `scripts/architecture/check_cte_safety.sh`
14. `scripts/architecture/check_localization_studio_boundaries.sh`
15. `scripts/architecture/check_localization_resource_diagnostics.sh`
16. `scripts/architecture/check_localization_migration_guardrail.sh`
17. `scripts/architecture/check_localization_studio_v2_1_readiness.sh`
18. `scripts/architecture/check_label_designer_boundaries.sh`
19. `scripts/architecture/check_token_impact_explorer_boundaries.sh`
20. `scripts/architecture/check_css_live_editor_preview_boundary.sh`
21. `scripts/architecture/check_report_designer_p1_boundaries.sh`
22. `scripts/architecture/check_customization_studio_boundaries.sh`
23. `scripts/architecture/check_shell_style_catalog_boundaries.sh`
24. `scripts/architecture/check_platform_style_registry_boundaries.sh`
25. `scripts/architecture/check_style_chain_parity.sh`
26. `scripts/architecture/check_shell_style_consumption_boundary.sh`
27. `scripts/architecture/check_read_only_consumption_probe_boundaries.sh`
28. `scripts/architecture/check_platform_style_consumer_boundary.sh`
29. `scripts/architecture/check_shell_consumption_boundary.sh`
30. `scripts/architecture/check_platform_style_consumption_surface_boundary.sh`
31. `scripts/architecture/check_runtime_style_application_boundary.sh`
32. `scripts/architecture/check_shell_insertion_boundary.sh`
33. `scripts/architecture/check_rendered_admin_proof_boundary.sh`
34. `scripts/architecture/check_resolved_experience_truth.sh`
35. `scripts/architecture/check_surface_contribution_contracts.sh`
36. `scripts/architecture/check_navigation_composition_duplicates.sh`
37. `scripts/architecture/check_migration_debt_regressions.sh`
38. `scripts/architecture/check_capability_ownership_boundaries.sh`
39. `scripts/architecture/check_business_app_module_contracts.sh`
40. `scripts/architecture/check_shared_app_extension_contract.sh`
41. `scripts/architecture/check_parties_contract.sh`
42. `scripts/architecture/check_theme_source_integrity.sh`
43. `scripts/architecture/check_theme_runtime_fallback_contract.sh`
44. `scripts/architecture/check_style_compliance_css_ownership.sh`
45. `scripts/architecture/check_windows_checkout_paths.sh`
46. `scripts/architecture/check_first_boot_css_safety.sh`
47. `scripts/architecture/check_operator_responsive_navigation_integrity.sh`
48. `scripts/architecture/check_studio_deletion_family_capability_suite.sh`

## Coverage Map

### 1) `check_core_lock_scope.sh`

- Architecture law protected:
  - Core is locked platform law.
  - Core changes are exceptional and require explicit approval.
- Owner layer responsible:
  - Core + Tooling/System Tools governance.
- Scope scanned:
  - Current git diff and untracked files under `app/`.
- Pass/fail meaning:
  - PASS when no Core diff exists.
  - FAIL when Core diff exists without explicit override.
  - PASS with explicit visible override when `ARCHITECTURE_GATE_ALLOW_CORE=1`.
- Intentionally does not do:
  - Does not mutate Core.
  - Does not validate Core correctness beyond change-scope lock.
- Known warnings/debt:
  - None by default; this is hard-block oriented.

### 2) `check_system_app_contracts.sh`

- Architecture law protected:
  - Shell/Platform system-app ownership contract baseline.
  - Runtime-truth and compatibility policy clarity.
- Owner layer responsible:
  - Shell + Platform + Tooling/System Tools governance.
- Scope scanned:
  - `apps/Shell`, `apps/Platform` manifests, routes, navigation, local AGENTS/docs contract notes.
- Pass/fail meaning:
  - PASS when required baseline files and contract checks are valid.
  - FAIL on contract gaps that break required baseline.
- Intentionally does not do:
  - Does not enforce lifecycle behavior changes at runtime.
  - Does not rewrite manifests.
- Known warnings/debt:
  - Compatibility/lifecycle booleans may remain as transitional flags when policy is documented.

### 3) `check_shell_runtime_menu_boundary.sh`

- Architecture law protected:
  - Shell owns runtime menu composition entry in layout chrome.
  - Core SidebarBuilder remains compatibility implementation behind Shell composition boundary, not called directly by layout runtime.
- Owner layer responsible:
  - Shell runtime boundary + Tooling/System Tools governance.
- Scope scanned:
  - `public/views/layouts/header.php` and `apps/Shell/Services/ShellRuntimeMenuComposer.php` for boundary contract references and direct-Core invocation regressions.
- Pass/fail meaning:
  - FAIL when header bypasses Shell runtime composer or directly invokes Core SidebarBuilder.
  - PASS when header composes via Shell runtime composer and compatibility metadata remains explicit.
- Intentionally does not do:
  - Does not change runtime menu behavior.
  - Does not migrate Core SidebarBuilder implementation ownership.
- Known warnings/debt:
  - Core SidebarBuilder compatibility usage remains known migration debt until explicitly approved retirement.

### 4) `check_shell_css_ownership.sh`

- Architecture law protected:
  - Shell CSS owns generic primitives/chrome only.
  - App/module CSS ownership must not leak into Shell.
- Owner layer responsible:
  - Shell + app/module owners + Tooling/System Tools governance.
- Scope scanned:
  - Shell views/styles and ownership contract docs; selector leakage patterns.
- Pass/fail meaning:
  - FAIL on concrete new ownership violations.
  - PASS with warning when only known compatibility debt remains.
- Intentionally does not do:
  - Does not move CSS ownership.
  - Does not auto-fix style files.
- Known warnings/debt:
  - Existing QR/Timecard selector leakage in Shell CSS is documented compatibility debt.

### 5) `check_shell_rendering_contract.sh`

- Architecture law protected:
  - Shell owns z-index layering, viewport rendering, overlay infrastructure, overflow/scroll behavior, and breakpoint contracts.
  - Theme source files are tokens-only; no rendering selectors (z-index, position, overflow, scroll) are allowed in theme sources.
  - New hardcoded z-index values in Shell CSS must not be introduced without token reference.
  - Overlay classes must be defined only in Shell CSS.
- Owner layer responsible:
  - Shell + Tooling/System Tools governance.
- Scope scanned:
  - `apps/Shell/styles/operator.css`, `components.css`, `admin.css` for overlay classes, z-index values, and breakpoint patterns.
  - `resources/themes/*.css`, `resources/themes/semantic/*.css` for rendering selector violations.
  - `public/views/layouts/footer.php`, `public/views/layouts/header.php`, `apps/Shell/Composers/OperatorSurfaceComposer.php` for overlay infrastructure.
  - Git diff HEAD for regression detection in Shell CSS.
- Pass/fail meaning:
  - FAIL when overlay infrastructure is missing (`.shell-overlay` CSS, HTML container, `__overlayCount` JS, `__setShellOverlayActive` wiring).
  - FAIL when rendering selectors appear in theme source files.
  - FAIL when new hardcoded z-index values are added in Shell CSS git diff.
  - PASS when all V1 foundation invariants hold.
- Intentionally does not do:
  - Does not enforce contract z-index token values — remaining 40 raw values are documented known debt.
  - Does not enforce canonical breakpoint adoption — 26+ non-canonical breakpoints are documented known debt.
  - Does not unify shell models or merge overlay infrastructure.
  - Does not change runtime rendering behavior.
- Known warnings/debt:
  - 40 hardcoded z-index values remain across Shell CSS (only 3 of 43 references use contract tokens).
  - 26+ non-canonical breakpoints remain.
  - Two shell models (admin and operator) have partially duplicated overlay infrastructure.
  - Admin overlay handlers are not yet migrated to the shared overlay API.
  - Sidebar and scroll-lock models remain separate.

### 6) `check_shell_sidebar_content_geometry_contract.sh`

- Architecture law protected:
  - Shell sidebar/content geometry is canonicalized to `shell-sidebar-content-geometry.css`.
  - `.layout-sidebar`, `.app-shell`, `.app-sidebar`, `.u-sidebar`, `.layout-main`, `.content`, `.container`, `.main-content`, and `.u-main` geometry rules (position, sizing, padding, grid templates, flex layout) must live in the canonical geometry file.
  - Geometry must not remain in `shell-navigation.css`, `shell-layout.css`, `shell-forms.css`, `shell-tokens.css`, or `shell-operator.css`.
- Owner layer responsible:
  - Shell + Tooling/System Tools governance.
- Scope scanned:
  - `apps/Shell/styles/shell-sidebar-content-geometry.css` for required selectors, variable references, and breakpoints.
  - `apps/Shell/styles/shell-navigation.css` for geometry remnants (old sidebar position/sizing values).
  - `apps/Shell/styles/shell-layout.css`, `shell-forms.css`, `shell-tokens.css`, `shell-operator.css` for padding/grid/flex remnants.
  - `apps/Shell/styles/shell.css` for proper import.
  - `apps/Shell/manifest.json` for registered style key.
  - PHP probe result for comprehensive validation.
- Pass/fail meaning:
  - FAIL when canonical file is missing or missing required selectors/variables.
  - FAIL when geometry remnants detected in the stripped source files.
  - FAIL when shell.css does not import canonical file.
  - FAIL when manifest.json does not register canonical key.
  - PASS when all invariants hold.
- Intentionally does not do:
  - Does not move CSS between files — structural extraction is manual.
  - Does not change runtime rendering behavior.
  - Does not enforce variable usage in non-geometry files.
- Known warnings/debt:
  - Responsive breakpoints remain duplicated in the canonical file — future unification needed.

### 7) `check_asset_registry_integrity.sh`

- Architecture law protected:
  - Manifest-declared CSS source ownership and delivery integrity.
  - Generated public assets are delivery copies, not source truth.
- Owner layer responsible:
  - App/module owners + Shell + Tooling/System Tools governance.
- Scope scanned:
  - App/module manifest style entries, source paths, public delivery targets, asset publisher docs.
- Pass/fail meaning:
  - FAIL on malformed/missing critical source/target contract errors.
  - PASS with warnings for non-fatal asset drift/missing delivery copies.
- Intentionally does not do:
  - Does not publish assets.
  - Does not create dummy files.
- Known warnings/debt:
  - Source/public asset drift can be warning-level until refreshed by owner workflow.

### 8) `check_operator_confinement.sh`

- Architecture law protected:
  - Operator surfaces remain confined to `/u/{username}/*`.
- Owner layer responsible:
  - Shell operator wrapper + app/module contribution owners.
- Scope scanned:
  - Shell/app/module operator views/composers/services for route/action escapes.
- Pass/fail meaning:
  - FAIL on unsafe operator route escapes to admin/app raw surfaces.
  - PASS when confinement holds.
- Intentionally does not do:
  - Does not rewrite links/forms/actions.
- Known warnings/debt:
  - Admin-switch compatibility affordances may remain when documented.

### 9) `check_display_readonly.sh`

- Architecture law protected:
  - Display layer is readonly and actionless.
- Owner layer responsible:
  - Shell display wrapper + app/module display contribution owners.
- Scope scanned:
  - Display views/composers/services for forms, submit handlers, mutation patterns, route/action escapes.
- Pass/fail meaning:
  - FAIL when mutation affordances or unsafe actions exist.
  - PASS when readonly contract holds.
- Intentionally does not do:
  - Does not alter display implementations.
- Known warnings/debt:
  - None expected; this is primarily fail-on-violation.

### 10) `check_admin_route_contract.sh`

- Architecture law protected:
  - Canonical admin routing and compatibility alias boundaries.
- Owner layer responsible:
  - Shell routing/wrapper contract + Tooling/System Tools governance.
- Scope scanned:
  - `public/index.php`, `apps/Shell/routes.php`, wrapper registry, admin/operator emitters.
- Pass/fail meaning:
  - FAIL on missing canonical contracts or invalid primary `/me` emissions.
  - PASS when canonical admin and compatibility-only alias behavior remain intact.
- Intentionally does not do:
  - Does not redesign `/admin` or `/me` behavior.
- Known warnings/debt:
  - `/me` remains documented compatibility alias and should not become primary again.

### 11) `check_studio_boundary.sh`

- Architecture law protected:
  - Studio remains optional app/worker and does not become runtime owner.
- Owner layer responsible:
  - Studio + Shell + Platform + Tooling/System Tools governance.
- Scope scanned:
  - Studio ownership/dependency boundaries, bridge patterns, runtime dependency regressions.
- Pass/fail meaning:
  - FAIL on concrete Studio ownership/runtime coupling violations.
  - PASS when boundary and bridge constraints hold.
- Intentionally does not do:
  - Does not implement Studio features or migration.
- Known warnings/debt:
  - Compatibility bridge patterns may remain while documented.

### 12) `check_studio_enforcement_readiness.sh`

- Architecture law protected:
  - Studio governance-readiness contracts before apply expansion.
- Owner layer responsible:
  - Studio + System Tools governance + owner-layer contracts.
- Scope scanned:
  - Studio docs/manifests/readiness contracts for risk/approval/snapshot/rollback/handover context.
- Pass/fail meaning:
  - FAIL on missing required readiness contract coverage.
  - PASS with warnings for non-fatal readiness gaps.
- Intentionally does not do:
  - Does not enforce live apply behavior at runtime.
- Known warnings/debt:
  - Legacy Studio compatibility alias/bridge may remain with explicit marking.

### 13) `check_cte_safety.sh`

- Architecture law protected:
  - CSS Token Editor critical invariants must not regress.
  - CSS fetch URL must come from server, not hardcoded path.
  - `updateTokenStatus()` must not write to `tokenValues` (event listener is sole writer).
  - `selectorField.value` must be initialized on load.
  - `(*NO_JIT)` must remain in save service PCRE regex.
  - Save authority is server-side only (POST form, no client-side file writes).
- Owner layer responsible:
  - Studio CTE tool owner + Tooling/System Tools governance.
- Scope scanned:
  - `apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/assets/css_token_editor.js`
  - `apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/Services/CssTokenEditorSaveService.php`
  - `apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/Views/preview.php`
  - `docs/architecture/css-token-editor-safety-checkpoint.md`
- Pass/fail meaning:
  - FAIL when any critical invariant is broken (hardcoded fetch URL, `tokenValues` write in `updateTokenStatus()`, missing `(*NO_JIT)`, missing selector init, client-side save mechanism).
  - PASS when all invariants hold.
- Intentionally does not do:
  - Does not validate CSS parsing correctness or token value semantics.
  - Does not check feature completeness of the CTE UI.
- Known warnings/debt:
  - None.

### 14) `check_localization_studio_boundaries.sh`

- Architecture law protected:
  - Localization Studio is a governed Studio worker, not a source of truth for translations.
  - Locale ownership follows view ownership; apps/modules own their locale files.
  - Studio must not write, apply, or mutate locale files in v1 (read-only diagnostics only).
  - Core locale behavior must not be changed by Studio.
  - No generated locale cache may become runtime source truth.
- Owner layer responsible:
  - Studio LocalizationStudio tool owner + app/module locale owners + Tooling/System Tools governance.
- Scope scanned:
  - `apps/Studio/Tools/LocalizationStudio/` placeholder folder, tool.json, README.md.
  - `docs/architecture/localization-studio-v1-foundation.md` architecture document.
  - `docs/architecture/localization-studio-v1-checkpoint.md` v1 completion checkpoint.
  - `docs/architecture/localization-studio-v2-edit-apply-contract.md` v2 architecture contract (planning only).
  - `docs/architecture/localization-studio-v2-1-readiness-diagnostic-plan.md` v2.1 readiness diagnostic plan (planning only).
  - Shell/Platform/Core runtime files for references to Studio localization drafts.
  - Git diff for Core locale changes and locale file ownership violations.
  - Locale cache patterns under `var/`, `storage/`, `public/assets/`.
- Pass/fail meaning:
  - FAIL when any of: missing placeholder folder/tool.json/README/doc, invalid tool.json JSON, save/apply/write behavior present in LocalizationStudio files, runtime code references LocalizationStudio drafts, Core locale files modified, locale files stored inside LocalizationStudio folder, untracked locale cache files exist, or server-side file generation/download behavior present.
  - PASS when all v1 foundation invariants hold (currently 51 invariants checked).
- Intentionally does not do:
  - Does not validate individual locale file content correctness or key naming conventions.
  - Does not check cross-language key parity (covered by future inspection phase).
  - Does not propose or apply locale edits.
  - Does not change runtime translation loading behavior.
- Known warnings/debt:
  - None expected; this is primarily fail-on-violation for v1 foundation invariants.

### 15) `check_localization_resource_diagnostics.sh`

- Architecture law protected:
  - Locale ownership follows view ownership; apps/modules own their locale files.
  - Locale file content integrity is a read-only diagnostic concern, not a runtime enforcement concern.
  - Studio must not write or apply locale files; content validation remains diagnostic-only.
- Owner layer responsible:
  - Studio LocalizationStudio diagnostic tool + app/module locale file owners + Tooling/System Tools governance.
- Scope scanned:
  - All locale files under `app/lang/`, `apps/*/lang/`, `apps/*/modules/*/lang/`, `apps/Studio/Tools/*/lang/`, `plugins/*/lang/`.
  - Per-file checks: parse validity, duplicate keys, key naming convention, dangerous values, boundary violations.
  - Cross-locale parity: missing keys per language per owner.
- Pass/fail meaning:
  - FAIL on parse errors, unsupported locale codes, or files outside known ownership paths.
  - PASS with warnings when duplicate keys, naming convention violations, dangerous values, or cross-locale key mismatches are detected.
  - Warnings do not block the gate; errors do.
- Intentionally does not do:
  - Does not validate translation accuracy or semantic correctness.
  - Does not detect orphaned keys (keys in locale file but unused in source code).
  - Does not detect placeholder mismatch between translation values and templates.
- Does not propose, apply, or generate locale file content.
- Does not modify any file, DB, or cache.
- Known warnings/debt:
  - Key naming convention checks may produce false positives for tool-specific keys using flat naming patterns.

### 16) `check_localization_migration_guardrail.sh`

- Architecture law protected:
  - Canonical `Resources/lang/` path is the only allowed location for new locale files.
  - Legacy `lang/` paths are frozen — no new files may be added.
- Owner layer responsible:
  - App/module/plugin locale owners + Tooling/System Tools governance.
- Scope scanned:
  - Git staged, unstaged, and untracked files matching legacy path patterns: `apps/*/lang/*.php`, `apps/*/modules/*/lang/*.php`, `plugins/*/lang/*.php`, `apps/Studio/Tools/*/lang/*.php`.
- Pass/fail meaning:
  - FAIL when new staged or unstaged legacy-path locale files are detected.
  - PASS with warnings when only untracked legacy-path locale files exist (author wants to add — migrate instead).
- Intentionally does not do:
  - Does not remove legacy files.
  - Does not migrate existing legacy files.
  - Does not enforce canonical path usage for already-existing legacy files.
- Known warnings/debt:
  - Existing legacy files are documented migration debt; only NEW additions are blocked.

### 17) `check_localization_studio_v2_1_readiness.sh`

- Architecture law protected:
  - No implementation before gate readiness — v2 edit/apply implementation must not appear before its readiness level gates pass.
  - Read-only is the default state; unauthorized mutation patterns must be detected and blocked.
  - Runtime isolation is permanent — Studio draft/proposal/snapshot state must never be consumed by runtime.
- Owner layer responsible:
  - Studio LocalizationStudio tool owner + Tooling/System Tools governance.
- Scope scanned:
  - `apps/Studio/routes.php` for save/apply/write/submit/update/delete route patterns for localization-studio.
  - All view templates under `apps/Studio/Tools/LocalizationStudio/Views/` for form actions pointing to save/apply/write/submit/update/delete endpoints, download attributes, Blob URL creation, and form POST/PUT/DELETE.
  - All service files under `apps/Studio/Tools/LocalizationStudio/Services/` for translation update/apply/import/export/generate functions, HTTP clients, DB INSERT/UPDATE, and cache store calls.
  - Runtime paths (`app/`, `apps/Shell/`, `apps/Platform/`) for coupling to LocalizationStudio draft/apply/snapshot state.
  - DB table reference patterns and migration files for locale/translation/proposal artifacts.
  - Studio tool policy for apply/save/v2 enable flags.
  - `tool.json` for `runtime_behavior` and v2 enable fields.
- Pass/fail meaning:
  - FAIL when any route, form action, service function, runtime coupling, DB reference, migration, policy flag, or config change indicates unauthorized early implementation of v2 edit/apply behavior.
  - PASS when all 35 invariants hold — v2.1 readiness is verified, no implementation has started.
- Intentionally does not do:
  - Does not duplicate the v1 boundary gate (`check_localization_studio_boundaries.sh`) — this is a focused v2.1 readiness gate.
  - Does not validate locale file content — that is the resource diagnostic's scope.
  - Does not enable or allow any v2 edit/apply behavior.
  - Does not mutate any file, DB, cache, or runtime state.
- Known warnings/debt:
  - View template checks scan only localized locale-related strings. Non-localized strings outside LocalizationStudio are out of scope.
  - DB pattern checks use regex matching; SQL patterns embedded in strings may generate false positives.

### 18) `check_label_designer_boundaries.sh`

- Architecture law protected:
  - Label Designer remains a generic Studio builder/editor for owner-owned label resources.
  - Apps/modules/plugins remain source of truth for label contexts, templates, rules, allowed fields, print entry points, and runtime label meaning.
  - Label Designer may inspect owner-scoped candidate DB metadata, execute guarded context-create flow for owner context resources, and expose a disabled/read-only template creation preview foundation; it must not become a generic DB browser, SQL builder, unrestricted field-binding store, or runtime source of truth.
  - Direct owner-resource editing is allowed only through guarded context create with snapshot-before-write, validation, confirmation, diagnostics, and rollback metadata.
- Owner layer responsible:
  - Studio Label Designer tooling + app/module/plugin label resource owners + Tooling/System Tools governance.
- Scope scanned:
  - `apps/Studio/Tools/LabelDesigner/` for path confinement, manifest state, guarded context-create forms, disabled/read-only template-preview controls, context-create service write boundaries, template-preview service no-write boundaries, DB access outside the dedicated read-only data-source discovery service, SQL/table browser patterns, print/export execution, and source-of-truth drift.
  - `apps/Studio/routes.php` for approved Label Designer context-create POST endpoint and regressions.
  - `docs/architecture/label-designer-operating-contract.md`, `docs/architecture/label-resource-contract.md`, `docs/architecture/label-designer-apply-snapshot-safety-contract.md`, and `docs/architecture/label-designer-template-apply-snapshot-safety-contract.md` for required resource contracts, standard owner paths, source-of-truth language, apply lifecycle, snapshot metadata, target path rules, and future apply safety.
  - Core, Manufacturing, Platform QR, and Platform print/style registry paths for runtime coupling back to Label Designer.
  - Migration paths for premature Label Designer/label resource DB tables.
- Pass/fail meaning:
  - FAIL when the apply/snapshot safety contract is missing or incomplete, when Label Designer writes outside owner context path boundaries, when template/rule mutation is introduced, or when unrestricted DB browsing, SQL builder behavior, runtime print/export execution, QR/Manufacturing/Platform/Core runtime coupling, or Studio-owned permanent label resource truth appears.
  - PASS when Label Designer exposes owner-filtered candidate DB metadata, guarded context-create flow with snapshot-before-write, and disabled/read-only template preview foundation, while keeping template/rule writes and runtime coupling blocked.
- Intentionally does not do:
  - Does not implement unrestricted DB discovery or approved source binding.
  - Does not implement template/rule create/edit/save/apply.
  - Does not render, export, or print labels.
  - Does not mutate runtime state, owner template/rule files, DB tables, public assets, or Core.
- Known warnings/debt:
  - Manufacturing QR Product Label remains visible as a reference implementation only.
  - Pattern checks are conservative; future authorized features should update the contract and gate narrowly rather than weakening owner-boundary checks broadly.

### 19) `check_token_impact_explorer_boundaries.sh`

- Architecture law protected:
  - Token Impact Explorer is read-only discovery only.
  - It does not write files, mutate theme.css, call StyleRegistry runtime, or introduce Shell consumption.
  - It must not become a source of truth for token runtime consumption.
- Owner layer responsible:
  - Studio Token Impact Explorer tooling + Tooling/System Tools governance.
- Scope scanned:
  - `apps/Studio/Tools/CustomizationStudio/Diagnose/TokenImpactExplorer/` for read-only manifest contract (can_modify false, writes_to_owner_artifact false), no POST routes, no file write API, no DB operations, and no Style Registry runtime coupling.
  - `apps/Studio/routes.php` for GET-only route registration.
  - `apps/Studio/Controllers/StudioController.php` for controller method presence.
- Pass/fail meaning:
  - FAIL when POST routes, file write calls, DB operations, or Style Registry coupling appear.
  - PASS when the tool declares and enforces full read-only inspection behavior.
- Intentionally does not do:
  - Does not modify theme.css, CSS files, or any source file.
  - Does not call StyleRegistry::getValue() or any runtime consumption API.
  - Does not persist scan results or create a token usage database.
  - Does not connect to Shell or any app runtime.
- Known warnings/debt:
  - Token usage scanning is on-demand and may be slow for large codebases.
  - Runtime tokens are discovered by scanning CSS files and published assets; tokens defined entirely in inline styles or DB-driven themes may not appear.

### 20) `check_css_live_editor_preview_boundary.sh`

- Architecture law protected:
  - CSS Live Editor is a read-only click-inspector tool with no save/apply/edit controls.
  - Preview feed must be static-fixture-backed, not live runtime content.
  - No DB access, no file writes, no arbitrary template path loading.
  - CSS source discovery is owner-confined to approved roots.
  - Preview iframe is restricted to same-origin only (no scripts, popups, top navigation, or forms).
- Owner layer responsible:
  - Studio CssLiveEditor tooling + Tooling/System Tools governance.
- Scope scanned:
  - `apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/` for provider fixture declaration, service confinement, approved view roots, owner-confined CSS candidate paths, no header redirects to runtime routes, no DB access, no client-side persistence or network API outside iframe navigation, and no edit/save/apply behavior in JS.
  - `apps/Studio/Controllers/StudioController.php` for CSP form-action none, provider lookup, feed rendering, template revalidation, and CSS source catalog.
  - Preview iframe sandbox attribute for allow-same-origin only (no allow-scripts, allow-top-navigation, allow-popups, allow-forms).
  - Declaration preview for read-only `<pre>` display (no contenteditable, no textarea).
- Pass/fail meaning:
  - FAIL when provider lacks static fixture data mode, preview endpoint lacks CSP form-action block, CSS source catalog is missing owner path confinement, iframe sandbox allows scripts/popups/top-navigation/forms, declaration preview uses editable controls, JS has edit/save/apply behavior in highlight/preview functions, or any DB/cookie/persistence API appears in CLE files.
  - PASS when all 30+ boundary invariants hold and the tool is fully read-only.
- Intentionally does not do:
  - Does not validate CSSOM rule correctness or selector specificity.
  - Does not check feature completeness of the preview feed.
  - Does not authorize any save, apply, or file-write behavior.
  - Does not validate theme correctness or CSS source content.
- Known warnings/debt:
  - None expected; this is primarily fail-on-violation for read-only boundary invariants.

### 21) `check_report_designer_p1_boundaries.sh`

- Architecture law protected:
  - Report Designer P1 is read-only foundation preparation.
  - It does not add DB migrations, new routes, report execution/export runtime, save/apply/builder UI, or Core changes.
  - ReportDesigner remains the canonical tool — ReportBuilder is legacy-only.
  - ModuleReportRegistryService files are not modified.
- Owner layer responsible:
  - Studio Report Designer P1 tooling + Tooling/System Tools governance.
- Scope scanned:
  - `apps/Studio/Tools/ReportDesigner/` for path confinement, no DB operations, no file writes, no export/print/execution runtime, no Style Registry coupling, no form/submit behavior.
  - `apps/Studio/Tools/ReportBuilder/manifest.php` for legacy-only status.
  - `apps/Studio/routes.php` for single GET route only, no POST routes, no report-builder route.
  - `apps/Studio/Controllers/StudioController.php`, `apps/Studio/Services/StudioToolInstancePolicyService.php`, `apps/Studio/Views/pages/home.php`, `apps/Studio/Views/pages/tool_placeholder.php` for Studio entry references.
  - All ModuleReportRegistryService files for absence of Report Designer coupling.
  - Core, Shell, Platform runtime paths for absence of Report Designer coupling.
  - Migration paths for report designer/ReportResource DB tables.
- Pass/fail meaning:
  - FAIL when DB migrations, POST routes, runtime export/print/execution, Core references, Shell/Platform runtime coupling, save/apply/draft behavior, form/submit controls, or ModuleReportRegistryService modifications appear.
  - PASS when all P1 foundation files are confined to Studio-owned paths and no prohibited behaviors exist.
- Intentionally does not do:
  - Does not authorize any PHP ReportResource class, validation service, compilation service, or contract interface.
  - Does not authorize route, controller, or view changes.
  - Does not authorize DB, migration, or Core changes.
  - Does not authorize Shell consumption or runtime export behavior.
- Known warnings/debt:
  - The existing placeholder view at `Views/index.php` returns a metadata array only — this is the correct baseline.
  - `ReportBuilder/manifest.php` exists as a legacy artifact with `status => 'planned'` and no route.

### 22) `check_customization_studio_boundaries.sh`

### 23) `check_shell_style_catalog_boundaries.sh`

### 24) `check_platform_style_registry_boundaries.sh`

### 25) `check_style_chain_parity.sh`

### 26) `check_shell_style_consumption_boundary.sh`

### 27) `check_read_only_consumption_probe_boundaries.sh`

- Architecture law protected:
  - Read-Only Consumption Probe Contract exists and documents allowed read paths, diagnostic codes, and exit-code model before probe implementation.
  - Future probe at `scripts/platform/probe_resolved_style_consumer.php` must remain diagnostic-only with no Studio coupling, writes, theme compilation, route registration, or DB/HTTP side effects.
- Owner layer responsible:
  - Platform Style Registry + Tooling/System Tools governance.
- Scope scanned:
  - Probe contract doc, aggregate runner wiring, optional future probe script when present.
- Pass/fail meaning:
  - PASS when contract documentation is complete and future probe (if present) respects side-effect prohibitions.
  - PASS when future probe script is absent (gate prep only).
  - FAIL when contract content is missing or future probe violates boundary invariants.
- Intentionally does not do:
  - Does not create or execute the probe script.
  - Does not wire Shell runtime to Platform Style Registry.
  - Does not validate registry/catalog alignment at runtime.
- Known warnings/debt:
  - Probe script is optional until the implementation slice; contract-only checks run when absent.

### 28) `check_platform_style_consumer_boundary.sh`

- Architecture law protected:
  - Platform-owned `ResolvedStyleConsumer` placeholder must remain disabled (no runtime consumption enabled).
  - All platform/Style/ files must not couple to Studio, Shell, DB, HTTP, filesystem writes, shell commands, web routes, or draft/approval artifacts.
  - Registry read access is restricted per file scope: only `ApprovedStyleReaderAdapter` may reference `ApprovedStyleRegistry`, `getValue()`, or registry storage paths. Consumer may only use the `ApprovedStyleReaderContract` interface. Contract file must contain only read methods.
  - Consumer must not expose public consumption API methods (approvedValuePreview, resolveValue, resolvedValue, apply, consume, runtimeStyle, styleMap, tokenMap, css).
  - Comments/docblocks referencing Studio or Shell ownership are allowed; actual imports and calls are blocked.
- Owner layer responsible:
  - Platform Style + Tooling/System Tools governance.
- Scope scanned:
  - All `.php`/`.json` files under `platform/Style/` for invariant groups 3-9 (Studio/Shell/writes/shell/routes/DB/draft).
  - `platform/Style/ResolvedStyleConsumer.php` for disabled status, direct registry coupling, reader injection expectations, and public consumption API methods.
  - `platform/Style/Adapters/ApprovedStyleReaderAdapter.php` for blocked write/governance methods.
  - `platform/Style/Contracts/ApprovedStyleReaderContract.php` for contract purity (no setValue/isWritable/save/approve/delete).
  - Contract existence checks for 6 architecture documents.
- Pass/fail meaning:
  - FAIL when `isRuntimeConsumptionEnabled()` returns true, `$runtimeConsumptionEnabled` is set to true, diagnostics report runtime consumption as true, actual (non-comment) Studio/Shell imports/paths appear, filesystem writes, shell calls, route registration, DB/HTTP access, draft/approval access, or registry path access in non-adapter files appear.
  - FAIL when consumer imports `ApprovedStyleRegistry` directly (must use contract interface only).
  - FAIL when consumer declares any blocked public consumption method (approvedValuePreview, resolveValue, resolvedValue, apply, consume, runtimeStyle, styleMap, tokenMap, css).
  - FAIL when adapter calls `setValue()` or `isWritable()`.
  - FAIL when contract defines write/governance methods.
  - WARN when consumer has not yet been updated to import and use `ApprovedStyleReaderContract` (expected until injection slice).
  - PASS when all invariant groups hold and only expected pre-injection warnings remain.
- Intentionally does not do:
  - Does not enable runtime consumption.
  - Does not block documentation comments that reference ownership boundaries.
  - Does not validate ResolvedStyleConsumer correctness or feature completeness.
  - Does not require reader injection before gate passes (warnings tolerated until injection slice).
  - Does not validate adapter/contract feature completeness (deferred warnings for missing files).
  - Does not validate registry value correctness (probe scope).
- Known warnings/debt:
  - Adapter file checks deferred with warning when `platform/Style/Adapters/ApprovedStyleReaderAdapter.php` does not yet exist.
  - Contract purity checks deferred with warning when `platform/Style/Contracts/ApprovedStyleReaderContract.php` does not yet exist.

### 29) `check_shell_consumption_boundary.sh`

- Architecture law protected:
  - Shell must not directly import `Platform\Style` types (ResolvedStyleConsumer, ApprovedStyleReaderContract, ApprovedStyleReaderAdapter, ApprovedStyleRegistry) before a Platform-owned consumption surface exists.
  - Shell must not call registry read methods (`readValue`, `isReachable`, `getValue`) or access registry storage paths (`storage/platform/style-registry`, `approved-values`).
  - Shell must not mutate theme source (`resources/themes/`) or the compiled theme artifact (`theme.css`) via consumption code paths.
  - Shell must not use dangerous operations (filesystem writes, shell commands, DB connections, HTTP calls, route registration).
- Owner layer responsible:
  - Shell + Tooling/System Tools governance.
- Scope scanned:
  - All `.php` files under `apps/Shell/` — services, composers, controllers, routes, views.
  - `public/views/layouts/*.php` — Shell layout templates (header, footer, auth_header).
  - Contract existence checks for `resolved-style-consumer-contract.md`, `registry-read-contract.md`, `resolved-style-consumer-reader-injection-plan.md`, `shell-consumption-contract.md`.
- Pass/fail meaning:
  - FAIL when Shell files contain non-comment imports of `Platform\Style\ResolvedStyleConsumer`, `Platform\Style\Contracts`, `Platform\Style\Adapters`, or `Apps\Platform\StyleRegistry`.
  - FAIL when Shell files call `getValue(`, `readValue(`, or `isReachable(` in non-comment code.
  - FAIL when Shell files reference `storage/platform/style-registry` or `approved-values`.
  - FAIL when Shell files reference `theme.css` or `resources/themes` in non-comment code.
  - FAIL when Shell files contain `file_put_contents`, `fwrite`, `mkdir`, `unlink`, `rename`, `exec`, `shell_exec`, `proc_open`, `system`, `passthru`, `PDO`, `mysqli`, `curl_`, URL-based `file_get_contents`, `stream_context_create`, or `Route::`.
  - PASS when all invariant groups hold with zero failures.
- Intentionally does not do:
  - Does not block Shell from importing its own services or Platform services outside the Style namespace.
  - Does not block Shell from reading its own CSS files (`apps/Shell/styles/`).
  - Does not block Shell from writing to its own styles during legitimate development (check is consumption-path specific).
  - Does not enable runtime consumption.
  - Does not validate completeness of the Shell Consumption Contract.
  - Does not replace the existing `check_shell_style_consumption_boundary.sh` (which covers Studio/registry/runtime coupling from the Shell Style directory).
- Known warnings/debt:
  - The planned future `Platform Style Consumption Surface` is not yet created. When it exists, the gate should be updated to allow Shell to import `Platform\Style\Consumption\*` and block all other Platform/Style imports.

### 30) `check_platform_style_consumption_surface_boundary.sh`

- Architecture law protected:
  - Platform Style Consumption Surface is Platform-owned (`platform/Style/Consumption/`).
  - Surface must remain read-only: no filesystem writes, no shell commands, no DB/HTTP, no routes, no theme/Shell CSS mutation.
  - Surface must not depend on Studio (`Apps\Studio`), Shell (`Apps\Shell`), or registry directly (`Apps\Platform\StyleRegistry`, `storage/platform/style-registry`, `approved-values`).
  - Surface must use typed accessors (`radiusScale()`) not generic reads (`readValue`, `getValue`, `valueFor`).
  - Surface must not expose consumption API methods (`styleMap`, `tokenMap`, `css`, `apply`, `consume`, `resolve`).
  - `isRuntimeConsumptionEnabled()` must return `false` until Shell integration contract completes.
  - No setValue, isWritable, draft, or approval access on the consumption surface.
  - No theme compilation, `theme.css`, `resources/themes`, `public/assets`, or `apps/Shell/styles` references.
- Owner layer responsible:
  - Platform + Tooling/System Tools governance.
- Scope scanned:
  - `docs/architecture/platform-style-consumption-surface-contract.md` — contract existence and integrity.
  - `platform/Style/Consumption/*` — surface implementation files (optional; absent is valid pre-implementation).
  - `platform/Style/Consumption/StyleConsumptionSurface.php` — allowed public methods, blocked methods, runtime disabled state, dependency rules, forbidden operations, positive expectations.
- Pass/fail meaning:
  - FAIL when consumption surface contract is missing.
  - FAIL when surface implementation files exist outside `platform/Style/Consumption/`.
  - FAIL when surface declares blocked methods (`readValue`, `getValue`, `valueFor`, `styleMap`, `tokenMap`, `css`, `apply`, `consume`, `resolve`, `setValue`, `isWritable`).
  - FAIL when `isRuntimeConsumptionEnabled()` returns `true`.
  - FAIL when surface imports `Apps\Platform\StyleRegistry`, `Apps\Studio`, or `Apps\Shell`.
  - FAIL when surface performs filesystem writes, shell commands, DB access, HTTP calls, or route registration.
  - FAIL when surface references `draft`, `approval`, `theme.css`, `resources/themes`, `public/assets`, or `apps/Shell/styles`.
  - PASS WITH WARNINGS when implementation is absent (expected pre-implementation).
  - PASS when all invariant groups hold with zero failures and implementation is present.
- Intentionally does not do:
  - Does not implement the consumption surface.
  - Does not inject the surface into Shell composers.
  - Does not enable runtime consumption.
  - Does not generate CSS or mutate theme files.
  - Does not validate Shell integration (covered by gate #28 with future allowlist update).
  - Does not validate registry read correctness (covered by gate #27).
- Known warnings/debt:
  - Gate emits warnings when implementation directory is absent (expected pre-implementation).
  - When Surface is implemented, warnings are replaced by passing or failing checks.
  - Shell gate #28 must be updated to allow `Platform\Style\Consumption\StyleConsumptionSurface` in 3 allowlisted composer files after this gate passes.

### 31) `check_runtime_style_application_boundary.sh`

- Architecture law protected:
  - Runtime style application remains Platform-owned under `platform/Style/Runtime/`.
  - Phase 1 is limited to `radius.scale` values (`sharp`, `soft`, `round`) mapped only to `--corner-radius`.
  - The future application class may depend only on `Platform\Style\Consumption\StyleConsumptionSurface`, not consumer, reader, registry, storage, Studio, or Shell internals.
  - Shell runtime wiring remains forbidden until an explicitly authorized integration slice.
- Owner layer responsible:
  - Platform Style runtime boundary + Tooling/System Tools governance.
- Scope scanned:
  - `docs/architecture/runtime-style-application-contract.md`.
  - Optional `platform/Style/Runtime/` directory and future `RuntimeStyleApplication.php`.
  - Shell and layout PHP under `apps/Shell/` and `public/views/layouts/` for premature runtime application wiring.
- Pass/fail meaning:
  - PASS when the contract exists and the implementation directory is absent.
  - FAIL when unexpected implementation files exist, the public API differs from constructor plus `cornerRadius()`, `diagnostics()`, and `isRuntimeApplicationEnabled()`, the sole dependency is not `StyleConsumptionSurface`, the exact Phase 1 mapping/fallback drifts, runtime application becomes enabled, generic token/CSS map engines appear, forbidden dependencies or operations appear, active `RSC-S*` codes are emitted while disabled, or Shell/layout files reference the runtime application.
  - PASS when the implementation has the required namespace/final class, exact API/dependency/mapping, hardcoded disabled flag, placeholder-safe `RSA-*` diagnostics, and all side-effect/Shell boundaries remain intact.
- Intentionally does not do:
  - Does not implement runtime style application.
  - Does not enable runtime consumption or apply approved values.
  - Does not wire Shell, layouts, operators, admin surfaces, CSS, themes, registry, or runtime output.
- Known warnings/debt:
  - The Platform skeleton is compliant (classification A), but runtime application and Shell wiring remain intentionally disabled. Only Shell insertion planning is allowed.

### 32) `check_shell_insertion_boundary.sh`

- Architecture law protected:
  - Future Shell insertion remains a Platform-owned handoff boundary, not direct Shell ownership of registry or style resolution.
  - Phase 1 is restricted to the admin `.layout-main` wrapper and the single `--corner-radius` property.
  - Runtime style application remains disabled and Shell wiring remains absent during gate preparation.
- Owner layer responsible:
  - Platform Style Shell insertion boundary + Shell markup ownership + Tooling/System Tools governance.
- Scope scanned:
  - `docs/architecture/shell-insertion-planning-contract.md`.
  - Optional `platform/Style/ShellInsertion/` directory and future `ShellInsertion.php`.
  - `platform/Style/Runtime/RuntimeStyleApplication.php` for hardcoded disabled state.
  - Shell and layout PHP for premature insertion wiring, plus the selected admin layout target for direct Platform Style bypass imports.
- Pass/fail meaning:
  - PASS when the planning contract exists and the implementation directory is absent.
  - FAIL when unexpected implementation files exist; the public API differs from constructor plus `adminLayoutMainStyle()`, `diagnostics()`, and `isShellInsertionEnabled()`; the sole dependency is not `RuntimeStyleApplication`; `adminLayoutMainStyle()` does not return only `[]`; insertion is not hardcoded disabled; the complete RSI skeleton diagnostic set drifts or claims active insertion; future scope expands beyond admin `.layout-main`; the property or socket allowlist drifts; direct registry/reader/adapter/consumer imports appear; operator/display/workspace/navigation/topbar surfaces appear; mutations or side effects appear; runtime application becomes enabled; or Shell/layout PHP wires the insertion prematurely.
  - PASS when the skeleton has the exact API and constructor dependency, empty style output, hardcoded false insertion flag, exact `RSI-P001`, `RSI-P002`, `RSI-P003`, `RSI-W001`, and `RSI-W002` diagnostics, no active insertion claim, and every existing scope, side-effect, and Shell boundary remains intact.
- Intentionally does not do:
  - Does not implement Shell insertion.
  - Does not modify admin wrapper markup.
  - Does not enable RuntimeStyleApplication.
  - Does not apply styles to rendered pages.
  - Does not mutate CSS, themes, registry state, or public assets.
- Known warnings/debt:
  - The disabled Platform skeleton exists and returns no Shell styles.
  - Operator, display, workspace, navigation, and topbar insertion remain explicitly unauthorized.
  - Legacy Shell style-registry coupling outside the selected admin insertion target remains owned by existing Shell style consumption gates and is not reclassified by this gate.
  - Gate hardening now enforces skeleton behavior structurally rather than relying on marker presence.

### 33) `check_rendered_admin_proof_boundary.sh`

- Architecture law protected:
  - The first rendered proof remains synthetic, process-local, and disconnected from production Shell rendering.
  - Proof scope is limited to admin `.layout-main`, `radius.scale`, `--corner-radius`, and the fixed `4px`/`8px`/`16px` value set.
  - `RuntimeStyleApplication` and `ShellInsertion` remain globally disabled.
- Owner layer responsible:
  - Platform Style proof boundary + Tooling/System Tools governance; Shell retains production wrapper ownership.
- Scope scanned:
  - `docs/architecture/rendered-admin-proof-planning-contract.md`.
  - Optional `platform/Style/Proof/RenderedAdminProof.php`.
  - Optional `scripts/platform/rendered-admin-proof/rendered_admin_proof.php`.
  - Runtime and insertion skeletons for literal disabled flags.
  - Shell and layout PHP for premature proof coupling.
- Pass/fail meaning:
  - PASS when the planning contract exists and both proof directories/artifacts are absent.
  - FAIL when unexpected proof files exist, only one proof artifact exists, proof scope expands beyond the exact admin/socket/property/value allowlist, generic maps or multiple sockets/values appear, forbidden dependencies or direct registry paths appear, mutations or side effects appear, either global enabled flag changes, proof diagnostics do not use all RAP severity families, skeleton/application diagnostics become primary proof diagnostics, or Shell/layout PHP references the proof.
  - PASS when future artifacts use the expected namespace/class/dependencies, remain CLI/test-only and side-effect-free, preserve global disabled flags, and emit proof-local RAP diagnostics.
- Intentionally does not do:
  - Does not create or run a proof.
  - Does not render an HTML fragment.
  - Does not modify Shell, templates, CSS, themes, Registry, or assets.
  - Does not enable runtime application or Shell insertion.
- Known warnings/debt:
  - Both future proof directories are intentionally absent.
  - Rendered fragment structure becomes executable gate coverage only after proof implementation.

### 34) `check_resolved_experience_truth.sh`

### 35) `check_surface_contribution_contracts.sh`

### 36) `check_navigation_composition_duplicates.sh`

### 37) `check_migration_debt_regressions.sh`

### 38) `check_capability_ownership_boundaries.sh`

### 39) `check_business_app_module_contracts.sh`

- Architecture law protected:
  - Business app/module ownership contract baseline and scan-scope stability.
- Owner layer responsible:
  - App/module owners + Tooling/System Tools governance.
- Scope scanned:
  - Reference business app manifests/modules/contracts and scan-scope anchors.
- Pass/fail meaning:
  - FAIL when baseline contract drift is detected.
  - PASS when required ownership baseline remains valid.
- Intentionally does not do:
  - Does not infer runtime feature completeness.
  - Does not mutate app/module manifests or runtime state.
- Known warnings/debt:
  - Reference-app debt may remain documented without blocking unless contract drift appears.

### 40) `check_shared_app_extension_contract.sh`

- Architecture law protected:
  - Shared App is a governance classification of a normal business Domain App (no new runtime type, no suites/ runtime, no extensions/ loader).
  - Shared App metadata (`shared_contract.kind=shared_app`) requires non-empty unique consumers not self-referencing, known-app consumers where reasonable; rejects App-level scope fields `scoped_by_company`, `company_scoped`, `branch_scoped`, `tenant_scope` (scope belongs to entity contracts).
  - App Extension is a normal module under `apps/<Owner>/modules/<Module>/plugin.json` with `ownership_type=app_extension`, `extension_of` (non-empty, != owner, target exists), `extension_key` (unique), forbidden `target_app` duplicate, and valid `required_target_version` syntax if present.
  - Dependency direction Owner → Target is one-way; no extensions directory and no runtime type `shared_app`.
- Owner layer responsible:
  - App/module owners + Platform decision governance + Tooling/System Tools governance.
- Scope scanned:
  - `docs/architecture/business-app-module-ownership-contract.md` for Shared/Extension contract sections and cross-references.
  - `apps/*/manifest.json` for `shared_contract` and forbidden `type=shared_app`.
  - `apps/*/modules/*/plugin.json` for `ownership_type/extension_of/extension_key/target_app` and location.
  - `apps/` filesystem for forbidden `extensions/` directory.
  - Temp fixtures for positive/negative self-validation (missing `extension_of`, self-target, nonexistent target, duplicate key, forbidden `target_app`, self-consumer, duplicate consumers, runtime type, obsolete `scoped_by_company`/`company_scoped`/`branch_scoped`/`tenant_scope`).
- Pass/fail meaning:
  - PASS when contract docs exist, no forbidden runtime surfaces, all present Shared/Extension metadata is valid, no duplicate keys/cycles, and self-validation fixtures pass.
  - FAIL on missing contract sections, forbidden `extensions/` dir or `type=shared_app` or `target_app` field, invalid consumers/self-consumer/unknown consumer, missing `extension_of`/`extension_key`, self-target, unknown target, duplicate key, invalid version syntax, or self-validation failure.
- Intentionally does not do:
  - Does not create Shared App or extension module.
  - Does not change `core_apps`/`core_app_modules`/`core_app_hooks` or business migrations.
  - Does not add company scoping gate or prove DB isolation (belongs with first shared schema).
  - Does not claim runtime suppression of extension when target disabled.
- Known warnings/debt:
  - All current apps/modules have no Shared/Extension metadata (clean pre-enforcement state).
  - Company-isolation enforcement is deferred to first Shared App schema slice.

### 41) `check_parties_contract.sh`

- Architecture law protected:
  - Parties Shared App skeleton is canonical data owner only (no App-level scope, no company/branch/tenant, no findOrCreate, no status/note).
  - Manifest is business type with shared_contract kind shared_app, consumers hospitality/sbaio/procurement, no dependencies on consumers.
  - Migration creates parties(id, party_type nullable person|organization, display_name, email, phone, timestamps) with CHECK constraint, no UNIQUE on contact, no company_id/branch_id/tenant_id/status/note.
  - Service PartyService exposes create/getById/searchCandidates/updateCanonical, no findOrCreate, no auto-dedupe, blank email/phone → null, validates party_type.
  - Docs distinguish canonical vs Hospitality/SBAIO/Procurement owns, no premature party_id outside Parties.
- Owner layer responsible:
  - Parties app owner + App/module owners + Tooling/System Tools governance.
- Scope scanned:
  - `apps/Parties/manifest.json` for Shared App contract and dependencies.
  - `apps/Parties/migrations/001_create_parties.sql` for canonical schema and CHECK/UNIQUE/column invariants.
  - `apps/Parties/Services/PartyService.php` for create/getById/searchCandidates/updateCanonical and no findOrCreate.
  - `apps/Parties/routes.php` for inert entry (no UI, no party_id).
  - `docs/architecture/parties-data-model.md` for canonical vs domain ownership.
  - `apps/Hospitality/`, `apps/SBAIO/`, `apps/Procurement/` for premature party_id.
- Pass/fail meaning:
  - PASS when manifest/migration/service/routes/docs invariants hold, no premature party_id, and service has no findOrCreate.
  - FAIL on missing manifest/migration/service, wrong shared_contract, missing consumers, App-level scope fields, wrong party_type, missing display_name/email/phone, present company_id/branch_id/tenant_id/status/note, present UNIQUE on contact, present findOrCreate, missing create/getById/searchCandidates, premature party_id outside Parties.
- Intentionally does not do:
  - Does not execute parties migration against working DB.
  - Does not migrate Guests/Customers/Suppliers.
  - Does not add party_id to domain tables.
  - Does not create Parties UI or consumer adoption.
  - Does not prove isolated DB integration (reported as static/unit).
- Known warnings/debt:
  - Isolated DB integration test for Parties is not yet available (hospitality probes use working DB + drop, forbidden per Slice 4); probe is static/unit.
  - No Parties UI, no consumer adoption, no party_id FK yet.

### 42) `check_theme_source_integrity.sh`

- Architecture law protected:
  - Theme source files remain tokens-only (no component selectors reintroduced).
  - Foundation/semantic token separation is preserved.
  - `semantic/semantic.css` exists and contains semantic tokens.
  - `public/assets/theme.css` is generated output, not source-owned.
  - Theme manifest source order is foundation < semantic < light for correct cascade.
  - Known owner selectors (`.coverage-kpi`, `.mfg-subgroup-card`, `.sc-card`, etc.) are not reintroduced.
- Owner layer responsible:
  - Theme source layer + Tooling/System Tools governance.
- Scope scanned:
  - `resources/themes/*.css`, `resources/themes/semantic/*.css`.
  - `resources/themes/theme-manifest.json`.
  - `public/assets/theme.css`.
  - Known owner selector patterns (Prompt 1-3 extraction outputs).
- Pass/fail meaning:
  - FAIL when component selectors are reintroduced into theme source files, disallowed semantic tokens appear in `foundation.css`, required semantic tokens are missing from `semantic.css`, `theme.css` is not generated output, known owner selectors reappear in theme sources, or manifest ordering is invalid.
  - PASS when all integrity invariants hold.
- Intentionally does not do:
  - Does not validate CSS syntax or token value semantics.
  - Does not move selectors or fix separation violations — it is regression-only.
  - Does not change ThemePreferenceService, Studio tooling, or compiler behavior.
- Known warnings/debt:
  - Foundation/semantic separation is final for v1; future migration slices may restructure subdirectory files.

### 43) `check_theme_runtime_fallback_contract.sh`

- Architecture law protected:
  - Runtime theme preference normalization must be resilient: invalid, legacy, and null preference inputs must resolve to allowed built-in selections.
  - Fallback logic must avoid recursive normalization dependency loops.
- Owner layer responsible:
  - Shell runtime theming + Tooling/System Tools governance.
- Scope scanned:
  - `apps/Shell/Services/ThemePreferenceService.php` fallback code contract.
  - Runtime normalization smoke behavior via direct service invocation.
  - Runtime auth layout emission under invalid configured values via `public/views/layouts/auth_header.php` render smoke.
  - Runtime admin layout fallback constant emission under invalid configured values via `public/views/layouts/header.php` render smoke.
  - Header/auth layout anchors that consume runtime theme preferences.
- Pass/fail meaning:
  - FAIL when recursive fallback pattern is present in `normalizePreference()`.
  - FAIL when `defaultPreference()` is not normalized against an explicit safe fallback.
  - FAIL when invalid/legacy/default preferences do not normalize into `allowedPreferences()`.
  - FAIL when rendered runtime auth layout emits an invalid/disallowed configured preference instead of fallback normalization.
  - FAIL when rendered admin layout emits invalid/disallowed `THEME_FALLBACK_PREFERENCE` and `THEME_ALLOWED_PREFERENCES` constants under invalid configured values.
  - PASS when fallback contracts and smoke checks are stable.
- Intentionally does not do:
  - Does not modify DB settings or runtime preferences.
  - Does not enforce specific style names beyond allowed-choice membership.
  - Does not alter setup-first static CSS chain behavior.
- Known warnings/debt:
  - Runtime theme choice completeness across future style additions depends on manifest/style discovery governance.

### 44) `check_style_compliance_css_ownership.sh`

- Architecture law protected:
  - Style Compliance CSS ownership is enforced through externalized owner stylesheet delivery.
  - Shared Studio primitives (`gs-tool-*`) remain the source of generic rendering behavior.
  - Tool-specific domain rendering remains in owner-prefixed `sc-*` styles.
- Owner layer responsible:
  - Studio app owner (Style Compliance tool) + Tooling/System Tools governance.
- Scope scanned:
  - `apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/Shared/_page_header.php`
  - `apps/Studio/styles/style-compliance.css`
  - Style Compliance action-link emitters under `apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/`
- Pass/fail meaning:
  - FAIL when inline `<style>` is reintroduced in the Style Compliance header view.
  - FAIL when stylesheet linkage to `style-compliance.css` or shared `gui_studio.css` is missing.
  - FAIL when owner stylesheet redefines shared `gs-tool-*` primitives or global `[hidden]` behavior.
  - FAIL when SC action links stop consuming `gs-tool-action-link`.
  - PASS when ownership boundaries and primitive consumption invariants hold.
- Intentionally does not do:
  - Does not modify scanner, repair flow, routes, or runtime behavior.
  - Does not lint or reformat unrelated Studio tools.
- Known warnings/debt:
  - Some domain-scoped local constants (scan aura and ring visuals) remain intentionally local to Style Compliance.

### 45) `check_first_boot_css_safety.sh`

- Architecture law protected:
  - `/setup`, login, 2FA, recovery, and account setup render from code-shipped CSS without database-backed theme selection.
  - First-boot CSS source truth remains owned by Shell/Platform and public assets remain generated output.
  - Raw token definitions remain confined to their owning layer prefixes.
- Owner layer responsible:
  - Shell + Platform + Tooling/System Tools governance.
- Scope scanned:
  - First-boot owner CSS, token declarations, publication manifest/compiler, setup/pre-auth layout, static load order, and published artifact parity.
- Pass/fail meaning:
  - FAIL when owner sources or token prefixes are missing, a layer defines another owner's raw tokens, setup consumes legacy style/tone aliases, the compiler source map drifts, published output is stale/non-generated, or setup/pre-auth reaches forbidden runtime resolvers.
  - PASS when both six-link static chains render with forbidden resolvers guarded and all seven published assets match their exact owner-source compile plan.
- Intentionally does not do:
  - Does not change runtime theme selection, Theme Manager, Studio, owner app CSS, or database schema.
  - Does not mutate generated assets.
- Known warnings/debt:
  - Post-setup effect/density selection and owner CSS normalization remain later phases.

### 46) `check_operator_responsive_navigation_integrity.sh`

- Architecture law protected:
  - Operator responsive navigation continuity: coordinated breakpoint, hamburger trigger, shared sidebar/drawer source, bottom-nav active-state exclusivity, and username confinement.
- Owner layer responsible:
  - Shell operator layer (composers, styles, services).
- Scope scanned:
  - CSS breakpoint rules, hamburger trigger markup/JS, sidebar/drawer PHP source consumption, bottom-nav focus-array assignments, tab/sidebar URL patterns.
- Pass/fail meaning:
  - FAIL on breakpoint mismatch, missing hamburger trigger, duplicated sidebar/drawer sources, dual-active bottom-nav tabs, or unconfined URL patterns.
  - PASS when all five structural invariants hold.
- Intentionally does not do:
  - Does not test rendering, viewport behavior, or live breakpoint switching.
  - Does not validate overlay or display-layer responsiveness.
- Known warnings/debt:
  - Secondary CSS at 760px (`display: grid` for bottom nav layout) is allowed alongside the canonical 820px show/hide rule.

### 47) `check_studio_deletion_family_capability_suite.sh`

- Architecture law protected:
  - Studio owner-deletion staged capability truth is continuously certified through canonical and workspace gate suites.
  - Existing deletion family checkpoints remain enforced without duplicating stage assertions.
- Owner layer responsible:
  - Studio + Owner Structure Scan + Tooling/System Tools governance.
- Scope scanned:
  - Wrapper orchestration of:
    - `scripts/architecture/check_studio_deletion_*_capability.sh` (14 gates)
    - `scripts/architecture/check_owner_structure_deletion_*_workspace.sh` (14 gates)
- Pass/fail meaning:
  - FAIL when any underlying deletion stage gate fails or is missing.
  - PASS when all 28 existing deletion gates pass unchanged.
- Intentionally does not do:
  - Does not add or alter deletion stage assertions.
  - Does not modify routes, controllers, services, views, or runtime behavior.
  - Does not implement owner deletion execution or reference-remediation patch apply.
- Known warnings/debt:
  - Deletion stage gates exist as an extensive suite and are orchestrated through this wrapper to avoid top-level runner noise.
  - Actual owner deletion execution and reference-remediation patch apply remain intentionally unimplemented capabilities.

### 47) `check_shell_behavior_contract.sh` (Planned — Phase 5)

- Architecture law protected:
  - Shell owns viewport, layout frame, sidebar, topbar, footer, overlays, scroll lock, z-index application, and responsive mechanics.
  - No raw arbitrary z-index values outside Shell-owned layer map.
  - No hardcoded px breakpoint values in `@media` rules.
  - Desktop sidebar is not `position: fixed`.
  - Overlays route through single shell overlay container with unified scroll-lock.
  - All z-index values reference `var(--z-*)` layer tokens.
- Owner layer responsible:
  - Shell + Tooling/System Tools governance.
- Scope scanned:
  - `apps/Shell/styles/operator.css`, `components.css`, `admin.css` for raw z-index values, hardcoded breakpoints, and `position: fixed` desktop sidebar.
  - Shell composer/services for scroll-lock and overlay state management.
  - App/module CSS for viewport `@media` queries used for component-level responsiveness (should use `@container`).
- Pass/fail meaning:
  - FAIL when raw numeric z-index values (over 10) exist in Shell CSS without token reference.
  - FAIL when hardcoded `px` values appear in `@media (max-width|min-width)` rules in Shell CSS.
  - FAIL when desktop sidebar uses `position: fixed`.
  - FAIL when overlapping breakpoint ranges exist (e.g., both `900px` and `901px`).
  - PASS when all Shell rendering contract invariants hold.
- Intentionally does not do:
  - Does not validate app dashboard responsiveness.
  - Does not enforce container query adoption in app CSS.
  - Does not change runtime rendering behavior.
- Status:
  - **Planned** — Implementation deferred to Shell Behavior Contract Phase 5.

## Planned Universal Component Coverage

`docs/architecture/universal-component-contract-v1.md` is the active ownership and component-anatomy contract.

A Universal Component diagnostic is intentionally not added to the aggregate runner in Contract Prompt 1 because no canonical component implementation, selector migration, or runtime registry is authorized yet.

Future read-only coverage may validate:

1. Shell owns only neutral universal component structure and behavior.
2. Theme sources remain values-only.
3. App/module semantics, data, labels, actions, and workflow meaning remain owner-scoped.
4. Owner-specific selectors do not become new Shell universal selectors.
5. Studio remains an editor/composer and does not become runtime component truth.
6. Core contains no presentation component implementation.
7. Interactive universal components preserve authorization, wrapper confinement, and display readonly rules.

Any future gate must be introduced only with an approved implementation or enforcement phase and must document scan scope, pass/fail meaning, non-goals, and known compatibility debt.

## Coverage Boundaries (What The Gate Suite Does Not Do)

The current architecture gate suite intentionally does not:

1. Apply runtime fixes.
2. Redesign routes/surfaces.
3. Migrate existing compatibility debt automatically.
4. Replace owner-layer implementation tests.
5. Generate public assets or run destructive operations.

## Known Global Debt (Expected, Unchanged)

1. QR integration debt warnings remain known and unchanged.
2. QR/Timecard Shell CSS selector compatibility debt remains known and unchanged.
3. Compatibility alias and bridge debt remains documented migration debt; new regressions are blocked.

## How To Use This Index

1. Start with the failing gate only; do not patch unrelated areas.
2. Use owner-layer mapping to keep fixes ownership-correct.
3. If all gates pass, prefer documentation/read-only diagnostics work over runtime edits.
4. Treat warnings as inventory unless they represent new regressions in active diff scans.


## Operator focus declaration/provider parity (added 2026-08-24)

Gate: `scripts/architecture/check_operator_focus_declaration_parity.sh`
(probe: `scripts/architecture/probe_operator_focus_declaration_parity.php`).

Enforces docs/app-manifest-spec.md "Operator focus_views hook contract:
focus_tokens": for every ENABLED app declaring an operator_surface/focus_views
hook, declared `focus_tokens` must exactly equal the provider-returned
`focus_views.view_map` keys (both directions), with token-format validation,
duplicate detection, and missing-provider detection. Providers execute with a
synthetic assigned context. Read-only; no DB writes.
