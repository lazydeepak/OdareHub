# OH-AUTO1-070 — Installed Platform Identity Verification

Date: 2026-10-15
Status: **Implemented and verified — read-only validation boundary only**

## Scope

This milestone adds a Platform-owned verifier for an explicitly supplied,
installation-owned platform identity record. It establishes whether one record
is structurally valid and agrees with independently supplied package, signed
release, and schema observations.

The verifier does not discover identity, persist identity, write files, access a
database, change compatibility or readiness behavior, grant update authority,
apply releases, or perform installation/recovery work.

## Authoritative record shape

The V1 record version is `odarehub.installation-identity.v1` and requires exactly
these non-empty string fields:

| Field | Meaning |
| --- | --- |
| `installation_id` | Stable installation identifier, supplied by the installation authority |
| `product_id` | Must be `susankhya-os` |
| `release_version` | Three-part release version |
| `build_id` | Immutable release build identifier |
| `lane` | Must be `app` for this release lane |
| `package_sha256` | Lowercase SHA-256 of the installed package |
| `release_statement_fingerprint` | Lowercase SHA-256 fingerprint of the accepted release statement |
| `signing_key_id` | Key identity associated with the accepted release statement |
| `installed_at` | Canonical UTC timestamp (`YYYY-MM-DDTHH:MM:SSZ`) |
| `schema_state_identity` | Installation schema-state identity |
| `record_version` | Exact V1 record version |

Unknown fields, missing fields, invalid formats, duplicate current records, and
unsupported record versions fail closed.

## Independent observations

Verification requires explicit caller-supplied observations for:

- package SHA-256;
- signed release product, version, build, lane, statement fingerprint, and
  signing-key ID; and
- schema-state identity.

An application-registry value may be included only as comparison evidence. It is
never an authority source. A contradiction is reported; the verifier never
silently reconciles it or falls back to it.

## Diagnostic states

The verifier emits these stable states:

- `verified`
- `identity_missing`
- `identity_ambiguous`
- `identity_payload_mismatch`
- `identity_release_mismatch`
- `identity_schema_unknown`
- `identity_unavailable`

Every result explicitly returns `update_eligible=false` and
`apply_eligible=false`. Verification evidence is not authorization.

## Authority exclusions

The verifier never derives authoritative identity from Git HEAD, application
registry version/build fields, release history, package filenames, package
checksums alone, file timestamps, or generated identifiers.

The existing `InstalledSystemEvidenceProvider` and
`InstalledSystemCompatibilityPreflightService` remain unchanged. This slice
does not reimplement or alter their compatibility checks.

## Future persistence boundary

Persistence is a later, separately authorized installation step. A future
writer may be introduced only after an installation-owned storage contract,
atomic write/recovery semantics, stable installation-key provisioning, and
post-write read-back verification are approved. The writer must:

1. accept only a verifier-approved record;
2. refuse an existing contradictory current record;
3. write through an installation-owned location, never an application registry;
4. preserve the prior record or recovery evidence before replacement; and
5. re-read and verify the committed record before any later release authority
   can consume it.

No writer, schema migration, database table, readiness integration, or apply
eligibility path is part of OH-AUTO1-070.

## Evidence

- `platform/Updates/InstalledPlatformIdentityVerifier.php`
- `platform/Updates/tests/probe_installed_platform_identity_verifier.php`
- Existing regression: `platform/Updates/tests/probe_installed_build_identity.php`
- Existing compatibility boundary: `platform/Updates/InstalledSystemCompatibilityPreflightService.php`
