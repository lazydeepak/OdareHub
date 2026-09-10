# Shared Commercial Foundation — Session D

Status: Foundation contract / readiness audit — read-only; no runtime change; no Manufacturing edit; no universal order master; no new loader.

Date: 2026-09-10
Base: 316b806 (validated OdareHub integration baseline)
Branch: work/shared-commercial-foundation
References: Session D durable (shared-app-suite-extension-contract.md @ c886b89); Session A (shared-items-foundation.md @ 2b19280); Session B/C (referenced conceptually).

Purpose: Define whether OdareHub requires reusable Shared Commercial foundation; select smallest shared primitives; reject universal transaction master where unrelated workflows differ.

---

## 1. Existing Commercial-Domain Inventory (316b806 audit evidence)

- Procurement (Domain App — undecided): apps/Procurement/; business workflow; module-owned.
- Manufacturing — supply/fulfillment (Supply L0 embedded Manufacturing Core; 3 files; service_only); dispatch (DispatchEntries 31 files, process_execution); production orders/plans (DailyOrders/PreOrders/ProductionEntries/ProductionPlans); supplier/vendor/default_supplier embedded in Products business_entity L3; Ledger (business_entity, repair/reconcile); no universal transaction/invoice/billing/pricing/tax module.
- SBAIO — sales/customer/payroll/staff/expense; no universal order/invoice master.
- Hospitality — folio/reservations/charges (module scope); not invoice/posting.
- No apps/Commercial/, apps/Order/, apps/Billing/, apps/Invoice/; no universal pricing / tax / discount / payment master.

---

## 2. Common-vs-Domain-Specific Analysis

Concept | Evidence | Classification | Reason
---|---|---|---
Order / Document identity | Procurement orders; Manufacturing orders/plans; SBAIO sales; Hospitality reservations | DOMAIN-OWNED | Different authorization, lifecycle, reporting
Order Line / Transaction line | Procurement supplier line; Manufacturing dispatch entry; SBAIO sales line | DOMAIN-OWNED | Different fields
Price / Pricing | Manufacturing embedded cost; SBAIO pricing; Hospitality folio | DOMAIN-OWNED | Base/sales/purchase/tax-inclusive differ
Quote / Quotation | Procurement / SBAIO domain | DOMAIN-OWNED | Domain-specific approval
Invoice / Billing | Manufacturing Ledger repair; SBAIO payroll; Hospitality folio (not invoice) | BILLING / ACCOUNTING — deferred (not Commercial) | Accounting boundary (rule 9)
Tax / Discount / Total | Jurisdiction-specific | DOMAIN-OWNED / ACCOUNTING — deferred |
Status / Lifecycle | Manufacturing-plan-approved; Procurement-approved; Sales-fulfilled | PARTIAL (draft/confirmed/cancelled only if adopted) | Full lifecycle stays domain
Counterparty | Manufacturing supplier/vendor embedded; SBAIO customer; Hospitality guest | PARTY-OWNED (Session B deferred) | Not Commercial
Product / Item reference | Manufacturing Products L3 | SHARED ITEM (Session A) — Commercial references via adapter | Not owned by Commercial
Fulfillment / Shipping / Dispatch | Manufacturing DispatchEntries | MANUFACTURING-OWNED / SALES-OWNED | Domain-specific routing

---

## 3. Model Evaluation (Rule 6 — Explicit)

Model A — Universal master with domain-specific extensions: REJECTED. Would force unrelated Procurement/Manufacturing/SBAIO/Hospitality workflows into one master with incompatible authorization/stage/reporting. No evidence supports.

Model B — Independent domain documents sharing only small primitives/contracts: SELECTED. Keeps domain autonomy; permits adapter-level document identity / counterparty / item reference / status vocabulary / line-value contracts when second consumer justifies; avoids artificial universal master.

Model C — Hybrid (shared identity/line/value with domain lifecycle/header): NOT SELECTED (deferred until adapter contracts prove useful for sustained second-consumer need).

---

## 4. Shared Primitives — Minimal Set (If Justified by Second Consumer)

Only if second consuming domain demonstrates sustained need (e.g., Procurement + SBAIO sales need minimal document reference adapter):

- Document identity adapter (`document_ref` — optional adapter reference; not universal master)
- Counterparty adapter (`party_ref` — optional; uses Session B when adopted)
- Item adapter (`item_ref` — optional; uses Session A when adopted)
- Status vocabulary adapter (`draft` / `confirmed` / `cancelled` — optional; adopted by consumer, not forced)
- Line/value contracts (quantity / unit / amount — optional read-only contracts; not universal fields)

Not universal master; no universal pricing; no universal tax/discount/total; no universal payment/reconciliation; no universal workflow status.

---

## 5. Dependency / Integration (From Session D Contract §2 / §5 / §6)

- Shared Commercial (future) → Core / Shell / Platform (allowed; generic contracts).
- Shared Commercial → Shared Items / Parties / Inventory (optional adapter only; must not take identity or data ownership).
- Manufacturing Supply (L0) / DispatchEntries / ProductionEntries / ProductionPlans / Products / Ledger: stay Manufacturing-owned; not promoted to Commercial.
- Manufacturing must not import Commercial business logic directly (§MANUFACTURING AGENTS.md boundary rules).
- Commercial adapter references Manufacturing / SBAIO / Hospitality / Procurement only through approved adapter/service contracts (not direct import or table mutation).
---

## 6. Accounting Boundary (Rule 9 — Explicit)

Shared Commercial does NOT own:
- Ledger / chart of accounts / reconciliation / period closure / tax accounting / revenue recognition / costing.
- Manufacturing Ledger (business_entity L3, repair/reconcile) stays Manufacturing/Platform.
- SBAIO finance/payroll stays SBAIO.
- Commercial may expose transaction facts (document identity, line references, amount/value references) for downstream accounting consumption through adapter/service contracts.
- No accounting mutation from Commercial.

---

## 7. Migration / Adoption Strategy (Future — Not Executed)

- Existing domain modules remain fully operational.
- Shared primitives added through adapter/service contracts; no universal master replacement.
- Historical records preserved in domain tables.
- Adapter optional; rollback by disabling adapter.
- Version: shared-commercial-foundation.v1.

---

## 8. Architecture / Gate / Tests (Proposed — Not Implemented)

Gate `check_shared_commercial-foundation.sh` (if adapter/module ever needed):
- Verify no universal `order` / `invoice` / `transaction` table exists outside domain modules.
- Verify no domain module imports Commercial business logic directly (only adapter contracts).
- Verify Commercial adapter (if exists) does not claim item/party/inventory/ledger ownership.
- Verify Manufacturing / SBAIO / Hospitality / Procurement routes stay domain-owned.
- Verify accounting logic (ledger / chart of accounts) does not leak into Commercial core.

Not implemented; no DB mutation; no module created.

---

## 9. Verdict

VERDICT: DEFER SHARED COMMERCIAL FOUNDATION — with minimal shared primitives defined.

Reasoning: No universal order/invoice/transaction master exists; Procurement, Manufacturing, SBAIO, Hospitality genuinely differ in lifecycle/authorization/reporting/fulfillment; Model A unjustified; Model B adequate; no second consumer demands universal master.

No Commercial module created; no Manufacturing edit; no Supply separation; no Parties adoption; no Inventory promotion; no DB mutation; no deployment.

---

## References

- Session D durable: docs/architecture/shared-app-suite-extension-contract.md (shared-app-suite-extension-contract @ c886b89)
- Session A durable: docs/architecture/shared-items-foundation.md (shared-items-foundation @ 2b19280)
- Manufacturing audit: Session E (isolated /tmp/session-e-odarehub); MANUFACTURING AGENTS.md; Manufacturing module inventory
- APP-CONTRACT.md; MODULE-CONTRACT.md; ODAREHUB-OS-ARCHITECTURE-CHARTER-V1.md
filePath: /tmp/session-d-shared-commercial-items/docs/architecture/shared-commercial-foundation.md