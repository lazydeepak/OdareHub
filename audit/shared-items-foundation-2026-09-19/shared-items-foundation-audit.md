# Shared Items / Item Foundation — Architecture Audit

Date: 2026-09-19
Branch: main
HEAD: `2961330` (docs(audit): finalize manufacturing decomposition audit)
Manufacturing decomposition closed at this commit — no Manufacturing changes were made.

**Scope**: `shared/Item/Foundation/` and direct repository consumers/references only.
**Method**: Read-only repository inspection, composer/autoload analysis, runtime-reachability sweep, consumer classification, identity/storage ownership analysis, canonicality pressure test, naming/ownership consistency check.
**Hard constraints honored**: No Manufacturing changes, no Shared Items code changes, no schema/migration changes, no autoload/loader changes, no `/tmp` store mutation, no Shared Parties changes, no extraction/promotion, no redesign.

---

## Executive Conclusion

**Shared Items / Item Foundation is an UNDEPLOYED COMPATIBILITY ARTIFACT combined with a TEST PROTOTYPE.**

It is **not** an active shared runtime foundation. It is **not** a deployed contract awaiting adoption by multiple domains. It is **not** a migration artifact in active use. It is a contract and skeleton implementation that was committed at `fdf47b9` as a "minimal Shared Items slice" but remains entirely unused in production runtime.

The architecture documentation (`docs/shared-items-foundation/canonical-item-contract.md`, line 10-11) explicitly states: *"no `shared_items` DB table was created (identity store remains test/verification JSON until a runtime consumer lands)."*

| Dimension | Finding |
|---|---|
| Active shared runtime foundation | **No** |
| Contract/prototype awaiting adoption | **Partial** — exists but has zero adopters |
| Migration/compatibility artifact | **Yes** — `item_ref` columns are Manufacturing-opaque references, not Shared Items resolution points |
| Test/audit/planning artifact | **Yes** — probe is the only code consumer |
| Genuine runtime consumers | **0** |
| Second independent domain consumer | **No** |
| Classification | **Undeployed Compatibility Artifact + Test Prototype** |

---

## A. Physical Inventory

### A.1 Core Foundation Files (`shared/Item/Foundation/`)

| File | Purpose | Lines |
|---|---|---|
| `shared/Item/Foundation/Contracts/ItemIdentityContract.php` | Canonical identity contract interface | 69 |
| `shared/Item/Foundation/Services/ItemIdentityService.php` | Minimal canonical identity service (JSON store) | 172 |
| `shared/Item/Foundation/Tests/probe_item_identity.php` | Verification probe (19 assertions) | 120 |

**Total**: 3 files, 361 lines.

### A.2 Documentation (`docs/shared-items-foundation/`)

| File | Purpose |
|---|---|
| `docs/shared-items-foundation/canonical-item-contract.md` | Canonical contract (identity fields, exclusion rules, extension pattern, schema proposal, migration strategy, consumer contract) |
| `docs/shared-items-foundation/manufacturing-product-extension.md` | Manufacturing → Shared Item reference mapping descriptor |

### A.3 Related Schema Artifacts (Indirect Consumers of Contract Vocabulary)

| File | Purpose | Relationship |
|---|---|---|
| `apps/Manufacturing/modules/Products/migrations/012_add_item_ref.sql` | Adds `item_ref INT NULL` column to Manufacturing `products` table | Schema reference adoption per contract |
| `apps/Manufacturing/modules/Products/migrations/013_add_manufacturing_bom.sql` | Creates `manufacturing_bom` + `manufacturing_bom_line` with `finished_item_ref`/`component_item_ref` | BOM references canonical identity via opaque INT (no FK to Shared identity) |
| `apps/Manufacturing/modules/Bom/migrations/001_bom_contract.sql` | Bom module BOM contract (identical DDL) | References canonical contract doc; opaque INT references |

### A.4 Storage

| Path | Type | Purpose |
|---|---|---|
| `/tmp/shared-items-store.json` | JSON file (test/verification) | Default store for `ItemIdentityService`; NOT a production DB |
| `/tmp/shared-items-test-{unique}.json` | JSON file (probe-only) | Created and cleaned up by `probe_item_identity.php` |

### A.5 Identity Fields (Per Contract §3.1)

`item_id` (int), `code_ref` (string), `name` (string), `status` (enum: draft/active/deprecated/archived), `category_ref` (optional), `metadata_ref` (optional), `created_at`, `updated_at`.

### A.6 Explicit Exclusions (Per Contract §3.2)

Manufacturing-specific fields excluded from Shared Item identity: `model`, `producer`, `lead`, `notes`, `cycle_time`, `supply_model`, `qc_time_per_item`, `execution_planning_fields`, `processing_dispatch_mode`, `part_molds`, `machine_assignment`, `packaging_profile`, `stock_quantity`, `warehouse_id`, `price`, `cost`, `tax`.

---

## B. Runtime Reachability

### B.1 Composer/PSR-4 Autoload Registration

**Finding**: `Shared\Item\Foundation` is **NOT registered** in `composer.json` autoload.

Evidence — `composer.json` PSR-4 mapping:
```
App\           => app/
Apps\          => apps/
Platform\      => platform/
Plugins\Workflow\Services\ => apps/Manufacturing/modules/Workflow/Services/
```
**No `Shared\` namespace exists** in `composer.json` PSR-4, `composer.lock`, or `vendor/composer/autoload_psr4.php`.

### B.2 Manual `require`/`include`

**Finding**: Only the probe test itself uses `require_once` for the foundation files.

Evidence — `shared/Item/Foundation/Tests/probe_item_identity.php`:
- Line 10: `require_once APP_ROOT . '/shared/Item/Foundation/Contracts/ItemIdentityContract.php'`
- Line 11: `require_once APP_ROOT . '/shared/Item/Foundation/Services/ItemIdentityService.php'`

No other PHP file in the repository requires or includes these files.

### B.3 Loader/Bootstrap/Container Registration

**Finding**: No registration found in any bootstrap, container, service provider, or dependency injection configuration.

Evidence: `grep -rn "Shared\\\\Item\\\\Foundation" --include='*.php' --include='*.json' --include='*.xml' --include='*.yml' --include='*.yaml' --include='*.sh' .` across `apps/`, `app/`, `platform/`, `public/`, `plugins/`, `packages/`, `scripts/`, `tests/` — **0 matches outside of the source files themselves and the probe**.

### B.4 Route/API/Hook/Event Registration

**Finding**: No routes, API endpoints, event hooks, or listeners reference Shared Items components.

### B.5 CLI/Background Consumers

**Finding**: No CLI scripts or background workers reference Shared Items components.

### B.6 Summary: Runtime Reachability

| Reachability Path | Status | Evidence |
|---|---|---|
| Composer autoload | ❌ **Not registered** | No `Shared\` in composer.json PSR-4 |
| Manual requires | ⚠️ **Probe only** | Only probe uses `require_once` |
| Loader/bootstrap | ❌ **Not registered** | No bootstrap references |
| Service/container | ❌ **Not registered** | No container config |
| Route/API | ❌ **Not registered** | No route references |
| Hooks/events | ❌ **Not registered** | No hook references |
| CLI/background | ❌ **Not registered** | No CLI references |

**Conclusion**: `Shared\Item\Foundation` is **unreachable through normal application runtime**. The only code execution path is the standalone probe script (`php shared/Item/Foundation/Tests/probe_item_identity.php`), which is a manually invoked CLI script.

---

## C. Consumer Inventory & Classification

### C.1 Genuine Runtime Consumers: **0**

Evidence — comprehensive grep across all production PHP code:
```
grep -rn "Shared\\\\Item\\\\Foundation" --include='*.php' apps/ app/ platform/ public/ plugins/ packages/ scripts/
→ 0 matches (outside of source files themselves)
```

```
grep -rn "ItemIdentityContract\|ItemIdentityService" --include='*.php' apps/ app/ platform/ public/
→ 0 matches (outside of shared/Item/Foundation/ source)
```

### C.2 Schema/Reference-Only Dependencies: **2 SQL migrations**

These reference `item_ref` vocabulary from the contract but do **not** call Shared Items runtime services:

| Migration | Nature | Details |
|---|---|---|
| `apps/Manufacturing/modules/Products/migrations/012_add_item_ref.sql` | Schema reference | Adds opaque `item_ref INT NULL` column to Manufacturing `products` table |
| `apps/Manufacturing/modules/Bom/migrations/001_bom_contract.sql` | Schema reference | BOM with `finished_item_ref`/`component_item_ref` as opaque INTs; doc comment references canonical contract; explicitly "no FK to Shared identity" |
| `apps/Manufacturing/modules/Products/migrations/013_add_manufacturing_bom.sql` | Schema reference | Manufacturing-owned BOM referencing canonical identity via opaque INTs |

**Classification**: These are schema-level vocabulary alignments, not runtime dependencies on Shared Items services.

### C.3 Contract/Docblock/Reference Vocabulary Only

| File | Nature |
|---|---|
| `docs/shared-items-foundation/canonical-item-contract.md` | Contract specification |
| `docs/shared-items-foundation/manufacturing-product-extension.md` | Reference mapping descriptor |
| `docs/architecture/odarehub-future-architecture-pressure-test-results.md` | Pressure test documenting zero consumers |
| `AGENTS.md` (Session Summary 2026-09-12) | Session record |
| `AGENT-COMPLIANCE-CHECKLIST.md` | Compliance tracking |
| `apps/Manufacturing/modules/Bom/BomService.php` (line 5-6) | Docblock comment: `(docs/shared-items-foundation/canonical-item-contract.md); no FK to Shared identity` |

### C.4 Test/Audit/Planning Artifacts

| File | Nature |
|---|---|
| `shared/Item/Foundation/Tests/probe_item_identity.php` | Verification probe (19/19 PASS); standalone CLI script |
| `docs/architecture/odarehub-future-architecture-pressure-test-results.md` | Pressure test documenting zero consumers and disproving canonical assumptions |

### C.5 Compatibility/Migration Artifacts

The `item_ref` columns in Manufacturing `products` and BOM tables serve as opaque Manufacturing-local integer references. They are validated against Manufacturing's own `products` table, not resolved through Shared Items services. These are **compatibility references** in the schema layer, not runtime dependencies.

### C.6 Consumer Matrix Summary

| Category | Count | Details |
|---|---|---|
| Runtime code dependencies | **0** | No production code imports or calls Shared Items services |
| Schema/reference-only dependencies | **3 SQL migrations** | `012_add_item_ref.sql`, `013_add_manufacturing_bom.sql`, `001_bom_contract.sql` — all use opaque INT references |
| Contract/docblock/reference vocabulary | **6+** | Contract docs, pressure test docs, AGENTS.md, AGENT-COMPLIANCE-CHECKLIST.md, BomService docblock |
| Test/audit/planning artifacts | **2** | Probe test, pressure test results |
| Compatibility/migration artifacts | **3 SQL migrations** | Schema-level `item_ref` columns as opaque references |

**Genuine runtime consumers: 0**

---

## D. Identity and Storage Ownership

### D.1 What an Item Identity Consists Of

Per canonical contract §3.1: `item_id` (int, auto-increment), `code_ref` (string, unique), `name` (string), `status` (enum), `category_ref` (optional), `metadata_ref` (optional), `created_at`, `updated_at`.

### D.2 Who Allocates IDs

`ItemIdentityService::create()` (line 104-107): allocates `maxId + 1` by scanning existing records in the JSON store. This is a **non-atomic, file-based** counter — not suitable for concurrent production use.

### D.3 Where Identity is Persisted

| Location | Type | Purpose |
|---|---|---|
| `/tmp/shared-items-store.json` | JSON file | Default store; configurable via `SHARED_ITEMS_STORE` env var |
| `/tmp/shared-items-test-{unique}.json` | JSON file | Probe-only; created and cleaned up |

**Not persisted** in any database. No `shared_items` table exists in the schema.

### D.4 `/tmp/shared-items-store.json` Classification

**Classification: TEST/VERIFICATION ONLY (accidental runtime storage if invoked outside probe)**

Evidence — `ItemIdentityService.php` line 21: `$this->storePath = $storePath ?? (getenv('SHARED_ITEMS_STORE') ?: '/tmp/shared-items-store.json');`

The default is `/tmp/shared-items-store.json` which is:
- Ephemeral (lost on restart/reboot)
- Not on a shared/volume filesystem
- Not part of any deployment artifact
- Only used when `SHARED_ITEMS_STORE` env var is unset AND the service is instantiated (which never happens in production since there are no runtime consumers)

The probe uses its own isolated `/tmp/shared-items-test-{unique}.json` and cleans up after itself (line 115: `@unlink($store)`).

### D.5 Domain Duplication of Identity

**Manufacturing maintains its OWN identity and does NOT duplicate Shared Items identity.**

Evidence:
- Manufacturing `products` table has `id` (auto-increment INT), `parts_number` (business key), `parts_name` (display name) — all Manufacturing-owned
- `PartItemRefService.php` (lines 11-18, 40-60): manages `item_ref` as an opaque integer assigned/cleared via `Plugins\Products\Services\PartItemRefService` — validates uniqueness against Manufacturing's own `products` table only
- `BomService.php` (lines 11-20, docblock): `finished_item_ref`/`component_item_ref` are "opaque Manufacturing-local integer references" validated within Manufacturing's own operational boundary — "No cross-domain identity registry is consulted at runtime"
- The contract doc itself confirms (§3.4): Manufacturing Product "continues to own its `products` table"

### D.6 Shared Items Owns Operational Item Truth?

**No.** Shared Items Foundation owns only cross-domain identity **in theory** — it has no runtime authority. Manufacturing owns all operational Product truth (schema, lifecycle, extension data). BOM references resolve against Manufacturing's own `products` table via `item_ref` join, not against any Shared Items service.

---

## E. Canonicality Pressure Test

### E.1 How Many Independent Domain Consumers Actually Exist

**Answer: 0 genuine runtime consumers.**

Evidence:
```bash
# Production code import sweep (excluding tests/probes/docs):
grep -rn "Shared\\\\Item\\\\Foundation" --include='*.php' apps/ app/ platform/ public/ plugins/ packages/
→ 0 matches

# Service method call sweep:
grep -rn "ItemIdentityContract\|ItemIdentityService" --include='*.php' apps/ app/ platform/ public/
→ 0 matches (outside shared/Item/Foundation/ source)
```

### E.2 Is There a Demonstrated Second Consumer Beyond Manufacturing Schema Vocabulary?

**Answer: No.**

Manufacturing is the only domain that uses `item_ref` vocabulary, and even Manufacturing's usage is purely as an opaque integer column on its own tables — NOT as a runtime reference to Shared Items services. The BOM service explicitly states it does NOT consult any cross-domain identity registry.

The pressure test documentation (`docs/architecture/odarehub-future-architecture-pressure-test-results.md`, line 177) confirms: **"Item Ref (Manufacturing) | PROTOTYPE / MIGRATION AID | shared/Item/Foundation/ code exists... 0 runtime imports; Manufacturing `item_ref` validated against own `products` table; BOM probe asserts no Shared\ namespace"**

### E.3 Is the Current Contract Generic Enough for Multiple Suites Without Manufacturing Assumptions?

**Answer: The contract IS generically worded, but it is TAILORED to Manufacturing's context in practice.**

Evidence:
- The contract (§3.2) lists Manufacturing-specific exclusions (`model`, `producer`, `lead`, etc.) — showing awareness of Manufacturing's domain
- The `itemRef()` method (contract §3.3) explicitly references "Manufacturing Product → Shared Item" relationship
- The service implementation only has a generic JSON store and no extension point for non-Manufacturing consumers
- The contract doc (§9) defers all other sessions (B, C, D) as future work — they have no adopted consumers

### E.4 Are Lifecycle/Mutation Semantics Defined?

**Answer: Yes, in the contract.**

The contract defines: `create()` (with forbidden-field validation), `update()` (with allowed-field restrictions), `deactivate()`, `activate()`, `list()`, `getById()`, `getByCode()`, `itemRef()`.

However, these semantics are **only exercised by the probe test** — no production code calls any of them.

### E.5 Would Making It Canonical Today Reduce Coupling?

**Answer: No — it would merely promote an unfinished abstraction.**

Evidence:
1. **No runtime need**: Manufacturing's `item_ref` already works as an opaque integer with no dependency on Shared Items resolution
2. **No consumer benefit**: No domain is waiting for Shared Items to be canonical
3. **Added complexity**: Promoting to canonical would require autoload registration, a real database, production-grade storage, and service discovery — none of which have business justification
4. **Pressure test verdict** (line 92): "A reference seam is designed (contract document, adapter pattern in contract section 3.3) but not justified. No second consumer adopts Shared Items at runtime. Manufacturing maintains opaque references self-sufficiently. The code is a prototype/skeleton, not an active shared foundation."
5. **Contract doc line 211-212**: "Shared Items must be independently useful (not tied to Manufacturing)" — yet no independent use is demonstrated

---

## F. Naming/Ownership Consistency

### F.1 Directory Names vs. Namespace

| Aspect | Value | Consistent? |
|---|---|---|
| Directory | `shared/Item/Foundation/` | Suggests shared foundation |
| Namespace | `Shared\Item\Foundation\Contracts` | ✅ Matches directory convention |
| Namespace | `Shared\Item\Foundation\Services` | ✅ Matches directory convention |
| Test file | `shared/Item/Foundation/Tests/probe_item_identity.php` | ✅ Consistent |

### F.2 Docs vs. Reality

| Claim | Reality | Match? |
|---|---|---|
| Contract title: "Shared Items Foundation — Canonical Item Identity Contract" | Implementation exists as contract + service + probe | ✅ Structural match |
| Contract §10: "Shared Items must be independently useful (not tied to Manufacturing)" | Zero independent consumers; all vocabulary is Manufacturing-local | ❌ **Reality contradicts intent** |
| Contract doc line 7-8: "COMMITTED MINIMAL SLICE" | Committed at `fdf47b9` but unreachable at runtime | ⚠️ Partially accurate (committed but not deployed) |
| `ItemIdentityService.php` docblock line 12-13: "Storage: isolated JSON file (test/verification) — NOT production DB; No consumer adoption required" | Accurate description | ✅ Self-aware |
| Contract doc line 11: "no `shared_items` DB table was created" | No `shared_items` table in any migration | ✅ Accurate |

### F.3 Ownership Agreements and Disagreements

| Claim | Source | Issue |
|---|---|---|
| "Shared Items: owned by Shared/Foundation" | Contract §3.4 | No autoload, no service registration — no runtime ownership mechanism exists |
| "mutation authority is shared, not Manufacturing" | Contract §3.4 | No runtime path exists for mutation |
| Directory uses `shared/` (lowercase) | Filesystem | Namespace uses `Shared\` (uppercase) — conventional PSR-4 mapping; not a functional mismatch |
| `Shared\Item\Foundation` NOT in composer.json autoload | `composer.json` | Directory structure claims foundation but autoload does not recognize it |

### F.4 Naming Mismatches Summary

1. **Directory says "Shared Foundation" but autoload ignores it** — `shared/Item/Foundation/` exists as a directory with `Shared\Item\Foundation\` namespace, but `composer.json` has no `Shared\` PSR-4 mapping, making the namespace invisible to the autoloader.
2. **Contract says "canonical" but implementation is a skeleton** — The contract doc calls it a "Canonical Item Identity Contract" but the implementation uses file-based JSON storage with non-atomic ID allocation.
3. **Contract says "independently useful" but there are no independent users** — The contract explicitly requires Shared Items to be "independently useful (not tied to Manufacturing)" yet all vocabulary and schema references are Manufacturing-local.
4. **"Shared" in path vs. "Plugins\Products" in Manufacturing code** — Manufacturing's `PartItemRefService` uses namespace `Plugins\Products\Services` and manages `item_ref` — there is no `Shared\` namespace usage in Manufacturing code.

---

## G. Blockers

| # | Blocker | Type | Impact |
|---|---|---|---|
| 1 | **Zero autoload registration** | Infrastructure | `Shared\Item\Foundation` classes are invisible to Composer autoloader; any runtime use requires manual `require_once` |
| 2 | **Zero runtime consumers** | Demand | No domain has adopted the foundation; no business justification for deployment |
| 3 | **Test-only storage** | Persistence | `/tmp/shared-items-store.json` is ephemeral, not shared, not durable; unsuitable for production |
| 4 | **Non-atomic ID allocation** | Correctness | `maxId + 1` scan is unsafe for concurrent access; no locking mechanism |
| 5 | **No service/container registration** | Discovery | No framework integration exists; services cannot be resolved via DI |
| 6 | **No `shared_items` table** | Schema | No database persistence; only JSON file storage |
| 7 | **Contract intent vs. reality** | Governance | Contract claims independence from Manufacturing but all vocabulary is Manufacturing-local |

---

## H. Recommended Next Decision

Based on evidence alone (no implementation recommended):

The decision boundary is between three evidence-supported options:

1. **Retain as dormant prototype**: Keep the code committed but unreachable; document that it awaits a runtime consumer. Suitable if a future session plans to adopt Shared Items with a real consumer (e.g., Shared Inventory Session C, Shared Commercial Session D). This is the **lowest-effort** option and aligns with the existing commit.

2. **Archive/deprecate**: If no consumer is planned within the next development cycle, the skeleton should be archived (move to `archive/` or similar) since it adds zero value in its current state and the naming/ownership mismatch creates confusion.

3. **Promote with conditions**: If AND only IF a concrete runtime consumer is approved (e.g., Shared Inventory Session C), THEN register autoload, create `shared_items` table, implement durable storage, and add service/container registration. This requires explicit approval and is **not** supported by current evidence since zero consumers exist.

**Evidence supports Option 1 as the default**: The code is committed (at `fdf47b9`), passes its own probe (19/19), and the contract doc states it awaits a runtime consumer. Options 2 and 3 require decisions beyond the audit scope.

---

## Verification

### Evidence Searches

**Consumer count verification (0 runtime consumers):**
```bash
# 1. PHP import sweep across all production code:
grep -rn "Shared\\\\Item\\\\Foundation" --include='*.php' apps/ app/ platform/ public/ plugins/ packages/
→ 0 matches (only within shared/Item/Foundation/ source files themselves)

# 2. Service method call sweep:
grep -rn "ItemIdentityContract\|ItemIdentityService" --include='*.php' apps/ app/ platform/ public/
→ 0 matches (outside shared/Item/Foundation/ source)

# 3. Namespace autoload check:
grep "Shared" composer.json
→ (empty — no Shared namespace registered)

grep "Shared" composer.lock | head -5
→ (empty — no Shared namespace in lock file)
```

**Runtime reachability verification:**
```bash
# 4. Manual require check (outside probe):
grep -rn "require.*shared.*Item\|require.*Foundation.*Contracts\|require.*Foundation.*Services" --include='*.php' .
→ Only matches probe_item_identity.php itself

# 5. Probe runs standalone:
php shared/Item/Foundation/Tests/probe_item_identity.php
→ PASS: 19/19
```

**Storage/identity verification:**
```bash
# 6. No shared_items table in migrations:
grep -rn "shared_items" --include='*.sql' .
→ 0 matches in committed SQL files (only in contract doc §4 as a proposal)

# 7. item_ref usage in Manufacturing is opaque:
grep -rn "no FK to Shared\|opaque.*reference\|opaque.*integer" apps/Manufacturing/
→ Confirms Manufacturing item_ref does NOT resolve through Shared Items
```

### Git Status

```
 M apps/Manufacturing/Services/Search/ManufacturingSearchProvider.php
 M storage/logs/app-lifecycle-dedupe.json
 M storage/logs/app-lifecycle.log
```

These 3 modifications are pre-existing, unrelated to this audit, and preserved untouched. No staging or commit was performed for this audit artifact.

### Git Diff Check
```
git diff --check
→ Clean (no whitespace errors in tracked modifications)
```

---

## Summary Table

| Question | Answer | Evidence |
|---|---|---|
| Classification | **Undeployed Compatibility Artifact + Test Prototype** | Zero consumers, test-only storage, no autoload |
| Active shared runtime foundation? | **No** | Composer autoload absent; no runtime imports |
| Contract/prototype awaiting adoption? | **Partial** | Contract exists; 0 adopters |
| Migration/compatibility artifact? | **Yes** | Schema `item_ref` columns are opaque Manufacturing-local references |
| Test/audit/planning artifact? | **Yes** | Probe is only code consumer; pressure test documents zero consumers |
| Genuine consumer count | **0** | Verified via grep across all production PHP |
| Second independent consumer? | **No** | All vocabulary is Manufacturing-local |
| Storage/identity ownership | **Test/verification JSON at `/tmp`** | No DB table; file-based; non-atomic |
| Would canonicalization reduce coupling? | **No** | Would promote unfinished abstraction; no demand exists |
| Commit SHA | **None** | Audit artifact not committed; pre-existing unrelated working tree preserved |

---

*Audit complete. No changes were made to any code, schema, configuration, or working-tree state.*
