# OH-AUTO1-023 — SEC-AUTH-001 ACL Deny-Precedence Reproduction and Remediation Specification

Date: 2026-10-14
Status: **Reproduced; Core correction not authorized**

## Scope and safety

The reproduction is an in-memory reflection probe at
`scripts/security/probe_acl_deny_precedence.php`. It does not call the
database-backed user-context resolver, does not call `AclPolicy::can()`, does
not use a live account, and does not modify `app/Core`.

Probe result: **10/10 assertions pass**; database connected: **no**; database
mutated: **no**; Core modified: **no**.

## Exact effective-permission path

1. `app/Core/AclPolicy.php::can()` resolves a user context through
   `Plugins\Base\Services\UserDashboardAssignmentService::resolveUserContext()`.
2. `AclPolicy::effectivePermissionsForContext()` first copies the authority and
   legacy role permissions from `effectiveRolePermissions()`.
3. `effectiveRolePermissions()` applies `acl_role_permission_overrides`:
   `deny` unsets a role permission at lines 645–648, and `allow` adds one.
4. The same context method then adds permissions from:
   - `accessProfileMatrix()`;
   - `$ctx['permissions']`;
   - `APP_PERMISSION_GRANTS` for each assigned app.
5. The later additions are not filtered against a retained deny set.

Relevant functions/files:

- `app/Core/AclPolicy.php::can()`;
- `app/Core/AclPolicy.php::effectiveRolePermissions()`;
- `app/Core/AclPolicy.php::effectivePermissionsForContext()`;
- `plugins/Base/Services/UserDashboardAssignmentService.php::resolveUserContext()`;
- `plugins/Base/Services/UserDashboardAssignmentService.php::accessProfilePermissionMatrix()`.

## Reproduction result

The probe injects an empty synthetic role baseline and evaluates only the
composition stage:

- empty role baseline: `workflow.production_plan.approve` absent;
- `manufacturing_approval` access profile: the same permission is present;
- explicit user permission: `acl.manage` is present;
- assigned `manufacturing` app: `workflow.production_plan.approve` is present;
- combined later grants retain both permissions.

This is the deny-readdition mechanism. It does not assert that every deployed
user currently has a deny row; deployment-specific account/configuration
confirmation remains outstanding.

## Existing precedence contract

The comments in `AclPolicy::can()` state that custom deny overrides take runtime
effect. `plugins/ACL/Services/AclManagementService.php` also uses
`AclPolicy::can()` for ACL management decisions. Under that documented
contract, an explicit deny must dominate every additive grant source.

## Affected permission surfaces

The affected surface is any route, controller, service, or workflow that
delegates authorization to `AclPolicy::can()`. Verified examples include:

- `plugins/Base/Controllers/RoleDashboardsController.php` for `acl.manage`;
- `plugins/Base/bootstrap.php` for administrative and operational gates;
- `apps/Manufacturing/modules/Workflow/Services/WorkflowPolicy.php` and
  `WorkflowTransitionEngine.php` for submit/approve/finalize/reopen/override;
- Manufacturing production-plan, QC-entry, and dispatch-entry route guards;
- Hospitality view/manage checks and operator mutation guards;
- Platform and Manufacturing authorized-search result filtering.

Exploitability is **high if an attacker can combine an explicit deny with any
later grant source**. The practical account/route reachability and whether
production has such combinations require Core/security-owner confirmation and
live-independent environment evidence.

## Cache assessment

`AclPolicy::$effectiveRolePermissions` is a process-local cache. It is reset by
`AclPolicy::invalidateCache()`, but no deny-set cache or final effective-context
cache was identified. A role override change can therefore remain stale in a
long-lived process unless the existing invalidation path is called. This is a
secondary verification requirement, not a proposed workaround.

## Smallest Core-owned correction proposal

Preserve role deny decisions separately while building the effective role map,
then apply the deny set after all additive sources have been merged in
`effectivePermissionsForContext()`. The correction must remain generic:

- no app-specific exceptions;
- no plugin/app workaround;
- no change to `tv_display` read-only behavior;
- no changes to experience composition ownership;
- explicit cache invalidation or request-bound recomputation semantics.

The exact code change requires Core/security-owner approval and must not be
implemented in this assignment.

## Required regression matrix after authorization

At minimum, Core tests should cover each permission source independently and in
combination:

1. role baseline allows permission;
2. role override denies permission;
3. deny + access-profile grant remains denied;
4. deny + explicit user permission remains denied;
5. deny + assigned-app grant remains denied;
6. deny + all grant sources remains denied;
7. explicit allow after deny follows the approved override contract;
8. `tv_display` remains non-interactive;
9. cache invalidation reflects changed overrides;
10. unrelated permissions and roles retain existing behavior.

## Architectural contradiction / decision required

The implementation comments promise deny precedence, while the composition
algorithm implements additive grants without deny subtraction. This is a Core
authorization-semantics contradiction. It cannot be resolved safely in Base,
an app, or a plugin. Planner/security-owner authorization or formal risk
acceptance is required before release readiness can change.
