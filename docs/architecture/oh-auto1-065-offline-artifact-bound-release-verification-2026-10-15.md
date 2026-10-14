# OH-AUTO1-065 — Offline Artifact-Bound Release Verification

Date: 2026-10-15
Status: **Implemented and verified — production integration remains disabled**

## Component

`Platform\Updates\OfflineReleaseArtifactVerifier::verify()` accepts:

```php
verify(
    string $packagePath,
    string $metadataPath,
    string $statementBytes,
    ?string $detachedSignature,
    ?string $keyId,
    array $trustedKeys,
    string $verificationTimestamp,
    ?array $channelRecord = null
): array
```

The adapter is read-only. It validates absolute, co-located, non-symlink ZIP
and JSON paths; reads stable file snapshots; computes hashes and byte sizes from
the exact bytes; validates sidecar identity and package binding; copies the
exact ZIP snapshot to a disposable inspection path; invokes the unchanged
`ReleasePackageInspectionService`; rechecks source bytes and file identity; and
then invokes the OH-AUTO1-064 Ed25519 verifier with the exact artifact binding.

The sidecar hash is computed over its original bytes. It is never reserialized
before hashing. Package and metadata mutations or substitutions during the
verification window fail closed with `RAV-ARTIFACT-MUTATED`.

## Readiness spoof finding

Before OH-AUTO1-066, the existing `ReleaseReadinessEvidenceService` was
reproduced with a caller-supplied readiness object containing
`authenticity: authorized-verifier`, the three required checks marked `passed`,
and matching artifact fields. It returned:

```text
ok=true
eligible=true
```

That result was metadata-driven and was not cryptographic proof. OH-AUTO1-066
now forces `eligible=false` while preserving structural validation. The adapter
does not consume or interpret readiness authenticity and remains disconnected
from preview or apply eligibility.

## Regression evidence

The disposable artifact probe passed **18/18**, covering valid ZIP/sidecar
verification, package and sidecar modification, size mismatch, identity and
build-ID mismatch, invalid/unknown/revoked/missing keys, traversal and symlink
paths, mutation during verification, strict package inspection, and readiness
spoof reproduction.

Existing release regressions remain passing: production Composer portability
**16/16**, REL-004 **15/15**, builder **13/13**, preview **30/30**, apply
preparation **7/7**, readiness evidence **19/19**, and isolated source runner
**17/17**. Architecture gates remain **49/49** and deployment readiness is
**PASS**.

No database, SQL, migration, installation, activation, publication,
deployment, signing, trust-store mutation, or Core change occurred.
