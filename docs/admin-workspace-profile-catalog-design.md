# Admin Workspace Profile Catalog Design Plan

**Status**: Design Phase (Ready for Implementation)  
**Target Audience**: Platform Admin + Dev Team  
**Date**: 2026-05-05

---

## 1. Executive Summary

The **Admin Workspace Profile Catalog** is a first-class admin feature that enables platform administrators to:

- **Define workspace profiles** (e.g., "Production Leader", "Cashier", "Teacher") without code changes
- **Compose user experience** per role: landing route, navigation sections, quick actions, visible modules
- **Assign profiles** to individual users or user groups/teams
- **Preview as target user** to validate workspace before deployment
- **Reuse profiles** across future apps (POS, School, HR, Accounting) without modification

**Problem Solved**: 
Currently, `/u/{username}` workspace (operator layer) and landing pages are hardcoded to Manufacturing/SBAIO sections. Adding new modules (School, POS, HR) requires code changes to:
- [apps/Shell/Services/LandingPageService.php](apps/Shell/Services/LandingPageService.php)
- [apps/Shell/Services/OperatorLayerSidebarService.php](apps/Shell/Services/OperatorLayerSidebarService.php)

**With Profile Catalog, all future apps become configuration changes**, not code changes.

---

## 2. Current State Analysis

### 2.1 Existing User Governance Model

**Table**: `user_dashboard_assignments` (23 columns)

```
user_id                       (PK)
dashboard_type               (operator, production_leader, cashier, teacher, etc.)
account_type                 (platform_admin, app_admin, app_user)
default_app                  (manufacturing, sbaio, school, pos, etc.)
default_landing_page         (/u/{username}/dashboard, /u/{username}/qc-queue, etc.)
assigned_apps                (csv: manufacturing,sbaio,school)
access_profiles              (csv: manufacturing_admin, production_operations, etc.)
module_visibility            (json per module: {module: "view|work|approve|manage"})
permissions                  (json: operation-level permissions)
```

**Existing Profiles** (Hardcoded in [plugins/Base/Services/UserDashboardAssignmentService.php](plugins/Base/Services/UserDashboardAssignmentService.php)):

- `productionworker`, `productionleader`, `assemblyworker`, `assemblyleader`
- `qcworker`, `qcleader`, `dispatchworker`, `dispatchleader`
- `platform_admin`, `app_admin`

**Current Architecture Problem**:
- Dashboard type + assigned_apps are stored per-user
- Landing route + sidebar config built at runtime from hardcoded role mappings
- No reusable profile templates
- New app = new hardcoded dashboard_type values

### 2.2 Runtime Entry Points (Today)

| Entry Point | File | Logic | Issue |
|---|---|---|---|
| **Landing** | [apps/Shell/Services/LandingPageService.php](apps/Shell/Services/LandingPageService.php) | Uses `default_landing_page` pref | Immutable per user; no templates |
| **Sidebar** | [apps/Shell/Services/OperatorLayerSidebarService.php](apps/Shell/Services/OperatorLayerSidebarService.php) | Static Manufacturing/SBAIO/MyWork sections | Hardcoded sections; new apps require code edit |
| **User Context** | [apps/Platform/Services/UserAssignmentContext.php](apps/Platform/Services/UserAssignmentContext.php) | Pulls user_dashboard_assignments | Works fine; no change needed |

### 2.3 Admin Governance Pages (Existing)

| Page | Route | Purpose | Model |
|---|---|---|---|
| **User Control Board** | `/ops/user-control` | User lifecycle (create, disable, verify) | Simple grid + summary |
| **User Detail** | `/ops/user-control/detail?user_id=X` | User security + events | Form + audit log |
| **Access Control Board** | `/ops/access-control` | All user assignments (grid view) | Filterable table, bulk edit |
| **Access Control Detail** | `/ops/access-control/detail?user_id=X` | Detailed governance editor | 5 tabs (Access, Visibility, Overrides, Experience, Diagnostics) |

---

## 3. Data Model Design

### 3.1 New Table: `workspace_profiles`

Store reusable workspace configurations (not per-user; templates).

```sql
CREATE TABLE workspace_profiles (
  id                    INT PRIMARY KEY AUTO_INCREMENT,
  profile_key          VARCHAR(120) NOT NULL UNIQUE,
  name                 VARCHAR(255) NOT NULL,                  -- e.g., "Production Leader"
  description          TEXT,                                   -- Locale key or user-facing text
  account_type         VARCHAR(40) NOT NULL,                   -- platform_admin, app_admin, app_user
  interaction_profile  VARCHAR(40) NOT NULL,                   -- worker, leader, admin, read_only, display
  control_scope        VARCHAR(40) NOT NULL,                   -- platform, app, module, personal, shared
  
  -- Navigation & UX
  landing_route        VARCHAR(255) NOT NULL,                  -- e.g., /u/{username}/dashboard
  nav_sections         JSON,                                    -- [{section: "Manufacturing", modules: ["production", "qc"]}, ...]
  quick_actions        JSON,                                    -- [{label: i18n_key, route: "/...", icon: "..."}, ...]
  default_app          VARCHAR(120),                           -- e.g., "manufacturing"
  assigned_apps        TEXT,                                    -- CSV: manufacturing,sbaio,school
  
  -- Access & Visibility
  access_profiles      TEXT,                                    -- CSV: manufacturing_admin, production_operations
  module_visibility    JSON,                                    -- {manufacturing: "work", qc: "view", ...}
  permissions          JSON,                                    -- {create_order: true, export_report: false, ...}
  
  -- Widget Configuration (for /u/{username}/dashboard)
  dashboard_blocks     JSON,                                    -- [{widget_id, title_i18n_key, config}, ...]
  widget_discovery     VARCHAR(16) DEFAULT 'auto',             -- auto, manual, none
  
  -- Profile State
  is_active           BOOLEAN DEFAULT true,
  is_system           BOOLEAN DEFAULT false,                   -- System profiles (read-only)
  created_by          VARCHAR(190),
  updated_by          VARCHAR(190),
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 3.2 New Link Table: `user_profile_assignments`

Map users to profiles (many-to-one, with override support).

```sql
CREATE TABLE user_profile_assignments (
  id                  INT PRIMARY KEY AUTO_INCREMENT,
  user_id             INT NOT NULL UNIQUE,
  profile_id          INT NOT NULL,
  override_landing    VARCHAR(255) NULL,                        -- Allow per-user override
  override_assigned_apps TEXT NULL,                             -- Allow per-user override
  override_enabled    BOOLEAN DEFAULT false,
  assigned_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  assigned_by         VARCHAR(190),
  updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (profile_id) REFERENCES workspace_profiles(id) ON DELETE CASCADE
);
```

### 3.3 Migration Path (No Breaking Changes)

**Phase 1** (This iteration): Add new tables; existing `user_dashboard_assignments` remains functional.

**Phase 2** (Future): Add `profile_id` column to `user_dashboard_assignments` for explicit profile link.

```sql
-- Future: Add link to profiles
ALTER TABLE user_dashboard_assignments ADD COLUMN profile_id INT NULL;
ALTER TABLE user_dashboard_assignments ADD FOREIGN KEY (profile_id) 
  REFERENCES workspace_profiles(id) ON DELETE SET NULL;
```

---

## 4. Admin UI Pages & Workflow

### 4.1 New Page: `/ops/workspace-profiles` (Profile Catalog)

**Route**: `/ops/workspace-profiles`  
**Template**: Create new `plugins/Base/Views/ops/workspace_profiles.php`  
**Controller Method**: `RoleDashboardsController::renderWorkspaceProfiles()`

**Features**:
- **Filter Row**:
  - Search by name/key
  - Filter by account_type (platform_admin, app_admin, app_user)
  - Filter by is_active
  - Filter by assigned_apps (multi-select)
  - Show "System" vs "Custom" toggle

- **Profile Grid** (Table):
  | Profile Name | Key | Account Type | Landing | Apps | Active | Users | Actions |
  |---|---|---|---|---|---|---|---|
  | Production Leader | `production_leader` | app_user | /u/{username}/workboard | mfg,sbaio | ✓ | 5 | Edit/Duplicate/Delete |
  | Cashier | `cashier` | app_user | /u/{username}/register | pos,mfg | ✓ | 3 | Edit/Duplicate/Delete |

- **Row Actions**:
  - `Edit` → `/ops/workspace-profiles/detail?profile_id=X`
  - `Preview As` → Show preview modal or link
  - `Duplicate` → Copy profile with new key
  - `Delete` → (soft delete; show warning if users assigned)
  - `Assign Users` → Modal dialog to add users to profile

- **Create Button** (Top Right):
  - "New Profile" → Form on `/ops/workspace-profiles/detail` (profile_id omitted = new)

### 4.2 New Page: `/ops/workspace-profiles/detail` (Profile Editor)

**Route**: `/ops/workspace-profiles/detail?profile_id=X` or `/ops/workspace-profiles/create`  
**Template**: `plugins/Base/Views/ops/workspace_profile_detail.php`  
**Controller Method**: `RoleDashboardsController::renderWorkspaceProfileDetail()`

**Tab-Based Form** (Follows same pattern as Access Control Detail):

#### Tab 1: Basic Info
- **Profile Key** (immutable after creation): `production_leader`
- **Name** (with locale key dropdown): `mfg.profile.production_leader_name`
- **Description**: (optional locale key)
- **Account Type**: Dropdown (platform_admin, app_admin, app_user)
- **Interaction Profile**: Dropdown (worker, leader, admin, read_only, display)
- **Control Scope**: Dropdown (platform, app, module, personal, shared)
- **Active**: Toggle
- **System Profile**: Checkbox (read-only if checked)

#### Tab 2: Navigation & Routing
- **Default Landing Route**: Text input (e.g., `/u/{username}/dashboard`)
  - Validator: Route must exist in app manifest + match account_type
  - Helper: Dropdown of available routes per assigned_apps
- **Default App**: Dropdown (manufacturing, sbaio, school, pos, etc.)
- **Nav Sections Builder**:
  - Drag-drop interface or textarea JSON
  - Each section: {section_name_i18n_key, modules: [module_keys]}
  - Example: 
    ```json
    [
      {
        "section": "manufacturing.sidebar.production",
        "modules": ["daily_orders", "production_queue", "workboard"]
      },
      {
        "section": "manufacturing.sidebar.quality",
        "modules": ["qc_queue", "qc_plans"]
      }
    ]
    ```
- **Quick Actions Builder**:
  - Array of {label_i18n_key, route, icon}
  - Example:
    ```json
    [
      {"label": "common.start_shift", "route": "/u/{username}/shift-start", "icon": "play"},
      {"label": "common.log_defect", "route": "/u/{username}/defect-log", "icon": "alert"}
    ]
    ```

#### Tab 3: App & Module Assignment
- **Assigned Apps**: Multi-select checkboxes (manufacturing, sbaio, school, pos, accounting)
  - Updates available_modules list in real-time
- **Module Visibility Matrix**:
  - Rows: All modules from assigned_apps
  - Cols: Access Level (none, view, work, approve, manage)
  - Example: Production Leader sees manufacturing:work, qc:view, shipping:none

#### Tab 4: Access Profiles & Permissions
- **Access Profiles**: Multi-select (manufacturing_admin, production_operations, readonly_observer, etc.)
  - Autocomplete from UserDashboardAssignmentService::ROLE_DEFAULTS
- **Permissions Builder**:
  - Toggle grid: {operation: [create_order, export_report, ...], allowed: true/false}
  - Grouped by feature (Orders, Reports, Exports, Admin)

#### Tab 5: Dashboard Widgets
- **Widget Discovery**: Radio (auto, manual, none)
- **Dashboard Blocks** (if manual):
  - Drag-drop re-orderable list of widgets
  - Each widget: {widget_id, title_i18n_key, position, config}
  - Add Widget button → Modal with available widgets from all assigned modules

#### Tab 6: Preview & Validation
- **Validation Summary**:
  - ✓ Landing route exists
  - ✓ Landing route matches account_type
  - ✓ All assigned_apps are active
  - ✓ All modules exist in assigned apps
  - ✓ All nav_sections reference valid modules
  - ✓ All locale keys present
  - ✗ (If errors, show red badges)
- **Preview Button**:
  - "Preview as Admin" or "Preview as User" → Opens `/u/PREVIEW_USER/dashboard?profile_id=X`
  - Shows actual workspace with this profile
- **Save Button**: Validate → Save to DB

### 4.3 New Page: `/ops/workspace-profiles/assign` (Bulk Assignment)

**Route**: `/ops/workspace-profiles/assign?profile_id=X`  
**Template**: `plugins/Base/Views/ops/workspace_profile_assign.php`  
**Controller Method**: `RoleDashboardsController::renderWorkspaceProfileAssign()`

**Features**:
- Show target profile (card at top)
- **User/Team Selection**:
  - Search users by name/email
  - Filter by current profile (show who's currently assigned)
  - Multi-select checkbox list
  - "Select All / Deselect All" buttons
- **Assignment Options**:
  - Radio: "Replace current profile" or "Add to current profile"
  - Checkbox: "Allow overrides" (let user customize later)
- **Summary**:
  - "Assigning X users to profile Y"
  - Confirmation before save
- **Save** → Bulk update `user_profile_assignments` + link `user_dashboard_assignments.profile_id`

### 4.4 Integration with Existing Access Control Detail

**Modify**: `/ops/access-control/detail?user_id=X` (existing page)

**Add Tab 1**: "Workspace Profile"
- Display: Current profile + override toggle
- Link to profile detail (read-only or edit)
- Quick-switch dropdown to change profile
- Shows all profile + overrides stacked

---

## 5. Runtime Integration

### 5.1 Update Landing Service

**File**: [apps/Shell/Services/LandingPageService.php](apps/Shell/Services/LandingPageService.php)

```php
// Pseudocode (existing method, modify)
public function resolveLanding($userId, $accountType)
{
    // NEW: Check if user has profile assignment
    $profile = $this->getProfileForUser($userId);
    
    if ($profile && $profile['landing_route']) {
        // Use profile's landing route
        return $this->interpolateRoute($profile['landing_route'], ['username' => $user->handle]);
    }
    
    // FALLBACK: Use existing user preference (backward compat)
    $prefs = $this->getUserPreferences($userId);
    if ($prefs['default_view']) {
        return $prefs['default_view'];
    }
    
    // FALLBACK: Use role defaults (existing logic)
    return $this->getRoleDefaultLanding($accountType);
}

private function getProfileForUser($userId)
{
    // Query: user_profile_assignments.profile_id -> workspace_profiles
    // Apply overrides from user_profile_assignments.override_landing
    return $this->db->fetch("
        SELECT wp.* FROM workspace_profiles wp
        JOIN user_profile_assignments upa ON wp.id = upa.profile_id
        WHERE upa.user_id = ? AND wp.is_active = true
    ", [$userId]);
}
```

**Backward Compatibility**: ✅ Profile check happens first; if no profile, falls back to user pref + role defaults.

### 5.2 Update Sidebar Service

**File**: [apps/Shell/Services/OperatorLayerSidebarService.php](apps/Shell/Services/OperatorLayerSidebarService.php)

```php
// Pseudocode (existing method, modify)
public function buildOperatorSidebar($userId, $accountType, $currentModule)
{
    $sections = [];
    
    // NEW: Get profile for user
    $profile = $this->getProfileForUser($userId);
    
    if ($profile && $profile['nav_sections']) {
        // Use profile's nav sections (JSON)
        $navConfig = json_decode($profile['nav_sections'], true);
        foreach ($navConfig as $section) {
            $sections[] = $this->buildNavSection($section, $profile['assigned_apps']);
        }
    } else {
        // FALLBACK: Static Manufacturing/SBAIO (existing logic)
        $sections = $this->getDefaultSections($accountType);
    }
    
    return $this->renderSidebarHTML($sections, $currentModule);
}

private function buildNavSection($sectionConfig, $assignedApps)
{
    $section = [
        'label' => $this->tr($sectionConfig['section']),
        'icon' => $sectionConfig['icon'] ?? 'folder',
        'items' => []
    ];
    
    foreach ($sectionConfig['modules'] as $moduleKey) {
        // Check if module is in assigned_apps
        if (in_array($moduleKey, explode(',', $assignedApps))) {
            $section['items'][] = $this->buildModuleLink($moduleKey);
        }
    }
    
    return $section;
}
```

**Backward Compatibility**: ✅ If no profile, uses default sections (existing behavior).

### 5.3 Quick Actions

**File**: [apps/Shell/Views/operator/header.php](apps/Shell/Views/operator/header.php) or similar

```php
<?php
// Quick actions from profile or user settings
$profile = $this->context->getProfileForUser($userId);
$quickActions = $profile['quick_actions'] ?? [];
?>
<div class="quick-actions">
  <?php foreach ($quickActions as $action): ?>
    <a href="<?php echo $action['route']; ?>" class="quick-action" title="<?php echo $this->tr($action['label']); ?>">
      <i class="icon icon-<?php echo $action['icon']; ?>"></i>
      <?php echo $this->tr($action['label']); ?>
    </a>
  <?php endforeach; ?>
</div>
```

### 5.4 Dashboard Widgets

**File**: [apps/Shell/Views/operator/dashboard.php](apps/Shell/Views/operator/dashboard.php)

```php
<?php
// Get dashboard blocks from profile
$profile = $this->context->getProfileForUser($userId);
$blocks = $profile['dashboard_blocks'] ?? [];

if (!$blocks && $profile['widget_discovery'] === 'auto') {
    // Auto-discover widgets from assigned modules
    $blocks = $this->discoverWidgetsForModules($profile['assigned_apps']);
}
?>
<div class="dashboard-grid">
  <?php foreach ($blocks as $block): ?>
    <div class="dashboard-block" style="grid-column: span <?php echo $block['width'] ?? 2; ?>">
      <h3><?php echo $this->tr($block['title_i18n_key']); ?></h3>
      <?php 
        // Render widget (use existing OperatorLayerWidgetService)
        echo $this->renderWidget($block['widget_id'], $block['config']);
      ?>
    </div>
  <?php endforeach; ?>
</div>
```

---

## 6. Future App Profile Packs

### 6.1 Profile Pack Definition

A **Profile Pack** is a pre-built set of profiles for a specific app domain, designed to be deployed without code changes.

**Format** (JSON or PHP):
```json
{
  "app_key": "pos",
  "profiles": [
    {
      "profile_key": "cashier",
      "name": "pos.profile.cashier",
      "account_type": "app_user",
      "assigned_apps": "pos,inventory",
      "landing_route": "/u/{username}/register",
      "nav_sections": [
        {
          "section": "pos.sidebar.sales",
          "modules": ["register", "returns"]
        },
        {
          "section": "pos.sidebar.inventory",
          "modules": ["stock_check"]
        }
      ],
      "module_visibility": {
        "register": "work",
        "returns": "work",
        "stock_check": "view"
      }
    },
    {
      "profile_key": "shift_lead",
      "name": "pos.profile.shift_lead",
      ...
    }
  ]
}
```

### 6.2 Deployment Workflow (Admin CLI + UI)

**Phase 1**: Save profile packs as `.json` files in:
```
storage/profile_packs/{app_key}/{pack_version}.json
```

**Phase 2**: Admin UI page `/ops/workspace-profiles/import`
- Upload `.json` file
- Show preview of profiles to import
- Validate (locale keys, app existence, route validity)
- Save to DB

**Phase 3**: CLI command (future)
```bash
php app/Console/workspace import-profiles storage/profile_packs/pos/1.0.json
```

### 6.3 Pre-Built Packs (To Deliver)

#### **POS Profile Pack** (`pos` app)

Profiles:
- `cashier`: Register (work), stock_check (view)
- `shift_lead`: Register (approve), stock_check (work), daily_close (work)
- `store_manager`: All POS modules (work), Inventory (approve)

Routes:
- Cashier lands on `/u/{username}/register`
- Shift Lead lands on `/u/{username}/workboard`
- Manager lands on `/u/{username}/dashboard`

#### **School Profile Pack** (`school` app)

Profiles:
- `teacher`: Grades (work), Attendance (work), Lessons (work)
- `principal`: All modules (approve)
- `admin_office`: Enrollment (work), Reports (view)

Routes:
- Teacher lands on `/u/{username}/my-classes`
- Principal lands on `/u/{username}/dashboard`

#### **HR Profile Pack** (`hr` app)

Profiles:
- `recruiter`: Jobs (work), Applicants (work), Interviews (work)
- `officer`: Employee (work), Payroll (view), Benefits (work)
- `payroll_reviewer`: Payroll (approve), Tax (work)

Routes:
- Recruiter lands on `/u/{username}/recruitment`
- Officer lands on `/u/{username}/my-team`
- Payroll Reviewer lands on `/u/{username}/payroll-queue`

#### **Accounting Profile Pack** (`accounting` app)

Profiles:
- `ap_clerk`: Bills (work), Payments (work)
- `ar_clerk`: Invoices (work), Collections (work)
- `finance_approver`: Approvals (work), Reports (view)

Routes:
- AP Clerk lands on `/u/{username}/bill-queue`
- AR Clerk lands on `/u/{username}/invoice-queue`
- Finance Approver lands on `/u/{username}/approvals`

---

## 7. Localization Strategy

### 7.1 Locale Keys (To Add)

All profile names, nav sections, quick actions use locale keys.

**New Namespaces**:

```php
// app/Locale/en.php additions

'profiles' => [
    'catalog_title' => 'Workspace Profiles',
    'create_new' => 'New Profile',
    'edit' => 'Edit Profile',
    'delete' => 'Delete Profile',
    'assign_users' => 'Assign Users',
    'preview' => 'Preview',
    
    'basic_info' => 'Basic Information',
    'navigation' => 'Navigation & Routing',
    'apps_modules' => 'Apps & Modules',
    'access' => 'Access & Permissions',
    'widgets' => 'Dashboard Widgets',
    'validation' => 'Validation',
    
    'key' => 'Profile Key',
    'name' => 'Profile Name',
    'description' => 'Description',
    'account_type' => 'Account Type',
    'landing_route' => 'Landing Route',
    'nav_sections' => 'Navigation Sections',
    'assigned_apps' => 'Assigned Apps',
    'module_visibility' => 'Module Visibility',
    'active' => 'Active',
],

// System profiles (reserved keys)
'sys_profiles' => [
    'platform_admin' => 'Platform Administrator',
    'production_leader' => 'Production Leader',
    'cashier' => 'Cashier',
    'teacher' => 'Teacher',
    'recruiter' => 'Recruiter',
    'finance_approver' => 'Finance Approver',
],

// App-specific profile names (used in packs)
'pos.profile.cashier' => 'POS Cashier',
'pos.profile.shift_lead' => 'POS Shift Lead',
'school.profile.teacher' => 'School Teacher',
'hr.profile.recruiter' => 'HR Recruiter',
'accounting.profile.ap_clerk' => 'Accounting - AP Clerk',
```

**Japanese/Nepali**: Add equivalent translations to `ja.php` and `ne.php`.

### 7.2 Locale Key Validation

**On Profile Save** (controller method):
- Scan profile JSON for locale keys (e.g., `mfg.profile.*`, `common.*`)
- Verify keys exist in all 3 locale files
- Return validation error if missing
- Prevent save if keys missing

---

## 8. Implementation Timeline & Slices

### **Slice 1: Data Model + Migration** (1 day)
- Create `workspace_profiles` table
- Create `user_profile_assignments` table
- Seed system profiles (production_leader, platform_admin, etc.)
- Add migration script to `app/Console/`

### **Slice 2: Admin Pages - List & Detail** (1.5 days)
- `/ops/workspace-profiles` (grid)
- `/ops/workspace-profiles/detail` (form, all 6 tabs)
- Controller methods in `RoleDashboardsController`
- Validation logic
- Save/update/delete operations

### **Slice 3: Admin Pages - Assign & Integration** (1 day)
- `/ops/workspace-profiles/assign` (bulk assignment page)
- Add "Workspace Profile" tab to `/ops/access-control/detail`
- Test assignment workflow end-to-end

### **Slice 4: Runtime Integration** (1 day)
- Update `LandingPageService` to check profile first
- Update `OperatorLayerSidebarService` to use nav_sections
- Update `/u/{username}/dashboard` to use dashboard_blocks
- Test landing + sidebar with test users

### **Slice 5: Localization + Testing** (0.5 days)
- Add all locale keys to en/ja/ne.php
- Test profile validation with missing i18n keys
- End-to-end smoke test with 3 languages

### **Slice 6: POS Profile Pack** (1 day)
- Create pos.json pack
- Build profile import page `/ops/workspace-profiles/import`
- Test import workflow
- Seed test users with POS profiles

### **Slice 7: School + HR + Accounting Packs** (2 days)
- Create school.json, hr.json, accounting.json packs
- Add locale keys for all pack profiles
- Test each pack independently

**Total**: ~8 days (7 slices, 1 day per slice avg.)

---

## 9. Validation & Testing Gates

### 9.1 Data Validation Rules

**On Profile Save**:
- ✓ Profile key: Unique, alphanumeric + underscore only
- ✓ Landing route: Must exist in app manifest
- ✓ Landing route: Must be compatible with account_type (e.g., `/u/` for app_user)
- ✓ Assigned apps: All must be active in `core_apps`
- ✓ Nav sections: All modules must exist + belong to assigned_apps
- ✓ Locale keys: All must exist in en.php (validated on save)
- ✓ No orphaned modules in module_visibility

### 9.2 Runtime Validation

**On Landing Route Resolution**:
- ✓ Profile must be active
- ✓ Profile.assigned_apps must include the target app for route
- ✓ User must have account_type matching profile
- ✓ Fallback to default if profile broken

**On Sidebar Render**:
- ✓ All nav_sections exist in profile
- ✓ All modules in nav_sections are in assigned_apps
- ✓ User has access to each module

### 9.3 Test Scenarios

1. **Create & Assign Profile**
   - Admin creates "Test Lead" profile
   - Assigns to test user
   - Verify user lands on custom route
   - Verify sidebar shows custom sections

2. **Profile Inheritance**
   - Create parent profile "Manufacturing Worker"
   - Create child profile "Production Operator" (copies parent)
   - Modify child; verify parent unchanged

3. **Override Handling**
   - Assign profile to user
   - Enable override in user detail
   - Change user's landing route
   - Verify override takes precedence over profile

4. **Locale Fallback**
   - Create profile with missing i18n key
   - Attempt to save
   - Verify validation error
   - Fix key; save succeeds

5. **App Removal Scenario**
   - Create profile with assigned_apps: manufacturing,sbaio
   - SBAIO app marked inactive
   - Verify validation shows warning
   - Prevent save until app re-enabled or removed from profile

---

## 10. Security & Authorization

### 10.1 Access Control

| Operation | Who | Validation |
|---|---|---|
| View profile list | platform_admin | Default; all profiles visible |
| Create profile | platform_admin | Only platform_admin can create |
| Edit own profile settings | app_admin | Can edit their own profile's assigned_apps only |
| Edit system profiles | platform_admin only | Locked; no modification allowed |
| Delete profile | platform_admin | Warn if users assigned; soft delete |
| Assign profile to user | platform_admin | Bulk assign via page or API |
| View assigned profiles (diagnostics) | platform_admin | Show in `/ops/access-control/detail` |

### 10.2 Audit Trail

- Log profile creation/updates in `audit_log` table
- Track who assigned profile to user (user_profile_assignments.assigned_by)
- Track profile changes (updated_at, updated_by)

---

## 11. API Endpoints (Optional - Future)

For programmatic profile management:

```
GET  /api/v1/workspace-profiles              → List all profiles
GET  /api/v1/workspace-profiles/{id}        → Get profile detail
POST /api/v1/workspace-profiles              → Create profile
PUT  /api/v1/workspace-profiles/{id}        → Update profile
DELETE /api/v1/workspace-profiles/{id}       → Delete profile (soft)

POST /api/v1/workspace-profiles/{id}/assign  → Assign profile to users (bulk)
GET  /api/v1/workspace-profiles/import/validate  → Validate pack JSON
POST /api/v1/workspace-profiles/import       → Import profile pack
```

---

## 12. Rollout Strategy

### Phase 1: MVP (Slices 1-5)
- **Deliverable**: Platform admin can create + assign profiles; landing/sidebar respond to profiles
- **Users**: Internal test users only
- **Duration**: 1 week
- **Validation**: Manual testing; smoke tests pass

### Phase 2: Manufacturing Profiles (Slice 6)
- **Deliverable**: Pre-built POS profiles available for import
- **Users**: Select POS pilot team (5-10 users)
- **Duration**: 3 days
- **Validation**: Pilot users validate landing/sidebar; no hardcoded values in code

### Phase 3: Multi-App Profiles (Slice 7)
- **Deliverable**: School, HR, Accounting profiles available
- **Users**: Broader pilot across departments
- **Duration**: 1 week
- **Validation**: Each app team validates profiles; all navigation working

### Phase 4: Production Deploy
- **Prerequisite**: All 4 app packs validated; zero hardcoded role references in code
- **Go-Live**: All production users transitioned to profile-based workspace
- **Monitoring**: Check landing errors, sidebar load times, user feedback

---

## 13. Acceptance Criteria

✅ **Profile Catalog**:
- Platform admin can create custom workspace profiles in UI (no code changes)
- Profile can specify landing route, nav sections, quick actions, widget config
- Profile can specify assigned apps, module visibility, access profiles
- All profile names/sections use i18n keys; validation prevents missing translations
- Profile can be assigned to multiple users or user groups

✅ **Runtime Integration**:
- User with assigned profile lands on profile.landing_route
- User with assigned profile sees profile.nav_sections in sidebar
- User with assigned profile sees profile.quick_actions in toolbar
- User with assigned profile sees profile.dashboard_blocks on dashboard
- All features fall back to defaults if profile missing (backward compatible)

✅ **Admin UX**:
- Profile list shows all profiles with summary (account type, apps, active status, user count)
- Profile detail uses tabbed form with validation feedback
- Bulk assign page allows assigning profile to 1+ users
- Preview shows exactly what user will see (landing, sidebar, dashboard)

✅ **App Deployment**:
- POS/School/HR/Accounting profile packs can be imported without code changes
- Each pack includes 3-5 pre-built profiles per app
- Admin can customize profiles after import
- All future apps follow same pattern (no new code for new app roles)

✅ **Data Integrity**:
- Soft delete profiles (preserve history; users stay assigned)
- Validate all locale keys before save
- Validate all routes/apps/modules exist
- Prevent orphaning users if profile deleted

---

## 14. Open Questions & Decisions

| Question | Proposed Answer | Approval? |
|---|---|---|
| Should profiles support **role inheritance** (parent → child)? | Not in MVP; add in Phase 2 if needed | ⏳ |
| Should users be able to **customize** assigned profile? | Yes; override per user in user detail tab | ✅ |
| Should profiles be **versioned** (draft, published, archived)? | Not in MVP; simple active/inactive flag | ⏳ |
| Should **profile import** include user assignments? | No; import profiles only; admin assigns separately | ✅ |
| Should POS/School/HR/Accounting profiles use **shared modules**? | Yes; e.g., inventory shared between POS + Manufacturing | ⏳ |
| Should system profiles be **immutable**? | Yes; no edit/delete on system profiles | ✅ |

---

## 15. Files to Create/Modify

### New Files

```
docs/admin-workspace-profile-catalog-design.md      (this file)
apps/Console/Commands/WorkspaceImportProfilesCommand.php  (future CLI)
plugins/Base/Views/ops/workspace_profiles.php       (profile list page)
plugins/Base/Views/ops/workspace_profile_detail.php  (profile editor)
plugins/Base/Views/ops/workspace_profile_assign.php  (bulk assign page)
storage/profile_packs/pos/1.0.json                  (POS profiles)
storage/profile_packs/school/1.0.json               (School profiles)
storage/profile_packs/hr/1.0.json                   (HR profiles)
storage/profile_packs/accounting/1.0.json           (Accounting profiles)
```

### Modified Files

```
plugins/Base/Controllers/RoleDashboardsController.php
  + renderWorkspaceProfiles()
  + renderWorkspaceProfileDetail()
  + renderWorkspaceProfileAssign()
  + saveWorkspaceProfile()
  + deleteWorkspaceProfile()
  + bulkAssignProfile()

apps/Shell/Services/LandingPageService.php
  + getProfileForUser()
  + resolveLanding() [modify to check profile first]

apps/Shell/Services/OperatorLayerSidebarService.php
  + buildOperatorSidebar() [modify to use profile.nav_sections]
  + buildNavSection()

plugins/Base/Views/ops/dashboard_assignment_detail_page.php
  + Add "Workspace Profile" tab

app/Locale/en.php, ja.php, ne.php
  + Add 50+ locale keys for profiles + packs

app/Routes/routes.php
  + Register /ops/workspace-profiles routes
```

### Database

```
CREATE TABLE workspace_profiles (...)
CREATE TABLE user_profile_assignments (...)
```

---

## Summary

The **Admin Workspace Profile Catalog** transforms role management from **code-driven** (hardcoding new dashboard_type values in UserDashboardAssignmentService) to **data-driven** (profiles as first-class DB entities).

**This enables**:
- ✅ Platform admin defines roles without coding
- ✅ Multiple apps (POS, School, HR, Accounting) deployed without touching role code
- ✅ User experience customization per role (landing route, sidebar sections, quick actions)
- ✅ Profile templates reusable across orgs
- ✅ Audit trail of profile assignments

**Timeline**: ~8 days (7 slices)  
**Complexity**: Medium (tabbed form UI, JSON config, runtime integration)  
**Risk**: Low (new tables; existing landing/sidebar logic unchanged until profile exists)
