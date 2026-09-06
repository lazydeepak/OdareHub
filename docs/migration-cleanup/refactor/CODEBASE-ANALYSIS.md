# SBAIO Codebase Comprehensive Analysis

**Generated:** April 16, 2026  
**Scope:** Route definitions, Views/Templates, Navigation, Dead Code Detection, Duplicates, Experimental Features

---

## 1. ROUTE DEFINITIONS

### Route Loading Architecture
**Bootstrap Chain:**
1. Core tables + setup check
2. Base plugin + ACL plugin auto-install
3. All active plugins load their routes
4. App routes (`/app/Routes/*.php`)
5. AppManager routes
6. Enabled apps from `/apps` directory load routes
7. Final route map cached in RouteRuntimeAuthority

### Core App Routes (`/app/Routes/`)
- **[admin_architecture_health.php](app/Routes/admin_architecture_health.php)** — Architecture health inspection endpoints (admin-only)

### Base Plugin Routes (`/plugins/Base/routes.php`) — 2,196 lines
**Primary Routes (Public/Core):**

| Route | Method | Purpose | Status |
|-------|--------|---------|--------|
| `/` | GET | Root resolver → redirects to `/me` | **ACTIVE** |
| `/me` | GET | Platform admin workspace → redirects to `/admin/{current_user_handle}` | **ACTIVE (redirect)** |
| `/admin/{username}` | GET | Platform admin dashboard for user (canonical admin path) | **ACTIVE** |
| `/admin` or `/admin/` | GET | Bare admin → redirects to `/admin/{current_user_handle}` | **ACTIVE (redirect)** |
| `/ops/handoff-board` | GET | Redirects to canonical handoff URL | **ACTIVE** |
| `/ops/audit-log` | GET | Audit Explorer view | **ACTIVE** |

**Admin Dashboards (Role-Based):**
- `/ops/dashboard` - Generic ops dashboard
- `/ops/platform-admin-dashboard` - Platform admin view
- `/ops/app-admin-dashboard` - App admin view
- `/ops/admin-dashboard` - Generic admin redirect
- `/ops/editor-dashboard` - Editor role view
- `/ops/itadmin-dashboard` - IT admin dashboard
- `/ops/sysadmin-dashboard` - System admin dashboard
- `/ops/accountadmin-dashboard` - Account admin dashboard

**ACL & Permissions:**
- `/ops/access-control` - ACL matrix view
- `/ops/access-control/detail` - Role detail with permissions
- `/ops/user-control` - User access control
- `/ops/user-control/detail` - User detail view
- `/ops/user-dashboard` - User-specific dashboard
- `/ops/access-control/save` (POST) - Save ACL changes
- `/ops/access-control/send-message` (POST) - Message users

**Navigation & Settings:**
- `/ops/dashboard-assignments` - Dashboard widget assignments
- `/ops/dashboard-assignments/save` (POST) - Persist widget assignments
- `/ops/navigation-tree` - Navigation structure (JSON)

**Auth Routes:**
- `/login`, `/logout` - Authentication
- `/2fa` - Two-factor authentication
- `/forgot-password`, `/reset-password` - Password recovery
- `/account/setup` - Account initialization

**Notifications:**
- `/me/notifications` - Notifications view
- `/notifications/mark-read` (POST) - Mark notification read

---

### Manufacturing App Routes

#### Core Routes (`/apps/Manufacturing/Routes/`)

**Workboards (Leader Dashboards):**
- `/manufacturing/production-workboard` (GET) — Production leader execution board
- `/manufacturing/qc-workboard` (GET) — QC leader execution board
- `/manufacturing/dispatch-workboard` (GET) — Dispatch leader execution board
- `/manufacturing/assembly-workboard` (GET) — **REDIRECT** → `/manufacturing/assembly-queue` (302)

**Role Dashboards:**
- `/manufacturing/production-dashboard` (GET) — Production leader summary dashboard
- `/manufacturing/assembly-dashboard` (GET) — Assembly leader summary dashboard
- `/manufacturing/qc-dashboard` (GET) — QC leader summary dashboard
- `/manufacturing/dispatch-dashboard` (GET) — Dispatch leader summary dashboard

**Legacy Compatibility Redirects (from [compat.php](apps/Manufacturing/Routes/compat.php)):**
- `/dispatch` → `/dispatch-entries` (301)
- `/qc` → `/qc-entries` (301)
- `/production` → `/manufacturing/production-queue` (301)
- `/manufacturing` → `/apps/manufacturing` (301)
- `/mfg` → `/apps/manufacturing` (301)
- `/assembly-plans` → `/manufacturing/assembly-plans` (301)

**Legacy dashboard redirects:**
- `/ops/production-dashboard` → `/manufacturing/production-dashboard` (302)
- `/ops/assembly-dashboard` → `/manufacturing/assembly-dashboard` (302)
- `/ops/qc-dashboard` → `/manufacturing/qc-dashboard` (302)
- `/ops/dispatch-dashboard` → `/manufacturing/dispatch-dashboard` (302)
- `/ops/production-leader-dashboard` → `/manufacturing/production-dashboard` (302)
- `/ops/assembly-leader-dashboard` → `/manufacturing/assembly-dashboard` (302)
- `/ops/qc-leader-dashboard` → `/manufacturing/qc-dashboard` (302)
- `/ops/dispatch-leader-dashboard` → `/manufacturing/dispatch-dashboard` (302)

**Execution/Operations:**
- `/apps/manufacturing` (GET) — Main Manufacturing portal + Demand execution dashboard
- `/apps/manufacturing/production-operation` (GET/POST) — Production operation center
- `/apps/manufacturing/processing-operation` (GET/POST) — QC/Assembly processing center
- `/apps/manufacturing/processing-operation/assembly-update` (POST) — Update assembly status
- `/apps/manufacturing/processing-operation/qc-update` (POST) — Update QC status
- `/manufacturing/qc-queue` (GET) — QC queue view (canonical)
- `/manufacturing/assembly-queue` (GET) — Assembly queue view (canonical)
- `/manufacturing/execution-dashboard` → `/apps/manufacturing` (302)

**Dispatch Operations:**
- `/manufacturing/dispatch-ops` (GET) — Dispatch operations main view
- `/manufacturing/dispatch-ops/preparation` (GET) — Dispatch preparation form
- `/manufacturing/dispatch-ops/preparation` (POST) — Save dispatch prep
- `/manufacturing/dispatch-ops/ready` (POST) — Mark shipment ready
- `/manufacturing/dispatch-ops/prepare` (POST) — Prepare shipment
- `/manufacturing/dispatch-ops/complete` (POST) — Complete shipment

**Handoff Board:**
- `/apps/manufacturing/handoffs` (GET) — Handoff board (cross-role coordination)
- `/apps/manufacturing/handoffs/assign-owner` (POST) — Assign owner to handoff item
- `/apps/manufacturing/handoffs/escalate` (POST) — Escalate handoff item

**Assembly Plans:**
- `/manufacturing/assembly-plans` (GET) — Assembly plans list
- `/manufacturing/assembly-plans/detail` (GET) — Assembly plan detail
- `/manufacturing/assembly-plans/update` (POST) — Update plan
- `/manufacturing/assembly-plans/approve` (POST) — Approve plan
- `/manufacturing/assembly-plans/execution-log` (POST) — Add execution entry
- `/manufacturing/assembly-plans/execution-approve` (POST) — Approve execution
- `/manufacturing/assembly-plans/transition` (POST) — Transition plan state
- `/manufacturing/assembly-workbench` → `/manufacturing/assembly-plans?only_open=1` (redirect)

**Daily Orders:**
- `/daily-orders` (GET) — Daily orders list
- `/daily-orders/add` (GET/POST) — Create new daily order
- `/daily-orders/import` (GET/POST) — Import daily orders
- `/daily-orders/edit` (GET/POST) — Edit daily order
- `/daily-orders/delete` (POST) — Delete daily order
- `/daily-orders/360` (GET) — Order 360 detail view

**Production Plans:**
- `/production-plans` (GET) — Production plans list
- `/production-plans/add` (GET/POST) — Create plan
- `/production-plans/start-draft` (POST) — Create draft
- `/production-plans/edit` (GET/POST) — Edit plan
- `/production-plans/delete` (POST) — Delete plan
- `/production-plans/approval-action` (POST) — Submit/approve/reopen plan
- `/production-plans/print-pdf` (GET) — View PDF
- `/production-plans/download-pdf` (GET) — Download PDF
- `/production-plans/print-next-two-weeks` (GET) — Print 2-week schedule
- `/production-plans/print-next-two-weeks-pdf` (GET) — PDF 2-week schedule
- `/production-plans/queue` (GET) — Production queue view

**Other Routes:**
- Coverage routes (via [coverage.php](apps/Manufacturing/Routes/coverage.php) → apps/Manufacturing/modules/Coverage/routes.php)
- Stage transitions, demands, production queues, etc.

---

### Plugin Routes Summary

| Plugin | Routes | Size | Status | Purpose |
|--------|--------|------|--------|---------|
| **Base** | 30+ core + admin | 2,196 lines | **ACTIVE** | Core platform, auth, dashboards, ACL, notifications |
| **ACL** | ~20 | 105 lines | **ACTIVE** | Role/permission management |
| **AdminTools** | ~15 | 203 lines | **ACTIVE** | Admin tools interface |
| **Audit** | Event tracking | 2 lines | **ACTIVE** | Audit log integration |
| **Bus** | Event bus | 2 lines | **ACTIVE** | Event handling |
| **DomPdf** | PDF generation | 21 lines | **ACTIVE** | PDF rendering |
| **_PluginTemplate** | N/A | 2 lines | **UNUSED** | Scaffolding template |

---

## 2. DUPLICATED MODULES

### ⚠️ CRITICAL: 8 Modules Exist in BOTH Locations

These modules have **identical copies** in `plugins/` and `apps/Manufacturing/modules/`:

| Module | Plugin Size | Manufacturing Size | Views | Status |
|--------|-------------|-------------------|-------|--------|
| **DailyOrders** | 77 lines | duplicated | 5 identical | DUAL-DEFINED |
| **DispatchEntries** | 123 lines | duplicated | 5 identical | DUAL-DEFINED |
| **Ledger** | 55 lines | duplicated | 3 identical | DUAL-DEFINED |
| **Machines** | 68 lines | duplicated | 5 identical | DUAL-DEFINED |
| **PartMachineMap** | 55 lines | duplicated | 3 identical | DUAL-DEFINED |
| **PreOrders** | 71 lines | duplicated | 4 identical | DUAL-DEFINED |
| **ProductionEntries** | 55 lines | duplicated | 3 identical | DUAL-DEFINED |
| **ProductionPlans** | 123 lines | duplicated | 3 identical | DUAL-DEFINED |
| **Products** | 126 lines | duplicated | 4 identical | DUAL-DEFINED |
| **QCEntries** | 81 lines | duplicated | 4 identical | DUAL-DEFINED |
| **QCPlans** | 62 lines | duplicated | 4 identical | DUAL-DEFINED |

**Total Duplicate Code:** ~900 lines of route definitions + massive duplication of Views, Controllers, Services, EntityDefinitions

### Resolution Strategy
Based on code analysis:
- **Canonical location:** `apps/Manufacturing/modules/` (app-native)
- **Legacy location:** `plugins/` (pre-migration)
- **Action:** Remove `plugins/*` versions entirely and migrate to app-native structure
- **Tests currently reference both:** Test files import from both locations (needs cleanup)

---

## 3. VIEWS & TEMPLATES

### Base Plugin Views (`/plugins/Base/Views/`)
- `home.php` — **NOT RENDERED** (legacy, unused)
- `home_portal.php` — **NOT RENDERED** (experimental, unused)
- `notifications.php` — **ACTIVE** (rendered in `/me/notifications` route)
- `approval_inbox.php` — **REFERENCED** but rendered indirectly via service
- `cross_role_handoff.php` — **REFERENCED** but rendered via service redirect
- `role_inbox.php` — **REFERENCED** but rendered via service redirect

**Subdirectories:**
- `admin/` — Admin panel views, apps manager
- `auth/` — Login, 2FA, password recovery views
- `ops/` — Operational dashboards (my_work_v2.php, platform_admin, etc.)
- `account/` — Account management views

### Manufacturing App Views (`/apps/Manufacturing/Views/`)
- `exports.php` — Data export interface
- `imports.php` — Data import interface
- `restores.php` — Backup restore interface
- `assembly_plans/` — Assembly plan CRUD
- `daily_orders/` — Daily order CRUD
- `production_plans/` — Production plan CRUD
- `production_queue/` — Queue board view
- `handoffs/` — Handoff board
- `dispatch_ops/` — Dispatch operations
- `demand/`, `demands/` — Demand views
- `stage_board/` — Workflow stage board
- `coverage/` — Coverage planning

### Manufacturing Module Views (example: DailyOrders)
- `apps/Manufacturing/modules/DailyOrders/Views/index.php`
- `apps/Manufacturing/modules/DailyOrders/Views/add.php`
- `apps/Manufacturing/modules/DailyOrders/Views/edit.php`
- `apps/Manufacturing/modules/DailyOrders/Views/import.php`
- `apps/Manufacturing/modules/DailyOrders/Views/order_360.php`

**Plus duplicate copies in:**
- `plugins/DailyOrders/Views/` (identical)

### SBAIO App Views (`/apps/SBAIO/Views/`)
- `dashboard.php` — SBAIO dashboard
- `exports.php`, `imports.php`, `restores.php` — Data operations
- `reports.php` — Reporting interface
- `partials/` — Shared view components

---

## 4. NAVIGATION STRUCTURE

### Sidebar Configuration (`/app/Navigation/`)
- **[sidebar.php](app/Navigation/sidebar.php)** — Master sidebar structure (sections, groups, sorting, auto-open behavior)
- **[sidebar_sources.php](app/Navigation/sidebar_sources.php)** — Sources list (deprecated per comments)
- **[sidebar_sources/core.php](app/Navigation/sidebar_sources/core.php)** — Core operations navigation
- **[sidebar_sources/admin.php](app/Navigation/sidebar_sources/admin.php)** — Admin navigation
- **[sidebar_sources/apps.php](app/Navigation/sidebar_sources/apps.php)** — App navigation (deprecated, empty)

### Sidebar Groups Defined
| Key | Section | Label | Source | Order |
|-----|---------|-------|--------|-------|
| `platform_operations_work` | Operations | Work | core.operations.work | 10 |
| `platform_operations_review` | Operations | Approve / Review | core.operations.review | 20 |
| `platform_operations_tracking` | Operations | Track / Coordinate | core.operations.tracking | 30 |
| `manufacturing_workboards` | Apps | Manufacturing | apps.manufacturing.workboards | 10 |
| `manufacturing_execution` | Apps | Demand & Orders | apps.manufacturing.demand_orders | 20 |
| `manufacturing_execution_queues` | Apps | Work Queues | apps.manufacturing.queues | 30 |
| `manufacturing_reference` | Apps | Reference / Planning | (continuation...) | 40 |

### Navigation Link Usage
From `MyWorkService::build()`:
- `/me` — Personal workspace (home)
- `/apps/manufacturing` — Manufacturing portal
- `/manufacturing/production-workboard` — Production leader workboard
- `/manufacturing/qc-workboard` — QC leader workboard
- `/manufacturing/dispatch-workboard` — Dispatch leader workboard
- `/manufacturing/assembly-queue` — Assembly queue
- `/manufacturing/assembly-plans` — Assembly planning
- `/manufacturing/dispatch-ops` — Dispatch operations
- `/apps/manufacturing/handoffs` — Cross-role handoff board
- `/ops/approval-inbox` — Approval/governance inbox (referenced but indirect)

---

## 5. DEAD CODE & UNUSED ROUTES

### Potentially Unused/Legacy Views
| View | Location | Status | Reason |
|------|----------|--------|--------|
| `home.php` | plugins/Base/Views | **UNUSED** | MyWork dashboard uses `ops/my_work_v2.php` instead |
| `home_portal.php` | plugins/Base/Views | **UNUSED** | Appears to be experimental portal, never rendered |
| `role_inbox.php` | plugins/Base/Views | **REFERENCED but indirect** | Service builds host regions; view not directly called |
| `cross_role_handoff.php` | plugins/Base/Views | **REFERENCED but indirect** | Service redirect used instead; view wrapper only |

### Dashboard Redirects (302 Temporary)
All `/ops/*-dashboard` routes now redirect to `/manufacturing/*-dashboard`. These are:
- **Soft deprecation** — not removed but redirected
- Bookmarks will still work (but via redirect)
- Canonic URLs are `/manufacturing/*-dashboard`

### Coverage Module Complexity
- [apps/Manufacturing/modules/Coverage/](apps/Manufacturing/modules/Coverage/) exists
- Routes loaded via [apps/Manufacturing/Routes/coverage.php](apps/Manufacturing/Routes/coverage.php)
- Service at `app/Core/CoverageService.php` has bridge to Manufacturing version
- **Status:** ACTIVE but complex dual-location pattern

---

## 6. EXPERIMENTAL / INCOMPLETE FEATURES

### Code Marked as Experimental
| Location | Comment | Status |
|----------|---------|--------|
| `app/Services/PluginCatalogService.php` | 'Future / Experimental' category | Defined but feature category |
| `plugins/Base/Views/admin/apps.php` | "Future / Experimental" section in UI | UI section for future apps |
| `app/Core/PluginManager.php` | Experimental flag in plugin.json | Plugin metadata flag |

### TODO/FIXME in Application Code (Non-Vendor)
Limited TODOs found in app code:
- `app/Services/PluginCatalogService.php` — Plugin category definitions
- Test files have some inline TODOs

Most TODOs are in vendor/dompdf (PDF library - safe to ignore)

### Incomplete Patterns Detected

**1. Handoff Board Conditional Availability:**
```php
// apps/Manufacturing/Routes/handoffs.php
if (!HandoffBoardService::isAvailable()) {
    header('Location: /me', true, 302);
    exit;
}
```
- Feature flag-gated: `HandoffBoardService::isAvailable()`
- **Status:** Feature complete but conditional

**2. Assembly Plan Service Availability:**
```php
// apps/Manufacturing/Routes/assembly_plans.php
AssemblyPlanService::requireAvailability();
```
- Similar conditional pattern
- **Status:** Feature complete but conditional

**3. Platform Mode Refinement** (doc: [docs/platform_mode_refinement_plan.md](docs/platform_mode_refinement_plan.md))
- Multiple routing strategies exist
- Ongoing architecture evolution documented

---

## 7. COMPREHENSIVE ROUTE USAGE ANALYSIS

### Routes with Navigation Links
**Actively Promoted (in Navigation):**
- ✅ `/apps/manufacturing` — Main app entry
- ✅ `/manufacturing/production-workboard` — Production leader
- ✅ `/manufacturing/qc-workboard` — QC leader
- ✅ `/manufacturing/dispatch-workboard` — Dispatch leader
- ✅ `/manufacturing/assembly-queue` — Assembly execution
- ✅ `/manufacturing/assembly-plans` — Assembly planning
- ✅ `/manufacturing/dispatch-ops` — Dispatch operations
- ✅ `/apps/manufacturing/handoffs` — Handoff coordination
- ✅ `/me` — Personal workspace

### Routes Without Navigation Links (Internal/API)
- POST actions (create, update, delete, state transitions)
- Administrative endpoints (`/ops/*`)
- Special-purpose endpoints (`/2fa`, `/setup`, etc.)

### Routes Never Referenced in Codebase
- None identified (all routes either nav-linked, API endpoints, or redirects)

---

## 8. RECOMMENDED CLEANUP PRIORITY

### PRIORITY 1 (Immediate - High Risk Duplication)
1. **Remove all plugin-based modules** (8 modules):
   - Delete `plugins/DailyOrders/`, `plugins/DispatchEntries/`, etc.
   - Keep only `apps/Manufacturing/modules/` versions
   - **Effort:** Medium | **Risk:** Medium | **Value:** Very High
   - **Reason:** Eliminates ~900 lines of duplicate routes + massive view/controller duplication
   - **Test impact:** Update test imports to use app-native paths only

2. **Remove unused view templates**:
   - `plugins/Base/Views/home.php`
   - `plugins/Base/Views/home_portal.php`
   - **Effort:** Low | **Risk:** Low | **Value:** Low
   - **Reason:** Never rendered; safe removal

### PRIORITY 2 (Medium - Consolidation)
3. **Consolidate dashboard redirects**:
   - `/ops/production-dashboard` and siblings currently redirect (302)
   - Decide: keep as compatibility layer OR remove + update bookmarks
   - **Effort:** Low-Medium | **Risk:** Low (users affected by redirect removal)
   - **Value:** Medium
   - **Reason:** Reduces route clutter

4. **Clarify Coverage module architecture**:
   - Document why Coverage exists in BOTH `plugins/Coverage/` and `apps/Manufacturing/modules/Coverage/`
   - Choose single authority location
   - **Effort:** Medium | **Risk:** Medium
   - **Value:** Medium
   - **Reason:** Current dual-location pattern is confusing

### PRIORITY 3 (Low - Documentation)
5. **Document experimental features**:
   - Handoff board conditional availability
   - Assembly plan service gates
   - **Effort:** Low | **Risk:** None
   - **Value:** Low
   - **Reason:** Help future developers understand feature flags

---

## 9. ROUTING ARCHITECTURE NOTES

### Route Loading Order (Matters!)
1. **Plugins first** (Base, ACL, then others)
   - Plugins can define fallback routes
2. **App routes** (/app/Routes/)
   - Architecture health, admin tools
3. **AppManager routes**
   - Setup, app management
4. **Enabled apps** (/apps/Manufacturing, etc.)
   - Can override earlier routes due to loading order
   - Manufacturing routes override compatibility routes

### Why Compatibility Routes Exist
- System migrated from plugin-based to app-based architecture
- Old bookmarks + navigation links still reference `/ops/` paths
- 302 redirects provide graceful migration path
- Users' browser history auto-updates through redirects

### View Rendering Pattern
- Views use namespace: `$view->render('Base::home.php')`
- Services can build "host regions" (dynamic view fragments)
- Some views are rendered indirectly via services (`approval_inbox`, `role_inbox`)

---

## 10. STATISTICS SUMMARY

| Metric | Count |
|--------|-------|
| **Total Route Files** | 43 |
| **Routes in plugins/** | ~20 route files, 3,495 lines |
| **Routes in apps/Manufacturing/Routes/** | 14 route files |
| **Total Unique Routes** | 250+ |
| **Duplicate Module Locations** | 11 (plugins/ + apps/Manufacturing/modules/) |
| **View Files** | 144+ |
| **Navigation Groups** | 8+ configured |
| **Base Plugin Routes** | 30+ with special behavior (redirects, conditionals) |
| **Dead/Unused Views** | 2-3 (home.php, home_portal.php, experimental) |
| **Legacy Compatibility Redirects** | 12 (302 temporary) |
| **Unused Plugins** | 1 (_PluginTemplate) |

---

## KEY RECOMMENDATIONS

1. **Execute module consolidation** (Priority 1) - Remove plugin versions, keep apps/Manufacturing/modules/ only
2. **Update test infrastructure** - All tests must reference canonical app-native paths
3. **Document architecture decisions** - Why certain dual-location patterns exist (Coverage, etc.)
4. **Create deprecation schedule** - For legacy 302 redirects (currently indefinite)
5. **Clean up experimental code** - Clarify status of feature-gated endpoints
6. **Remove true dead code** - home.php, home_portal.php views that are never rendered

**Estimated Cleanup Effort:** 2-3 developer-days for full consolidation + testing

---

*End of Analysis*
