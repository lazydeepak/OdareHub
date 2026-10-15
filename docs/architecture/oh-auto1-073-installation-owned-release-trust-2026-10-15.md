# OH-AUTO1-073 — Installation-Owned Release Trust

Date: 2026-10-15
Status: **Implemented and verified — disposable trust integration only**

## Trust contract

The installation-owned trust file is stored as `trust.json` beneath a root
outside the replaceable payload. Its exact envelope is:

- `storage_record_version`: `odarehub.installation-trust-storage.v1`;
- `trust_source`: `installation-owned`; and
- `records`: one or more records containing `key_id`, `algorithm`,
  `public_key_base64`, `fingerprint`, `status`, `not_before`, and `not_after`.

The reader strictly validates Ed25519 key length, canonical base64, lowercase
SHA-256 fingerprint binding, key identifiers, lifecycle status, timestamps,
validity ordering, duplicate IDs, and duplicate fingerprints. It decodes to the
existing `OfflineReleaseSignatureVerifier` raw trust-record shape. Missing,
malformed, contradictory, mutated, unsafe, or non-installation-owned state
fails closed. No package, sidecar, channel, or candidate key is a fallback.

## Disposable enrollment

`DisposableTrustProvisioner` is an explicit test-only adapter. Enrollment is
restricted to runner-owned temporary roots and requires:

1. an independently supplied expected public-key fingerprint;
2. the exact independent-input source marker; and
3. explicit simulated operator approval.

It refuses repository targets, existing trust state, incorrect fingerprints,
candidate key replacement, and missing approval. The simulation is evidence of
workflow behavior only and is not production human authorization.

## Cryptographic proof

The disposable probe creates a signed canonical release statement and exact ZIP
and sidecar fixtures, enrolls the fixture public key independently, reads the
installation-owned record, and passes it to the existing artifact verifier.
Valid signatures verify only through that enrolled key. Invalid, unknown,
revoked, expired, not-yet-valid, tampered ZIP, tampered sidecar, and packaged-key
cases remain blocked. No second verifier or package-inspection bypass exists.

All trust and artifact results keep release authority and apply eligibility
disabled. Preview authenticity remains diagnostic-only.

## Limitations and next boundary

No production key custody, trust enrollment, administrator authorization,
release coordinator, migration execution, installation, update apply, or
deployment was implemented. The trust file write uses temporary-file plus
same-filesystem atomic rename and read-back verification, but does not claim
multi-file crash durability or a production trust-rotation protocol.
