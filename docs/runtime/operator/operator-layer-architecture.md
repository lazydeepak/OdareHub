# Operator Layer Architecture

**Date**: 2026-04-27  
**Status**: Historical architecture sketch; production route/wrapper rules are in [../../../AGENTS.md](../../../AGENTS.md)

Current experience composition policy is defined in [experience-composition-architecture-plan.md](../../experience-composition-architecture-plan.md). Operator runtime is currently Shell-rendered, but should move toward read-only `ResolvedExperience` diagnostics before any catalog-driven rendering migration.

---

## Overview

The Operator Layer (`/u/{operator}/{view}`) is a **completely separate UI experience** for field operators and task workers. It is separate from admin governance (`/admin/{username}`) and legacy `/me`.

**Key principle**: Operator layer is **independent and isolated**. It has its own service, rendering, styling, and navigation structure. No contamination of existing Shell/Core systems.

---

## Strict Separation Boundaries

### ❌ DO NOT EDIT (Frozen for Operator Layer Phase)

These systems are **locked** during operator layer development:

- `plugins/Base/Views/ops/me.php` — Old Shell layer view
- `apps/Shell/Services/ShellCompositionService` — Old composition logic  
- `app/Core/SidebarBuilder.php` — Old sidebar menu builder
- `public/views/layouts/` — Shell wrapper templates
- `public/assets/app.css` (for Shell layout) — Shared theme only, no layout changes
- All existing menu/navigation rendering code

### ✅ DO EDIT (Operator Layer Only)

Only these files belong to operator layer development:

- `apps/Shell/Services/OperatorLayerService.php` — Core operator layer service
- `apps/Shell/routes.php` — Only the `/u/*` and `/admin/*` layer route definitions
- Operator-specific view templates (future: under `apps/Shell/Views/` or similar)
- Operator-specific CSS (embedded in service, not in app.css)
- Operator-specific JavaScript (if needed)

---

## Current Architecture

### File Structure

```
public/
├── index.php                       # Request preprocessor (url rewriting for /u/*)

apps/Shell/
├── bootstrap.php
├── routes.php                      # Contains /u route handler (operator) and /admin (admin)
├── Services/
│   ├── ShellCompositionService.php # ❌ FROZEN (old /me layer)
│   ├── OperatorLayerService.php    # ✅ ACTIVE (orchestrates operator layer)
│   └── AdminLayerService.php       # ✅ ACTIVE (orchestrates admin layer)
├── Composers/
│   ├── OperatorSurfaceComposer.php # ✅ Composes operator UI
│   └── AdminSurfaceComposer.php    # ✅ Composes admin UI
└── [other Shell files]

plugins/Base/Views/ops/
├── me.php                          # ❌ FROZEN (old Shell layer)
└── [operator-specific views]       # ✅ (future operator views)

composer.json                       # PSR-4: Apps\ → apps/
```

### URL Routing Strategy (Canonical)

Since the Router class only supports exact-path matching (no wildcards), the operator layer uses a **URL preprocessor** pattern and canonical route contract:

1. **Request arrives**: `/u/{operator}/{view}` (example: `/u/lazydeepak/parts`)
2. **Preprocessor** (in `public/index.php`): Matches `/u/{operator}` and `/u/{operator}/{view}` patterns
3. **URL rewritten**: Request is rewritten to `/u` internally with operator/view context in query vars
4. **Router matches**: `/u` route found, calls handler with extracted operator and view
5. **Service renders**: `OperatorLayerService::render()` renders shared shell + selected operator view

**Canonical Contract:**
- Use `/u/{operator}/{view}` for all operator pages
- Keep all operator navigation inside `/u/{operator}/*`
- Do not generate `/u/dashboard/dashboard`
- Do not omit operator identity segment from generated links
- Keep legacy `/u/{view}` links as temporary redirects only

**Regex pattern** (allows usernames, emails, slugs):
```
#^/u/([a-zA-Z0-9._@-]+)(?:/|$|\?)#
```

### Rendering Model

**Old Shell Layer** (frozen):
```
GET /me
  → Router calls ShellCompositionService::renderMyWork($view, ...)
  → Service uses View::render() with Shell layout wrapper
  → Output: Shell composition + sidebar + header + content
  → File: plugins/Base/Views/ops/me.php
```

**New Operator Layer** (active):
```
GET /u/{operator}/{view}
  ↓
  public/index.php extracts operator + view → rewrites to /u with context vars
  ↓
  Router calls /u handler
  ↓
  OperatorLayerService::render($operator, $viewContext) renders standalone operator page
  ↓
  Output: Self-contained operator workspace (shared header/footer/sidebar + data-driven view content)
```

---

## Services & Composers Architecture

### Layer Switching

Both operator and admin layers include footer links to switch between surfaces:

**Operator Layer Footer:**
- Label: "Operator Workspace"
- Link: "Switch to Admin ⚙️" → `/admin/{username}`
- Theme: Light background (#f8f9fa), red accent (#d33b27)

**Admin Layer Footer:**
- Label: "Admin Panel"
- Link: "Switch to Operator 👤" → `/u/{username}`
- Theme: Dark background (#1a1f2e), red accent (#ff4757)

**Access Control:** Admin layer access is role-gated for `platform_admin`; non-admins are redirected to the operator layer. Operator experience visibility is currently influenced by transitional ACL/user override fields and should migrate toward the `ResolvedExperience` model.

### Two-Layer Composition Pattern

The operator layer uses a **service + composer pattern** for clean separation of concerns:

```
HTTP Request → OperatorLayerService → OperatorSurfaceComposer → HTML Output
                (orchestration)        (composition)
```

### Orchestration Layer: Services

**OperatorLayerService** (`apps/Shell/Services/OperatorLayerService.php`)
- Responsibilities:
  - Boot authentication session
  - Check if user is logged in
  - Resolve user context from database
  - Handle authorization checks (TODO: implement role-based scoping)
- Delegates rendering to composer
- Pattern: Keeps business logic separate from UI rendering

**AdminLayerService** (`apps/Shell/Services/AdminLayerService.php`)
- Responsibilities:
  - Boot authentication session for admin users
  - Verify platform_admin role (TODO)
  - Resolve admin context
- Delegates rendering to AdminSurfaceComposer
- Pattern: Same orchestration approach for admin layer

### Composition Layer: Composers

**OperatorSurfaceComposer** (`apps/Shell/Composers/OperatorSurfaceComposer.php`)
- **Purpose**: Compose complete operator layer UI
- **Methods**:
  - `buildNavigation()` - Creates all navigation structures:
    - `composeTopHeaderMenu()` - Workspace, Notifications, Shift Context, Profile
    - `composeSidebarMenu()` - Primary Work, Assigned Queues, Reference Tools, Pinned Links
    - `composeMobileTopNav()` - Today, Queue, Alerts, Context
    - `composeMobileQuickNav()` - Start Task, Resume Work, Scan/Input, Escalate
  - `buildContent()` - Creates work surface content:
    - Empty state (emoji, title, subtitle)
    - Work items placeholder (future: real data binding)
  - `render()` - Generates final HTML page with Gmail-style styling
- **Design**: Light theme (`#f8f9fa` background), operator-focused menus, work queue interface
- **Output**: Complete standalone HTML document (no dependencies)

**AdminSurfaceComposer** (`apps/Shell/Composers/AdminSurfaceComposer.php`)
- **Purpose**: Compose complete admin layer UI
- **Methods**:
  - `buildNavigation()` - Creates admin-specific menus:
    - `composeTopHeaderMenu()` - System Status, Audit Log, Alerts, Profile
    - `composeSidebarMenu()` - Dashboard, Users & Roles, Applications, System Config, Database, Integrations, Audit Trail
    - `composeMobileNav()` - Dashboard, Users, Apps, System
  - `buildContent()` - Creates dashboard widgets:
    - System Health widget
    - Active Users widget
    - App Status widget
    - Recent Audit Events widget
  - `buildFooter()` - Composes footer with layer switching links
  - `render()` - Generates final admin HTML page with dark theme
- **Design**: Dark theme (`#0f1419` background), system-focused menus, dashboard interface
- **Output**: Complete standalone HTML document (no dependencies)

---


### Color Palette (Gmail-inspired)

- **Background**: `#f8f9fa` (light gray)
- **Surface**: `#fff` (white)
- **Border**: `#dadce0` (subtle gray)
- **Text Primary**: `#202124` (dark gray)
- **Text Secondary**: `#5f6368` (medium gray)
- **Accent (Active)**: `#fce8e6` with `#d33b27` text (Gmail red theme)
- **Hover**: `#f1f3f4` (very light gray)

### Components

- **Header**: Sticky top bar with logo, search, actions
- **Sidebar**: Navigation folders/sections (collapsible on mobile)
- **Main Content**: List-based work item display
- **Empty State**: Friendly message when no work items

---

## Development Phases

### Phase 1: Shell Foundation (Complete ✅)
- [x] Create standalone operator layer service
- [x] Implement Gmail-style HTML/CSS
- [x] Register route and verify rendering
- [x] Ensure complete separation from old Shell

### Phase 2: URL Canonicalization (In Progress)
- [ ] Enforce canonical `/u/{operator}/{view}` route generation
- [ ] Normalize existing `/u/*` links to include operator segment
- [ ] Add redirect compatibility for legacy `/u/{view}` links
- [ ] Block double-view URLs like `/u/dashboard/dashboard`

### Phase 3: View Rollout (Next)
- [ ] Dashboard view (`/u/{operator}/dashboard`)
- [ ] Parts view (`/u/{operator}/parts`) from products table
- [ ] Production view (`/u/{operator}/production`)
- [ ] Processing view (`/u/{operator}/processing`)
- [ ] Preparation view (`/u/{operator}/preparation`)
- [ ] Dispatch view (`/u/{operator}/dispatch`)

### Phase 4: Adapter Standardization (Future)
- [ ] Keep all view data in adapters/services (no heavy DB logic in templates)
- [ ] Reuse existing module services and contracts
- [ ] Add per-view validation for route ownership and data integrity
- [ ] Add regression checks for shared shell consistency across all views

### Phase 5: Operator Runtime Hardening (Future)
- [ ] Add route tests for `/u/{operator}/{view}` matrix
- [ ] Add guard tests for cross-operator URL access attempts
- [ ] Add smoke checks for shared shell (header/footer/sidebar) on every operator view
- [ ] Add localization coverage checks for new operator views

---

## Data Adapter Pattern (Phase 3: Custom Views)

### Motivation

Operator layer needs custom, operator-specific views that:
1. Consume module data (don't embed module pages)
2. Render operator-focused UI (not module UI)
3. Stay within `/u/{username}/*` (never link to `/apps/manufacturing/*`)
4. Reuse existing module data services (no new business logic)

### Architecture

**Three-Layer Pattern:**

```
Module Service (existing)
    ↓ Query/Fetch data
    ↓
Operator Adapter (NEW - data transform only)
    ↓ Return pure data structures
    ↓
Operator View (NEW - render operator-specific UI)
    ↓ Never links to /apps/* or embeds module UI
    ↓
Operator Layer (`/u/{username}/*`)
```

### Example: Coverage Adapter

**Module Service** (existing):
```php
Plugins\Coverage\Services\CoverageService::coverageSummary()
  → Returns: ['critical_orders_count' => 5, 'low_coverage_orders_count' => 12, ...]
```

**Adapter** (NEW):
```php
Apps\Shell\Services\OperatorLayerAdapters\CoverageAdapter::getChartData()
  → Calls CoverageService::coverageSummary()
  → Returns: ['rows' => [['label' => 'Critical', 'value' => 5, 'meta' => '...', 'tone' => 'danger']]]
```

**View** (NEW):
```html
<!-- apps/Shell/Views/operator/coverage.php -->
<canvas id="coverageChart"></canvas>
<script>
  renderChart(<?php echo json_encode($chartData['rows']); ?>);
</script>
```

**Route**:
```php
GET /u/{username}/coverage
  → CoverageAdapter::getChartData() [+ getKpiData(), getTableData()]
  → coverage.php renders Chart.js with operator-specific styling
```

### Data Structures

All adapters return consistent data shapes:

**Chart Rows** (all charts):
```php
[
    ['label' => 'Critical', 'value' => 5, 'meta' => 'Severe shortage...', 'tone' => 'danger'],
    ['label' => 'Low Coverage', 'value' => 12, 'meta' => 'Partial supply...', 'tone' => 'warning'],
]
```

**KPI Cards** (all dashboards):
```php
[
    ['key' => 'critical_count', 'label' => 'Critical Orders', 'value' => 5, 'meta' => 'Action items', 'tone' => 'danger'],
    ['key' => 'total_orders', 'label' => 'Total Orders', 'value' => 42, 'meta' => 'In flight', 'tone' => 'info'],
]
```

**Table Rows** (all tables):
```php
[
    ['id' => 1, 'order_number' => 'ORD-001', 'qty' => 100, 'status' => 'critical', 'tone' => 'danger', 'metadata' => [...]],
    ['id' => 2, 'order_number' => 'ORD-002', 'qty' => 50, 'status' => 'stable', 'tone' => 'success', 'metadata' => [...]],
]
```

### Implementation Checklist

**Tier 1: Core Manufacturing** (Start here)
- [ ] CoverageAdapter (simple, data-only, chart-based)
- [ ] QcAdapter (complex, multi-queue, KPI-heavy)

**Tier 2: Secondary Production**
- [ ] ProductionAdapter (work queues)
- [ ] DispatchAdapter (delivery tracking)

**Tier 3: Infrastructure**
- [ ] MachinesAdapter (capacity planning)
- [ ] MaterialsAdapter (stock tracking)
- [ ] AssemblyAdapter (planned sequences)

**Tier 4: Future** (defer)
- SBAIO adapters (Attendance, Payroll, Leave, Timecards)

### Design Principles

1. **Data-Driven**: Adapters fetch via existing services, never hardcode data
2. **Transform Only**: Adapters shape data for operator view, no new business logic
3. **Pure Data**: Return arrays only (no HTML, no URLs to `/apps/*`)
4. **Scope-Aware**: Respect user context (part_ids, role, permissions)
5. **Stateless**: Adapters are static utility classes with no side effects
6. **Module-Independent**: Modules don't change; adapters call existing services

### Status

✅ **Preparation Complete**
- Design documented
- Code templates created (session memory)
- Data structures validated
- Route pattern defined

🔄 **In Progress**
- User review of adapter pattern
- Decision on implementation order

⏳ **To Do**
- Implementation of first adapters (Coverage, QC)
- Creation of operator-specific view templates
- Route handler implementation
- Testing and validation

---

**Rule 1: No Shell Wrapper**
- Operator layer does NOT use `View::render()` or Shell's layout wrapper
- All HTML/CSS is self-contained within the service

**Rule 2: No Core Modifications**
- Core engine (`/app/Core`) is locked
- No changes to Router, Auth, View, or other core systems
- Use existing public APIs only

**Rule 3: Parallel Surfaces**
- Operator layer is a **separate surface**, not a mode of old `/me`
- Both surfaces can coexist and evolve independently
- No shared menu rendering between layers

**Rule 4: Authentication & Context**
- Operator layer boots its own session: `Auth::bootSession()`
- Operator layer resolves user context: `UserAssignmentContext::context()`
- All auth and context decisions are made independently

---

## Commits

- **03a60a0**: Fix operator layer standalone rendering (remove Shell wrapper)
- **1e31c08**: Apply Gmail-style design to operator layer

---

## Next Steps

1. ✅ Define operator-specific menu items (Inbox, Queues, Resources, etc.) — DONE
2. ✅ Implement interaction profile filtering for menu visibility — DONE (OperatorLayerWidgetService)
3. ✅ Create module widget discovery and display — DONE (hamburger menu + module components section)
4. ✅ Implement production adapters and operator-specific custom views — DONE for current route set
5. 🔄 Inventory current operator routes/sidebar/content as owner capability catalog entries
6. 🔄 Build read-only `ResolvedExperience` diagnostics without changing runtime behavior
7. 🔄 Compare diagnostics against current `/u/*` rendering before any renderer migration
8. Add mobile drawer navigation enhancements where still needed
