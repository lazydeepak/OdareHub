# OH-AUTO1-057 — Release Authority and Installed Identity V1 Design

Date: 2026-10-15  
Status: **Proposed for architecture/release-authority review**  
Scope: **REL-001 release authority and REL-002 installed build identity**

## 1. Decision boundary

This is a design proposal, not authorization to implement signing, database
persistence, package verification, installation, upgrade, deployment, or
release apply. It makes no runtime change and performs no database, filesystem,
network, or release operation.

The choices below require explicit architecture and release-authority approval.
Until then, existing checksum and metadata behavior must not be described as
authenticity or authorization evidence.

## 2. Existing implementation versus proposed V1

### Existing evidence

- `local-release-channel-contract.md` defines an immutable app-lane package,
  sidecar metadata, SHA-256 and byte-size binding, compatibility preview, and
  a filesystem-backed channel.
- SHA-256 is integrity evidence only; signing, trust, key rotation, revocation,
  and verification are explicitly deferred.
- `ReleasePackagingService`, `ReleaseHistoryService`, `VersionCatalogService`,
  `DeploymentReadinessService`, and `AppLocalDiscoveryService` provide
  packaging, history, version, readiness, and app-discovery behavior, but none
  is an authoritative release-attestation or installed-platform identity
  authority.
- `core_apps` stores app-level version/manifest/checksum data. It is not an
  authoritative platform `build_id` store.
- The existing Platform decision forbids inferring installed identity from Git
  HEAD, app version, app checksum, package checksum, timestamps, or generated
  values.

### Proposed V1 decisions

1. A release authority signs an immutable release statement over the exact
   package and canonical metadata.
2. Installation/upgrade completion persists the verified platform identity as
   one installation-owned record, written only after payload and post-migration
   checks succeed.

## 3. REL-001 — Release authority

### 3.1 Authority model

**Proposed:** separate release authority from the operator who transfers or
installs a package.

- **Release authority:** controls an offline signing key and approves the
  release statement. It is the only actor allowed to create an accepted V1
  release attestation.
- **Verifier:** verifies the statement against a locally trusted public-key
  record before apply eligibility is reported.
- **Operator:** may transfer a package and request preview/apply, but cannot
  turn an unsigned or invalid package into an accepted release.
- **Application administrator:** remains an application authorization actor;
  application roles do not become release-signing authority.

The release authority is a governance identity, not a PHP user role. Mapping a
future human or service identity to it is a release-governance decision outside
this design.

### 3.2 Cryptographic proposal

**Proposed V1 mechanism:** Ed25519 detached signatures over a canonical UTF-8
release statement. The verifier must use a platform-supported cryptographic
library; it must not execute a shell command or external signing binary at
runtime.

The signature covers canonical bytes containing:

```text
odarehub-release-v1
product_id=<canonical value>
release_version=<canonical value>
build_id=<canonical value>
lane=app
package_sha256=<lowercase hex>
package_bytes=<decimal integer>
metadata_sha256=<lowercase hex>
minimum_supported_schema=<canonical value>
created_at=<UTC RFC3339 value>
```

The exact canonicalization function and field grammar must be frozen before
implementation. Fields may not be omitted, duplicated, reordered, or supplied
with alternate encodings.

### 3.3 Trust anchor and key lifecycle

**Proposed:** installations trust a release-authority public-key fingerprint
from installation-owned trust configuration established during setup or an
explicit trust-maintenance operation. A package cannot replace its own trust
anchor.

V1 requires a key identifier, algorithm, public key, fingerprint, status,
validity interval, active/revoked state, UTC validity checks, explicit key
rotation, and revocation behavior that fails closed. The concrete trust-store
location, operator ceremony, rotation quorum, and revocation distribution
method remain approval decisions.

V1 must not silently accept a package-provided public key or fall back to
checksum-only acceptance.

### 3.4 Verification states

| State | Meaning | Apply eligibility |
|---|---|---|
| `verified` | Signature, trusted key, canonical statement, package bytes/hash, and metadata binding pass. | May proceed to independent gates. |
| `unsigned` | No release signature is present. | Blocked. |
| `invalid_signature` | Signature does not verify. | Blocked. |
| `untrusted_key` | Signature verifies but key is not trusted/active. | Blocked. |
| `revoked_key` | Signing key is explicitly revoked. | Blocked. |
| `statement_mismatch` | Statement does not match package or metadata. | Blocked. |
| `expired_or_not_yet_valid` | Statement or key validity interval fails. | Blocked. |
| `verification_unavailable` | Verifier/trust material is unavailable. | Blocked; never downgraded to warning. |

Failures must expose stable diagnostic codes and safe reasons, not private key
material or arbitrary package contents.

## 4. REL-002 — Installed platform identity

### 4.1 Authoritative record

**Proposed:** add one installation-owned platform release identity record,
separate from `core_apps` app rows and release history events.

Conceptual record:

```text
platform_installation_identity
  installation_id        immutable installation identifier
  product_id             canonical product identity
  release_version        verified installed release version
  build_id                verified immutable build identity
  lane                   app
  package_sha256         exact installed package identity
  release_statement_id   accepted statement fingerprint
  signing_key_id         verifying key identifier
  installed_at           UTC completion time
  installed_by           governed actor/service identity
  schema_state           post-migration compatibility state
  record_version         contract version
```

The record is one current identity, with append-only transition history owned
by the installation/update workflow. `core_apps` may retain app-local facts,
but must not be the platform identity source.

### 4.2 Build identity format

**Proposed:** `build_id` is an immutable release-build identifier issued by the
release packaging process and included in the signed statement. For V1 it must
be opaque, non-empty, stable across installation, and unique for different
payload bytes. A release commit may be descriptive metadata, but Git HEAD is
not authoritative unless signed as the build identity.

The package checksum remains a separate byte-integrity field. It is not a
substitute for semantic `build_id`, and a timestamp or random runtime value is
prohibited.

### 4.3 Write lifecycle

The installation/update workflow may write current identity only after:

1. signature and trust verification;
2. canonical statement/package/metadata binding;
3. package compatibility and protected-path checks;
4. recovery point creation and verification;
5. payload installation in the governed staging path;
6. migrations complete in approved order; and
7. post-install runtime/schema verification passes.

The identity record must be committed atomically with the final activation
marker. If a precondition fails, the prior identity remains authoritative and
the candidate is not recorded as installed.

### 4.4 Contradiction policy

Read-only installed evidence must classify contradictions instead of selecting
the most convenient value:

- missing identity: `identity_missing` — not eligible for update apply;
- duplicate current records: `identity_ambiguous` — blocked;
- package differs from recorded hash: `identity_payload_mismatch` — blocked;
- signed release differs from recorded build: `identity_release_mismatch` — blocked;
- schema state unknown or incompatible: `identity_schema_unknown` — blocked;
- identity record unreadable: `identity_unavailable` — blocked.

No fallback to Git, app rows, release history, mtime, or package filename is
permitted. Repairing a contradiction requires a governed recovery procedure,
not an automatic overwrite.

## 5. Recovery and failure behavior

- Verification failure is fail-closed and leaves installed identity unchanged.
- Failure before activation removes only runner-owned staging material; it must
  not delete active payload or customer configuration.
- Failure after migration begins requires the separately approved recovery
  contract; this design does not authorize rollback or downgrade execution.
- Successful apply records prior and new identity in transition history, but
  history never replaces the current identity record.
- A partially written identity record is unavailable/ambiguous, not repaired
  by inference.

## 6. Explicit non-goals

This proposal does not decide or implement REL-003 migration ordering,
compatibility, downgrade policy, or rollback; REL-004 protected paths/vendor
development files; key custody/HSM/cloud services; hosted updates, entitlement,
or online revocation; database migrations or physical schema; installer/update
routes; package generation/signing commands; deployment automation; production
acceptance; or database requalification.

## 7. Implementation gates after approval

1. Freeze canonical release-statement and trust-store contracts.
2. Add pure in-memory signature/trust verification with no package apply.
3. Add read-only installed-identity evidence and contradiction diagnostics.
4. Approve the installation-owned persistence/migration contract.
5. Add package preview eligibility checks; keep apply disabled.
6. Only after REL-003/REL-004 and recovery evidence are approved, design apply.

Each slice requires static ownership gates and deterministic fixtures. No
database execution is implied; no slice may claim production readiness from
signature verification alone.

## 8. Required decisions for review

The architecture/release authority must accept or reject:

1. Ed25519 as the V1 signature algorithm.
2. Release authority/verifier/operator separation.
3. Installation-owned identity as the authoritative platform source.
4. Opaque signed `build_id` semantics and distinction from package hash.
5. Fail-closed verification and contradiction behavior.
6. Trust-anchor provisioning, rotation, and revocation ownership.
7. Whether the conceptual identity record and transition history may proceed to
   a separate schema/implementation design.

Until accepted, REL-001 and REL-002 remain pending and release apply remains
blocked.

## Evidence

- `docs/architecture/local-release-channel-contract.md`
- `docs/architecture/deployment-update-foundation-audit.md`
- `engineering/Platform/decisions.md` — installed identity decision,
  2026-10-14
- `app/Services/ReleasePackagingService.php`
- `app/Services/ReleaseHistoryService.php`
- `app/Services/VersionCatalogService.php`
- `platform/Updates/InstalledSystemEvidenceProvider.php`
