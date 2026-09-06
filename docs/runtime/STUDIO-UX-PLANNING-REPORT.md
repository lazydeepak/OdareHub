# Studio UX Planning And Gap Analysis Report

## Date

- 2026-05-23

## Scope And Method

- Read-only browser/manual inspection only.
- No code edits.
- No route changes.
- No runtime behavior changes.
- No DB/migration changes.
- No form submissions.
- No fixes implemented.

## Inputs Reviewed

### Browser Surfaces

- /apps/studio
- /apps/studio/library
- /apps/studio/history
- /apps/studio/library/module?app_key=inventory_app&module_key=parts_master
- /apps/studio?library_item=route%3Adeclared%3A%2Fapps%2Fmanufacturing%3A0

### Contracts And App Metadata

- docs/architecture/studio-operating-contract.md
- docs/architecture/studio-resource-registry-baseline.md
- docs/architecture/studio-change-lifecycle-apply-contract.md
- docs/architecture/studio-change-record-schema-baseline.md
- docs/architecture/studio-approval-risk-validation-policy.md
- apps/Studio/manifest.json
- apps/Studio/routes.php
- apps/Studio/AGENTS.md

## 1) Studio Current UX Map

### Visible Sections

- Studio shell is rendered inside the standard admin wrapper (left module sidebar, topbar, alerts/account controls).
- Main Studio workspace shows tabbed action areas (Library, Editor, Run) and mode toggles (Create Flow, Upgrade Flow).
- Library-facing controls are visible: Views, Modules, Apps, Routes filters; search box (Search views, modules, routes...); quick-load blocks.
- Editor-facing controls are visible: Edit, Analyze, Changes, Apply tabs and detail-level controls (Simple, Guided, Advanced).
- Context paneling appears in-page with loaded context labels, surface exposure summary, and field/component sections.

### Navigation

- Primary entry route is /apps/studio.
- Explicit Studio history link is visible from the Studio workspace.
- Library navigation is URL-param driven through library_item query paths, not only route changes.
- Legacy alias remains declared in manifest (/ops/gui-studio) as compatibility route.

### Library/Search/Load Behavior

- Library and module-linked pages resolve successfully and stay in the Studio shell.
- Search control is visible and consistently present on Studio home/library/module/deep-item views.
- Quick-load and load-app controls are visible; multiple load controls appear concurrently in the action area.
- Library policy text distinguishes loadable-in-editor vs inspect-only resources.

### History/Change-Log Behavior

- /apps/studio/history renders successfully and exposes history-oriented controls (Back to Studio, Export as ZIP, Register).
- History page title currently surfaces untranslated key text in the browser title: ops.gui_studio.history_title.

### App/Module/View Loading Behavior

- Module route load /apps/studio/library/module?app_key=inventory_app&module_key=parts_master renders correctly.
- Deep library item loading via query token also renders correctly.
- Context marker confirms loaded source/context in editor state (for example, loaded context and view upgrade editor indicators).

## 2) What Works Now

- All inspected Studio pages render with HTTP 200.
- No immediate browser console fatal errors were observed during inspected flows.
- Studio library surface is populated with substantial data (apps/modules/views/routes).
- Analyze/Changes/Apply UI controls are visible and discoverable from the Studio workspace.
- Quick-load and filter/search controls are present and appear operational from a render/readiness perspective.
- History surface is reachable and populated enough to support operational review actions.

## 3) UX Gaps And Confusing Areas

### Label/Terminology Clarity

- History browser title shows raw i18n key text (ops.gui_studio.history_title), reducing user confidence in completeness.
- Multiple advanced terms (library_item token routing, inspect-only vs loadable) are visible but not always accompanied by concise first-use guidance.

### Empty-State And Guidance Gaps

- Workspace is data-dense and appears optimized for known users; first-time orientation guidance is limited.
- There is no obvious progressive onboarding path explaining where to start between Library, Editor, and Run.

### Create vs Edit vs Upgrade Flow Clarity

- Create Flow and Upgrade Flow toggles are visible but intent boundaries are not strongly reinforced at decision points.
- Edit/Analyze/Changes/Apply sequence is present, but risk/approval expectations are not always prominent in the immediate task context.

### Sidebar/Search/Load Cognitive Load

- Global platform sidebar + Studio internal filter/search/load controls create layered navigation complexity.
- Repeated load controls and dense library listings can make action precedence unclear (load app vs load module vs library item deep-link).

### Risk/Approval/Preview Visibility Gaps

- Contract documents define strict approval/risk/validation lifecycle, but in-page UX emphasis is uneven for user-facing readiness cues.
- Preview/approval guardrails are represented functionally but could be perceived as expert-only due to dense technical surface.

## 4) Architecture-Safe Improvement Candidates (Planning Only)

These are planning candidates only. No implementation is included in this checkpoint.

1. Add explicit workflow orientation panel in Studio shell: Start in Library -> Analyze -> Changes -> Apply, with create vs upgrade decision hints.
2. Improve inline lifecycle visibility badges in editor tabs: risk level, approval-required, validation-required, snapshot-required.
3. Add clearer inspect-only vs editable affordance in library cards/list rows, with short rationale text.
4. Add first-load empty-state or quick-start guidance for new operators of Studio.
5. Reduce duplicate load affordances by clarifying primary and secondary load actions.
6. Standardize user-facing localized title/heading rendering on all Studio pages.
7. Add lightweight context breadcrumbs for current loaded asset (app/module/view/route) with ownership reminder.

Architecture safety constraints for all candidates:

- No Core ownership expansion.
- No runtime ownership shift from apps/modules to Studio.
- Studio remains a governed worker and orchestration layer.
- Generated/modified artifacts remain owned by target app/module and handed back per contract.

## 5) Priority Recommendation

### P0 (Clarity/Blockers)

1. Remove user-facing untranslated keys on Studio surfaces (history page title currently visible debt).
2. Add explicit create vs upgrade flow intent guidance at the top-level Studio action area.
3. Surface assignment of current loaded context and editability state in a simpler, persistent banner.

### P1 (Guided Workflow)

1. Introduce guided workflow hints for Analyze -> Changes -> Apply with policy checkpoints.
2. Improve library/search/load hierarchy to reduce action ambiguity.
3. Add risk/approval/preview readiness indicators with plain-language explanations.

### P2 (Polish/Later)

1. Refine dense Studio list/card visual hierarchy for scanability.
2. Add richer empty-state examples and contextual microcopy.
3. Improve consistency of control labels and button grouping across Studio pages.

## 6) No Fixes Implemented

- This report is planning and gap analysis only.
- No implementation patches are included.

## 7) No Code Changes Made (Runtime)

- No runtime code, routes, or behavior were changed.
- No DB or migration changes were made.
