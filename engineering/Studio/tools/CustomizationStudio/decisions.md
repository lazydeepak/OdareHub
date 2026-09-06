# Studio/tools/CustomizationStudio — Decisions

## Decision Record Format

### YYYY-MM-DD — Decision title

**Decision**

**Reason**

**Impact**

**Revisit when**

## Recorded Decisions

### Customization Studio consolidation

**Decision**

Customization-theme-style-effects tooling is consolidated under `apps/Studio/Tools/CustomizationStudio/` as tool subgroups.

**Reason**

The tools share governance, style ownership, and route ownership concerns.

**Impact**

Legacy standalone route paths redirect to canonical customization-studio paths.

**Revisit when**

A tool needs independent ownership or runtime boundaries.

### Theme/Style/Effects governance separation

**Decision**

Theme Doctor and Special Effects are read-only; Design Token Editor may write source theme files; Style Compliance remains read-only unless guarded repair is explicitly enabled.

**Reason**

Each tool has a different mutation risk profile.

**Impact**

Manifest capability flags remain the source of truth for write eligibility.

**Revisit when**

Guarded repair capability is enabled in the consolidated manifest.

### Guarded repair direction

**Decision**

Repair execution is server-resolved-only: browser submits proposal id and CSRF, while the server resolves trusted scan evidence and rechecks readiness.

**Reason**

Browser-supplied paths, selectors, or values would be unsafe.

**Impact**

Repair work requires fresh server-side evidence and post-apply validation.

**Revisit when**

Runtime repair execution is enabled for broader owners.

### Manifest capability flag not yet enabled

**Decision**

Do not treat guarded repair as enabled until the consolidated manifest explicitly says so.

**Reason**

Code exists, but capability state must match the active manifest.

**Impact**

The repair engine remains unavailable as executable user capability until manifest verification.

**Revisit when**

Manifest capability alignment is requested.

### Foundation rendering concerns

**Decision**

Foundation rendering detection stays inside the Style Compliance scanner as an independent concern.

**Reason**

It keeps compliance evidence in one tool without making it part of per-declaration token logic.

**Impact**

The scanner can report table overflow concerns separately from token findings.

**Revisit when**

Foundation diagnostics move to a dedicated Shell/Foundation tool.

### Special Effects — read-only status

**Decision**

Special Effects is read-only in its current phase.

**Reason**

Appearance observation and readiness can be inspected without mutating preferences or CSS assets.

**Impact**

No POST routes, preference saves, theme apply, CSS writes, compiled asset edits, or source migrations exist.

**Revisit when**

Special Effects receives an approved mutation contract.

### 2026-07-18 — Manifest-driven capability inventory

**Decision**

Customization Studio capability presentation and future orchestration must derive from registered subtool manifests through `CustomizationStudioCapabilityInventory`.

Do not maintain a second hardcoded capability matrix when the manifest already declares the relevant state.

**Reason**

The existing workspace contained misleading status text and a placeholder tool that advertised capabilities it did not implement. A manifest-driven inventory reduces drift, makes capability truth inspectable, and creates a reusable discovery boundary for larger Studio workflows.

**Impact**

- The landing workspace can truthfully distinguish inspection, preview, draft, validation, approval, apply, snapshot, rollback, source writes, registry writes, runtime impact, status, and blockers.
- Placeholder and disabled tools must advertise non-executable capability state.
- The inventory remains read-only and does not execute tool actions.
- Operational algorithms remain in their existing services until they are deliberately extracted or adapted into shared capabilities.
- Future App Creator or Appearance Studio orchestration may query this inventory instead of scraping tool pages.

**Revisit when**

A capability cannot be represented truthfully by manifest data alone, or when executable shared capability contracts are introduced.

### 2026-07-19 — Shared scaffolding fills-in over new service creation

**Decision**

Fill in existing empty shared scaffolding stubs (`Shared/Services/CssPathResolver`, `OwnerResolver`, `StyleDiffService`, `StyleValidationService`) rather than creating new parallel services. `StyleValidationService` is the single facade; its public methods delegate to existing production services instead of duplicating their logic.

**Reason**

Creating new parallel services for socket discovery, token exploration, and value validation would duplicate proven algorithms and introduce drift risk. The existing stubs already follow the desired `Shared/` contract, and the existing services (`VisualCustomizerMetadataService`, `TokenImpactDiscoveryService`, `CssTokenEditorSaveService`) are already tested and stable. Adapters are cheaper than replacements.

**Impact**

- `CssTokenEditorSaveService::validateValue()` is the sole source of value validation — shared facade calls the same static method, not a copy.
- `StyleValidationService::readiness()` returns hardcoded `false` for all four runtime flags — same invariant as the Platform pipeline chain.
- The integration proof in `VisualCustomizerMetadataService::discover()` is a single array key addition; no new services are wired into any controller or route.
- Eight deferred scaffolding stubs (`StyleToolRouter`, `StyleSnapshotService` — Services; `StyleMutationContract`, `StyleSnapshotContract`, `StyleHandoffContract`, `StyleAuthorityContract`, `TokenOwnershipContract`, `StyleValidationContract` — Contracts) remain empty and are not modified.

**Revisit when**

A shared capability needs mutation, runtime consumption, or a new discovery algorithm that no existing production service provides.
