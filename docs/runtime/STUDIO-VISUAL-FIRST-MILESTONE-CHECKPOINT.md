# Studio Visual-First Milestone Checkpoint

**Date:** May 23, 2026  
**Branch:** main  
**Latest Commit Before Checkpoint:** f9b32c54 (chore(studio): add clear loaded context action)

---

## Summary

This checkpoint documents the completion of Studio visual-first milestone (P0–P1.5 visual UX work). The Studio workbench now has a cohesive visual identity with grouped tools, loaded-resource state visibility, workflow status panels, and mode indicators. All changes are pure UX composition with no backend behavior wired. The architecture boundaries remain intact: Studio is a governed preview/inspection tool, apps/modules own capabilities, Core is unchanged, and no new backend routes or permissions were added.

**Key Achievement:** Studio is now visually complete as a workbench, with all planned UI layers and panels rendered. No functionality is wired yet, but the visual/UX foundation is solid for future governed implementation work.

---

## Completed Visual-First Work

### P0: Clarity & Planning

| Commit | Title | Summary |
|--------|-------|---------|
| 79445829 | Studio UX planning report | Initial UX audit and visual roadmap definition |
| 42f797fb | Studio P0 clarity | Finalized scope: visual-first, zero backend mutation, workbench identity |

### P1: Visual Layers & Panels

| Commit | Title | Summary |
|--------|-------|---------|
| 175fb393 | P1 backlog | Detailed breakdown of P1 work items (library, panels, mode strip, bridge) |
| 218165e5 | P1.1 Library / Editor separation | Separated Library sidebar from Editor canvas; added tabs/organization |
| 3379cc5e | P1.2 Loaded Resource Identity panel | Rendered owner app, module, resource-key identity of loaded context |
| 6969d0d1 | P1.4 Workflow Status / validation-diff-apply visibility | Workflow progress indicator, apply/diff buttons placeholder UI |
| 373b80a6 | P1.3 Create/Edit/Upgrade mode strip | Mode selector tabs; shows active Studio mode (create new / edit loaded / upgrade) |
| f0317226 | P1.5 Tool/module navigation model docs | Architecture documentation for tool catalog organization |
| be946bff | P1.5 tool navigation grouping labels | Visual tool grouping with category labels (Design, Analysis, Preview, etc.) |

### P1.6+: Dashboard & Preview Panels

| Commit | Title | Summary |
|--------|-------|---------|
| ed7cc8fb | Visual Workbench dashboard | Home/entry panel showing tool overview, recent items, quick-action cards |
| 35001f9c | Tool Detail Preview panel | Renders tool metadata, icon, description, capabilities in side panel |
| 75999629 | Tool + Resource Preview bridge | Linked tool selection to resource preview; shows relationship state |
| 5f24cba7 | Preview/bridge handler cleanup | Refactored event handlers; removed debug code; consolidated render calls |

### P1.7+: UX Polish & Fixes

| Commit | Title | Summary |
|--------|-------|---------|
| ec25d977 | Checklist correction | Corrected AGENT-COMPLIANCE-CHECKLIST.md validation entries |
| e08d961b | Nested module identity hydration | Fixed hydration of nested module identities in loaded-resource context |
| f9b32c54 | Clear Loaded Context action | Added local-only clear button for resetting loaded-resource preview state |

---

## Current User-Visible Studio State

### Visual Layout
- **Workspace Structure:** Studio renders as a workbench with distinct visual zones:
  - **Sidebar (Left):** Library panel with grouped tools, recent items, breadcrumb navigation
  - **Canvas (Center):** Editor surface with visual builder placeholder and composition grid
  - **Right Panel:** Tool Detail Preview and Resource Bridge state display
  - **Top Bar:** Mode selector (Create / Edit / Upgrade), Loaded Resource Identity, Workflow Status
  - **Bottom Bar:** Action buttons (Apply, Diff, Settings) — non-functional placeholders

### Tool Catalog Visibility
- **Tool Grouping:** Tools are organized into visual categories (Design, Analysis, Preview, Execution, etc.)
- **Tool Cards:** Each tool shows icon, name, description, and capability badges
- **Planned Tools:** All tools visible but marked as "planned" or "preview-only"; no backend wiring active
- **Search/Filter:** Tool library search is styled and ready for future search implementation

### Loaded Resource State
- **Identity Panel:** Displays current loaded resource (owner app, module, resource-key)
- **Status:** Shows "Not loaded" when no resource selected; shows live identity when resource is loaded
- **Clear Action:** User can click "Clear Loaded Context" to reset preview state without mutation

### Mode Indicators
- **Mode Strip:** Tabs for Create New, Edit Loaded, Upgrade Baseline
- **Active Mode:** Visually highlighted; mode affects visible panels and allowed actions
- **Mode Persistence:** Mode is stored in localStorage draft for session continuity

### Workflow Status
- **Apply Readiness:** "Apply not active" indicator (wired when apply workflow is governed)
- **Validation State:** Placeholder for future validation result display
- **Diff View:** Non-functional diff button; future read-only comparison view

### Bridge & Relationship View
- **Tool-to-Resource Bridge:** When tool is selected, shows relationship to currently loaded resource
- **State Label:** "No resource loaded" / "Preview only" / "Read-only analysis"
- **Cross-checks:** Prevents impossible operations (e.g., no apply when not editing)

---

## Architecture Boundaries Preserved

### Studio Ownership
✅ Studio remains a **governed workbench tool**, not an owner of capabilities  
✅ Studio owns: visual composition, tool catalog presentation, preview state, UX workflow  
✅ Studio does NOT own: app/module capability definitions, business logic, apply behavior

### Core Boundary
✅ `/app/Core/*` is unchanged  
✅ No new Core services or contracts  
✅ No Core dependencies on Studio  

### Apps & Modules
✅ Manufacturing, SBAIO, Platform modules unchanged  
✅ Apps remain owners of their routes, capabilities, and data contracts  
✅ Studio is a read-only inspector of app/module structure, not a modifier  

### Routes & Permissions
✅ No new `/ops/design-studio*` routes added  
✅ No new permissions or ACL entries  
✅ `/apps/studio` remains the only Studio entry point  
✅ No backend tool execution routes  

### Database & Migrations
✅ Zero database changes  
✅ Zero migration files  
✅ Studio state lives only in localStorage (draft, preferences, loaded context)  

### Business Behavior
✅ No new business operations  
✅ No change to app/module runtime behavior  
✅ No apply/create/edit/upgrade backend actions wired  
✅ Studio is purely a preview/inspection surface  

---

## Known Remaining Debt

### Template Duplication
**Issue:** `apps/Studio/Views/gui_studio.php` contains duplicated/parallel Studio sections for two different layouts  
**Impact:** Code maintainability; adding new panels requires changes in multiple places  
**Resolution:** Template cleanup recommended as P2 work (see recommendations below)  
**Risk:** Low — duplication is isolated to view file; does not affect architecture or contracts  

### Tool Catalog Metadata Encoding
**Issue:** Tool metadata (icons, descriptions, capabilities, grouping) is partially encoded in view card HTML comments and metadata attributes  
**Impact:** Catalog structure is not centralized; future catalog mutations will require view edits  
**Resolution:** Extract to Studio-owned config/service without behavioral change (see recommendations)  
**Risk:** Low — metadata is read-only at runtime; no contracts broken by extraction  

### Planned Tools Without Backend
**Issue:** All tools are visually rendered but backend wiring is not implemented  
**Impact:** Clicking tools does not execute backend fetches or mutations  
**Design Rationale:** Visual-first approach ensures UX is settled before backend complexity  
**Resolution:** Backend wiring deferred to P2 (see recommendations)  
**Risk:** Low — planned behavior is explicit in UI copy; users are not confused  

### Direct Library/Load Behavior
**Issue:** Direct studio-load flow (e.g., `/apps/studio?library_item=...`) bypasses future governed design  
**Impact:** Library resource loading is not yet governed by ACL/profile shaping  
**Resolution:** Future work will wire load through governed Access Control flow  
**Risk:** Medium — future API contracts may change; current direct load is interim  
**Mitigation:** Load behavior is documented in `/apps/studio/routes.php` with TODO markers  

### Material Management Validation Debt
**Issue:** Material Management module still requires `account_admin` role for tool visibility; not yet harmonized with operator/leader RBAC  
**Impact:** Material Management tool may not be visible to non-admins  
**Resolution:** Align Material Management RBAC with Manufacturing/QC/Dispatch leadership model (post-Studio)  
**Risk:** Low — existing ACL is applied correctly; Studio just exposes it visually  

### `/ops` Root Compatibility Debt
**Issue:** `/ops` (bare) still resolves as a compatibility 404 redirect; should route to `/u/{username}/dashboard` or `/admin/{username}`  
**Impact:** Direct `/ops` requests are not routed correctly  
**Design Rationale:** `/ops` was legacy old admin panel; fully replaced by `/admin/{username}` and `/u/{username}`  
**Resolution:** Documented in AGENTS.md; no action needed for Studio milestone  
**Risk:** Low — backward compatibility maintained via redirect  

### QR & Timecard Compatibility Debt
**Issue:** QR scanning and Timecard flows have bridging code in Shell and Platform; full extraction deferred to future refactor  
**Impact:** QR/Timecard are not fully isolated from admin layer  
**Design Rationale:** Low priority; existing flows work correctly  
**Resolution:** Documented in migration debt; deferred post-Studio  
**Risk:** Low — existing flows are stable  

---

## Recommended Next Safe Work

All following work should pass the same validation gates as this checkpoint:
```bash
bash scripts/architecture/run_architecture_gates.sh
bash scripts/system/check_deployment_readiness.sh
```

### A. Studio Template Cleanup (P2.0)
**Scope:** Deduplicate the dual Studio sections in `gui_studio.php`  
**Effort:** Low — pure refactoring, zero behavior change  
**Validation:** No UI change; lint + architecture gates + visual spot-check  
**Benefit:** Future panel additions require single-site edits  
**Blocker:** None; can start immediately  

### B. Studio Tool Catalog Service (P2.1)
**Scope:** Extract tool metadata from view HTML to `Studio/Services/ToolCatalogService.php`  
**Example:**
```php
class ToolCatalogService {
  public static function getToolsByGroup(): array { ... }
  public static function getTool(string $toolId): array { ... }
}
```
**Effort:** Low — read-only service wrapping existing metadata  
**Validation:** Architecture gates; service used in view without behavior change  
**Benefit:** Catalog mutations and extensions can be done via service config, not view edits  
**Blocker:** None; can start after A (template cleanup)  

### C. Resource Explorer Backend Wiring (P2.2)
**Scope:** Design and implement RESTful Resource Explorer API  
**Endpoints:**
- `GET /api/studio/resources/search?app={app}&module={module}&type={type}` — Search resources  
- `GET /api/studio/resources/{id}` — Load single resource metadata  
- `GET /api/studio/resources/{id}/structure` — Load resource definition (read-only)  
**Validation:** New `/api/studio/*` routes; no changes to app/module capabilities  
**Effort:** Medium  
**Blocker:** Requires API contract design with Manufacturing/SBAIO/Platform owners  

### D. Read-Only App Builder Preview (P2.3)
**Scope:** Render app manifest structure as read-only visual tree  
**Features:**
- List modules, routes, permissions  
- Show validation state (lint, gate pass/fail)  
- Show manifest inheritance/override state  
**Validation:** Architecture gates; no app manifest mutations  
**Effort:** Medium  
**Blocker:** Requires manifest structure stability (AGENT-APPROVED)  

### E. Read-Only Module Builder Preview (P2.4)
**Scope:** Render module widget/adapter/form structure as read-only tree  
**Features:**
- List widgets, adapters, forms, permissions  
- Show module-to-app dependency graph  
- Diff against baseline (read-only)  
**Validation:** Architecture gates; no module code mutations  
**Effort:** Medium  
**Blocker:** Requires module structure audit (P1.5 tool/module navigation docs are prerequisite)  

### F. Governed Create/Edit/Upgrade Backend (P3.0)
**Scope:** Wire apply/diff/create/edit/upgrade backend actions  
**Constraints:**
- All mutations must go through existing app/module apply workflows  
- Studio remains a preview tool, not an ownership layer  
- ACL/profile shaping must be validated before apply  
**Effort:** High  
**Blockers:**
- C, D, E must be complete (explorer API, read-only previews)  
- ACL/Studio apply governance contract must be finalized  
- Manufacturing/SBAIO/Platform must approve apply handler patterns  

---

## Stop-Doing Guidance

### Visual Panels

❌ **Do not add more visual panels until gui_studio.php duplication is cleaned up (P2.0)**

- The current dual-section structure will require changes in two places per new panel
- The cost of duplication grows with each addition
- Template cleanup is low effort with zero risk
- After cleanup, adding panels becomes simple single-site edits

### Backend Wiring

❌ **Do not wire backend tool behavior before ownership/apply contracts are explicit**

- Planned tools are correctly marked as "planned" / "not functional"
- Users understand they are visual previews, not real actions
- Adding backend wiring without governance will violate Studio's authority boundary
- Wait for C (Resource Explorer API) and F (governed apply backend) design to be approved

### Ownership Movement

❌ **Do not move capability ownership into Studio**

- Studio remains a workbench, not an owner
- Apps/modules are the authoritative owners
- Studio previews and composes, it does not mutate
- Future apply workflows must delegate back to owning apps/modules

### Core Changes

❌ **Do not touch Core for Studio UX improvements**

- Core is locked for business behavior and contracts only
- Studio UX belongs in Studio view, service, and JavaScript layers
- Any Core change requires explicit approval from architecture owner

---

## Validation Commands for Future Studio Slices

When implementing future Studio work, always run these validators before committing:

### 1. PHP Syntax Lint
```bash
/opt/homebrew/bin/php -l apps/Studio/Views/*.php apps/Studio/Services/*.php
```

### 2. Architecture Gates (Required)
```bash
bash scripts/architecture/run_architecture_gates.sh
```

**Expected Output:** All gates PASS; Studio changes do not modify Core, do not add non-governed app/module behavior, preserve route confinement.

### 3. Deployment Readiness (Required)
```bash
bash scripts/system/check_deployment_readiness.sh
```

**Expected Output:** PASS; no generated asset drift, no database changes, no orphan code.

### 4. Diff Hygiene
```bash
git diff --check
```

**Expected Output:** No trailing whitespace, no conflicts, no >0 size limits exceeded.

### 5. Git Status
```bash
git status --short
```

**Expected Output:** No untracked files left behind; all intended files staged.

### 6. Visual Spot-Check (if UI changed)
- Open `http://localhost:8000/apps/studio` in authenticated browser
- Verify panels render correctly
- Verify no JS console errors
- Verify localStorage preservation (refresh page, state persists)

---

## Summary: What Changed in This Milestone

| Layer | Change | Status |
|-------|--------|--------|
| **Core** | — | ✅ No changes |
| **App/Module Routes** | — | ✅ No changes |
| **Database** | — | ✅ No changes |
| **Permissions** | — | ✅ No changes |
| **Studio View** | Added 16 visual panels, tool catalog, mode strip, workflow status, clear action | ✅ Complete |
| **Studio Service** | Added OperatorLayerWidgetService, preview bridge helpers | ✅ Complete |
| **Localization** | Added en/ja/ne strings for all new UI elements | ✅ Complete |
| **CSS** | Added studio.css theme tokens, workbench styling | ✅ Complete |
| **Business Logic** | — | ✅ No changes (planned tools not wired) |

---

## Milestone Gates

This checkpoint is ready for production if:

- ✅ All 16 commits in this milestone are on main branch
- ✅ `bash scripts/architecture/run_architecture_gates.sh` exits with 0
- ✅ `bash scripts/system/check_deployment_readiness.sh` exits with 0
- ✅ Studio visually renders as a workbench with all documented panels
- ✅ Loaded resource identity is visible and updatable (clear action works)
- ✅ Mode strip is functional (mode changes persist to localStorage)
- ✅ No Console errors in authenticated browser
- ✅ All localization keys resolve (en/ja/ne)
- ✅ No Core, app/module, or database changes

---

## Next Checkpoint

When work on P2 begins (template cleanup, tool catalog service, resource explorer API), create a companion checkpoint document at:

```
docs/runtime/STUDIO-P2-WORKBENCH-BACKEND-CHECKPOINT.md
```

Include the same structure with P2-specific work items, debt carryover, and P3 recommendations.

---

**End of Checkpoint**
