# Shell — Work

## Current Focus

Continue safely decomposing `OperatorSurfaceComposer.php` toward orchestration-only facade.

## In Progress

- [ ] Decompose next `OperatorSurfaceComposer.php` non-rendering business-data block into focused composer/service class without changing runtime output.

## Next

- [ ] Identify next extraction candidate from remaining `OperatorSurfaceComposer.php` private methods.

## Blocked

- None recorded.

## Review inventory

- Preserve and review unfinished/compatibility admin-mode surfaces documented in `engineering/Shell/admin-mode-leftover-inventory.md`; no removal or repurposing is authorized yet.
- Keep the Platform Admin Dashboard link visible until the unique-content placement and parity matrix in `engineering/Shell/platform-admin-dashboard-migration-matrix.md` is complete and owner-approved.
- Manage code-first admin upgrade candidates through `engineering/Shell/admin-dashboard-candidate-inventory.json`; the accompanying Markdown summarizes coverage and lifecycle rules.

## Completed

- Extracted UI, navigation, and runtime-artifact discovery from Core into the owner-declared `PlatformArtifactSearchProvider`. Runtime GET/POST discovery now consumes `RouteRuntimeAuthority` instead of parsing route files; Manufacturing retains its workflow and fallback-chart semantics. Added provider-owned default/intent priorities and deterministic precedence-aware deduplication. `SearchService.php` is now 715 lines and the unified contract probe contains 45 assertions. No route, schema, or index changes were made.
- Added the generic owner-declared search-provider boundary and moved Manufacturing machines, parts, orders, plans, production entries, QC entries, and dispatch entries out of Core. Core now discovers `apps/*/search.php` declarations without app hardcoding, performs final result authorization/deduplication, and retains only compatibility UI-artifact discovery. Unified search coverage expanded to 29 assertions; no routes or schema changed.
- Consolidated Operator Search onto the authenticated `/api/search` entry point using a generic Platform-owned authorized candidate index. Removed inlined route/entity JSON, live DOM/form crawling, duplicate client scoring, and duplicate route-match branches; preserved Shell candidate ownership and `/u/{username}` confinement. Corrected `product_id`/`part_id` scope alias handling in the Admin data path and added a 20-assertion unified search probe. Database indexing remains evidence-gated; no schema change was made.
- Added deterministic theme-source fingerprinting and runtime self-healing for `public/assets/theme.css`. Dynamic page bootstrap now detects missing, stale, or timestamp-preserved generated output and recompiles before rendering; the first-boot fallback no longer treats any non-empty target as current and dry-run paths do not mutate. Added a 14-assertion isolated probe and extended the first-boot architecture gate.
- Moved remaining basic surface geometry into rendering Foundation: shared panel/card radii, card padding, icon-chip size/radius, spacing and safe-area compatibility aliases. Defined the previously missing admin `--radius`, `--border`, and `--surface*` aliases, removed card/icon geometry from the semantic theme, republished runtime assets, and added a 35-assertion probe. Live admin cards now resolve to a 14px radius and 1px border with valid theme-owned backgrounds; link corners resolve to 9.8px; no console errors or overflow.
- Removed the Shell aggregate stylesheet from runtime eligibility while preserving it as the published source import map. Centralized the versioned isolated-preview chain in `StyleRegistryService`, migrated Special Effects and CSS Live Editor previews away from aggregate imports, and added a 24-assertion regression probe. Live checks confirmed no aggregate/duplicate stylesheet URLs, no stale 58px desktop offset, no horizontal overflow, and no inactive blur at all nine required widths.
- Consolidated Admin (`topbar`/`.topbar-inner`) and Operator (`.u-header`) header geometry into a single canonical `apps/Shell/styles/shell-header-geometry.css` authority. Moved sticky positioning, block-size (58px), padding (12px/16px), gap (12px), safe-area (--sys-safe-area-*), and responsive compaction (720px/480px breakpoints) into the shared file. Removed the fixed-topbar model (`position: fixed !important` on `.topbar` and `padding-top` spacer on `.layout-shell`) and deleted duplicate `.topbar`/`.topbar-inner` sticky blocks from `shell-layout.css`. Stripped geometry properties from `.u-header` in `shell-surfaces.css`, keeping only appearance (border, background). Updated `notif-dropdown`/`overflow-dropdown` positioned calculations to use `--shell-header-block-size` and `--sys-safe-area-top`. Added `--shell-header-*` variables to `shell-essential.css`. Wired manifest entry and import chain. Created probe (32/32 assertions) and architecture gate.
- Started the five-candidate governance implementation slice without merging owners: moved Notifications embedded CSS to the Shell admin layer, localized the administrative compose form, cleaned Workspace Profile/User Control/Access presentation, localized Audit scope labels, preserved notification/user/profile/access forms and destructive confirmations, and advanced Access, Users, Profiles, Audit, and Notifications from target-designed to implementing.
- Upgraded the next admin bulk slice across Runtime Report, App Manager, and Data Control: removed remaining inline presentation from the touched entry surfaces, localized runtime boolean/state and lifecycle status chrome, retained report export and all app lifecycle mutation/confirmation/CSRF contracts, corrected the stale “future mutation flow” Data Control copy to describe the already-implemented governed workflow, and advanced `ADM-DATA-001` to implementing.
- Upgraded the Configuration, Release, and Setup Overview surfaces as one admin slice: removed inline presentation, added English/Japanese/Nepali interface coverage, masked sensitive values in configuration read-only previews, mapped structured setup statuses to governed Shell classes, and preserved every save/import/export/readiness/generate/cancel/download/profile handler plus CSRF and preview-token boundary.
- Upgraded the Environment Snapshot & Clone specialist surface without changing its state machine: localized all interface chrome in English, Japanese, and Nepali; moved layout rules into the Shell admin stylesheet; clarified the controlled apply gate; and preserved snapshot, upload/preview, CSRF, preview-token, apply/cancel, dependency, history, and download contracts.
- Added a Shell-owned unified admin launcher projected from the already-authorized runtime navigation model, with Governance, Apps & Config, System Tools, and Developer groups.
- Consolidated Organization into Apps & Config, registered Setup under System Tools, and registered Architecture Health as the canonical developer destination.
- Established `/admin/{username}` as the upgraded admin home while retaining the visible Platform Admin Dashboard specialist link until unique controls and metrics reach approved parity.
- Preserved assigned-app operational views below a localized Assigned Apps boundary and removed duplicate legacy Quick Links when their destinations are already present in the launcher.
- Enabled `app_admin` access to the role-filtered admin wrapper without promoting app admins to platform-admin, Base, or Developer authority.
- Added signed-in identity confinement for `/admin/{username}` so mismatched handles redirect to the session user's canonical home.
- Added focused launcher and wrapper-role probes covering declared-route projection, deduplication, auto-child exclusion, compatibility-route preservation, role access, authority separation, and identity matching.

- Completed the overlay consolidation program by removing the redundant `__overlayCount`/`has-active-overlay` visual path and the unused `closeInstance()` compatibility API.
- Updated the Shell rendering gate to enforce the actual single-controller anchors (definitions, manager, visual application, reference-counted scroll lock).
- Marked the ten thin adapter files as stable named binding factories rather than remaining lifecycle/policy migration debt.
- Removed the unused overlay runtime registry and all duplicated candidate priority/modality/backdrop/dismissal/scroll/visual metadata.
- Reduced all ten adapters to definition selection, DOM bindings/callbacks, and compatibility open/close/sync methods.
- Removed adapter pre-registration calls from public/operator renderers and replaced stale registry/service architecture documents with concise single-controller contracts and inventory.
- Moved `shell-inactive-layer` from the outer `.app-shell` container to the `.main-content.workspace-surface` content surface in `OperatorSurfaceComposer::renderHTML()`, so overlays now blur/deactivate only operator workspace content while the contextual sidebar remains sharp. Added `id="operatorMainContent"` to the canonical content element. Verified: 1 file changed (+2/-2), PHP lint clean, visual effects probe 104/104, overlay framework probe 17/17, shell rendering contract PASS, operator confinement PASS, studio boundary PASS, git diff --check clean.

- Migrated shared public/operator camera overlay-root dismissal and adapter state synchronization to the Shell controller using the minimal `outsideSurfaceSelf` binding.
- Preserved camera stream/detector/RAF cleanup, DOM removal, manual/cancel actions, scan result resolution, and Escape-disabled behavior in local scanner callbacks.
- Removed duplicate camera overlay click handlers and added the canonical surface class to the public dynamic overlay.
- Migrated operator mobile action-sheet Escape/outside dismissal, focus restoration, and scroll lock to the Shell controller.
- Refactored panel/backdrop/ARIA/overlay-activation rendering into a local callback; retained action-link and peer-close behavior.
- Removed action-sheet-specific global dismissal handlers and direct overflow writes.
- Migrated operator mobile hamburger Escape/outside dismissal, focus restoration, scroll lock, and viewport-close state to the Shell controller.
- Refactored mobile hamburger DOM/peer-close behavior into a local callback while preserving desktop collapse/peek behavior.
- Removed hamburger-specific global Escape/outside branches and direct body/main-content overflow writes.
- Added minimal reference-counted scroll locking to the Shell controller with prior inline-overflow restoration and optional candidate scroll-target binding.
- Migrated operator avatar dismissal, scroll lock, focus restoration, and adapter state synchronization to the controller; retained avatar DOM/classes/ARIA/preferences as one local callback.
- Removed avatar-specific global Escape/outside branches and direct body/main-content overflow writes.
- Migrated public mobile-sidebar backdrop/outside dismissal and focus restoration to the Shell controller while preserving responsive desktop/mobile rendering and navigation-close behavior.
- Hardened selector-bound trigger exclusion so any matching sidebar toggle is treated as inside control, not only the first matched element.
- Migrated operator search outside dismissal to the Shell controller while keeping query handling, content/route matching, active-descendant navigation, result activation, and Escape locally owned.
- Removed the operator page's nested outside-search branch and disabled focus restoration for outside search dismissal.
- Migrated public topbar search outside dismissal to the Shell controller while explicitly retaining query-clearing Escape, result navigation, activation, async rendering, and active-descendant behavior locally.
- Disabled generic focus restoration for search dismissal so clicking elsewhere does not pull focus back into the search input.
- Migrated public notifications and admin-action dropdowns to controller-owned Escape/outside-click routing and focus restoration, leaving content rendering and explicit close-button behavior local.
- Added selector-based trigger resolution to the controller so candidates without trigger IDs retain outside-click exclusion and focus restoration.
- Migrated the public account-overflow dropdown to controller-owned Escape/outside-click routing and focus restoration using small `onOpen`/`onClose` DOM hooks.
- Removed account-overflow handling from the public page-wide Escape and outside-click branches while keeping trigger, ARIA, hidden/class, peer-close, and fallback rendering behavior intact.
- Folded visual instance selection, strength override, CSS-variable application, and cleanup into the single `OdareHubOS.ShellOverlay` controller and removed the separate `ShellOverlayVisualEffects` runtime/service.
- Updated public and operator strength controls to use `ShellOverlay.visualEffects` without changing stored preference keys, slider behavior, or rendered CSS effects.
- Simplified the Shell overlay architecture to one browser controller with Shell-owned `dropdown`, `drawer`, `sidebar`, and `viewport` definitions.
- Replaced repeated adapter visual effect/scope/strength literals with one preset selection per candidate and added executable runtime coverage for idempotent open/close/toggle/closeTop behavior.
- Removed the unused `ShellOverlayLifecycle` browser namespace and the non-runtime PHP overlay service/value-object simulation model, reducing the slice by more than 5,900 lines while preserving active adapters and the canonical rendered host.
- Centralized compatibility-adapter close dispatch in `ShellOverlayFramework.manager.closeInstance()` and removed the duplicated `managerClose()` implementation from all ten overlay compatibility adapters.
- Updated the framework and migration-candidate probes to assert the centralized close compatibility contract while retaining a direct `manager.close()` fallback for older framework payloads.
- Extracted operator focus/page-label normalization and query-to-label resolution into `OperatorFocusLabelComposer` (`resolveFromQuery()`, `normalizeFocus()`) and removed the now-redundant facade helper from `OperatorSurfaceComposer`.
- Extended extracted-composer probe coverage for focus-label normalization/query resolution parity.
- Removed residual Shell `.DS_Store` artifacts from `apps/Shell/` and `apps/Shell/Views/`.
- Verified Shell owner-scan unknown classification is now zero (`unknown_count=0`).
- Added decomposition extraction from `OperatorSurfaceComposer.php` into `OperatorFocusLabelComposer.php` and `OperatorPartPlanComposer.php` with facade delegation only.
- Hardened Shell owner-structure probe to assert cleanup noise absence for Shell root and Shell Views `.DS_Store` paths.
- Fixed Owner Structure Scan artifact-section runtime failure by importing `EngineeringWorkspaceArtifactService` in `StudioController` and hardening `EngineeringWorkspaceArtifactService` read/validation paths.
- Restored Engineering Workspace support scoping to canonical mapped keys in `EngineeringWorkspaceContentContract` to avoid unsupported-owner fallback regressions.
- Added explicit dependency loading in `EngineeringWorkspaceInitializationService` for probe-safe class resolution.
- Extracted notification severity/icon presentation mapping from `OperatorSurfaceComposer.php` into `OperatorNotificationPresentationComposer.php` and delegated all call sites without changing route/render behavior.
- Extracted notification URL normalization from `OperatorSurfaceComposer.php` into `OperatorNotificationPresentationComposer.php` and delegated all notification link call sites.
- Extracted workspace-profile quick-action resolve/sanitize helpers from `OperatorSurfaceComposer.php` into `OperatorProfileQuickActionComposer.php` and delegated mobile/profile quick-action call sites.
- Extracted operator header/mobile/workflow navigation assembly from `OperatorSurfaceComposer.php` into `OperatorNavigationComposer.php` and delegated call sites without output changes.
- Finalized Shell owner-structure domain taxonomy in Owner Structure Scan by adding a canonical Shell Domain Model (Runtime, Styling, Contracts, Quality) that distinguishes architectural domains from implementation folders.
- Updated Owner Structure Scan contract output and UI to explain `DesignSystem` vs `styles`, wrapper-composer placement policy, and top-level-domain decisions.
- Revised Shell domain contract wording to architecture-level ownership laws (not implementation-detail constraints) and added explicit domain ownership boundaries (`owns` vs `never`).
- Hardened Owner Structure reference discovery for Shell folder rename operations (`apps/Shell/Style` -> `apps/Shell/DesignSystem`) to use actionable path/namespace evidence and avoid low-confidence generic term matching.
- Refined Shell rename reference discovery again to remove high-confidence `/Style/`, platform-style, and generic style-catalog signals; now only Shell-owned actionable path/namespace references are counted and promotion is classification-based (runtime/build blockers), not threshold-based.
- Added read-only Shell Style -> DesignSystem migration-plan details to Owner Structure reference discovery output: per-reference matched pattern/current/proposed replacement/category, relevance_summary grouping, and migration readiness state (`blocked_runtime_references`, `needs_tooling_review`, `ready_for_manual_rename`).
- Deduplicated Shell Style -> DesignSystem reference rows by `file+line+excerpt` with specificity preference (`namespace declaration`/`use`/`namespace path` before raw escaped namespace substring), reducing runtime-blocking rows from `18` to `9` while keeping blocking semantics unchanged.
- Completed physical Shell governance-folder migration from `apps/Shell/Style` to `apps/Shell/DesignSystem` and updated deterministic namespace/import references (`Apps\\Shell\\Style` -> `Apps\\Shell\\DesignSystem`) in moved PHP artifacts and direct probe usage.
- Renamed Owner Structure Scan shell governance classification display label from `Shell Style Governance` to `Shell Design System Governance` (classification key unchanged: `shell_style_governance`).

## Evidence

- UI/navigation/artifact extraction: unified search contract `45/45`; touched PHP lint, ownership, and route-parser static checks pass. Remaining architecture gates require a complete checkout.
- Owner-declared search provider probe: `29/29`; module health minimum, business app/module contract, Shell rendering, Operator confinement, Platform/capability ownership, and approved Core-lock gates: `PASS`.
- Unified search contract: `20/20`; Operator extracted composers: `51/51`; Operator search adapter: `20/20`; Shell rendering, Operator confinement, Platform ownership, capability ownership, and approved Core-lock gates: `PASS`.
- Runtime theme self-healing probe: `14/14`; first-boot safety, theme integrity, and Shell rendering gates: `PASS`; dry-run/apply compiler behavior verified.
- Theme-independent surface geometry probe: `35/35`; control geometry probe: `27/27`; first-boot safety, theme integrity, and Shell rendering gates: `PASS`.
- Single Shell stylesheet-chain probe: `24/24`; theme-independent controls probe: `27/27`; asset registry integrity and Shell rendering gates: `PASS`.
- Live widths `390, 480, 720, 860, 861, 894, 900, 901, 1200`: no overflow; mobile content full width through 900px; desktop grid begins at 901px; topbar starts at `y=0`; no blur/dimming; browser errors absent.
- Unified admin launcher probe: `15/15`; admin wrapper role/identity probe: `9/9`.
- Live platform-admin acceptance: four launcher groups rendered; Setup consolidated under System Tools; legacy Quick Links absent; Assigned Apps and Manufacturing content preserved; browser console errors absent.
- Shell rendering, operator confinement, and Studio boundary gates: `PASS`; touched PHP lint and `git diff --check`: `PASS`.

- Final overlay completion validation: controller `24/24`, framework `17/17`, visual `114/114`, candidates `188/188`, architecture gates `PASS`.
- Framework probe: `17/17` with explicit no-registry assertion; visual probe: `114/114`; candidate probes: `188/188` after metadata removal.
- Owner Structure Shell contract: `60/60`; Shell rendering, operator confinement, and Studio boundary gates: `PASS`.
- Controller runtime probe: `24/24` including viewport root-dismiss coverage; camera candidate probe: `18/18` across public/operator paths.
- Operator mobile action-sheet candidate probe: `18/18`; controller `23/23`; full candidate and architecture gates remained green.
- Operator hamburger candidate probe: `16/16`; controller `23/23`; full candidate and architecture gates remained green.
- Controller runtime probe: `23/23` including scroll-lock acquire/restore and nested lock retention; avatar candidate probe: `18/18`.
- Public mobile-sidebar candidate probe: `17/17`; full overlay probes and architecture gates remained green.
- Operator search candidate probe: `19/19`; controller, visual, full candidate, and architecture gates remained green.
- Public topbar search candidate probe: `18/18`; all controller, visual, candidate, and architecture gates remained green.
- Public notifications and admin-action candidate probes: `18/18` each; all candidate probes and controller runtime probe remained green.
- Controller runtime probe: `20/20` passed with generic Escape, outside-click, DOM-hook, and trigger-focus restoration coverage.
- Public account-overflow candidate probe: `18/18`; all ten candidate probes remained green.
- Single-controller runtime probe: `17/17` passed with direct visual apply/clear and strength ownership assertions.
- Unified visual-effects probe: `104/104`; ten candidate probes: `178/178`; framework probe: `17/17`.
- Shell rendering, operator confinement, and Studio boundary gates: `PASS` after visual-controller consolidation.
- Minimal Shell overlay controller runtime probe: `14/14` passed.
- Shell overlay framework probe: `17/17`; visual-effects probe: `105/105`; ten candidate probes: `178/178`.
- Owner Structure Shell contract probe: `60/60` passed after removing obsolete Overlay service/value-object paths.
- Shell rendering, operator confinement, and Studio boundary gates: `PASS` after simplification.
- PHP lint and `git diff --check` passed for the simplified overlay slice.
- Shell overlay framework Phase 1 probe: `15/15` passed after adding the `closeInstance()` contract assertion.
- Ten overlay migration-candidate adapter probes: `178/178` passed.
- PHP lint passed for all 22 touched PHP files in the centralized close-dispatch slice.
- Shell rendering contract, operator confinement, and Studio boundary gates: `PASS`.
- `git diff --check` passed for the centralized close-dispatch slice.
- PHP lint passed for `OperatorFocusLabelComposer.php`, `OperatorSurfaceComposer.php`, and `probe_operator_extracted_composers.php`.
- Operator extracted composer probe: `24/24` passed.
- Shell rendering contract gate: `PASS`.
- Operator confinement gate: `PASS`.
- Studio boundary gate: `PASS`.
- `git diff --check` passed.
- PHP lint passed for touched PHP files.
- Owner Structure Shell contract probe: `39/39` passed.
- Operator extracted composer parity probe: `21/21` passed.
- Operator confinement gate: `PASS`.
- Shell rendering contract gate: `PASS`.
- Shell CSS ownership gate: `PASS` with existing warning debt (`121` owner-specific selectors).
- Studio boundary gate: `PASS`.
- Owner Structure artifact probe: `108/108` passed.
- Engineering workspace contract probe: `322/322` passed.
- Engineering workspace editing probe: `26/26` passed.
- Live smoke: `/u/lazydeepak/dashboard` loaded in browser with operator-shell markers; realtime websocket endpoint `localhost:8001` unavailable (non-blocking for shell render parity).
- `git diff --check` passed.
- Browser verification: Owner Structure Scan (`owner=Shell&scan=1`) no longer shows `EWS_ARTIFACT_SERVICE_FAILED`; Engineering Workspace Artifacts renders controlled `Not applicable` state.
- PHP lint passed for `OperatorSurfaceComposer.php` and `OperatorNotificationPresentationComposer.php`.
- Operator confinement gate: `PASS` after notification presentation extraction.
- Shell rendering contract gate: `PASS` after notification presentation extraction.
- PHP lint passed for `OperatorSurfaceComposer.php` and `OperatorNotificationPresentationComposer.php` after notification URL normalization extraction.
- Operator confinement gate: `PASS` after notification URL normalization extraction.
- Shell rendering contract gate: `PASS` after notification URL normalization extraction.
- PHP lint passed for `OperatorSurfaceComposer.php`, `OperatorProfileQuickActionComposer.php`, and `OperatorNotificationPresentationComposer.php` after quick-action extraction.
- Operator confinement gate: `PASS` after quick-action extraction.
- Shell rendering contract gate: `PASS` after quick-action extraction.
- PHP lint passed for `OperatorSurfaceComposer.php`, `OperatorNavigationComposer.php`, `OperatorProfileQuickActionComposer.php`, and `OperatorNotificationPresentationComposer.php` after navigation extraction.
- Operator confinement gate: `PASS` after navigation extraction.
- Shell rendering contract gate: `PASS` after navigation extraction.
- Owner Structure Shell contract probe: `48/48` passed after Shell Domain Model contract update.
- Owner Structure Shell contract probe: `56/56` passed after DesignSystem transition and ownership-boundary checks.
- Owner Structure reference discovery strict-matching probe: `14/14` passed.
- Owner Structure reference discovery strict-matching probe: `20/20` passed.
- Owner Structure reference discovery strict-matching probe: `23/23` passed.
- Shell Style -> DesignSystem reference discovery state: `group=needs_review`, `safe_to_promote=no`, `reference_count=18`.
- Shell Style -> DesignSystem relevance summary: `RUNTIME_BLOCKING=18`, `STUDIO_TOOLING=0`, `ENGINEERING_WORKSPACE=0`, `DOCUMENTATION_HISTORY=0`, `OWNER_METADATA=0`, `SELF_REFERENCE=0`, `LOW_CONFIDENCE_TEXT=0`.
- Runtime smoke verification: `migration_readiness_state=blocked_runtime_references`, `safe=no`, sample deterministic replacement `namespace Apps\\Shell\\Style -> namespace Apps\\Shell\\DesignSystem`.
- Runtime smoke verification after dedupe: `group=needs_review`, `safe_to_promote=no`, `reference_count=9`, `runtime_blocking=9`, `duplicate_file_line_excerpt_rows=0`.
- Physical-migration validation: PHP lint passed for renamed DesignSystem PHP files; Shell owner contract probe `57/57`; reference discovery probe `34/34`; Owner Structure diagnosis reports `style_migration_required=no`; Shell rendering + operator confinement + Studio boundary gates `PASS`; `git diff --check` passed.
- Label-rename validation: no remaining `Shell Style Governance` phrase references, classifier PHP lint `PASS`, Shell owner contract probe `57/57`, Studio boundary gate `PASS`, `git diff --check` passed.
- Studio boundary gate: `PASS` after Owner Structure Scan domain model update.
- PHP lint passed for Owner Structure diagnosis service, preview view, and shell-contract probe.

## 2026-07-14 — Dashboard insight composer extraction

- Extracted six workspace-specific dashboard insight methods (`buildOrdersInsights`, `buildPartsInsights`, `buildOverstockInsights`, `buildWasteInsights`, `buildZairyoInsights`, `buildDispatchInsights`) and three private helpers (`countOrdersBetween`, `sumOrderedPartsBetween`, `makeDeltaTrend`) from `OperatorSurfaceComposer.php` into `OperatorDashboardInsightsComposer.php`.
- The facade's `buildRoleAwareDashboardTiles()` now delegates to static methods on the new composer. Private method count dropped from 55 to 46; facade lines reduced from 3527 to 3071 (‑456 lines). New composer is 435 lines.
- All DB access, error handling, `$tr()` localization, and trend/bar computation preserved identically.
- PHP lint: ✅ both files. No dangling references to removed methods.

## 2026-07-19 — Shell breakpoint seam and overlay fail-safe

- Made the admin shell mobile-first in the canonical sidebar/content geometry sheet, with a column layout through 900px and a direct desktop-grid switch at 901px.
- Removed responsive shell-direction ownership from `shell-layout.css`; the appearance sheet now retains only visual styling for `.layout-shell`.
- Added explicit full-width/intrinsic-height topbar geometry and full-width, shrink-safe main-content geometry.
- Gated blur/dimming on `data-shell-overlay-open="true"` and tied that attribute to the overlay manager's active lifecycle.
- Added `.shell-overlay:empty { display: none; }` as a fail-safe for an unused host.
- Extended geometry and overlay probes for the mobile-first and explicit-open contracts.
- Browser acceptance passed at 390, 480, 720, 860, 861, 894, 900, 901, and 1200px: no horizontal overflow; mobile content/header are full width through 900px; desktop grid begins at 901px; inactive content has no blur and opacity remains 1 when no overlay is open.

## 2026-07-19 — Theme-independent basic control geometry

- Moved button/control height, typography sizing, radius, padding, gaps, and field-width primitives out of the compiled semantic theme into Shell's rendering Foundation as `--render-*` tokens.
- Added Foundation-owned checkbox size/radius and radio radius primitives; Shell form selectors now consume them and radios are explicitly circular.
- Kept theme ownership limited to control colors, borders, shadows, and color-bearing select-arrow artwork.
- Added the versioned rendering Foundation to authenticated runtime globals before `theme.css`; corrected global asset version resolution so Foundation and theme links use real file modification versions instead of `v=1`.
- Updated first-boot semantic aliases and Shell compatibility aliases to resolve control geometry from Foundation.
- Added a focused `27/27` control-geometry probe and strengthened first-boot/theme-source architecture gates.
- Live acceptance on `light-liquid-glass`: plain button `42px` high with `12px` radius; Foundation choice size `18px`, checkbox radius `4px`, radio radius `50%`; no console errors.

## 2026-07-13 — Unified admin upgrade placement pass

- Added the role-aware unified admin launcher and platform-admin upgrade workbench.
- Added the governed 19-candidate upgrade catalog and machine-readable inventory.
- Implemented the Platform Mode System Tools workspace with shared mutation ownership, return-path allowlisting, localization, and deployment-lock presentation.
- Consolidated System Tools into Platform Operations, Health & Diagnostics, Logs/Reports/Audit, Apps & Package Lifecycle, Runtime Diagnostics, and Roadmap lanes.
- Consolidated Platform Operations governance into identity/access, experience/visibility, workforce/communications, and audit/compatibility lanes.
- Preserved compatibility dashboards and specialist routes until live parity and owner approval.
- Added focused probes for candidate/status consistency, source preservation, role confinement, and canonical overview-card placement.

## 2026-07-13 — Schema, organization, and developer architecture slice

- Upgraded Architecture Health into the shared admin wrapper with route, app-contract, localization, and declared-surface summaries; added Nepali coverage and corrected widget/dashboard metrics to derive from actual surface declarations.
- Kept Route Registry as the detailed read-only authority while moving its embedded status presentation into Shell-owned classes and localized status labels.
- Localized the remaining Base Builder metadata and confirmation copy without changing module, schema-sync, field-save, or soft-disable handlers.
- Removed the remaining Widget Builder inline presentation while preserving save, publish, archive, restore, clone, and bulk operations.
- Verified the existing Organization overview remains localized, class-based, and connected to separate company, branch, fiscal, and branding workspaces.
- Advanced `ADM-SCHEMA-001`, `ADM-ORG-001`, `ADM-ROUTES-001`, and `ADM-WIDGET-001` to `implementing`; source routes remain retained.
- Extended the catalog regression probe to `228/228`, including wrapper ownership, locale coverage, presentation boundaries, mutation-path continuity, CSRF fields, and confirmations.
- Reconciled the final planned-capability debt: app/suite export and restore are implemented owner workflows, while only centralized backup job monitoring remains planned; `ADM-PLAN-001` is now analyzed with App Management as its working handoff.
- Recorded the App Admin compatibility blocker explicitly: route links are access-filtered, but current Manufacturing KPI rollups still require real-account proof of assigned-app data confinement before migration or cleanup.
- Live acceptance found and fixed the `/admin/architecture-health` collision with the `/admin/{username}` URL preprocessor; Architecture Health is now an explicit fixed-route exclusion with catalog regression coverage.
- Live acceptance found Widget Builder rendering raw `ops.widget_builder.*` keys. Added the Platform-owned English runtime catalog (with normal locale fallback) and replaced JavaScript-generated inline display styles with semantic `hidden` state in create/edit views.
- Live acceptance also removed the remaining inline option-label presentation from Platform Mode; the selector now uses a Shell-owned class while retaining its service, CSRF, lock, return-path, and POST boundaries.
- Clarified the live Apps & Config launcher taxonomy: route tooling remains under Developer, while the group description now names organization, dependencies, schemas, and shared configuration; the generic Organization `Overview` launcher label is now `Organization Overview`.
- Demo-mode acceptance exposed an inconsistent Developer launcher: workbench references were hidden but the main group remained visible. The launcher composer now receives the same authoritative `admin_tools` visibility decision, hides Developer in Production/Demo, and preserves Governance plus the System Tools recovery path.
- Completed reversible live Platform Mode acceptance: Development → Demo → Production → Development, verified success feedback and current-mode radio state at each step, confirmed Developer/workbench references are hidden in Demo/Production and restored in Development, and verified GET misuse returns controlled `method_not_allowed` feedback without changing mode.
- App Admin acceptance discovery found no App Admin record in the local database, so no identity was created or impersonated. Added `AppAdminDashboardScopeService` and integrated it before compatibility KPI queries: active assignments are authoritative, platform is excluded, non-Manufacturing/empty contexts fail closed, and Manufacturing rollups run only when Manufacturing is assigned. Added a pure `10/10` scope probe and advanced `ADM-DASH-002` to implementing.
- Platform-admin integration rendering of `/ops/app-admin-dashboard` confirmed the Manufacturing label, assigned-app scope, and absence of platform-admin links. The retained role dashboard's nine inline role/mode/KPI styles were then replaced with allowlisted Shell-owned accent, mode, and value classes.
- Added `/admin/system-tools/resilience` as a read-only owner-workflow map for app/module exports, application inventory exports, environment snapshots, release artifacts, and Manufacturing/SBAIO imports and restores. It owns no mutation forms or synthetic state; centralized backup job monitoring remains explicitly planned with its missing ownership, persistence, authorization, retention, and retry contracts stated. Advanced `ADM-PLAN-001` to implementing.
- Closed a resilience-path authorization gap by making app export, module export, and export download handlers invoke their already-declared AdminTools policies; export failures are logged and return localized controlled feedback instead of raw exception text.
- Extracted the Platform Mode pre-mutation decision table into `PlatformModeSwitchDecisionService`. Expired/missing CSRF, invalid modes, deployment lock, and untrusted return paths now resolve before `setMode()` can run; the pure probe passes `13/13`. The signed-in page still reports Development. Browser-level lock presentation remains conditional on running the server with the optional lock enabled.
- Completed signed-in Platform Admin KPI parity. The first comparison exposed that the optional Base snapshot adapter was not loaded on the unified Shell route; added explicit lazy owner-adapter/controller loading and an explicit degraded-state contract. The unified home now matches all nine live legacy values (`5`, `27`, `0`, `0`, `2`, `0.01`, `1`, `0`, `0`) plus PHP/runtime/route facts, while specialist links replace copied mini/sync actions. Added a pure `17/17` snapshot mapping, visibility, mutation-exclusion, empty, degraded, and role-confinement probe.
- Audited every Platform/App Admin dashboard reference and ran the navigation composition diagnostic. No composed duplicates exist. Compatibility, landing-page, search, diagnostic, and alias references remain intentional; no route or source was deleted. Final keep/compatibility/redirect/planned decisions are recorded in the migration matrix.
- Recovered the hidden `/admin/platform-admin-links` compatibility chunk: its route used an invalid `../SectionCards` include and emitted raw hard-coded HTML. Preserved all eight links and the hidden route, regrouped them by Compatibility/Governance/Apps/Developer ownership, added EN/JA/NE labels and explicit retained status, and moved rendering into the shared admin wrapper with no mutation forms or inline presentation.
- Completed the two retained mode/developer presentation jobs. The dormant header mode payload now renders as a localized, Shell-styled, Platform-authority-only read-only indicator linking to the canonical Platform Mode workspace. The empty Developer sidebar placeholder is now one Development-only gateway to the unified Developer group anchor, with no duplicated specialist list or second selector.
- Live reversible visibility acceptance passed: Development shows exactly one header indicator, one sidebar gateway, and one Developer anchor; Demo hides the gateway and Developer group while retaining exactly one Demo indicator. Development was restored and reconfirmed.
- Graduated the temporary Admin Upgrade Workbench presentation into a stable Platform Admin Status component. Live owner metrics, environment facts, mode state, and the canonical Platform Mode handoff remain operational content; candidate IDs, migration method/focus, catalog, source comparison, and architecture evidence are now Development-only. Compatibility service/view names and source routes remain retained to avoid unnecessary churn.
- Added an explicit compatibility identity to the retained Platform Admin and App Admin role dashboards. The shared view now explains why each source remains, links the signed-in user back to `/admin/{username}`, and leaves all existing KPIs, quick links, mode controls, authorization, and mutation handlers intact. Live Platform Admin acceptance confirmed the banner, retained selector/KPI content, and canonical handoff.
- Removed the duplicate Platform Admin Dashboard destination from ordinary navigation, admin-system actions/widgets, embedded Platform Admin panels, Platform Operations, Platform Mode, Navigation Tree, User Control, and Access/Dashboard Assignments back-links. Normal flows now resolve to Unified Admin, Platform Operations, Base, or the owning specialist. The source route remains only as hidden/Development compatibility evidence; no route or source view was deleted.
- Following explicit user approval, deleted the duplicate `/ops/platform-admin-dashboard` route, its `/ops/admin-dashboard`, `/ops/itadmin-dashboard`, and `/ops/sysadmin-dashboard` aliases, manifest/navigation declarations, catalog candidate, comparison links, legacy mode-return defaults, and stale landing/diagnostic mappings. Unified Admin and specialist workspaces now own every former entry point. App Admin compatibility remains retained pending its independent acceptance evidence.
- Reconciled the active 18-candidate catalog with code reality: 16 canonical capability destinations are now `promoted`, while only App Admin signed-in parity and centralized backup monitoring remain `implementing`. Promoted cards now state that canonical placement is active while specialist workflows remain with their owners.
- Closed deterministic App Admin content parity in Unified Admin. The full role-scoped payload now renders without truncation alongside assigned-app widgets, including all KPIs, scope/control sections, quick links, placeholders, and empty-assignment state, with no legacy-dashboard handoff. Pure parity (`11/11`) and assigned-app scope (`10/10`) probes pass; `ADM-DASH-002` is now `parity_review` pending the explicit route-retirement decision.
- Deleted the parity-complete `/ops/app-admin-dashboard` route plus `/ops/editor-dashboard` and `/ops/accountadmin-dashboard` aliases, navigation/manifest declarations, catalog candidate, and stale landing templates. App Admin now lands on `/admin/{username}` and receives the complete scope-safe payload directly in Unified Admin. The catalog now contains 16 promoted capabilities and one implementing monitoring capability.
- Promoted the final resilience candidate by replacing its planned placeholder with a read-only activity monitor at `/admin/system-tools/resilience`. The monitor merges authoritative export, import, restore, environment portability, and release histories, orders them by timestamp, preserves owner status/error evidence and handoffs, and degrades explicitly when a source is unavailable. All mutations, downloads, retention, and retry behavior remain with specialist owners; the catalog now contains 17 promoted capabilities and no implementing candidates.
