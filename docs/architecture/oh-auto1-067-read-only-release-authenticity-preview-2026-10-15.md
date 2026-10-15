# OH-AUTO1-067 — Read-only Release Authenticity Preview

Date: 2026-10-15
Status: **Implemented and verified — release authority remains disabled**

## Scope

`LocalReleaseChannelPreviewService::preview()` now accepts an optional fourth
argument containing explicit, caller-supplied in-memory verification inputs:

```php
[
    'statement_bytes' => string,
    'detached_signature' => string|null,
    'key_id' => string|null,
    'trusted_keys' => list<array<string,mixed>>,
    'verification_timestamp' => string,
]
```

The preview passes those inputs to the existing
`OfflineReleaseArtifactVerifier`. That adapter reads the exact ZIP and sidecar
bytes, runs unchanged strict package inspection, binds the canonical statement
to those bytes, and performs offline Ed25519 verification using only the
supplied in-memory trust records.

When inputs are absent or malformed, preview returns an explicit unavailable
authenticity diagnostic. It never interprets the release metadata
`authenticity` field as authority.

## Result contract

Preview now exposes an additive top-level `authenticity` result containing:

- `state`
- `verified`
- `key_id`
- verifier diagnostics
- exact artifact evidence when available

Existing package, readiness, compatibility, recovery, blocker, and warning
fields remain unchanged.

## Security boundaries

- A verified signature is diagnostic evidence only; it does not set readiness
  eligibility or `apply_eligible`.
- `ReleaseReadinessEvidenceService::validate()` remains hard-coded fail-closed
  with `eligible=false` until a separately approved authority integration.
- Trust records are supplied in memory for the call and are not persisted or
  mutated.
- No signing, installation, apply, activation, deployment, database, SQL,
  migration, or Core behavior was added.

## Regression evidence

The local preview probe covers 44 assertions, including valid, unsigned,
invalid, unknown-key, revoked-key, modified package, modified sidecar, missing
inputs, forged readiness metadata, valid signature with failed readiness,
valid signature with failed compatibility, and the invariant that verified
signatures do not grant apply eligibility.

Existing preview and apply-preparation behavior remains covered by the existing
30/30 and 7/7 probes respectively.
