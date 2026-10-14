# OH-AUTO1-015 — Authentication, Authorization, and Tenant-Isolation Audit

**Date:** 2026-10-14
**Status:** Audit complete with one escalated Core authorization defect
**Scope:** Read-only repository audit and pure in-memory probes only

## Scope and safety boundary

This audit inspected authentication/session lifecycle, CSRF enforcement, route and
controller authorization, ACL composition, resource ownership, and organization/tenant
identifiers. It did not perform live browser testing, penetration testing, database writes,
schema changes, migrations, installation, deployment, restore, apply, commit, or push.

Existing uncommitted OH-AUTO release/recovery work and runtime-log changes were preserved.
No Core file was modified.

## Verified positive controls

- `Auth::bootSession()` enables strict session mode, cookie-only sessions, HttpOnly cookies,
  SameSite=Lax, HTTPS-aware secure cookies, and session regeneration on login.
- `Auth::isLoggedIn()` checks the database account status and, when available, the persisted
  `auth_session_version`; status/version mismatch invalidates the session.
- Login rejects invalid credentials, inactive/setup-pending accounts, and site-lock violations.
- 2FA pending sessions are not treated as logged in; successful 2FA completes the same session
  mutation path as password login.
- CSRF validation uses `hash_equals()` and is present in the inspected write controllers for
  Hospitality, Manufacturing, Procurement, SBAIO, Base/ACL, and account-management flows.
- Studio routes consistently delegate to `StudioController::guardPlatformAdmin()`; the guard
  requires an authenticated user and `authority_role=platform_admin`.
- Hospitality operator POST routes bind mutations to the authenticated user's own `/u/{username}`
  workspace before invoking operator actions.
- `tv_display` users receive no interactive permissions in the effective ACL context.
- The route scan's apparent missing guards for thin closures were verified as controller-level
  guards, not treated as evidence of a fail-open route.

## Finding SEC-AUTH-001 — ACL deny override can be re-added by later grants

**Severity:** High, pending deployment-specific confirmation
**Owner:** Core ACL policy; escalation required
**Status:** Verified by implementation path and pure in-memory probe; not fixed because Core
changes and authorization-semantics changes are outside this audit authorization.

### Evidence

`app/Core/AclPolicy.php::effectiveRolePermissions()` applies `deny` rows to the role baseline
at lines 635–653. However, `effectivePermissionsForContext()` subsequently adds permissions from:

1. the authority-role baseline;
2. the legacy role baseline;
3. access profiles;
4. the user `permissions` context field; and
5. `APP_PERMISSION_GRANTS` for assigned apps.

Those later additions are not filtered against the denied permission set. Consequently, a role
permission deny can be bypassed when the same permission is supplied by an access profile, an
explicit user permission field, or an assigned app. This contradicts the existing comments at
`AclPolicy::can()` and `RoleDashboardsController::canManageAssignments()` stating that custom
deny overrides take runtime effect.

### Isolated proof

The following disposable, in-memory reflection probe seeded an empty role baseline and supplied
the `platform_administration` access profile plus the `manufacturing` assigned app. The context
returned `acl.manage`, `workflow.production_plan.approve`, and `ops.my_work.view` as granted.
This demonstrates the re-addition mechanism without a database mutation or a live request.

The probe was executed from a temporary file outside the repository and was not retained as a
runtime artifact.

### Required remediation direction

The Core owner should preserve deny decisions separately and apply them after all additive role,
profile, user, and app grants, or otherwise make the final effective permission set subtract the
deny set. The remediation must include regression cases for each grant source and must explicitly
preserve `tv_display` read-only behavior. No app/plugin workaround should duplicate or override
Core ACL semantics.

## Tenant and organization model result

No generic end-user tenant boundary was found. `org_companies`, `org_branches`, fiscal settings,
branding, and hierarchy tables are platform organization-management data. Their routes are
protected by organization permissions/platform governance, but users do not carry a repository-wide
company/tenant/branch scope that can be enforced across business queries.

The inspected Hospitality schema is intentionally local and contains no `company_id`, `tenant_id`,
`organization_id`, or `branch_id`. Manufacturing migrations likewise contain no such ownership
columns. Parties explicitly documents the absence of tenant fields. Therefore cross-organization
IDOR testing is not meaningful for these tables under the current model; adding filters or columns
would change the data model and authorization semantics and is not authorized by this audit.

## Remaining risks and escalation

- SEC-AUTH-001 must be resolved or explicitly accepted by the Core/security owner before treating
  ACL deny overrides as reliable for privileged permissions.
- Tenant isolation is not a verified capability for business data because a generic tenant model
  does not exist. Future multi-organization work requires an explicit contract, ownership fields,
  query scoping policy, migration plan, and isolated cross-organization regression suite.
- The authenticated live HTTP surface remains unverified in this environment. The existing admin
  route probe failed before authentication because its local curl request returned no response
  body; this is an environment/probe issue, not a security pass.

## Validation record

- Pure in-memory ACL mechanism probe: reproduced permission re-addition.
- Static route/controller audit: completed across `apps/`, `plugins/`, and `app/` route registries.
- Hospitality foundation probes: available and prior suite remains unchanged.
- Architecture/deployment commands were run separately after the audit; unrelated pre-existing
  release/recovery work and environment-sensitive probe limitations remain as recorded in
  `engineering/Platform/work.md`.
- `git diff --check`: required before any later handoff; no runtime security code was changed here.
