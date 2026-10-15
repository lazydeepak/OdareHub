# OH-AUTO1-077 — Disposable Release Transaction and Health Gates

## Scope

This slice adds Platform-owned control boundaries for a later isolated signed
update rehearsal. It does not apply a release, execute SQL, activate an app,
write installation identity or trust, enable production readiness, or
authenticate a real administrator.

## Transaction and journal

`DisposableReleaseTransactionService` accepts only a verified,
execution-prohibited preflight and an explicit disposable environment token.
It derives the preflight evidence fingerprint and records the immutable
installation, candidate, trust, migration, schema, recovery, and health
bindings. `DisposableReleaseTransactionJournal` stores hash-chained JSONL
events under a runner-owned system-temporary root. It never rewrites, deletes,
repairs, or resumes ambiguous evidence.

The control states are:

`prepared → awaiting_approval → approved_for_disposable_rehearsal → executing → verifying → completed`

The journal also supports fail-closed `recovery_required`; malformed or
interrupted journals resolve to `indeterminate`. Executing and completed are
control-state rehearsal transitions only; no release apply operation enters
them.

## Approval boundary

`DisposableAdministratorApprovalAuthority` issues a one-time temporary
challenge and requires an explicit `simulated:<id>` approver, current
transaction preflight fingerprint, and unexpired token. Reuse, revocation,
expiry, real-admin identity claims, and changed evidence are rejected. The
simulator records that it is simulated and is not production administrator
authentication.

## Health boundary

`PostUpdateHealthVerifier` executes nine fixed read-only runner-owned sources:
release identity, target schema, migration ledger/checksums, bootstrap,
application discovery, critical connectivity, configuration/trust,
protected-path integrity, and unresolved migration/recovery state. It ignores
caller `healthy`/`passed` claims, computes each check's evidence digest, and
returns an opaque `PostUpdateHealthVerificationResult`. Health expectations
are journal-bound before rehearsal and must match the immutable release,
schema, trust, identity, and migration-plan fields. Completion requires a
verified result bound to both expectation and transaction health bindings.

Health status is `verified`, `failed`, or `indeterminate`; exceptions and
malformed source observations are indeterminate.

## Evidence

- Disposable transaction, approval, journal, and health probe: **20/20**.
- Existing OH-AUTO1-076 coordinator: **26/26**.
- Existing non-database release/recovery probes: **PASS**.
- Existing architecture gates: **49/49**.
- Deletion gates: **28/28**.
- Deployment readiness: **PASS** in read-only verification mode.

No SQL, installed database, release apply, activation, deployment, production
trust provisioning, real identity write, or eligibility enablement occurred.

## Remaining blockers

The subsequent disposable runner must separately own actual isolated update
execution, post-update source acquisition, failure injection, restore proof,
and any database/process attestation. Production approval, health authority,
execution authority, trust custody, and installed-database access remain
blocked.
