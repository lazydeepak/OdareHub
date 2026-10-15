# OH-AUTO1-076 — Release Evidence Authority Hardening

## Scope

This slice hardens the non-executing trusted-release preflight. It does not
implement administrator approval, post-install health authority, release
execution, installation, SQL, database access, trust mutation, identity
mutation, or eligibility enablement.

## Authenticated migration inventory

The signed sidecar now carries `migration_inventory` under the V1 migration
contract. The caller binding must independently provide the exact ordered
`steps` and `source_entries`; the coordinator rejects missing, additional,
reordered, dependency-changed, target-schema-changed, or fingerprint-only
substitutions. Each source entry is then matched to an exact SHA-256 hash of
the corresponding ZIP file entry returned by strict package inspection.

The existing Ed25519 statement remains the only authenticity format. No
migration executor or second signature authority was introduced.

## Installed-state provenance

`TrustedReleaseCoordinatorPreflightService` now requires an injected
`InstalledSystemEvidenceProvider`. Without it, the result is
`installed_state_authority_unavailable`; caller-supplied compatibility state
is never accepted as authority. Product, platform build, app/module state,
migration ledger evidence, schema version, and schema identity are compared
to installation-owned identity and to the caller's comparison evidence.

Runtime construction remains non-executing and database-prohibited in this
slice: `InstalledSystemEvidenceProvider::fromRuntime()` remains an explicit
future/runtime boundary, while the coordinator defaults to fail closed when
no independently authorized provider is supplied.

## Recovery identity

Coordinator success now requires recovery metadata to bind non-empty
`product_id`, `release_version`, `build_id`, and `installation.schema_state`
identity to the complete installation-owned identity. Structurally valid
recovery and rehearsal files with omitted identity fields remain integrity
evidence only and block coordinator verification.

## Evidence

- Coordinator fixture: **24/24**.
- Installed-system evidence provider: **22/22**.
- Offline artifact verifier: **18/18**.
- Migration governance: **18/18**.
- Installed compatibility: **16/16**.
- Recovery point service: **9/9**.
- Offline release/recovery integration: **23/23**.
- All non-database Updates/Recovery probes: **PASS**.
- Direct exact-step migration probe remains intentionally refused by its
  isolated-database entrypoint; the previously accepted attested **13/13**
  proof is preserved.

The coordinator retains `release_authority_granted=false`,
`update_eligible=false`, `apply_eligible=false`, `execution_authorized=false`,
`live_mutation_performed=false`, and `execution_prohibited=true`.

## Remaining authority gaps

Administrator approval, post-install health authority, execution authority,
production trust-anchor provisioning/key custody, and a complete isolated
signed install/update/recovery rehearsal remain separately gated.
