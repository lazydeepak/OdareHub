# OH-AUTO2-107 — Governed `/me` Host-Provider Admission Foundation

**Date:** 2026-10-16
**Status:** Implemented — bounded first phase
**Scope:** `/me` host providers only; non-Core; no database/schema changes

## Result

Added a Platform-owned admission service:

`apps/Platform/Services/HostProviderAdmissionService.php`

`plugins/Base/Services/HostSurfaceRegistryService.php` now invokes this
admission check before requiring a `/me` provider file or invoking its callback.
Base retains discovery, execution, normalization, route filtering, ordering, and
exception isolation.

Other host surfaces, including approval and role inbox surfaces, retain their
existing execution path.

## Trusted descriptor contract

Version-controlled application manifests now contain
`host_provider_capabilities` metadata. The first approved descriptors are
limited to navigation-only `/me` callbacks:

- Manufacturing: `me.header_actions`, `me.quick_links`
- SBAIO: `me.header_actions`, `me.quick_links`
- Studio: `me.header_actions`, `me.quick_links`

Each descriptor binds the owner app, exact provider file, exact class/method,
surface and region, existing app-assignment authority, effect, record-scope
responsibility, output contract, failure behavior, and static/isolated review
status.

Summary cards, monitoring sections, personnel/payroll data, watchlists, and
other operational callbacks have no admission descriptor and therefore fail
closed before provider loading. They remain suppressed pending OH-AUTO2-108
scope and read-safety migration.

## Admission ordering

For `/me`, admission now verifies, before provider loading:

1. exact surface, hook, owner, and manifest identity;
2. unique trusted descriptor;
3. enabled descriptor and exact payload provider/file binding;
4. canonical provider file existence and owner-path containment;
5. class/method syntax and static class declaration in the bound file;
6. read-only navigation effect, route-only scope, output, failure, and review
   contract;
7. existing Platform app-enabled, assignment, and module authority.

Only then does Base require and invoke the callback. The descriptor does not
claim to prove arbitrary PHP is non-mutating; static review and isolated tests
remain required.

## Files changed

- `apps/Platform/Services/HostProviderAdmissionService.php` — admission policy
- `apps/Platform/Tests/probe_host_provider_admission.php` — fake-provider
  pre-load and rejection proof
- `apps/Platform/Tests/probe_host_provider_descriptor_inventory.php` — actual
  approved manifest/provider loading proof
- `apps/Manufacturing/manifest.json` — two approved navigation descriptors
- `apps/SBAIO/manifest.json` — two approved navigation descriptors
- `apps/Studio/manifest.json` — two approved navigation descriptors
- `plugins/Base/Services/HostSurfaceRegistryService.php` — `/me` admission hook
- `apps/Shell/Tests/probe_host_surface_restricted_containment.php` — updated
  fake descriptors/policy for the existing containment regression

No Core file was changed. No routes, schema, migrations, database-backed probe,
browser test, activation, deployment, or SBAIO data restoration was performed.

## Verification

- Governed admission fixture: **9/9**
- Actual approved provider inventory and ordering: **7/7**
- OH-AUTO2-103 restricted containment: **15/15**
- OH-AUTO2-105 provider authorization: **12/12**
- PHP lint: pass for all changed PHP files
- Manifest JSON validation: pass

## Remaining limitations

- The admission service cannot prove arbitrary PHP is mutation-free.
- The six approved callbacks are navigation-only; operational providers remain
  unadmitted.
- Descriptor metadata is version-controlled trust evidence, not a replacement
  for owner record authorization.
- The existing dynamic database hook rows remain present, but their `/me`
  payloads cannot bypass missing or conflicting trusted descriptors.
- Legacy operator and widget-builder loaders are outside this phase.
