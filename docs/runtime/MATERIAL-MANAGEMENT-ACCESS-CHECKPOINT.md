# Material Management Access Checkpoint

## Date

- 2026-05-23

## Routes Checked

- /apps/manufacturing/materials
- /apps/manufacturing/materials/master

## Files And Services Involved

- apps/Manufacturing/modules/MaterialManagement/routes.php
- apps/Manufacturing/modules/MaterialManagement/Services/MaterialAccessService.php
- plugins/Base/Services/UserDashboardAssignmentService.php
- plugins/Base/bootstrap.php

## Access Decision Chain

1. Global assignment policy is enforced first by the Base bootstrap policy hook.
2. Route access is evaluated by UserDashboardAssignmentService route policy.
3. Module visibility must include materials or route access is denied with module_not_allowed.
4. After assignment policy passes, MaterialAccessService enforces section-level access requirements.
5. Section access requires either read permissions for dashboard-level access (materials.stock.view or materials.coverage.view) or manage-level permissions for master/manage sections.

## Current Result

- /apps/manufacturing/materials -> 403
- /apps/manufacturing/materials/master -> 403
- Response body marker: Access denied by assignment policy.
- Current status is expected for active accounts under the current assignment-policy state.

## Why This Is Not A Runtime Route Failure

- Both routes are registered and resolvable in Material Management route definitions.
- The observed 403 comes from access policy denial, not missing route registration or handler failure.
- The deny reason is module_not_allowed from assignment-policy route evaluation.
- Therefore this is an authorization and assignment-context outcome, not a routing defect.

## Required Authorized Test Account For Future Validation

To fully validate Material Management screens, the test account must satisfy both layers:

1. Assignment policy layer:
- module_visibility includes materials.

2. Material section permission layer:
- For materials dashboard access: materials.stock.view or materials.coverage.view (or broader manage/admin authority).
- For materials master access: manage-level authorization via one or more of:
  - materials.admin
  - materials.master.manage
  - materials.planning.manage
  - materials.orders.manage
  - materials.stock.adjust
  - materials.capacity.manage
  - materials.cost.manage
- Platform admin or app admin authority only works after assignment policy allows materials module access.

## Current Account Coverage

- Evaluated users: lazydeepak, lazy.
- Both resolve as platform_admin.
- Both currently deny materials and materials/master with module_not_allowed due to module_visibility excluding materials.
- No currently authorized account/session is available for full Material route validation without changing assignments.

## Constraints

- Docs-only checkpoint.
- No runtime code changes.
- No permission or assignment changes.
- No fixes proposed.
