# Read-Only Consumption Probe Contract

Status: Architecture contract. No probe implementation authorized. No runtime consumption authorized. No Shell behavior changes authorized.
Date: 2026-06-11
Builds on: [ResolvedStyleConsumer Contract](resolved-style-consumer-contract.md), Customization Studio Operating Contract, Style Registry Ownership Contract, Shell Style Socket Contract, Style Customization Chain Checkpoint

## Part A — Purpose

The Read-Only Consumption Probe exists to answer one diagnostic question:

```
Can the future ResolvedStyleConsumer read approved Platform Style Registry values safely?
```

It must not ask:

```
Can Shell apply approved values?
```

The probe purpose is limited to:

```
diagnose registry visibility
verify approved value shape
verify socket catalog alignment
verify Shell can be inspected safely
prove no runtime mutation
```

The probe is a **readiness diagnostic** for the future `ResolvedStyleConsumer` implementation. It validates that approved registry values are reachable, structurally valid, and aligned with Shell socket catalog metadata — without exercising runtime consumption, CSS generation, or Shell behavior changes.

## Part B — Probe Boundary

### The probe may

```
read approved registry values
read Shell socket catalog metadata
read active theme metadata
emit diagnostics
exit with status code
```

### The probe may not

```
write registry values
compile themes
modify theme.css
modify Shell CSS
modify runtime DOM
apply values
create drafts
approve requests
call Studio services
register routes
```

The probe is diagnostic-only. It inspects existing approved state and catalog metadata. It does not participate in governance, drafting, approval, apply, or runtime rendering.

## Part C — Inputs

### Allowed inputs

| Input | Description |
|---|---|
| `registry root path` | Path to approved registry storage (default: `storage/platform/style-registry/approved-values/`) |
| `socket catalog path` | Path to Shell Style socket catalog directory (default: `apps/Shell/Style/Resources/socket-catalog/`) |
| `active theme identifier` | Current theme style preference for context reporting (read-only metadata) |
| `optional socket key` | Single socket key to narrow diagnostics (e.g. `radius.scale`) |
| `diagnostic mode` | Output verbosity: `summary` (default), `full`, `json-only` |

### Forbidden inputs

```
draft IDs
approval request IDs
raw CSS
runtime selector mutation payloads
Studio session state
```

The probe reads **approved, persisted registry values only**. It must not accept draft identifiers, approval workflow state, raw CSS payloads, DOM mutation instructions, or Studio session artifacts as inputs.

## Part D — Outputs

The probe produces three output channels:

```
JSON diagnostic report
human-readable CLI summary
exit code
```

### Required report fields

| Field | Type | Description |
|---|---|---|
| `probe_status` | string | Overall status: `PASS`, `WARN`, `FAIL`, or `ERROR` |
| `checked_at` | string | ISO 8601 timestamp of probe execution |
| `registry_status` | string | Registry reachability and readability: `readable`, `unreadable`, `empty`, `missing` |
| `catalog_status` | string | Catalog reachability and readability: `readable`, `unreadable`, `missing` |
| `socket_matches` | array | Sockets where approved registry value and catalog entry align |
| `missing_registry_values` | array | Catalog sockets with no corresponding approved registry value |
| `orphan_registry_values` | array | Approved registry values with no corresponding catalog socket |
| `diagnostics` | array | Full diagnostic message list (see Part E) |

### JSON report shape (illustrative)

```json
{
  "probe_status": "WARN",
  "checked_at": "2026-06-11T12:00:00+00:00",
  "registry_status": "readable",
  "catalog_status": "readable",
  "socket_matches": ["radius.scale"],
  "missing_registry_values": ["spacing.base"],
  "orphan_registry_values": [],
  "diagnostics": [
    {
      "code": "RSC-P001",
      "severity": "PASS",
      "message": "Registry storage readable"
    }
  ]
}
```

### Human-readable CLI summary

The CLI summary must print:

1. Probe status line (PASS/WARN/FAIL/ERROR)
2. Registry status (readable/unreadable/empty)
3. Catalog status (readable/unreadable)
4. Match count, missing count, orphan count
5. Top-level diagnostic codes with severity

## Part E — Diagnostics Model

### Severity levels

| Severity | Meaning |
|---|---|
| `PASS` | Check succeeded; no action required |
| `WARN` | Non-blocking mismatch or incomplete alignment; informational |
| `FAIL` | Blocking readiness issue; probe cannot confirm safe consumption |
| `ERROR` | Safety violation or probe execution failure |

### Diagnostic codes

| Code | Severity | Description |
|---|---|---|
| `RSC-P001` | PASS | Registry readable — approved values storage is reachable and parseable |
| `RSC-P002` | PASS | Catalog readable — Shell socket catalog metadata is reachable and parseable |
| `RSC-W001` | WARN | Approved value exists but no Shell catalog socket — registry entry has no catalog counterpart |
| `RSC-W002` | WARN | Catalog socket has no approved value — catalog entry exists but registry has no approved value (expected when registry is partially populated) |
| `RSC-F001` | FAIL | Registry unreadable — storage directory missing, permission denied, or parse failure |
| `RSC-F002` | FAIL | Catalog unreadable — catalog directory missing, permission denied, or parse failure |
| `RSC-E001` | ERROR | Path traversal attempt blocked — input path escapes allowed confinement |
| `RSC-E002` | ERROR | Studio dependency detected — probe attempted to import or call Studio services |

### Diagnostic aggregation

- `probe_status` is derived from the highest severity present:
  - Any `ERROR` → `ERROR`
  - Else any `FAIL` → `FAIL`
  - Else any `WARN` → `WARN`
  - Else → `PASS`

## Part F — Exit Codes

| Exit code | Condition |
|---|---|
| `0` | PASS/WARN only — probe completed; no blocking failures or safety errors |
| `1` | FAIL present — registry or catalog unreadable, or blocking readiness issue |
| `2` | ERROR present — safety violation (path traversal, Studio dependency, or execution failure) |

Exit codes follow the same severity precedence as `probe_status`. If both FAIL and ERROR are present, exit code `2` takes precedence.

## Part G — Safety Rules

The probe implementation must enforce:

```
read-only filesystem access
path confinement
no Studio imports
no web routes
no registry writes
no theme compiler calls
no exec shelling into compiler
```

### Path confinement

- Registry root must resolve under project root and under `storage/platform/style-registry/`
- Catalog path must resolve under project root and under `apps/Shell/Style/Resources/socket-catalog/`
- Any input path that escapes confinement must emit `RSC-E001` and exit `2`

### Side-effect prohibition

The probe must not:

- Create, modify, or delete any file
- Open database connections
- Make HTTP requests
- Invoke `exec()`, `shell_exec()`, `system()`, or `passthru()`
- Call `compile_theme_sources.php` or any theme compiler
- Call Visual Customizer Apply, approval, or draft services
- Register or invoke web routes

### Dependency isolation

- The probe must not import `App\Studio\*` or any Studio namespace
- The probe may import Platform Style Registry read interfaces and Shell catalog discovery utilities only if those utilities are themselves read-only
- The probe must not depend on `ResolvedStyleConsumer` implementation — it validates readiness for the future consumer, not runtime behavior

## Part H — Relationship To ResolvedStyleConsumer

The probe is **not** the consumer.

```
Read-Only Consumption Probe  →  validates readiness
ResolvedStyleConsumer        →  future runtime consumer (not yet implemented)
Shell Runtime                →  unchanged in this phase
```

The probe validates that the future `ResolvedStyleConsumer` **can** read approved values safely. It shares diagnostic language (severity levels, socket key vocabulary) with the consumer contract but must not become a runtime dependency.

Rules:

- Shell must not call the probe at runtime
- The probe must not call `ResolvedStyleConsumer` (which may not exist yet)
- The probe must not be wired into header.php, footer.php, or any web request path
- Diagnostic codes may reuse the `RSC-` prefix family for consistency but probe codes (`RSC-P*`, `RSC-W*`, `RSC-F*`, `RSC-E*`) are distinct from consumer runtime diagnostics

## Part I — Relationship To Shell

Shell remains unchanged.

The probe only reads Shell socket catalog metadata from `apps/Shell/Style/Resources/socket-catalog/`. It does not:

- Modify Shell CSS (`apps/Shell/styles/*.css`)
- Change Shell runtime behavior
- Inject CSS variables into rendered pages
- Read or write Shell runtime state
- Connect Shell to Platform Style Registry

Shell socket catalog files define what **could** be customized. The probe compares catalog socket keys against approved registry values to report alignment gaps. This is metadata inspection only.

## Part J — Relationship To Visual Customizer

The probe may read values produced by Visual Customizer Apply — persisted approved registry entries written through the governed apply workflow.

The probe must not:

```
read drafts
read pending approvals
approve anything
apply anything
```

Visual Customizer owns drafting, validation, approval, snapshot, and apply governance. The probe reads only the **output** of a successful Apply (approved registry values). It must not access Studio draft files, approval request records, or pending workflow state.

## Part K — Explicit Non-Goals

No:

```
runtime style application
Shell consumption
theme mutation
registry mutation
CSS generation
theme compilation
CTE governance migration
Visual Customizer feature expansion
Special Effects tooling
```

This contract does not authorize:

- Wiring Shell runtime to Platform Style Registry
- Implementing `ResolvedStyleConsumer`
- Creating the probe script
- Adding architecture gate invariants (deferred to next slice)
- Modifying theme source, compiled theme.css, Shell CSS, or published assets
- Expanding Visual Customizer socket coverage
- Special Effects tooling or registry integration

## Part L — Future Implementation Plan

### Recommended implementation location

```
scripts/platform/probe_resolved_style_consumer.php
```

Do **not** create this file in this slice. The contract must be approved and boundary gate invariants defined before implementation.

### Recommended boundary invariants (for next slice)

Before implementing the probe, add architecture gate invariants:

```
probe script must not import Studio
probe script must not call compiler
probe script must not write registry/theme/Shell files
probe script must not register routes
```

Additional invariants recommended for the boundary gate prep slice:

```
probe script must exist at scripts/platform/probe_resolved_style_consumer.php
probe script must emit JSON diagnostic report
probe script must use read-only filesystem access only
probe script must enforce path confinement
probe script must not call exec/shell_exec/system/passthru
probe script must not open database connections
```

### Future CLI usage (illustrative, not implemented)

```bash
php scripts/platform/probe_resolved_style_consumer.php
php scripts/platform/probe_resolved_style_consumer.php --socket=radius.scale
php scripts/platform/probe_resolved_style_consumer.php --mode=json-only
```

## Part M — Cross References

This contract builds on and must remain consistent with:

- [ResolvedStyleConsumer Contract](resolved-style-consumer-contract.md) — Part J defines the probe question; this contract formalizes probe scope, diagnostics, and safety
- [Customization Studio Operating Contract](customization-studio-operating-contract.md) — lifecycle layers, Visual Customizer Apply output, governance boundaries
- [Style Registry Ownership Contract](style-registry-ownership-contract.md) — Platform registry ownership and approved value storage
- [Shell Style Socket Contract](shell-style-socket-contract.md) — catalog socket definitions the probe aligns against
- [Style Customization Chain Checkpoint](style-customization-chain-checkpoint.md) — chain completeness and Phase 2B readiness status

## Deliverable Summary

| # | Item | Value |
|---|---|---|
| 1 | Contract path | `docs/architecture/read-only-consumption-probe-contract.md` |
| 2 | Probe purpose | Diagnose registry visibility, verify approved value shape, verify socket catalog alignment, verify Shell inspectability, prove no runtime mutation |
| 3 | Allowed reads | Approved registry values, Shell socket catalog metadata, active theme metadata |
| 4 | Forbidden actions | Write registry, compile themes, modify CSS, apply values, create drafts, approve requests, call Studio, register routes |
| 5 | Diagnostics model | PASS/WARN/FAIL/ERROR; codes RSC-P001 through RSC-E002 |
| 6 | Exit code model | 0 = PASS/WARN only; 1 = FAIL present; 2 = ERROR present |
| 7 | Future implementation location | `scripts/platform/probe_resolved_style_consumer.php` (not created in this slice) |
| 8 | Boundary invariants recommended | No Studio imports, no compiler calls, no file writes, no route registration |
| 9 | Validation proof | See session validation in AGENT-COMPLIANCE-CHECKLIST.md |
| 10 | Recommended next slice | Read-Only Consumption Probe Boundary Gate Prep |

### Recommended next slice

```
Read-Only Consumption Probe Implementation
```

Implement `scripts/platform/probe_resolved_style_consumer.php` per this contract. Gate prep is complete at `scripts/architecture/check_read_only_consumption_probe_boundaries.sh`.
