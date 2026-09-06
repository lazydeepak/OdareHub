# Rendered Admin Proof Planning Contract

**Status:** Planning contract, boundary gate, and implementation skeleton complete.
Skeleton proof object, CLI harness, and boundary gate all pass (42 invariants).
No Shell changes. No runtime wiring. No runtime application enablement. No
production style application.

**Date:** 2026-06-12

**Builds on:**
- [Runtime Style Application Contract](runtime-style-application-contract.md)
- [Shell Insertion Planning Contract](shell-insertion-planning-contract.md)
- [Platform Style Consumption Surface Contract](platform-style-consumption-surface-contract.md)
- [Registry Read Contract](registry-read-contract.md)

---

## Part A — Proof Objective

The first rendered proof answers exactly one question:

> Can an approved `radius.scale` value travel through the entire Platform chain
> and reach a single admin wrapper safely?

The proof validates architecture, not styling quality. It must prove:

```text
Registry
    ↓
ResolvedStyleConsumer
    ↓
StyleConsumptionSurface
    ↓
RuntimeStyleApplication
    ↓
ShellInsertion
    ↓
synthetic admin .layout-main fragment
```

The proof does not assess visual polish, component coverage, design preference,
or production readiness. A successful proof demonstrates value provenance,
mapping correctness, insertion confinement, diagnostic coverage, and reversible
execution.

---

## Part B — Allowed Proof Scope

The proof allowlist is fixed:

| Dimension | Allowed value |
|---|---|
| Surface | `admin` only |
| Target | `.layout-main` only |
| Socket | `radius.scale` only |
| CSS custom property | `--corner-radius` only |
| `sharp` | `4px` |
| `soft` | `8px` |
| `round` | `16px` |
| Fallback | `8px` |

Nothing else is authorized. The proof must not accept arbitrary sockets,
selectors, properties, values, style maps, token maps, CSS bundles, or wrapper
names.

---

## Part C — Proof Model Options

| Model | Chain fidelity | Ownership impact | Blast radius | Decision |
|---|---|---|---|---|
| **A. Static rendered fixture** | Low; can show markup but cannot prove the Platform chain | Fixture-owned | Very low | Rejected as insufficient |
| **B. Dedicated proof page** | High | Requires route, controller, authorization, and page ownership | Medium | Rejected for first proof |
| **C. Admin wrapper proof** | Highest | Modifies the real Shell-owned wrapper | High | Deferred |
| **D. Synthetic test harness** | High when it constructs the real Platform chain and renders in memory | Tooling owns orchestration; Platform and Shell ownership remain unchanged | Lowest practical | **Selected** |

### Selected Model

**D — Synthetic test harness.**

The future harness must:

1. execute from CLI or test infrastructure only;
2. read one approved `radius.scale` value through the governed chain;
3. use a dedicated proof object to authorize proof-scoped execution;
4. pass through `RuntimeStyleApplication` and `ShellInsertion`;
5. render one in-memory fragment shaped as:

   ```html
   <div class="layout-main" style="--corner-radius: 8px"></div>
   ```

6. inspect the rendered fragment and diagnostics;
7. discard all proof state when the process exits; and
8. never write the fragment to a production template or public asset.

The harness may reproduce the selected Shell wrapper shape for verification, but
it does not own or replace the real Shell template. Shell retains ownership of
`.layout-main`; the fixture is only an assertion target.

---

## Part D — Runtime Enablement Strategy

### Options

| Strategy | Assessment | Decision |
|---|---|---|
| Hardcoded proof mode | Easy to invoke accidentally and difficult to distinguish from global enablement | Rejected |
| Scoped proof flag | Risks configuration, environment, query, or session leakage into production | Rejected |
| Dedicated proof object | Explicit capability, process-local lifetime, no global state, easy gate confinement | **Selected** |
| Global runtime enablement | Converts a proof into production behavior | Forbidden |

### Selected Strategy

Use a **dedicated proof object** as an explicit, immutable capability owned by
Platform proof infrastructure.

Conceptual future shape:

```text
RenderedAdminProof
    owns proof context
    constructs or receives governed chain
    invokes proof-scoped ShellInsertion path
    renders one in-memory fragment
    emits RAP-* diagnostics
```

The proof object must not be obtainable from:

- configuration;
- environment variables;
- query parameters;
- cookies or sessions;
- database state;
- routes or controllers; or
- production dependency injection.

`RuntimeStyleApplication::isRuntimeApplicationEnabled()` remains `false`.
`ShellInsertion::isShellInsertionEnabled()` remains `false`.

The dedicated proof object provides a third, explicitly scoped state:

```text
globally enabled: false
Shell insertion enabled: false
proof execution authorized: true, only inside the synthetic harness process
```

Gate preparation must define the exact proof object and invocation boundary
before implementation changes any existing API. No existing disabled flag may be
reinterpreted as proof-enabled.

---

## Part E — Shell Insertion Scope

The rendered proof may produce exactly one insertion:

```text
admin .layout-main
    style="--corner-radius: {4px|8px|16px}"
```

The fallback result is `8px`.

The following remain forbidden:

- operator wrappers;
- display wrappers;
- workspace surfaces;
- navigation surfaces;
- topbar surfaces;
- shared `body` or `.layout-shell` roots;
- auth, setup, recovery, maintenance, print, and output surfaces;
- app-owned or module-owned wrappers;
- child-level inline styles; and
- production `header.php` or layout edits.

The proof output must contain one `.layout-main` element and one CSS custom
property declaration. No additional style declaration is permitted.

---

## Part F — Rendered-Proof Diagnostics

Rendered proof diagnostics use the `RAP-*` family.

### PASS

| Code | Meaning |
|---|---|
| `RAP-P001` | Approved `radius.scale` value entered the governed proof chain |
| `RAP-P002` | Identifier resolved to the exact allowlisted CSS value |
| `RAP-P003` | Synthetic admin `.layout-main` fragment rendered |
| `RAP-P004` | Rendered fragment contains only `--corner-radius` |
| `RAP-P005` | Proof execution remained process-local and globally disabled |
| `RAP-P006` | Rollback/disposal completed with no persistent change |

### WARN

| Code | Meaning |
|---|---|
| `RAP-W001` | Approved value was absent and fixed `8px` fallback was used |
| `RAP-W002` | Proof succeeded but no visual-quality assertion was performed |

### FAIL

| Code | Meaning |
|---|---|
| `RAP-F001` | Approved value did not reach the consumption surface |
| `RAP-F002` | Identifier or mapped CSS value was outside the allowlist |
| `RAP-F003` | Target was not exactly admin `.layout-main` |
| `RAP-F004` | Additional selector, property, value, or inline declaration appeared |
| `RAP-F005` | ShellInsertion path was bypassed |
| `RAP-F006` | Runtime or Shell insertion became globally enabled |

### ERROR

| Code | Meaning |
|---|---|
| `RAP-E001` | Proof attempted to modify a production Shell template or layout |
| `RAP-E002` | CSS, theme, registry, public asset, or filesystem mutation occurred |
| `RAP-E003` | Route, DB, HTTP, command, session, or environment coupling appeared |
| `RAP-E004` | Proof state persisted after harness completion |

### Diagnostic Separation

```text
RSA-*    disabled RuntimeStyleApplication skeleton
RSI-*    disabled ShellInsertion skeleton
RAP-*    scoped synthetic rendered proof
RSC-S*   future active production Shell application
```

`RAP-*` may claim proof rendering only. It must never claim production Shell
application or emit active `RSC-S*` success codes.

---

## Part G — Rollback Contract

Rollback is disposal, not migration.

The future proof must be removable by deleting or disabling only proof-owned
artifacts. Rollback requires:

1. zero production template ownership drift;
2. zero Shell CSS ownership drift;
3. zero theme source or compiled theme ownership drift;
4. zero registry schema or approved-value migration;
5. zero public asset cleanup;
6. zero route removal;
7. zero configuration or feature-flag cleanup; and
8. no proof state remaining after process exit.

The proof must not write, update, or delete approved values. It may read the
approved value and report provenance. If the proof is removed, the Registry,
Platform chain, Shell, themes, and production output remain unchanged.

Rollback succeeds when:

```text
proof harness unavailable
AND production behavior identical
AND no persisted proof artifact exists
```

---

## Part H — Future Gate Impact

### Planned Gate

```text
scripts/architecture/check_rendered_admin_proof_boundary.sh
```

The future gate must be absent-implementation-safe and eventually enforce:

1. one proof owner and one proof entrypoint;
2. synthetic CLI/test execution only;
3. admin surface only;
4. `.layout-main` target only;
5. `radius.scale` socket only;
6. `--corner-radius` property only;
7. only `4px`, `8px`, and `16px`, with `8px` fallback;
8. dedicated proof-object authorization only;
9. `RuntimeStyleApplication` and `ShellInsertion` global flags remain false;
10. exact `RAP-*` diagnostic family;
11. no active `RSC-S*` diagnostics;
12. no Shell template, layout, route, CSS, theme, Registry, or public asset
    mutation;
13. no DB, HTTP, command, environment, session, or feature-flag coupling;
14. no operator, display, workspace, navigation, or topbar expansion; and
15. deterministic rollback/disposal with no persistent artifacts.

The gate must verify rendered fragment structure, not merely source markers.

---

## Part I — Success Criteria

The rendered proof succeeds only when all criteria pass:

| # | Measurable criterion |
|---|---|
| 1 | One approved `radius.scale` identifier is read with provenance |
| 2 | `sharp`, `soft`, and `round` resolve respectively to `4px`, `8px`, and `16px` |
| 3 | Missing or unsupported input resolves safely to `8px` |
| 4 | The value traverses the governed Platform chain without direct dependency bypass |
| 5 | ShellInsertion participates in the proof path |
| 6 | Exactly one in-memory `.layout-main` fragment is rendered |
| 7 | The fragment contains exactly one declaration: `--corner-radius` |
| 8 | Expected `RAP-*` diagnostics are emitted |
| 9 | Runtime application and Shell insertion global flags remain false before, during, and after proof execution |
| 10 | No production file or persistent state changes |
| 11 | Proof disposal restores the original process state |
| 12 | Repeated proof runs are deterministic for the same approved value |

Visual quality, component appearance, and broad theme consistency are explicitly
outside success criteria.

---

## Part J — Readiness Classification

**B — Skeleton implementation complete; compliance audit pending.**

The planning contract, gate, and skeleton proof object are implemented and
validated (42 invariants pass). The skeleton proof object, CLI harness, and
governed Platform chain exist and pass all boundary checks. No Shell changes,
no runtime enablement, and no production style application have been
introduced.

A compliance audit is required before any proof enhancement (active style
rendering, Chain variant testing, or Shell-facing proof execution).

---

## Part K — Recommended Next Slice

**Rendered Admin Proof Compliance Audit**

Audit the completed skeleton and boundary gate against the full planning
contract. Verify all 12 success criteria (Part I), scope restrictions (Part B),
diagnostics boundaries (Part F), rollback contract (Part G), and production
boundary (invariant group 10).

---

## Deliverable Summary

| Item | Decision |
|---|---|
| Contract path | `docs/architecture/rendered-admin-proof-planning-contract.md` |
| Selected proof model | Synthetic test harness rendering one in-memory admin fragment |
| Enablement strategy | Dedicated immutable proof object; global runtime and insertion flags remain false |
| Insertion scope | Admin `.layout-main` only |
| Allowed value | `radius.scale` → `--corner-radius`; `sharp`→`4px`, `soft`→`8px`, `round`→`16px`, fallback `8px` |
| Diagnostics | `RAP-P*`, `RAP-W*`, `RAP-F*`, `RAP-E*` |
| Rollback | Process-local disposal; no migration, template, CSS, theme, Registry, route, config, or asset cleanup |
| Future gate | `check_rendered_admin_proof_boundary.sh` |
| Proof object | `platform/Style/Proof/RenderedAdminProof.php` |
| CLI harness | `scripts/platform/rendered-admin-proof/rendered_admin_proof.php` |
| Boundary gate | ✅ 42 invariants pass |
| Readiness | **B — Skeleton implementation complete; compliance audit pending** |
| Recommended next slice | **Rendered Admin Proof Compliance Audit** |
