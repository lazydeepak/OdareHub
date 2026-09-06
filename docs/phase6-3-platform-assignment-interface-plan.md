# Phase 6.3 Preparation: User Assignment Interface Design

Date: 2026-04-18
**Aligns with [docs/access-control-view-architecture.md]: Surface, Interaction Profile, Access Authority, Control Scope, and Permission Profile remain distinct.**
Scope: analysis and design only (no code movement)

## Terminology Note

This design document predates the formalized 2026-04-19 architecture lock.

Interpret older mixed phrases here as follows:
- `dashboard type` = transitional legacy field, not the final architecture model
- `role` = compatibility/user-role language only, not a substitute for Access Authority or Interaction Profile
- `/me` = composed workspace Surface, not module workflow ownership

## Files analyzed
- plugins/Base/Services/UserDashboardAssignmentService.php
- apps/Platform/routes.php
- plugins/Base/Controllers/RoleDashboardsController.php
- plugins/Base/bootstrap.php
- apps/Shell/Services/ShellCompositionService.php
- app/Core/AclPolicy.php
- app/Core/Auth.php
- app/Core/SidebarBuilder.php
- app/Core/DashboardBuilder.php
- app/Core/SearchService.php
- apps/Manufacturing/** (controllers/services)
- apps/SBAIO/Controllers/SbaioDashboardController.php
- plugins/Base/Services/* (GovernanceInboxService, RoleInboxService, MyWorkService, OperatorWorkboardService)
- plugins/AdminTools/Controllers/AdminToolsController.php
- tests/UserDashboardAssignmentServiceTest.php

## 1) Current service analysis

### Service size and shape
- Class: Plugins\\Base\\Services\\UserDashboardAssignmentService
- File length: 4,486 lines
- Public static methods: 31

### Public API currently exposed (full)
- ensureSchema
- resolveUserContext
- routeForDashboardType
- primaryLandingForContext
- primaryLandingForUser
- dashboardTypeForUser
- routeAccessDecision
- scopeFiltersForTable
- enabledModulesForUser
- hasModuleRestrictions
- canAccessModule
- filterModulesForUser
- isAppEnabled
- listAssignmentRows
- listLifecycleUsers
- createUserFromInput
- updateBasicUserFromInput
- setUserStatusFromInput
- saveFromInput
- roleDefaultsForUi
- assignmentUiConfig
- meDashboardBlockCatalog
- mePluginCardCatalog
- meCoreQuickLinks
- resolveMeDashboardBlocks
- resolveMePluginCards
- accessProfileRegistry
- accessProfilePermissionMatrix
- normalizeExistingAssignments
- assignableRoles
- operationalProfiles

### High-level responsibilities in one class
1. Identity/context resolution for current user
2. Route access decisioning and module access checks
3. SQL scope filter generation
4. App enablement / assigned app enforcement
5. User-account lifecycle and assignment CRUD
6. Dashboard/landing compatibility and normalization
7. Permission/audit diagnostics and token tracing
8. UI catalogs for /me blocks/cards and admin role config
9. Schema migration/bootstrap for user assignment tables/columns

### Direct dependencies
- App\\Core\\DB
- App\\Core\\AclPolicy
- App\\Services\\InviteLifecycleService
- Plugins\\Base\\Services\\SuitePermissionTemplateService
- Global i18n function t(...) (optional)
- Database structures:
  - users
  - core_apps
  - user_dashboard_assignments
  - user_operational_scopes
  - user_module_visibility
  - user_role_duties
  - invite lifecycle tables via InviteLifecycleService::ensureSchema()

## 2) Caller analysis

### Method usage concentration (repo-wide, excluding service body)
- resolveUserContext: 24
- ensureSchema: 19
- routeForDashboardType: 8
- routeAccessDecision: 5
- scopeFiltersForTable: 5
- listAssignmentRows: 5
- meCoreQuickLinks: 4
- enabledModulesForUser: 4
- assignmentUiConfig: 4
- accessProfileRegistry: 4

### Caller distribution by file (top)
- plugins/Base/Controllers/RoleDashboardsController.php: 51
- plugins/Base/Services/OperatorWorkboardService.php: 8
- app/Core/SidebarBuilder.php: 5
- plugins/Base/Services/MyWorkService.php: 4
- plugins/AdminTools/Controllers/AdminToolsController.php: 4
- apps/Shell/Services/ShellCompositionService.php: 4
- app/Core/AclPolicy.php: 4
- plugins/Base/Services/GovernanceInboxService.php: 3
- apps/Manufacturing/modules/Workflow/Services/WorkflowTransitionEngine.php: 3
- plus many single-call sites in Manufacturing/Core/SBAIO

### Architectural finding
- Runtime callers from Core/Shell/Platform/Manufacturing depend on this Base service.
- This creates reverse layering pressure (Core and app layers reaching into plugin-owned governance identity logic).

## 3) Platform-owned contract design

Design intent:
- Platform owns assignment/identity governance contract.
- Remove caller dependence on Base namespace.
- Keep interface minimal for runtime extraction first.
- Keep admin CRUD and heavy diagnostics in a second phase contract.

### Proposed interface (minimal runtime contract)

```php
<?php
declare(strict_types=1);

namespace Apps\Platform\Contracts;

interface UserAssignmentContextContract
{
    /** @param array<string,mixed>|null $user */
    public function resolveUserContext(?array $user): array;

    /** @param array<string,mixed>|null $user */
    public function dashboardTypeForUser(?array $user): string;

    /** @param array<string,mixed> $context */
    public function primaryLandingForContext(array $context): string;

    public function routeForDashboardType(string $dashboardType): string;

    /** @param array<string,mixed>|null $user
     *  @return array{allowed:bool,reason:string}
     */
    public function routeAccessDecision(?array $user, string $path, string $method = 'GET'): array;

    /** @param array<string,mixed>|null $user
     *  @return array<int,string>
     */
    public function enabledModulesForUser(?array $user): array;

    /** @param array<string,mixed>|null $user */
    public function canAccessModule(?array $user, string $moduleKey): bool;

    public function isAppEnabled(string $appKey): bool;

    /** @param array<string,mixed> $scope
     *  @param array<string,string> $columnToScopeKey
     *  @return array{sql:string,params:array<int,int|string>}
     */
    public function scopeFiltersForTable(array $scope, string $table, array $columnToScopeKey): array;

    /** @param array<string,mixed> $context
     *  @return array<int,array<string,mixed>>
     */
    public function meCoreQuickLinks(array $context): array;
}
```

### Why this is minimal
- Covers all non-admin runtime concerns currently used by Core/Shell/Platform/Manufacturing.
- Excludes assignment CRUD, admin UI config catalogs, lifecycle bulk normalization, and schema mutation orchestration.
- Avoids exposing Base internals and role-pack implementation detail as contract requirements.

### Phase-2 optional admin contract (not in phase 6.3)
- listAssignmentRows
- listLifecycleUsers
- saveFromInput
- createUserFromInput
- updateBasicUserFromInput
- setUserStatusFromInput
- assignmentUiConfig
- accessProfileRegistry
- accessProfilePermissionMatrix
- roleDefaultsForUi
- normalizeExistingAssignments

## 4) Dependency graph

```text
Callers
  app/Core/Auth -------------------------------> UserAssignmentContextContract
  app/Core/AclPolicy --------------------------> UserAssignmentContextContract
  app/Core/SidebarBuilder ---------------------> UserAssignmentContextContract
  app/Core/DashboardBuilder -------------------> UserAssignmentContextContract
  apps/Shell/Services/ShellCompositionService -> UserAssignmentContextContract
  apps/Platform/routes ------------------------> UserAssignmentContextContract
  apps/Manufacturing/* ------------------------> UserAssignmentContextContract
  apps/SBAIO/Controllers/* --------------------> UserAssignmentContextContract
  plugins/Base/Services/* ---------------------> UserAssignmentContextContract

Contract implementation (initial adapter)
  Apps/Platform/Services/UserAssignmentContextAdapter
      -> delegates to Plugins/Base/Services/UserDashboardAssignmentService (temporary)

Current concrete service dependencies
  UserDashboardAssignmentService
    -> App/Core/DB
    -> App/Core/AclPolicy
    -> App/Services/InviteLifecycleService
    -> Plugins/Base/Services/SuitePermissionTemplateService
    -> tables: users, core_apps, user_dashboard_assignments,
               user_operational_scopes, user_module_visibility, user_role_duties
```

## 5) Migration plan (no code movement in this phase)

### Step-by-step plan
1. Add Platform contract interface in apps/Platform/Contracts.
2. Add Platform adapter implementation that proxies to current Base service.
3. Add a resolver/factory in Platform to supply the contract implementation.
4. Switch low-risk runtime callers to contract first:
   - apps/Platform/routes (already closest to target ownership)
   - apps/Shell/Services/ShellCompositionService
   - app/Core/Auth, app/Core/SidebarBuilder, app/Core/DashboardBuilder
5. Switch Manufacturing/SBAIO services/controllers to contract.
6. Keep RoleDashboardsController and UserDashboardAssignmentService admin CRUD untouched until dedicated admin-contract phase.
7. Add parity tests around routeAccessDecision, resolveUserContext, and scopeFiltersForTable before and after each caller migration batch.
8. Once all runtime callers use contract, mark direct Base static usage deprecated.
9. Introduce separate admin contract and migrate admin callers.
10. Only after parity and usage-zero checks, split internal service into smaller Platform-owned services and retire Base entry point.

### Guardrails
- No behavior changes while introducing contract/adapters.
- No changes under /app core runtime implementation during this prep phase.
- No movement of high-risk controllers/services in this phase.

## 6) Risks to manage in migration
- ensureSchema side effects currently triggered from many call sites; contract should centralize bootstrap behavior to avoid migration order bugs.
- Route-access logic includes mixed app-grant and token-check behavior; parity testing must lock current semantics.
- Some Core classes currently reference Base service strings/class_exists checks; these require controlled adapter wiring.
- UI config and assignment CRUD are tightly coupled in one class; keep out of runtime contract to reduce blast radius.
