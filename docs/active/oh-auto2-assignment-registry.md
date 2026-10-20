# OH-AUTO2 Assignment Registry

Assignment: `OH-AUTO2-GOV-001`  
Status: **Authoritative reconciliation — 2026-10-20**

This registry is the source of truth for the legacy `OH-AUTO2-*` assignment
identifiers. Historical identifiers are preserved exactly; they are not
renamed in existing work logs, decisions, compliance records, commits, or
architecture reports.

## Scope and method

The inventory covered:

- every `engineering/**/work.md` file;
- every `engineering/**/decisions.md` file;
- `AGENT-COMPLIANCE-CHECKLIST.md`;
- `docs/CURRENT.md` and OH-AUTO2 architecture reports.

The registry records an identifier when it is present in those sources. A
repeated identifier is classified as either a coordinated multi-owner
milestone, a deliberate suffix/incident record, or a collision requiring
domain-qualified identifiers going forward.

## Inventory

| Legacy ID | Recorded owner/workstream | Reconciled state |
|---|---|---|
| `OH-AUTO2-100` | Shell, Platform, Manufacturing | Completed coordinated restricted My Work preflight |
| `OH-AUTO2-101` | Manufacturing, Plugin/Base | Completed read-path safety boundary |
| `OH-AUTO2-102` | None found | **Unassigned; no repository evidence** |
| `OH-AUTO2-103` | Shell | Completed restricted host-surface containment |
| `OH-AUTO2-104` | Manufacturing | Completed non-mutating Material summary boundary |
| `OH-AUTO2-105` | Manufacturing, Shell, SBAIO | Bounded implementation completed provisionally; incident family remains open |
| `OH-AUTO2-105B` | SBAIO | **Blocked** pending separately authorized assessment |
| `OH-AUTO2-105R` | SBAIO incident | **Open; impact undetermined** after accidental DB-backed probe execution |
| `OH-AUTO2-105S` | SBAIO | Completed disposable entry-point safeguard |
| `OH-AUTO2-106` | Shell, Platform, SBAIO | Completed governance audit; implementation sequence authorized |
| `OH-AUTO2-107` | Shell, Platform | Completed governed `/me` provider admission foundation |
| `OH-AUTO2-108` | Shell, Platform, Manufacturing, SBAIO | Completed provider migration inventory; unsafe operational providers remain suppressed |
| `OH-AUTO2-109` | Manufacturing, Platform, Shell | Completed bounded Material extraction; later scope review superseded admission |
| `OH-AUTO2-110` | Manufacturing | Completed record-scope audit; found aggregate authorization defect |
| `OH-AUTO2-111` | Manufacturing, Platform, Shell | Completed containment; unsafe Material aggregate remains removed/suppressed |
| `OH-AUTO2-112` | Manufacturing | Investigation complete; Material restoration **blocked** by missing record-scope authority |
| `OH-AUTO2-113` | Manufacturing | Completed administrative blocker closure |
| `OH-AUTO2-114` | Shell | Completed account-panel extraction; older proposal text remains historical debt |
| `OH-AUTO2-115` | Shell | Completed recent-activity extraction |
| `OH-AUTO2-116` | Shell | Completed worker task-to-action identity integrity |
| `OH-AUTO2-117` | Shell | Completed recent-activity entity authorization |
| `OH-AUTO2-118` | Shell | Completed runtime authorization and safe presentation |
| `OH-AUTO2-119` | Hospitality **and** Procurement | **Collision:** Hospitality live acceptance blocked; Procurement supplier eligibility completed |
| `OH-AUTO2-120` | Hospitality **and** Procurement | **Collision:** Hospitality offline readiness completed/live acceptance blocked; Procurement selection completed |
| `OH-AUTO2-121` | Hospitality **and** Procurement | **Collision:** Hospitality behavioral verification accepted; Procurement request-link integrity completed |
| `OH-AUTO2-122` | Procurement | Completed controller write-authorization closure |
| `OH-AUTO2-123` | Procurement | Completed non-mutating GET/read safety |
| `OH-AUTO2-124` | Procurement | Completed GET readiness completeness |
| `OH-AUTO2-125` | Procurement | Completed CSRF-before-provisioning ordering |
| `OH-AUTO2-126` | Procurement | Completed receipt purchase-order identity integrity |
| `OH-AUTO2-127` | Procurement | Completed receipt posting transaction integrity |
| `OH-AUTO2-127R` | Procurement | Completed receipt posting reconciliation |
| `OH-AUTO2-128` | Procurement | Completed receipt-creation status integrity |
| `OH-AUTO2-129` | Procurement | Completed direct PO transition integrity |
| `OH-AUTO2-130` | Procurement | Completed direct request transition integrity |
| `OH-AUTO2-131` | Procurement | Completed manual PO creation status integrity |
| `OH-AUTO2-132` | Procurement | Completed request creation status integrity |
| `OH-AUTO2-133` | Procurement | Completed request creation transaction integrity |
| `OH-AUTO2-134` | Procurement | Completed supplier eligibility normalization alignment |
| `OH-AUTO2-135` | Procurement | Accepted after OH-AUTO2-PROC-001 bounded review; live DB/concurrency evidence remains out of scope |
| `OH-AUTO2-MFG-001` | Manufacturing | Provisional local assessment complete; report untracked and pending independent baseline verification |
| `OH-AUTO2-MFG-002` | Manufacturing | Contract assessment complete; dispatch operator exposure blocked; no implementation authorized |
| `OH-AUTO2-MFG-003` | Manufacturing | Accepted assessment; bounded status-redirect context defect advanced to MFG-004 |
| `OH-AUTO2-MFG-004` | Manufacturing | Implemented and verified Processing status-redirect context preservation; remote parity pending delivery |

## Confirmed collision set

The assignment numbers are not globally unique. The direct collisions requiring
future domain qualification are:

| Legacy ID | Hospitality meaning | Procurement meaning |
|---|---|---|
| `119` | Runtime acceptance, blocked | Supplier eligibility, completed |
| `120` | Offline acceptance readiness, completed | Eligible supplier selection, completed |
| `121` | Operator behavioral verification, accepted | Manual request-link integrity, completed |

The Procurement sequence also independently occupies `122–135`, while Shell
occupies `114–118`. These are historical records, not evidence that one
workstream superseded another.

## Preserved commit references

Where implementation commits are directly identifiable from repository history,
the legacy assignment remains associated with its original commit. The
Procurement sequence is preserved as follows:

| Legacy ID | Commit evidence |
|---|---|
| `119` | `941ae7c` |
| `120` | `8d3ca61` |
| `121` | `2304da8` |
| `122` | `c7cfedf` |
| `123` | `aec00b6` |
| `124` | `0e398f2` |
| `125` | `d31b378` |
| `126` | `14150ac` |
| `127` | `b4a5a6b` |
| `127R` | `52a5fa7` |
| `128` | `37045d8` |
| `129` | `b71db30` |
| `130` | `bf1f0dd` |
| `131` | `e60b0ed` |
| `132` | `71fbf6b` |
| `133` | `b351220` |
| `134` | `fd38b19` |
| `135` | `1107ca0` |

Hospitality records retain their verified commit references in their own
workstream evidence: OH-AUTO2-120 is `89ac01e8fbca3be18c53b35f03ec685bb091c8e8`
and OH-AUTO2-121 is `c7bf169d68139db5bfba6ce5bd5eb705f41f088f`.

## Future identifier policy

Starting with the next assignment, numeric `OH-AUTO2-###` identifiers must not
be allocated globally. Use a domain-qualified form:

- `OH-AUTO2-GOV-###` — governance and registry work;
- `OH-AUTO2-HOSP-###` — Hospitality;
- `OH-AUTO2-PROC-###` — Procurement;
- `OH-AUTO2-SHELL-###` — Shell;
- `OH-AUTO2-MFG-###` — Manufacturing;
- `OH-AUTO2-SBAIO-###` — SBAIO;
- `OH-AUTO2-PLAT-###` — Platform.

The registry owner must reserve the identifier before work begins, record the
owner/workstream, state, baseline commit, and intended evidence, and reject a
duplicate reservation. Legacy IDs remain searchable aliases only.

## Roadmap disposition

### Completed

The bounded Shell, Manufacturing, Platform, SBAIO-safeguard, Hospitality
offline, and Procurement integrity slices listed above have completion evidence.
They must not be reissued as duplicate offline assignments.

### Blocked or open

1. **Hospitality live acceptance** remains `OH-AUTO2-119` and requires the
   assigned user, isolated HTTP/database environment, explicit database
   authorization, and browser proof.
2. **OH-AUTO2-105R / OH-AUTO2-105B** remain separate SBAIO incident and
   assessment boundaries; no database operation is authorized by this registry.
3. **Material restoration** remains blocked under the OH-AUTO2-112 record-scope
   decision.
4. **Procurement OH-AUTO2-135** is accepted after the bounded
   `OH-AUTO2-PROC-001` review. Live database rollback/concurrency behavior was
   not exercised and remains an environment-bound limitation; any follow-up
   must use a qualified Procurement ID.

### Highest-priority unfinished product work

The highest-priority actionable item was **Procurement acceptance of the already
implemented supplier-creation transaction integrity correction** under
`OH-AUTO2-PROC-001`. That bounded acceptance review is now complete. It was an
acceptance/requalification task, not permission to run live database tests
without the required isolated authorization.

Hospitality live acceptance remains a separate blocked prerequisite and should
not be repeated offline. No speculative feature implementation is authorized
by this reconciliation.

### OH-AUTO2-MFG-001 assessment disposition — 2026-10-20

The read-only Manufacturing workflow assessment is complete. It found one
evidence-backed product candidate: expose the existing dispatch lifecycle
continuation (`markReady`, `prepare`, `complete`) through the operator
Fulfillment surface, which currently creates dispatch entries but does not
complete their lifecycle. Machine/assembly execution progress and operator
handoff ownership were recorded as lower-ranked candidates with additional
dependencies or unresolved product ownership.

No implementation assignment is reserved by this assessment. Any follow-up
must use a new domain-qualified `OH-AUTO2-MFG-*` identifier and separately
authorize operator role/status rules, record scope, transaction/audit behavior,
and isolated verification. Material restoration remains blocked under
OH-AUTO2-112.

### OH-AUTO2-MFG-002 contract disposition — 2026-10-20

The Fulfillment dispatch contract assessment traced the existing administrative
`markReady`, `prepare`, and `complete` graph. All three actions are blocked for
operator exposure because the service layer has no operator record-scope
authority, complete legal-status transition contract, transaction/rollback
boundary, or required AuditLog integration; it also performs runtime schema
ensure work. The admin route and CSRF checks do not establish operator
eligibility. No implementation assignment is authorized.

### OH-AUTO2-MFG-003 assessment disposition — 2026-10-20

The Processing operator workflow is supported by scoped Assembly/QC
projection, separate module authority, CSRF-protected actions, record-level
part scope, legal status checks, and isolated evidence (**35/35** Processing;
**23/23** work/process summary). One medium usability defect is confirmed:
Assembly/QC status forms drop the selected `processing_date` and `product_id`
when redirecting after submission. A future bounded implementation may preserve
that context; no implementation is authorized by MFG-003. Live persistence and
transaction behavior remain unverified under the no-database/no-HTTP boundary.

### OH-AUTO2-MFG-004 implementation disposition — 2026-10-20

The authorized bounded correction is implemented in the existing Processing
operator view. All Assembly/QC status forms preserve valid server-rendered
`processing_date` and `product_id` context in the same-user Processing return
URL; missing, invalid, and unlisted filters fall back safely. Rendered-form
verification passed **21/21**, with existing Processing and work/process
regressions passing **35/35** and **23/23**. No database, SQL, browser, HTTP,
activation, deployment, Core, or unrelated-owner operation was performed.

## Validation boundary

This reconciliation is read-only documentation. No database, SQL, browser,
HTTP, activation, deployment, Core, schema, or runtime operation was performed.
