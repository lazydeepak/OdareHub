# OH-AUTO2-106 — Host-Provider Capability Governance Design Audit

**Date:** 2026-10-16
**Mode:** Read-only repository investigation and isolated non-database verification
**Status:** Requires architectural decision; implementation not authorized

## 1. Scope and safety boundary

This audit examined provider manifests, persisted hook descriptors, Shell
registries/composers, owner provider implementations, existing authorization
boundaries, and isolated probes. No application bootstrap, installed database,
SQL, schema operation, activation, browser test, deployment, or Manufacturing
operation was performed. OH-AUTO2-105B remains blocked and the affected SBAIO
database was not accessed.

The existing lifecycle logs and PHPUnit cache were preserved. Their pre-existing
working-tree state remains untouched.

## 2. Architecture law and current contract

The broken architecture law is:

> A dynamically registered host contribution must not be executable merely
> because its manifest or database payload names a file, class, and method.

The current system has two related execution paths:

1. **Admin `/me` host surfaces** use
   `plugins/Base/Services/HostSurfaceRegistryService.php`. It reads enabled
   `core_app_hooks`, accepts a payload containing `surface`, `region`,
   `provider`, and `provider_file`, loads the declared file, invokes any
   existing static method named after `::`, then normalizes and filters the
   returned items.
2. **Operator surfaces** use
   `apps/Shell/Services/OperatorSurfaceContributionRegistry.php`. It reads an
   enabled app manifest, loads the declared provider file, invokes the declared
   static method, and only then interprets the returned contribution.

Both paths isolate exceptions, but exception isolation is not execution safety.
Neither path currently proves that the callback is read-only, that the method
is approved for the declared region, that the callback belongs to the owning
app, or that record-level scope is enforced before provider execution.

The existing OH-AUTO2-103 boundary is valuable but narrower: restricted,
malformed, unavailable, denied, and missing `/me` preflight states now return
before host-provider discovery. It does not govern an admitted unrestricted
provider callback.

## 3. Registration and loading findings

### 3.1 Registration

`AppManifestService::surfaceHooksFromManifest()` copies hook dictionaries with
minimal shape checks. `AppRuntimeRegistryService::refreshAppRuntimeArtifacts()`
persists those dictionaries to `core_app_hooks` without a capability/effect/scope
validation stage. The persisted row therefore acts as executable callback
metadata, not as independently verified capability evidence.

The scaffold generator also emits the same minimal host-surface shape. This
means newly generated providers inherit the current weak contract.

### 3.2 Admin host loading

`HostSurfaceRegistryService::build()` currently:

- selects every enabled app and enabled `host_surface` hook;
- filters by requested surface only after decoding the payload;
- accepts any non-empty `provider` containing `::`;
- concatenates `install_path` and `provider_file` without an explicit
  canonical containment check;
- loads the file before provider ownership, method, effect, or scope evidence is
  verified;
- invokes any existing static method;
- applies `permission_profile` deny-name filtering and GET route filtering to
  returned items after invocation.

The route filter protects emitted URLs. It does not protect the provider's
queries, writes, schema initialization, or record selection performed before
the URL is emitted.

### 3.3 Operator loading

`OperatorSurfaceContributionRegistry` improves assignment filtering by loading
only active assigned apps and requiring a provider file, but it still trusts the
manifest's class/method pair and invokes it before validating a capability
contract. `OperatorLayerWidgetService` is a second, partly hardcoded module
provider path; it independently loads module classes and, where a module path
does not match its expected pattern, can default to active behavior.

The existence of multiple loaders is itself a governance concern: a future
capability contract must cover all executable provider paths or explicitly
classify legacy paths as compatibility-only and block them from new providers.

## 4. Existing authorities and their limits

Existing authorities that should be reused:

- app enabled/disabled state;
- assigned and active app context;
- existing Platform app/module access policy;
- existing route authorization for emitted GET URLs;
- existing operator same-user `/u/{username}` confinement;
- existing preflight admission for restricted `/me` scopes;
- owner-provided record/scope rules where they are independently established.

These authorities do **not** currently prove:

- callback non-mutation;
- record-level authorization for broad counts, staff data, or watchlists;
- that a provider method is approved for a particular surface/region;
- that a declared provider file is contained by its owning installation path;
- that returned rows, action URLs, and counts share one authorization decision;
- that an output is safe merely because it has a recognized widget shape.

Metadata validation must not be treated as proof of non-mutation. That proof
requires static review, a restricted execution boundary, or runtime enforcement.

## 5. Proposed minimal capability contract

The proposed contract is a governance/admission contract, not a new ACL or
parallel business authorization system. It should consume existing Platform and
owner authorities.

Each registered provider capability should have an independently validated
descriptor with at least:

- **provider identity:** stable capability key, owning app/module, installation
  identity, source file, class, and approved method;
- **surface declaration:** exact approved host surface and region set;
- **lifecycle:** enabled/disabled state and compatibility/version boundary;
- **authority requirements:** existing app/module authority keys and whether
  identity, assignment, or operational scope is required;
- **scope contract:** owner contract identifier describing record-level
  filtering, or an explicit `unavailable`/suppressed state;
- **effect class:** `read_only_summary`, `read_only_navigation`,
  `read_only_projection`, or a separate mutation-capable class that cannot
  enter a read-only host path;
- **output contract:** permitted contribution types and required fields;
- **URL contract:** relative-path shape, surface confinement, and final route
  re-authorization requirements;
- **failure behavior:** provider load failure, missing evidence, unavailable
  storage, and authorization failure all fail closed to an unavailable or empty
  contribution without presenting verified zero activity;
- **review/evidence status:** static review or execution-isolation evidence
  required before admission.

The descriptor is evidence used by an admission validator. It is not authority
by itself: existing authorization and owner scope checks remain authoritative.

## 6. Execution-safety design

The minimum safe sequence is:

1. Validate descriptor syntax and exact provider identity.
2. Resolve the owning enabled app/module and canonicalize the provider path;
   reject traversal, symlink escape, missing file, namespace mismatch, and
   owner mismatch before loading.
3. Resolve the requested surface/region against the descriptor allowlist.
4. Resolve existing identity, assignment, app/module, and scope authorities.
5. Reject missing capability or scope evidence before provider file loading.
6. Admit only an approved callback method for the exact capability, not any
   method supplied in a request or arbitrary manifest payload.
7. Execute under the declared effect boundary. A read-only declaration is not
   enough unless backed by static review, a restricted execution mechanism, or
   runtime enforcement.
8. Normalize output, re-authorize every URL, enforce output limits, and convert
   unavailable/failure states to controlled suppression.

The first implementation should not attempt PHP sandboxing in Shell or Core.
If true mutation prevention cannot be established for an in-process callback,
the provider must remain unsupported for the read-only host surface rather than
being admitted on metadata alone.

## 7. Provider compatibility matrix

| Provider family | Current evidence | Classification | Required migration |
|---|---|---|---|
| Manufacturing `/me` host hooks | Existing app/module checks for summaries and module widgets; separate Ops check for approval counts; broad record-scope proof is incomplete for some aggregates | Compatible after bounded modification | Add exact descriptors and owner scope evidence; keep unsupported summaries suppressed |
| Manufacturing operator hooks | Assignment and view checks; `/u/*` URL shaping exists; data exchange advertises import/export | Mixed: read-only portions compatible after modification; mutation portions unsupported | Split navigation/read projections from import/export capabilities; validate focus/route/output contracts |
| Manufacturing widget-builder dataset | Manifest provider is dynamically loaded by Platform dataset registry and is not user-scoped | Unsupported for host-provider contract | Keep under a separate builder-dataset contract with its own authority/effect review |
| SBAIO `/me` host hooks | Header/navigation is route-authorized; personnel/payroll summaries and watchlists are intentionally suppressed for missing record-level evidence | Navigation compatible after modification; operational providers unsupported | Add descriptors; preserve suppression until record-level authorization exists |
| SBAIO operator hooks | Manifest-declared provider path; owner-specific behavior exists but no unified capability/effect proof | Compatible after bounded modification for proven read paths; otherwise unsupported | Add assignment, scope, output, and mutation classification |
| Hospitality operator hooks | Provider checks Hospitality assignment and emits `/u/{user}` routes/views | Compatible after bounded modification | Add descriptor and explicit route/output/scope evidence |
| Studio `/me` hooks | Provider self-checks platform-admin authority and links to conditional Studio surfaces | Compatible after bounded modification | Add optional-app lifecycle and exact platform-admin capability evidence |
| Legacy `OperatorLayerWidgetService` module providers | Hardcoded provider catalog and separate active-state logic | Unsupported as a new admission path; compatibility debt | Route through the canonical contract or freeze/remove after parity proof |
| Procurement | No current host/operator provider hook found in manifests | Not applicable | No migration until a provider is declared |

## 8. Required future regression proof

The implementation phase must add isolated, in-memory tests without application
bootstrap or database access for at least:

1. unknown provider key rejected before file load;
2. owner/path mismatch and traversal/symlink escape rejected before load;
3. unapproved method rejected before invocation;
4. unsupported surface/region rejected before invocation;
5. restricted, malformed, unavailable, denied, and missing preflight suppress
   discovery and execution;
6. missing authority, missing scope evidence, disabled app, and disabled module
   fail closed;
7. an authorized read-only provider returns its established output;
8. provider exceptions are isolated and produce unavailable/suppressed output;
9. every emitted URL is still subject to existing route and wrapper authority;
10. unauthorized counts, records, rows, and watchlists are not collected;
11. mutation-capable or ambiguous providers are rejected from read-only paths;
12. provider output limits and malformed output are handled safely;
13. the legacy compatibility adapter cannot bypass the validator;
14. all fixtures use fake providers and fake policy/DB objects, with assertions
    that no real DB connection, SQL, schema initialization, or write occurs.

Existing proof status:

- OH-AUTO2-103 containment: **15/15**;
- OH-AUTO2-105 provider scope authorization: **12/12**;
- PHP lint on audited registry/provider files: **pass**.

Those proofs validate current containment and selected owner behavior; they do
not yet prove the proposed capability contract.

## 9. Exact future change surface

At audit completion this was a proposed change surface pending Planner review.
Planner subsequently accepted the findings and authorized the bounded
OH-AUTO2-107 implementation. The implemented first-phase change surface is:

- a Platform-owned descriptor/validator/admission capability in the non-Core
  platform layer;
- `plugins/Base/Services/HostSurfaceRegistryService.php` as a compatibility
  adapter that cannot execute without admission;
- `apps/Shell/Services/OperatorSurfaceContributionRegistry.php` and the
  legacy `OperatorLayerWidgetService.php` path, either routed through the same
  admission boundary or explicitly frozen as migration debt;
- owner manifests and providers under Manufacturing, SBAIO, Hospitality, and
  Studio, with exact descriptors and scope/effect evidence;
- focused owner probes and one cross-loader governance probe;
- documentation/workspace decisions for any provider that remains suppressed.

OH-AUTO2-108 remains the planned follow-up for additional provider migration;
OH-AUTO2-105B remains separately blocked and was not reopened.

`app/Services/AppManifestService.php` and
`app/Services/AppRuntimeRegistryService.php` currently persist hook metadata.
They are Core-owned and locked. The first design should avoid modifying them;
if descriptor validation cannot be performed at the non-Core boundary, that is
an explicit architectural blocker requiring approval rather than a Core
shortcut.

## 10. Functional limitations expected after governance

- Providers without independently proven record-level scope will remain
  suppressed even when their app route is authorized.
- Mutation-capable contributions cannot share the read-only `/me` provider path.
- Some legacy module widgets may disappear until they are declared through the
  canonical contract and parity-tested.
- A provider exception will render as unavailable/suppressed, not as a
  best-effort partial count that could be mistaken for zero.
- Provider metadata will not be accepted as proof of non-mutation.

## 11. Classification and decision required

**Final classification: REQUIRES ARCHITECTURAL DECISION.**

The existing containment work is implementation-ready as a safety baseline,
but the governance mechanism is not implementation-ready. Planner decisions
are required on:

1. the canonical non-Core owner of descriptor validation/admission;
2. whether legacy Base and hardcoded module loaders are compatibility adapters
   or must be retired before new providers are admitted;
3. the accepted proof standard for read-only execution (static review,
   restricted execution, runtime enforcement, or a combination);
4. the minimum owner record-scope contract for summary/count providers; and
5. whether descriptor evidence is stored in manifests, a non-Core registry, or
   both while Core hook persistence remains unchanged.

No implementation, database assessment, activation, deployment, commit, or push
was performed for OH-AUTO2-106.
