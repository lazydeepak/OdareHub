# OH-AUTO1-071 — Installation Identity Persistence

Date: 2026-10-15
Status: **Implemented and verified — disposable filesystem boundary only**

## Scope

`Platform\Updates\InstallationIdentityStore` provides explicit creation and
controlled replacement of the installation-owned identity record. The store is
filesystem-only and is designed to receive an installation-owned root outside
the replaceable application payload.

No installed path, repository path, database, SQL, schema, release channel,
readiness service, apply service, signing authority, trust store, installer, or
deployment path is used or changed.

## Storage contract

Each installation-owned root contains:

- `identity.json` — the one current storage record;
- `.identity.lock` — exclusive writer coordination; and
- `predecessors/<identity-fingerprint>.json` — immutable predecessor evidence
  created before controlled replacement.

Temporary files use the `.identity.tmp-*` prefix. A leftover temporary file is
classified as `identity_interrupted_write`; subsequent writes refuse to proceed
until controlled recovery removes the incomplete artifact. The store never
regenerates identity or silently repairs an interrupted state.

The storage envelope is versioned as
`odarehub.installation-identity-storage.v1` and contains the OH-AUTO1-070
identity record, predecessor reference, and write timestamp.

## Write and recovery behavior

1. The path is canonicalized and rejected if it traverses the repository,
   forbidden installation roots, unsafe destinations, or non-approved symlink
   escapes.
2. The identity and independent observations are validated through
   `InstalledPlatformIdentityVerifier` before any write.
3. An exclusive lock is acquired.
4. Creation refuses an existing current record.
5. Replacement requires the exact previously observed current fingerprint.
6. The predecessor is written exclusively before replacement.
7. The candidate is written to a same-directory temporary file and atomically
   finalized with `rename()`.
8. The final record is read back and compared to the requested identity.
9. A read-back mismatch restores the predecessor, or removes a failed initial
   record, and returns `identity_readback_mismatch`.

The implementation does not claim crash durability beyond same-filesystem
temporary-write and atomic-rename behavior. It does not fsync directories or
provide a multi-file transaction.

## Authority boundary

The store does not accept a `verified=true` authority flag and does not infer
release completion from a persisted record. Every result explicitly reports:

```text
update_eligible=false
apply_eligible=false
release_authority_granted=false
```

A future trusted coordinator must separately authenticate release evidence,
confirm approved migration completion, verify recovery and health checks,
obtain manual administrator approval, and then invoke persistence under an
explicit coordinator contract. That coordinator is outside OH-AUTO1-071.

## Evidence

- `platform/Updates/InstallationIdentityStore.php`
- `platform/Updates/tests/probe_installation_identity_store.php`
- `platform/Updates/InstalledPlatformIdentityVerifier.php`
- `docs/architecture/oh-auto1-070-installed-platform-identity-verification-2026-10-15.md`
