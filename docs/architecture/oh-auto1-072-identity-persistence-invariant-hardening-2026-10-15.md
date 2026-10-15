# OH-AUTO1-072 — Identity Persistence Invariant Hardening

Date: 2026-10-15
Status: **Implemented and verified — disposable filesystem boundary only**

## Corrections

`InstallationIdentityStore` now:

1. rejects any replacement whose `installation_id` differs from the current
   authoritative record, before predecessor or temporary-file writes;
2. blocks `create()` and `replace()` when an existing `identity.json` is
   malformed, unreadable, unsupported, interrupted, or otherwise not safely
   interpretable; and
3. tracks atomic finalization and reconciles exceptions after `rename()`.

Post-finalization outcomes are explicit:

- `candidate_identity_committed` when the candidate is present and verifies on
  disk;
- `identity_state_indeterminate` when authoritative state cannot be reconciled;
- `identity_readback_mismatch` only when rollback successfully restores the
  previous state; and
- `previous_identity_retained` is reported as recovery metadata for successful
  rollback.

Rollback failure is never reported as successful recovery. Predecessor evidence
is written before replacement and remains available even when recovery becomes
indeterminate.

## Evidence

The disposable probe now contains **33 assertions**, including changed
installation-ID rejection, malformed-existing-byte preservation,
post-finalization candidate reconciliation, and rollback-failure
indeterminate-state classification. No installed path, database, SQL, release
authority, readiness, apply, signing, trust, or Core operation was performed.
