# OH-AUTO1-020 / OH-AUTO1-029–OH-AUTO1-033 — Isolated Business Transaction Verification

Date: 2026-10-14
Status: **Procurement and Manufacturing bounded transaction verification complete in independently attested disposable MySQL instances; broader concurrency and cross-domain verification remain open.**

## OH-AUTO1-033 consolidated regression update

`scripts/testing/run_business_integrity_regression.sh` now orchestrates the
existing assertions without sharing mutable database state. It runs the static
audits once and provisions three separate fresh disposable MySQL instances for
Procurement concurrency, Manufacturing transaction/rollback, and Manufacturing
concurrency. Each instance is independently authorized before application SQL,
then shut down and removed before the next instance starts.

The governed run completed in approximately 11 seconds:

- authorizer safety: **9/9**;
- static transaction/ownership audit: **32/32**;
- Procurement method boundaries: **9/9**;
- Procurement concurrency: **10/10** in 7 seconds;
- Manufacturing transaction/rollback: **21/21**;
- Manufacturing concurrency: **11/11** in 4 seconds.

All three disposable instances reported cleanup success and installed ServBay
PID `1181`/port `3306` remained preserved. Separate instances were retained to
prevent fixture collisions and execution-order contamination rather than forcing
the existing probes into a shared mutable database.

## OH-AUTO1-032 Manufacturing concurrency update

The public `UpstreamSupplyGenerationService::generate()` entry point was invoked
concurrently through independent PHP workers for both existing target branches:

- in-house supply → `mfg_production_plan_candidates`;
- third-party supply → `mfg_procurement_demands`.

The first deterministic run showed that the existing composite unique keys kept
durable target/link counts at one, but the losing worker surfaced a duplicate-key
exception. The bounded Manufacturing correction now recognizes only the existing
duplicate-entry/key failure after rollback, reloads the committed link/target, and
returns the established `updated` result. Other failures remain errors.

The corrected probe passed **11/11** assertions: both concurrent calls complete,
each branch leaves exactly one target and one link, and post-race retries update
without duplication. The existing OH-AUTO1-030 transaction probe was rerun and
passed **21/21**.

## OH-AUTO1-031 Procurement concurrency update

Two bounded workflows were exercised with independent PHP/MySQL connections and
deterministic database trigger pauses:

- simultaneous `createOrderFromApprovedRequest()` calls for one approved request;
- simultaneous posted `createReceipt()` calls for one purchase order and line,
  plus simultaneous duplicate receipt-reference submissions.

The first run reproduced two defects: two POs/two lines could be created for one
request, and concurrent posted receipts could leave the order status stale even
when the durable receipt total was correct. The smallest Procurement-owned fix:

- locks the request row with `FOR UPDATE` before the existing-PO check and keeps
  the conversion idempotency decision inside the transaction;
- locks the purchase-order row with `FOR UPDATE` across receipt insertion and
  posted-receipt status recomputation.

The corrected isolated probe passed **10/10** durable-state assertions: one PO and
one line, one conversion returning the existing PO, two receipts totaling the
ordered quantity with final status `closed`, and one durable row for a duplicate
receipt reference.

## OH-AUTO1-030 Manufacturing update

The bounded Manufacturing workflow was `UpstreamSupplyGenerationService::generate()`
with `dryRun=false`. It was exercised through the public entry point for both
supported branches:

- in-house supply → `mfg_production_plan_candidates` plus
  `mfg_upstream_generation_links` with `work_type=production`;
- third-party supply → `mfg_procurement_demands` plus
  `mfg_upstream_generation_links` with `work_type=procurement`.

The authoritative fixture sources were Products migrations `001`, `002`, `004`,
and `006`, PreOrders `001`, DailyOrders `001`, and DispatchEntries `001`.
The three upstream tables were created by
`UpstreamSupplyGenerationService::ensureSchema()` itself.

The pre-correction source path performed target persistence followed by link
persistence without a transaction. The smallest Manufacturing-owned correction
added commit/rollback scopes around each target/link create and update pair in
`syncProductionCandidate()` and `syncProcurementDemand()`.

The corrected isolated probe passed **21/21** assertions: both branches commit,
repeat generation updates rather than duplicates, missing products produce no
generated rows, and a controlled link insert failure rolls back the target and
leaves no orphan link.

## OH-AUTO1-029 Procurement update

The previously blocked database-backed assignment was executed only after a
fresh temporary MySQL instance passed the supplementary live process/socket/
datadir authorizer in `scripts/testing/DisposableDbExecutionAuthorizer.php`.
The existing `disposable_db_harness.php` remains proof-only and was not weakened
or treated as an execution authority.

The actual `ProcurementOverviewService` methods tested were:

- `createSupplier()` and `createRequest()` fixture setup;
- `createOrderFromApprovedRequest()` successful PO/line creation and request closure;
- `createReceipt()` successful receipt creation;
- invalid receipt-line rejection without a receipt row;
- missing-request rejection without a PO row;
- duplicate approved-request conversion returning the existing PO;
- `createOrder()` controlled line-insert failure with parent rollback.

The controlled failure reproduced the prior partial-write defect before the
correction: the PO parent remained after the line insert failed. The smallest
Procurement-owned correction added explicit `begin_transaction()` /
`commit()` / `rollback()` scopes to `createOrderFromApprovedRequest()`,
`createOrder()`, and `createReceipt()`. The post-correction run passed **15/15**
behavioral assertions, including zero parent rows after the injected line
failure.

## OH-AUTO1-020 historical safety boundary

- No installed or production database was connected to, mutated, or used for test
  fixture creation.
- The existing Procurement behavioral probes were not executed because they call
  `CREATE TABLE`, `INSERT`, `UPDATE`, and cleanup statements through the application
  DB layer.
- No disposable database identity, isolated credentials, or teardown guard could be
  proven from the current environment. Environment variables contained no explicit
  test database target, and the repository's available probes default to the shared
  application DB configuration.
- No application activation, schema migration, deployment, commit, or push occurred.

## Selected workflow contracts

### Procurement request → order → receipt

`apps/Procurement/Services/ProcurementOverviewService.php` was inspected without
execution.

- `createOrder()` performs an order insert, optional order-line insert, optional request
  status update, and audit write.
- `createOrderFromApprovedRequest()` performs an order insert, order-line insert,
  request status update, and audit write.
- `createReceipt()` performs receipt-line ownership validation, receipt insert, audit
  write, and optionally order-status recomputation for posted receipts.
- These multi-write methods now have explicit `begin_transaction()` / `commit()` /
  `rollback()` boundaries.
- The former `createOrder()` partial-write risk was reproduced in isolation and
  corrected in the Procurement service. The correction does not alter service
  signatures or schema contracts.

### Manufacturing upstream synchronization

`UpstreamSupplyGenerationService` updates or inserts a target record and then updates or
inserts its upstream-generation link. Both production and procurement synchronization
paths now have explicit commit/rollback boundaries around those paired writes. The
correction remains inside Manufacturing and does not claim ownership of Procurement
runtime workflows or shared inventory.

### Hospitality front desk comparison

`FrontDeskService::checkout()`, `addChargeForReservation()`, and `voidCharge()` each
contain explicit transaction handling and reservation-row `FOR UPDATE` evidence. This
is static contract evidence only; the existing DB-backed Hospitality probes were not
run against the installed database.

## Verification

- Static transaction-contract probe:
  `apps/Procurement/Tests/probe_transaction_contract_audit.php` — **30/30 PASS**.
- Probe is source-only and contains no application DB bootstrap or mutation call.
- Procurement method-boundary regression probe: **9/9 PASS**.
- Disposable authorizer safety probe: **9/9 PASS**.
- Authorized Procurement behavioral probe: **15/15 PASS**.
- Authorized Manufacturing behavioral probe:
  `apps/Manufacturing/Tests/probe_upstream_supply_transaction.php` — **21/21 PASS**.
- MySQL isolation evidence: fresh temporary datadir, unique socket, loopback-only
  ephemeral port, `--no-defaults`, `--skip-log-bin` only for trigger injection,
  and test-only credentials. Installed ServBay PID `1181` and port `3306`
  remained unchanged.
- Existing PHP lint and `git diff --check` remain required validation.

## Unverified behavior

- Rollback after request-status, audit, receipt-status, or posted-receipt
  recomputation failure was not separately injected.
- Duplicate submissions and concurrent request/order/receipt transitions under database
- isolation beyond the verified OH-AUTO1-031 workflows.
- Receipt over-quantity policy remains unverified; the tested concurrent total did
  not exceed ordered quantity.
- Manufacturing update-path failure injection after an already committed target/link
  pair was not separately injected.
- Concurrent Manufacturing generation and cross-process duplicate races remain
  unverified.
- Nested invocation inside a caller-owned MySQL transaction remains unverified and
  is not claimed as composable: the current Manufacturing correction starts and
  commits a transaction inside each target/link synchronization pair. In
  addition, `generate()` calls `ensureSchema()` before synchronization, whose
  `CREATE TABLE IF NOT EXISTS` statements can implicitly commit on MySQL. The
  generic `DB` layer exposes no transaction-depth or savepoint abstraction. No
  current `UpstreamSupplyGenerationService::generate()` caller was found to wrap
  the call in an existing transaction.
- The authoritative Manufacturing schemas do not declare foreign keys for these
  upstream tables; application-level target/link consistency was verified instead.

## Required next executable assignment

Run OH-AUTO1-034 updated integration baseline and source-inventory reconciliation.
Core, schema, and cross-domain transaction contracts remain out of scope.
