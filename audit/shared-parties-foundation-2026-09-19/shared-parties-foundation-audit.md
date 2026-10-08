# Shared Parties Foundation — Architecture Audit

Date: 2026-09-19
Branch: main
HEAD: `0fae8e2ba91e9654e6501f74e193f85b282f6a23`
Scope: Shared Parties implementation only. Manufacturing, Shared Items, and Hospitality schema changes excluded.

---

## Executive Conclusion

Shared Parties is **runtime-enabled but not actually adopted**. It is registered in the app catalog (`apps/Parties/manifest.json`) with `shared_contract.consumers = ["hospitality","sbaio","procurement"]`, and a `PartyService` with create/getById/searchCandidates/updateCanonical lives at `apps/Parties/Services/PartyService.php`. However, no genuine cross-domain consumer references Shared Parties at runtime: Hospitality explicitly guards against dependency on Parties, SBAIO uses a local customer identity, and Manufacturing uses opaque text supplier fields. Zero `party_id` FKs exist in any consumer table. The parties migration (`apps/Parties/migrations/001_create_parties.sql`) creates the canonical table, but no consumer table references it.

**Classification:** active-but-unadopted foundation (not a collision; not yet generic).

---

## A. Physical inventory

Files/classes/services:

| Path | Role |
|---|---|
| `apps/Parties/manifest.json` | App registration; `shared_contract.consumers: [hospitality,sbaio,procurement]` |
| `apps/Parties/routes.php` | Inert entry (standalone UI absent; comment says "No worker-facing UI in v1") |
| `apps/Parties/Services/PartyService.php` | `create`, `getById`, `searchCandidates` (read-only OR), `updateCanonical` |
| `apps/Parties/migrations/001_create_parties.sql` | `parties` table: `id`, `party_type`, `display_name`, `email`, `phone`, timestamps |
| `apps/Parties/Tests/probe_parties_service.php` | 28/28 pass, static/unit |
| `docs/shared-parties-foundation/SESSION_B_SYNTHESIS.md` | "Only Hospitality Guest adoption (party_ref link) demonstrated" |
| `docs/discussions/parties-consumer-role-adoption-design.md` | Adoption investigation |
| `docs/architecture/parties-data-model.md` | Canonical data model (referenced) |
| `scripts/architecture/check_parties_contract.sh` | Gate: rejects premature `party_id` outside Parties |

Persistence/schema: `parties` table (id BIGINT, party_type, display_name, email, phone). No consumer FKs. No `shared_parties` table.

Routes/APIs: none (inert routes.php).

Loader/service registration: `composer.json` PSR-4 maps `Apps\` → `apps/` — `Apps\Parties` loads. `manifest.json` is the app catalog entry.

Tests: probe exists; 28/28 pass.

Compatibility layers: none — parties is autonomous.

---

## B. Runtime reachability

Evidence:

- `composer.json` `"Apps\\": "apps/"` autoload covers `Apps\Parties\Services\PartyService`.
- `apps/Parties/manifest.json` registers `id: "parties"`, `entry: "routes.php"`, `migrations_path: "migrations"`, `type: "business"`.
- `apps/Parties/routes.php` exists as app runtime entry (commented skeleton).
- `apps/Parties/Services/PartyService.php` is concrete PHP with `use App\Core\DB`.
- `apps/Parties/migrations/001_create_parties.sql` is the canonical schema file.
- Probe: `php apps/Parties/Tests/probe_parties_service.php` → 28/28 PASS (static/unit; no DB harness).

Runtime consumers that actually call PartyService: **none found** in `apps/`, `platform/`, `plugins/`. Grep for `PartyService` across runtime code returns zero hits outside the app itself and its test.

No hooks/events registered from Parties. No CLI/background execution for parties.

---

## C. Genuine consumer inventory

| Reference | Classification |
|---|---|
| `apps/Hospitality/AGENTS.md` — "Do NOT depend on shared Billing, Items, Inventory, Parties, Accounting, or `apps/Procurement`" | **guardrail / explicit prohibition** |
| `apps/Hospitality/modules/Guests/plugin.json` — "local stand-in until a shared Parties app is approved" | **compatibility stand-in; not adoption** |
| `apps/Hospitality/Services/GuestsService.php` — docblock "local stand-in until a shared Parties app is explicitly approved" | **compatibility stand-in** |
| `docs/discussions/parties-consumer-role-adoption-design.md` — "Hospitality does not currently consume Parties: zero party_id matches" | **documentation / planning** |
| `docs/shared-parties-foundation/SESSION_B_SYNTHESIS.md` — "Only Hospitality Guest adoption (party_ref link) demonstrated. No Supplier/Customer/Guest/Mem cross-domain consumption proven." | **planning artifact** |
| `scripts/architecture/check_parties_contract.sh` — "No premature party_id outside Parties" | **gate / guardrail** |
| `apps/Hospitality/Tests/probe_slice7_frontdesk_folio.php` — "no shared Billing/Procurement/Parties/Inventory references" | **test guardrail** |
| `apps/SBAIO/modules/Customers/Controllers/CustomersController.php` / `Services/CustomersService.php` | **local customer identity; no Party reference** |
| `apps/Manufacturing` producers/suppliers text fields | **field-level text; no Party reference** |

---

## D. Domain adoption matrix

| Domain | Persists Party ID? | Calls Parties runtime? | Local identity authoritative? | Sync required/implemented? |
|---|---|---|---|---|
| Hospitality Guests | No (local `hosp_guests.id`) | No (explicitly prohibited) | Yes | No |
| SBAIO Customers | No (local `sbaio_customers.id`) | No | Yes | No |
| Manufacturing suppliers | No (text `producer`/`default_supplier`) | No | Yes | No |
| Procurement | Not referenced in runtime code | No | N/A | No |

No domain genuinely shares identity with Parties. Hospitality's "party_ref link" is documented as demonstrated only in SESSION B SYNTHESIS context (planning evidence), not in runtime code.

---

## E. Ownership and truth

- **Party identity owner**: `apps/Parties` (canonical `parties` table, PartyService).
- **Domain operational truth owners**: Hospitality (guest status/contacts), SBAIO (customer status/contacts), Manufacturing (producer/supplier business rules).
- **Canonical cross-domain identity**: declared in `manifest.json` `shared_contract`, but **not demonstrated at runtime** — no consumer table has a `party_id` FK.
- **Multiple independent domains sharing same identity model**: **No** — each domain retains local identity; no collision, no sharing.
- **Parties owns canonical identity only, or operational lifecycle/state?**: Parties owns canonical identity fields only (display_name, party_type, email, phone). No role state, no workflow.

---

## F. Canonicality pressure test

- **Genuine runtime consumer count**: 0.
- **Independent domain consumer count**: 0.
- **Demonstrated second consumer**: No — zero consumers demonstrated.
- **Generic across guest/customer/supplier roles?**: Schema is role-agnostic (person/organization type + display_name). Not yet proven generic in production.
- **Role/lifecycle semantics separated from shared identity?**: Yes — Parties intentionally has no role fields; domain roles stay local.
- **Making Parties canonical today justified?**: **No** — no runtime adoption, no consumer FK, zero genuine consumers. Premature.

---

## G. Guardrails / unresolved design boundaries

- `apps/Hospitality/AGENTS.md`: no dependency on Parties.
- `scripts/architecture/check_parties_contract.sh`: rejects premature `party_id` outside Parties.
- `docs/discussions/parties-consumer-role-adoption-design.md`: party_id must not be added to Hospitality yet; dependency on Parties not authorized; migration/backfill not authorized.
- `docs/shared-parties-foundation/SESSION_B_SYNTHESIS.md`: not promoted to Shared Foundation until second consuming domain verifies identity agreement.
- No `party_id` anywhere in consumer schemas (verified by grep).
- Parties `manifest.json` declares `shared_contract.consumers`, but no consumer actually depends on it at runtime.

Unresolved:
- Which consumer goes first? Hospitality guest has partial planning evidence only.
- Does `party_id` land as FK in consumer role table or as reference in consumer local table? (documented direction, non-binding sketch)
- Orchestrated transition / dual-write / cutover not described beyond additive FK sketch.

---

## H. Blockers

| # | Blocker |
|---|---|
| 1 | Zero runtime consumers |
| 2 | No `party_id` FK in any consumer table |
| 3 | Hospitality explicitly prohibits Parties dependency |
| 4 | No orchestrated transition plan authorized |
| 5 | Only one domain (Hospitality) has partial adoption evidence |
| 6 | Parties `routes.php` inert; no standalone UI |
| 7 | Duplicate display_name/email/phone permitted (no UNIQUE) — identity uniqueness not enforced |
| 8 | No `findOrCreate()` per Slice 3 — caller must explicitly choose reuse |

---

## I. Evidence-supported next decision

**Do not promote Parties to canonical foundation yet.** The evidence supports retaining current classification: registered-but-unadopted skeleton with zero genuine consumers. Next decision boundary: obtain explicit approval for (a) which consumer adopts first, (b) `party_id` placement (FK vs reference), (c) migration/backfill authorization, and (d) orchestrated transition plan. Parties code itself must not change until that decision is made.

---

*Audit complete. No changes were made to any code, schema, configuration, or working-tree state.*
