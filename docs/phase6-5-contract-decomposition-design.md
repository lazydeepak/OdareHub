# Phase 6.5: User Assignment Contract Decomposition (Design Only)

Date: 2026-04-18
Scope: design and planning only (no runtime code movement)

## Objective

Decompose the current Platform runtime contract into three narrower contracts:
- UserContextContract
- UserAccessPolicyContract
- UserScopeContract

The goal is to separate read-model/context concerns from policy checks and SQL-scope translation while preserving behavior through the existing adapter delegation model.

## Inputs Reviewed

- apps/Platform/Contracts/UserAssignmentContextContract.php
- apps/Platform/Services/PlatformUserAssignmentAdapter.php
- apps/Platform/Services/UserAssignmentContext.php
- apps/Platform/bootstrap.php
- apps/Platform/routes.php
- apps/Shell/Services/ShellCompositionService.php
- apps/Manufacturing/**/* (current resolver and remaining direct-call sites)
- apps/SBAIO/Controllers/SbaioDashboardController.php

## Current Contract Surface (Before Split)

Current interface: UserAssignmentContextContract

Methods:
- resolveUserContext
- dashboardTypeForUser
- primaryLandingForContext
- routeForDashboardType
- routeAccessDecision
- enabledModulesForUser
- canAccessModule
- isAppEnabled
- scopeFiltersForTable
- meCoreQuickLinks

## Proposed Split Interfaces

### 1) UserContextContract

Responsibility: user context materialization and context-derived navigation metadata.

Minimal method set:

```php
<?php
declare(strict_types=1);

namespace Apps\Platform\Contracts;

interface UserContextContract
{
    /** @param array<string,mixed>|null $user
     *  @return array<string,mixed>
     */
    public function resolveUserContext(?array $user): array;

    /** @param array<string,mixed>|null $user */
    public function dashboardTypeForUser(?array $user): string;

    /** @param array<string,mixed> $context */
    public function primaryLandingForContext(array $context): string;

    public function routeForDashboardType(string $dashboardType): string;

    /** @param array<string,mixed> $context
     *  @return array<int,array<string,mixed>>
     */
    public function meCoreQuickLinks(array $context): array;
}
```

### 2) UserAccessPolicyContract

Responsibility: policy decisions for route/module/app access.

Minimal method set:

```php
<?php
declare(strict_types=1);

namespace Apps\Platform\Contracts;

interface UserAccessPolicyContract
{
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
}
```

### 3) UserScopeContract

Responsibility: translate operational scope arrays into SQL fragments used by scoped queries.

Minimal method set:

```php
<?php
declare(strict_types=1);

namespace Apps\Platform\Contracts;

interface UserScopeContract
{
    /** @param array<string,mixed> $scope
     *  @param array<string,string> $columnToScopeKey
     *  @return array{sql:string,params:array<int,int|string>}
     */
    public function scopeFiltersForTable(array $scope, string $table, array $columnToScopeKey): array;
}
```

## Adapter Design Update (No Behavior Change)

Design intent for PlatformUserAssignmentAdapter:
- Keep class name and delegation model unchanged.
- Implement all three contracts simultaneously:
  - UserContextContract
  - UserAccessPolicyContract
  - UserScopeContract
- Preserve existing method signatures and direct delegation to Plugins\\Base\\Services\\UserDashboardAssignmentService.

Planned class declaration in Phase 6.6:

```php
final class PlatformUserAssignmentAdapter implements
    UserContextContract,
    UserAccessPolicyContract,
    UserScopeContract
{
    // Existing delegated methods remain unchanged.
}
```

Factory/accessor compatibility strategy:
- Keep platform_user_assignment_context() returning current unified type initially for zero breakage.
- Introduce additional typed accessors later if needed:
  - platform_user_context_contract()
  - platform_user_access_policy_contract()
  - platform_user_scope_contract()

This can be additive and backward-compatible.

## Caller Mapping: Current Usage -> Target Contract

### A) Current accessor-based callers

- apps/Platform/routes.php
  - uses resolveUserContext
  - target: UserContextContract

- apps/Manufacturing/modules/Machines/Services/MachineLeaderDashboardService.php
  - uses resolveUserContext
  - target: UserContextContract

- apps/Manufacturing/modules/QCEntries/Services/QcLeaderDashboardService.php
  - uses resolveUserContext
  - target: UserContextContract

- apps/Manufacturing/modules/DispatchEntries/Services/DispatchLeaderDashboardService.php
  - uses resolveUserContext
  - target: UserContextContract

- apps/Manufacturing/modules/MaterialManagement/Services/MaterialAccessService.php
  - uses resolveUserContext
  - target: UserContextContract

- apps/Manufacturing/modules/Workflow/Services/WorkflowTransitionEngine.php
  - uses resolveUserContext, canAccessModule
  - target: UserContextContract + UserAccessPolicyContract

- apps/Manufacturing/Controllers/DemandDashboardController.php
  - uses resolveUserContext
  - target: UserContextContract

- apps/Manufacturing/Services/ProductionOperationService.php
  - uses resolveUserContext
  - target: UserContextContract

- apps/Manufacturing/Services/ProcessingOperationService.php
  - uses resolveUserContext
  - target: UserContextContract

### B) Remaining direct Base callers (not yet migrated)

- apps/Manufacturing/Controllers/ProductionPlansController.php
  - uses resolveUserContext
  - target: UserContextContract

- apps/Manufacturing/Controllers/StageTransitionController.php
  - uses resolveUserContext
  - target: UserContextContract

- apps/Manufacturing/modules/ProductionEntries/Controllers/ProductionEntriesController.php
  - uses resolveUserContext
  - target: UserContextContract

- apps/SBAIO/Controllers/SbaioDashboardController.php
  - uses resolveUserContext
  - target: UserContextContract

- apps/Shell/Services/ShellCompositionService.php
  - uses resolveUserContext, meCoreQuickLinks
  - target: UserContextContract

### C) Expected scope-contract consumers

No current platform_user_assignment_context() caller in the migrated batch uses scopeFiltersForTable directly.

Scope-based filtering remains present in the underlying Base service internals and can be migrated to explicit UserScopeContract consumers in later phases where query builders are contract-aware.

## Contract Decomposition Limits Observed

1. Most runtime callers currently need only resolveUserContext.
- This validates splitting context from policy/scope to reduce interface bulk.

2. Policy calls are concentrated.
- canAccessModule currently appears in WorkflowTransitionEngine through the accessor path.
- routeAccessDecision and enabledModulesForUser are mostly consumed via Base/Shell or adapter delegation paths.

3. Scope contract has low direct surface usage in app callers.
- It is still necessary as a dedicated capability boundary, but rollout should be incremental and usage-driven.

## Phase 6.6 Migration Plan

### Step 1: Add new interfaces (no caller changes yet)
- Add:
  - apps/Platform/Contracts/UserContextContract.php
  - apps/Platform/Contracts/UserAccessPolicyContract.php
  - apps/Platform/Contracts/UserScopeContract.php
- Keep existing UserAssignmentContextContract temporarily for compatibility.

### Step 2: Multi-interface adapter
- Update PlatformUserAssignmentAdapter to implement all new contracts.
- Keep all methods delegating to Base service exactly as today.

### Step 3: Compatibility bridge contract
- Option A (preferred): make UserAssignmentContextContract extend the three new contracts during transition.
- Option B: keep old contract unchanged and have accessor return an intersection-compatible adapter type by implementation.

### Step 4: Accessor preservation
- Keep platform_user_assignment_context() stable for existing callers.
- Optionally add dedicated typed accessors after parity checks.

### Step 5: Migrate callers by risk tier
- Tier 1: remaining resolveUserContext-only controllers/services.
- Tier 2: policy callers (route/module checks).
- Tier 3: scope SQL consumers.

### Step 6: Parity checks
- For each migrated set:
  - php -l on changed files
  - smoke checks on impacted routes
  - no new manual include paths
  - no direct Base static calls in migrated files

### Step 7: Contract cleanup
- After all callers are moved to new contracts, deprecate/remove UserAssignmentContextContract as wrapper alias in a dedicated cleanup phase.

## Non-Goals in Phase 6.5

- No runtime logic changes.
- No Base service edits.
- No Core edits.
- No Shell edits.
- No caller rewiring in code.
