# Experience Layout Composition System
## How `/me` Layout is Computed & Fed to User Landing Page

**Date**: 2024-05-16  
**Status**: Phase 6 Foundation - Config stored, rendering pending implementation  
**Owner**: Shell (Apps/Shell)  
**Related Docs**: 
- [access-control-view-architecture.md](docs/access-control-view-architecture.md)
- [phase6-5-contract-decomposition-design.md](docs/phase6-5-contract-decomposition-design.md)

---

## 1. System Overview

The **Experience Layout Composition System** allows platform admins to customize what appears on the `/me` admin dashboard for each user by:
1. Selecting which dashboard blocks to show/hide
2. Selecting which plugin cards to show/hide  
3. Defining the display order via up/down buttons

### Current State: **Configuration STORED but NOT YET CONSUMED**

- ✅ UI Editor: `plugins/Base/Views/ops/experience_layout.php` - Full sortable UI with checkboxes
- ✅ Service Save: `UserDashboardAssignmentService::saveExperienceLayoutFromInput()` - Persists CSV to DB
- ✅ Database: `user_dashboard_assignments` table with `me_dashboard_blocks` and `me_plugin_cards` columns
- ✅ Context Passing: `AdminSurfaceComposer` passes `me_dashboard_blocks` and `me_plugin_cards` to view template
- ❌ **MISSING**: Rendering logic that actually consumes these CSV values to conditionally show/hide blocks and cards

---

## 2. Architecture Overview

### 2.1 Data Model

**Database Table**: `user_dashboard_assignments`

| Column | Type | Format | Scope |
|--------|------|--------|-------|
| `me_dashboard_blocks` | TEXT | CSV ordered keys | Platform admin only |
| `me_plugin_cards` | TEXT | CSV ordered keys | All users (normalized per authority role) |

**Storage Format**: Comma-separated list of enabled block/card keys in display order
```
Example: "admin_panels,platform_tools,top_nav,workspace_actions,search_alerts,operational_summary,primary_widgets,monitoring_widgets,action_queues,quick_links"
```

### 2.2 Data Flow

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. ADMIN CONFIG PHASE (in progress)                             │
├─────────────────────────────────────────────────────────────────┤
│ Admin edits at: /ops/access-control/detail?user_id=X&tab=experience
│ UI: plugins/Base/Views/ops/experience_layout.php
│ ├─ Sortable list of dashboard blocks (checkboxes + ↑↓ buttons)
│ ├─ Sortable list of plugin cards (checkboxes + ↑↓ buttons)
│ └─ Form submits to: /ops/access-control/save-experience
│
│ Service saves: UserDashboardAssignmentService::saveExperienceLayoutFromInput()
│ ├─ Validates blocks for user's authority role (app_admin, platform_admin)
│ ├─ Validates cards for assigned apps and permissions
│ ├─ Normalizes CSV from form input
│ └─ UPSERTS into user_dashboard_assignments (me_dashboard_blocks, me_plugin_cards)
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│ 2. PAGE LOAD PHASE (partially working)                          │
├─────────────────────────────────────────────────────────────────┤
│ User navigates to: /me or / (authenticated, redirects to /admin/{username})
│ Route: apps/Shell/routes.php → AdminLayerService::renderHome()
│ │
│ Service loads:
│ ├─ Auth::user() → current authenticated user
│ ├─ UserAssignmentContext::resolve($user) → loads user_dashboard_assignments
│ │  ├─ me_dashboard_blocks → CSV (✅ loaded)
│ │  └─ me_plugin_cards → CSV (✅ loaded)
│ └─ Returns user context array with 'me_dashboard_blocks' and 'me_plugin_cards'
│
│ Composer builds: AdminSurfaceComposer::renderHTML()
│ ├─ Loads workspace data (MyWorkService)
│ ├─ Loads host regions (HostSurfaceRegistryService)
│ ├─ Passes data to view:
│ │  ├─ 'me_dashboard_blocks' => (array)($ctx['me_dashboard_blocks'] ?? [])
│ │  └─ 'me_plugin_cards' => (array)($ctx['me_plugin_cards'] ?? [])
│ └─ Renders: apps/Shell/Views/admin/home.php
│
│ View includes: apps/Shell/Views/admin/dashboard.php
│ ├─ Uses $kpiCards (hardcoded manufacturing KPI layout)
│ ├─ Uses $dashboardItems (hardcoded dashboard items)
│ └─ ❌ DOES NOT use $me_dashboard_blocks or $me_plugin_cards
│
│ Result: Blocks and cards always rendered in FIXED order, regardless of config
└─────────────────────────────────────────────────────────────────┘
```

### 2.3 Available Dashboard Blocks

From `UserDashboardAssignmentService::ME_DASHBOARD_BLOCKS` (line 912):

```php
'admin_panels'              => 'Admin Dashboard Panels',
'platform_tools'            => 'Platform Admin Tools',
'top_nav'                   => 'Top Navigation / Module Launcher',
'workspace_actions'         => 'Workspace Actions',
'search_alerts'             => 'Search + Alerts',
'operational_summary'       => 'Operational Summary',
'primary_widgets'           => 'Primary Work Widgets',
'monitoring_widgets'        => 'Monitoring Widgets',
'action_queues'             => 'Action Queue Tables',
'quick_links'               => 'App Quick Links',
```

### 2.4 Available Plugin Cards

From `UserDashboardAssignmentService::ME_PLUGIN_CARDS` (line 925):

```php
'approval_inbox'            => 'Approval Inbox',
'notifications'             => 'Notifications',
'manufacturing_workspace'   => 'Manufacturing Workspace',
'daily_orders'              => 'Daily Orders',
'production_plans'          => 'Production Plans',
'dispatch_entries'          => 'Dispatch Entries',
'production_workboard'      => 'Production Workboard',
'qc_workboard'              => 'QC Workboard',
'dispatch_ops'              => 'Dispatch Ops',
'cross_role_handoff'        => 'Cross-Role Handoff',
'coverage_analytics'        => 'Coverage Analytics',
```

Platform-admin-only cards (line 947):

```php
'platform_health'           => 'Platform Health',
'security_posture'          => 'Security Posture',
'route_registry'            => 'Route Registry',
'acl_overrides'             => 'ACL Overrides',
'products'                  => 'Products',
'schema_sync'               => 'Schema Sync',
'security_alerts'           => 'Security Alerts',
```

---

## 3. Current Implementation Status

### 3.1 What Works ✅

| Component | File | Status | Notes |
|-----------|------|--------|-------|
| UI Editor | `plugins/Base/Views/ops/experience_layout.php` | ✅ Implemented | Sortable lists with drag/reorder, checkboxes, validation hints |
| Form Post | `plugins/Base/Views/ops/experience_layout.php` | ✅ Implemented | Submits to `/ops/access-control/save-experience` |
| Service Save | `UserDashboardAssignmentService::saveExperienceLayoutFromInput()` | ✅ Implemented | Validates, normalizes, upserts to DB |
| Context Load | `UserAssignmentContext::resolve()` | ✅ Implemented | Loads me_dashboard_blocks/me_plugin_cards from DB into context |
| Composer Pass | `AdminSurfaceComposer::renderHTML()` | ✅ Implemented | Passes CSV arrays to view template |
| View Template | `apps/Shell/Views/admin/home.php` | ✅ Implemented | Receives variables via view render call |

### 3.2 What's Missing ❌

| Component | File | Status | Action Needed |
|-----------|------|--------|----------------|
| CSV Parsing | N/A (no consumer yet) | ❌ Missing | Parse CSV string to ordered array in rendering logic |
| Conditional Rendering | `apps/Shell/Views/admin/dashboard.php` | ❌ Missing | Use parsed blocks/cards to render only selected items |
| Block Isolation | `apps/Shell/Views/admin/dashboard.php` | ❌ Missing | Extract each dashboard section into conditional block |
| Card Rendering | `apps/Shell/Views/admin/dashboard.php` | ❌ Missing | Loop through plugin cards in configured order |
| Fallback Logic | N/A | ❌ Missing | Provide sensible defaults if config is empty |

---

## 4. Step-by-Step Implementation Plan

### Phase 4A: Parser & Context Service (Foundation)

**Goal**: Create reusable service to parse CSV blocks/cards and validate against user authority

**Files to Create**:
- `apps/Shell/Services/ExperienceLayoutParserService.php` - Parse CSV, resolve keys to definitions

**Methods**:
```php
class ExperienceLayoutParserService
{
    /**
     * Parse me_dashboard_blocks CSV and return ordered array
     * @param string $csv comma-separated block keys
     * @param array $userContext user assignment context
     * @return array<int,array{key:string, label:string, enabled:bool}>
     */
    public static function parseBlocks(string $csv, array $userContext): array

    /**
     * Parse me_plugin_cards CSV and return ordered array
     * @param string $csv comma-separated card keys  
     * @param array $userContext user assignment context
     * @return array<int,array{key:string, label:string, enabled:bool}>
     */
    public static function parseCards(string $csv, array $userContext): array

    /**
     * Get default blocks for authority role
     * @return array<string>
     */
    public static function defaultBlocksForRole(string $authorityRole): array

    /**
     * Get default cards for authority role
     * @return array<string>
     */
    public static function defaultCardsForRole(string $authorityRole): array
}
```

**Inputs to Context**:
- `$userContext['me_dashboard_blocks']` - CSV from DB
- `$userContext['authority_role']` - User's role (platform_admin, app_admin, operator, etc.)
- `$userContext['assigned_apps']` - User's assigned apps

**Output to View**:
```php
// In AdminSurfaceComposer::renderHTML()
$parsedBlocks = ExperienceLayoutParserService::parseBlocks(
    (string)($this->ctx['me_dashboard_blocks'] ?? ''),
    $this->ctx
);
$parsedCards = ExperienceLayoutParserService::parseCards(
    (string)($this->ctx['me_plugin_cards'] ?? ''),
    $this->ctx
);

$this->view->render('shell::admin/home.php', [
    // ... existing vars ...
    'me_dashboard_blocks_parsed' => $parsedBlocks,
    'me_plugin_cards_parsed' => $parsedCards,
]);
```

### Phase 4B: Dashboard View Refactor (Rendering)

**Goal**: Modify `apps/Shell/Views/admin/dashboard.php` to consume parsed layout and conditionally render blocks/cards

**Changes**:

1. **Extract Fixed Sections into Reusable Blocks**
   - Move KPI grid into separate include: `apps/Shell/Views/admin/blocks/kpi_hero.php`
   - Move flow grid into separate include: `apps/Shell/Views/admin/blocks/flow_section.php`
   - Move timeline into separate include: `apps/Shell/Views/admin/blocks/timeline_section.php`
   - Move admin panels into separate include: `apps/Shell/Views/admin/blocks/admin_panels.php`
   - etc. for each block

2. **Implement Layout Loop in dashboard.php**
   ```php
   // apps/Shell/Views/admin/dashboard.php
   
   $parsedBlocks = (array)($me_dashboard_blocks_parsed ?? []);
   $parsedCards = (array)($me_plugin_cards_parsed ?? []);
   
   // Get defaults if empty
   if (empty($parsedBlocks)) {
       $parsedBlocks = ExperienceLayoutParserService::defaultBlocksForRole(
           (string)($authority_role ?? 'operator')
       );
   }
   if (empty($parsedCards)) {
       $parsedCards = ExperienceLayoutParserService::defaultCardsForRole(
           (string)($authority_role ?? 'operator')
       );
   }
   
   // Render dashboard blocks in configured order
   foreach ($parsedBlocks as $blockDef) {
       $blockKey = (string)($blockDef['key'] ?? '');
       $blockFile = APP_ROOT . "/apps/Shell/Views/admin/blocks/{$blockKey}.php";
       
       if (file_exists($blockFile)) {
           include $blockFile;
       }
   }
   
   // Render plugin cards in configured order
   $cardSection = null;
   foreach ($parsedCards as $cardDef) {
       $cardKey = (string)($cardDef['key'] ?? '');
       $cardFile = APP_ROOT . "/apps/Shell/Views/admin/cards/{$cardKey}.php";
       
       if (file_exists($cardFile)) {
           if ($cardSection === null) {
               echo '<section class="me-cards-container">';
               $cardSection = true;
           }
           include $cardFile;
       }
   }
   if ($cardSection) {
       echo '</section>';
   }
   ```

3. **Block File Structure**
   ```
   apps/Shell/Views/admin/blocks/
   ├── admin_panels.php         # Admin dashboard panels
   ├── platform_tools.php       # Platform admin quick links
   ├── top_nav.php              # Module launcher (may be shared with header)
   ├── workspace_actions.php    # Action tiles
   ├── search_alerts.php        # Search + alerts bar (may be shared with header)
   ├── operational_summary.php  # KPI + flow + timeline combined
   ├── primary_widgets.php      # Primary work widgets from host regions
   ├── monitoring_widgets.php   # Monitoring widgets from host regions
   ├── action_queues.php        # Queue tables
   └── quick_links.php          # App quick links row
   
   apps/Shell/Views/admin/cards/
   ├── approval_inbox.php
   ├── notifications.php
   ├── manufacturing_workspace.php
   ├── daily_orders.php
   ├── production_plans.php
   ├── dispatch_entries.php
   ├── production_workboard.php
   ├── qc_workboard.php
   ├── dispatch_ops.php
   ├── cross_role_handoff.php
   ├── coverage_analytics.php
   ├── platform_health.php
   ├── security_posture.php
   ├── route_registry.php
   ├── acl_overrides.php
   ├── products.php
   ├── schema_sync.php
   └── security_alerts.php
   ```

### Phase 4C: Integration (Context + View)

**Goal**: Wire parser service into AdminSurfaceComposer and dashboard view

**AdminSurfaceComposer.php Changes**:
```php
public function renderHTML(): void
{
    // ... existing code ...
    
    $parsedBlocks = ExperienceLayoutParserService::parseBlocks(
        (string)($this->ctx['me_dashboard_blocks'] ?? ''),
        $this->ctx
    );
    $parsedCards = ExperienceLayoutParserService::parseCards(
        (string)($this->ctx['me_plugin_cards'] ?? ''),
        $this->ctx
    );
    
    // Add to view data
    $viewData = [
        // ... existing ...
        'me_dashboard_blocks_parsed' => $parsedBlocks,
        'me_plugin_cards_parsed' => $parsedCards,
    ];
    
    $this->view->render('shell::admin/home.php', $viewData);
}
```

### Phase 4D: Testing & Validation

**Test Cases**:

1. **Empty Config Fallback**
   - User with no experience layout config → uses role-based defaults
   - Verify: All default blocks render in correct order

2. **Partial Config**
   - User with only 3 blocks enabled → renders exactly those 3 in configured order
   - Verify: Disabled blocks are not rendered

3. **Authority Role Filtering**
   - app_admin user → cannot see platform_health card
   - platform_admin user → can see platform_health card
   - Verify: Cards filtered per role

4. **Order Preservation**
   - Blocks configured as: "quick_links,admin_panels,operational_summary"
   - Verify: Rendered in that exact order

5. **Card Order**
   - Cards configured as: "notifications,approval_inbox,manufacturing_workspace"
   - Verify: Rendered in that exact order, with no gaps for unchecked cards

---

## 5. Migration Path

### Current State
- ✅ Config stored in DB
- ✅ UI editor functional
- ❌ Rendering not implemented

### Target State (Phase 4)
- ✅ Config stored in DB
- ✅ UI editor functional
- ✅ Rendering consumes config
- ✅ Defaults applied when config empty
- ✅ Authorization enforced per block/card
- ✅ Order preserved from config

### Dependencies
- **Upstream**: `UserAssignmentContext` and `AdminSurfaceComposer` (already in place)
- **Downstream**: None (self-contained)

### Risk Mitigation
- Parser service is testable in isolation
- Block files can be incrementally migrated (test one block at a time)
- Fallback to defaults ensures no broken rendering
- No changes to database schema required

---

## 6. Future Enhancements (Phase 6+)

### Worker-Perspective `/me` (Phase 6)
- Apply same experience layout system to operator layer (`/u/{username}/me`)
- Add role-specific default blocks (operator vs platform_admin)
- Introduce "compact" mode for mobile/display surfaces

### Dynamic Block Registration (Phase 7)
- Allow modules to register custom blocks
- Admin can add module-contributed blocks to experience layout
- Example: "Production Dashboard" block contributed by Manufacturing app

### Templated Experiences (Phase 8)
- Admin creates named layout templates (e.g., "Production Manager", "Warehouse Lead")
- Assign template to user instead of individual config
- Enables bulk configuration

### Mobile/Responsive Layout (Phase 9)
- Different block order for mobile/tablet
- Collapsible blocks for constrained screens
- Bottom-bar nav for mobile operator layer

---

## 7. Summary

The Experience Layout Composition System is **a working config system** that stores user dashboard preferences but **not yet a rendering system**. 

**Current State**: Blocks and cards are always rendered in fixed hardcoded order, regardless of config.

**Implementation Gap**: The `apps/Shell/Views/admin/dashboard.php` view does not read `me_dashboard_blocks` or `me_plugin_cards` CSV data to conditionally render sections in configured order.

**Next Steps**:
1. Build `ExperienceLayoutParserService` to parse CSV and validate per authority
2. Refactor dashboard view into isolated block/card files
3. Implement layout loop to consume parsed config and render conditionally
4. Test with various configs to verify order + filtering
5. Deploy + monitor for regressions

**Effort**: ~3-4 development days for full Phase 4 implementation (parser + view refactor + testing).

---

## 8. Configuration Example

**Current State for User ID 2** (platform.admin01):
```
me_dashboard_blocks: "admin_panels,platform_tools,top_nav,workspace_actions,search_alerts,operational_summary,primary_widgets,monitoring_widgets,action_queues,quick_links"

me_plugin_cards: "approval_inbox,notifications"
```

**What Should Render After Phase 4**:
1. Admin Dashboard Panels section
2. Platform Admin Tools quick link bar
3. Top Navigation / Module Launcher
4. Workspace Actions buttons
5. Search + Alerts header bar
6. Operational Summary (KPI + flow + timeline)
7. Primary Work Widgets from host regions
8. Monitoring Widgets from host regions
9. Action Queue Tables
10. Quick Links row
11. Plugin Cards:
    - Approval Inbox card
    - Notifications card

All others (Manufacturing Workspace, Daily Orders, etc.) **hidden**.

---

**Last Updated**: 2024-05-16  
**Next Review**: After Phase 4 implementation complete
