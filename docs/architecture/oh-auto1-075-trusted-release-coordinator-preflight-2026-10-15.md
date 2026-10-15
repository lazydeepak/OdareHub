# OH-AUTO1-075 — Trusted Release Coordinator Preflight

## Scope

This slice adds a Platform-owned, non-executing coordinator preflight. It
joins the existing installation-owned trust reader, exact ZIP/sidecar and
Ed25519 verifier, installation identity store/verifier, compatibility
preflight, migration-plan validator, recovery evidence verifier, and readiness
shape validator. It does not install, apply, migrate, write trust, write
identity, grant approval, or execute health checks.

## Independent evidence chain

1. Trust is read from the installation-owned store and read again after exact
   artifact verification; a changed trust fingerprint is indeterminate.
2. The existing artifact verifier binds stable ZIP and sidecar bytes to the
   canonical statement and detached Ed25519 signature, and the coordinator
   consumes the verifier-derived metadata and package hash.
3. The installation-owned identity is read twice and independently compared
   with package, signed-release, and schema observations.
4. Existing compatibility checks reject malformed state, failed/pending
   migrations, unsupported versions, and downgrades.
5. The migration plan is revalidated from its raw structure and then bound to
   authenticated release version, build, package hash, statement fingerprint,
   and target schema. `ReleaseMigrationPlanEvidence` remains structural-only;
   its public factory is not treated as authority.
6. Existing recovery verification checks recovery-point payload integrity,
   installed identity binding, and restore-rehearsal evidence.

## Result contract

The coordinator returns `preflight_verified`, `preflight_blocked`, or
`preflight_indeterminate`, with per-gate diagnostics and provenance digests.
Even a technically verified fixture always returns:

```text
release_authority_granted=false
update_eligible=false
apply_eligible=false
execution_authorized=false
live_mutation_performed=false
execution_prohibited=true
```

Administrator approval authority, post-install health authority, and release
execution authority are explicit missing controls. Caller-supplied `approved`,
`verified`, `ready`, `authorized-verifier`, and fingerprint claims are not
accepted as authority.

## Disposable evidence

`platform/Updates/tests/probe_trusted_release_coordinator_preflight.php`
creates only temporary signed artifacts, temporary trust and identity stores,
and a temporary recovery fixture. It proves **20/20** assertions, including a
valid non-executing preflight, missing/revoked trust, invalid signature,
identity contradiction, forged/unbound plan, failed migration history,
missing recovery, ZIP/sidecar tampering, unknown schema, downgrade, and forged
approval/health claims.

No SQL, installed database, release apply, installation, activation,
deployment, production trust write, or production identity write is used.
