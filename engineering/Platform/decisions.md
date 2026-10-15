# Platform — Decisions

## Decision Log

### 2026-10-15 — OH-AUTO1-060 migration execution feasibility reconciliation
**Status:** Proposed; approval required

**Finding**

`AppMigrationService::runMigrations()` is owner-batched: it lexically sorts
every SQL file for one owner and has no exact-step or release-plan parameter.
It cannot express a valid cross-owner sequence such as
`app:owner-a:001_a.sql -> app:owner-b:001_b.sql -> app:owner-a:002_a.sql`.
It also deletes an existing failed ledger row before retry, which loses prior
failure evidence and conflicts with REL-003 fail-closed recovery semantics.

**Minimal contract correction**

Retain `AppMigrationService` as the application-lane SQL authority, but later
add an exact planned-step entry point that receives one owner-relative filename,
expected checksum, and plan position. It must verify bytes and ledger state,
execute only that file through the existing schema-sync authority, preserve
failure evidence, and refuse an existing failed row until recovery/attempt
history policy is approved. The release coordinator—not the executor—owns
dependency satisfaction and cross-owner order.

**Boundary**

No runtime interface, failed-row behavior, schema, SQL, migration execution, or
database evidence changed in OH-AUTO1-060. Module/plugin migration paths remain
outside the application-ledger contract until governed adapters are approved.

**Evidence**

Pure REL-003 fixtures now pass 18/18, including A001/B001/A002 owner-batch
limitation, exact interleaving feasibility, and failed-row retry blocking.
Existing migration compatibility, installed compatibility, and installed
evidence probes remain 16/16, 16/16, and 22/22.

### 2026-10-15 — OH-AUTO1-059 release migration governance proposal
**Status:** Proposed; approval required

**Context**

The existing `AppMigrationService` executes owner-local SQL in lexical order,
while Platform preflight and installed evidence validate ledger facts. There is
no approved release-wide target-schema identity or cross-owner dependency
ordering contract.

**Proposal**

Use one release-owned migration inventory with immutable owner/migration
identity, exact SQL checksum, explicit dependency edges, opaque target-schema
identity, and stable topological ordering. Keep `AppMigrationService` as the
execution authority. Treat failed/partial state, unknown or modified
migrations, disabled-owner requirements, dependency errors, and downgrade
requests as fail-closed. Use verified recovery points rather than guessed
reverse SQL for rollback.

**Boundary**

This is design-only and fixture-verified. It does not add a migration runner,
execute SQL, change schema/ledgers, or authorize rollback/apply. REL-001/002
approval and database requalification remain separate gates.

**Evidence**

`docs/architecture/oh-auto1-059-release-migration-compatibility-ordering-contract-2026-10-15.md`;
pure fixture probe 13/13 and existing migration probes 16/16, 16/16, 22/22.

### 2026-10-15 — OH-AUTO1-058 vendor development metadata is build-excluded
**Status:** Implemented under the existing V1 package exclusion contract

**Context**

The strict package inspector rejected `vendor/ratchet/rfc6455/phpunit.xml.dist`
because its protected-path rule applies to `phpunit.xml*` at every depth, while
the release contract requires the runtime `vendor/` tree.

**Decision**

Keep the inspector strict and filter only named vendor development patterns at
build time: vendor tests/docs/examples, `.github`, `vendor/bin`, vendor
`phpunit.xml*`, and vendor `composer.cache`. Preserve all other vendor files,
including runtime autoload material. Reinspect the filtered package through the
unchanged strict inspector.

**Consequences**

Raw packages containing protected vendor paths remain rejected. The builder is
the only compatibility boundary changed; package acceptance is not widened.
Future dependency-specific exceptions require evidence that the file is
runtime-required and separate policy review.

**Evidence**

`docs/architecture/oh-auto1-058-release-package-protected-path-compatibility-2026-10-15.md`;
REL-004 fixture probe 15/15.

### 2026-10-15 — OH-AUTO1-057 release authority and installed identity proposal
**Status:** Proposed; approval required

**Context**

The local release contract binds packages to checksums and metadata but
explicitly defers signing and trust. Existing app records and release history
do not provide an authoritative installed platform `build_id`.

**Proposal**

Define a distinct release authority that signs a canonical V1 release
statement, initially proposing Ed25519 detached signatures and an
installation-owned trust anchor. Define one installation-owned current
platform identity record, written only after verified release, migration, and
post-install checks. Verification and identity contradictions fail closed.

**Boundary**

This is design-only. It does not authorize cryptographic implementation,
schema/migration changes, package apply, deployment, or database execution.
REL-003 and REL-004 remain separate decisions.

**Evidence**

`docs/architecture/oh-auto1-057-release-authority-installed-identity-design-2026-10-15.md`.

### 2026-10-15 — Authorized Core deny and plugin route rollback corrections
**Status:** Implemented and verified

**Decision**

Preserve explicit ACL role denials through all additive permission sources, and
make each plugin bootstrap/routes operation transactional for the existing
Router route map. Failed plugin initialization restores only the pre-operation
routes and rethrows the original exception; successful registration remains
live and route ordering/dispatch semantics are unchanged.

**Boundary**

This decision is limited to `app/Core/AclPolicy.php`, `app/Core/Router.php`,
and `app/Core/PluginManager.php` with isolated in-memory regression probes.
Database-backed verification, lifecycle activation, and deployment remain out
of scope.

### 2026-08-19 — V1 recovery provider uses local MySQL/MariaDB dump plus filesystem archive
**Status:** Accepted

**Context**
Susankhya currently uses `mysqli` with MySQL/MariaDB configuration, has no complete database backup provider, and must support local Windows/client installs before installers or online updates. Existing suite export/restore is selected module/table transport, not product recovery.

**Decision**
Use one V1 local provider: a MySQL/MariaDB-compatible dump plus a filesystem ZIP archive, coordinated by a Platform-owned recovery capability and exercised through a governed CLI before any web mutation surface. Require checksum verification and an isolated restore rehearsal. Defer cloud snapshots and multi-provider abstraction until a second real deployment provider is needed.

**Consequences**
The first implementation can be small and testable on local installations while remaining compatible with later installer and private-update work. Update apply stays blocked until the provider creates verified recovery points and rehearsal evidence under the recovery contract.

**Evidence**
`docs/architecture/backup-provider-implementation-plan.md`; `docs/architecture/backup-restore-recovery-contract.md`; `README.md`; `app/Core/DB.php`; `app/Services/SuiteExportService.php`.

### 2026-08-19 — V1 update apply is blocked pending verified database and filesystem recovery
**Status:** Accepted

**Context**
The deployment audit confirmed that existing suite export/restore flows cover selected module packages and table data, while no complete database backup/recovery provider, whole-installation filesystem recovery point, or rehearsed product rollback exists.

**Decision**
Block V1 update apply, repair upgrade, installer upgrade, and private online update work until an implementation produces and verifies a full database recovery point plus a filesystem/configuration/customer-state recovery point. Restore must reach one explicit final state and pass the defined post-restore verification before a recovery point is accepted.

**Consequences**
Existing `SuiteExportService`, `SuiteRestoreService`, restore routes, history services, and migration snapshots remain useful local/domain mechanisms but cannot authorize product update rollback. The next design work is backup-provider selection/contract and read-only package compatibility preview.

**Evidence**
`docs/architecture/backup-restore-recovery-contract.md`; `docs/architecture/deployment-update-foundation-audit.md`; `app/Services/SuiteExportService.php`; `app/Services/SuiteRestoreService.php`.

### 2026-08-19 — V1 local release uses one vendor-included app-lane package
**Status:** Accepted

**Context**
The deployment audit found one PHP modular monolith, a committed `composer.lock` and `vendor/` tree, hosting scenarios where Composer cannot run, existing release ZIP generation without a channel identity/checksum contract, and no safe apply implementation.

**Decision**
Define V1 as one filesystem-backed `app`-lane Susankhya OS release package. Include `vendor/` with `composer.json` and `composer.lock`; preserve customer configuration/state outside the package; bind sidecar metadata and the future `channel.json` entry to an immutable SHA-256 and byte size; make preview read-only; and defer signing, entitlement, online distribution, and apply implementation.

**Consequences**
V1 client delivery does not require Composer at install/update time. A checksum is integrity evidence only, not a signature or authorization mechanism. Backup/restore proof, compatibility preview, and explicit operator approval remain mandatory before any apply slice can be authorized.

**Evidence**
`docs/architecture/local-release-channel-contract.md`; `docs/architecture/deployment-update-foundation-audit.md`; `README.md`; `composer.json`; `composer.lock`.

### 2026-08-19 — Freeze productization vocabulary and sequence before Hospitality
**Status:** Accepted

**Context**
Runtime discovery recognizes top-level app bundles through `apps/*/manifest.json` and `core_apps`, while module lifecycle still uses `plugin.json` and several lifecycle services use legacy suite vocabulary. Beginning Hospitality, installer, private updates, or large app/module refactors without one declared model would extend this ambiguity.

**Decision**
Keep Susankhya OS a modular monolith. Treat App as the current runtime/lifecycle unit; freeze System App, Domain App, Shared App, Module, App Extension, Plugin, and Package meanings; reserve Suite for commercial/product or documented legacy grouping terminology until first-class suite architecture is separately approved. Deliver local deployment/update, backup/restore, and installer readiness before Hospitality implementation. Defer private `update.susankhya.com`, and position `cloud.susankhya.com` first as a customer/license/control portal rather than business-data synchronization.

**Consequences**
Hospitality remains a future Domain App, not a new runtime suite. Current Manufacturing Products/Parts remains Manufacturing-owned. Studio remains a future governed diagnostic/proposal workbench, not an autonomous upgrader or Hospitality generator. Any future installer or updater must consume the approved app/module/package and backup/restore contracts.

**Evidence**
`docs/architecture/susankhya-productization-roadmap.md`; supporting baselines: `app-ownership-classification-and-hospitality-readiness.md` and `local-deployment-update-channel-plan.md`.

### 2026-08-19 — Local deployment and update channel comes before Hospitality
**Status:** Accepted

**Context**
Hospitality should not start before Susankhya OS has a repeatable local deployment and update path. The repository already contains readiness, release packaging, release history, upgrade assistant, and version catalog services, but no clear local update channel baseline has been frozen.

**Decision**
Prioritize local deployment first with a filesystem-backed local update channel. Keep cloud distribution, hosted update APIs, automatic background updates, marketplace distribution, and first-class Suite lifecycle out of V1.

**Consequences**
Hospitality planning remains valid, but implementation waits until local deployment/update channel work has a usable baseline. New deployment planning should use app/module/package/channel vocabulary and avoid reviving `suite` as deployment terminology except where documenting legacy code.

**Evidence**
Planning baseline recorded in `docs/architecture/local-deployment-update-channel-plan.md`.

### 2026-08-17 — App ownership model is the Hospitality readiness gate
**Status:** Accepted

**Context**
The repository has mixed historical terminology across app, bundle, suite, plugin, and module lifecycle code. Hospitality should not start by adding another partially recognized concept.

**Decision**
Use App as the current runtime and lifecycle truth. Use Suite only as product/composition language until a first-class suite registry, manifest, and lifecycle service exist. Classify all current and future capabilities by ownership type before Hospitality implementation: Core Engine, System App, Platform Engine, Shared App, Domain App, App Module, App Extension, Studio Tool, Plugin / Adapter, or Legacy Bridge.

Hospitality may be marketed as Hospitality Suite, but the initial runtime owner is Hospitality App at `apps/Hospitality` with app key `hospitality`.

**Consequences**
Hospitality implementation is gated by an ownership audit. Studio remains a future system doctor and upgrade workbench, but it must not be relied on to generate Hospitality until the repository model is clear enough for Studio to inspect and reason about it.

**Evidence**
Planning baseline recorded in `docs/architecture/app-ownership-classification-and-hospitality-readiness.md`.

### 2026-07-03 — Engineering Workspace contract is mandatory for engineering operations
**Status:** Accepted

**Context**
Prompt-only reminders to read Engineering Workspace documents are too weak. Engineering agents, reviewers, planners, and implementation workflows need a platform contract that makes workspace resolution and contract loading procedural.

**Decision**
Every engineering operation begins by resolving its Engineering Workspace and loading the workspace contract before repository inspection. Engineering Workspace is the authoritative source of engineering intent and governance. Repository code is implementation truth, the current user request is task truth, and probes/tests/browser evidence are validation truth.

Unresolved implementation-capable work is blocked, including platform-scoped work. Platform implementation must resolve a platform Engineering Workspace such as `Platform` or another exact affected workspace.

**Consequences**
Agents no longer have a valid implementation path that skips the workspace contract. When workspace intent conflicts with repository truth or the current task, agents must report the conflict instead of silently choosing one source.

**Evidence**
Updated `platform/Engineering/EngineeringWorkspaceAgentProtocol.md`, bootstrap/gate instruction prefixes, execution gate decision behavior, and probes:
`probe_agent_bootstrap.php`, `probe_agent_execution_gate.php`, and `probe_agent_context_integration.php`.

### 2026-10-14 — Installed platform build identity is not inferred from repository or package data
**Status:** Confirmed gap; persistence decision required

**Context**

Release metadata and recovery metadata require an immutable `build_id`, but the current `core_apps`
schema persists application version, manifest, and checksum only. Local discovery does not persist a
platform build identity, and the installed evidence provider cannot obtain one from the current runtime
database. Repository HEAD identifies a release-build input, while a package checksum proves bytes only;
neither identifies the installed platform state.

**Decision**

Do not infer or synthesize an installed build identity from Git HEAD, application version, app-manifest
checksum, package checksum, timestamp, or a generated value. Keep installed evidence and recovery-point
creation fail-closed until an installation/upgrade-owned persistence contract defines the authoritative
field, format, write lifecycle, and contradiction behavior.

**Consequences**

Recovery creation now requires the existing read-only installed evidence provider and no longer records
repository HEAD as installed identity. Legacy installations without the authoritative identity remain
ineligible for recovery identity binding and release apply. A future schema/install decision must be
made before adding persistence or changing the runtime database.

**Evidence**

`app/Services/AppMigrationService.php`, `app/Services/AppLocalDiscoveryService.php`,
`platform/Updates/InstalledSystemEvidenceProvider.php`, `platform/Recovery/RecoveryPointService.php`,
`scripts/system/recovery_point.php`, and `platform/Updates/tests/probe_installed_build_identity.php`.

### YYYY-MM-DD — Decision title
**Status:** Proposed / Accepted / Superseded

**Context**
Why the decision was needed.

**Decision**
What was decided.

**Consequences**
What this enables, constrains, or requires next.

**Evidence**
Relevant validation, source references, or implementation links.

### 2026-10-15 — OH-AUTO1-062 Composer autoload preparation boundary
**Status:** Implemented correction; dependency-pruning policy required

**Context**

OH-AUTO1-061 showed that the package's filtered vendor tree retained Composer
autoload metadata for 147 unique development-only targets. The repository's
runtime contract requires vendor-inclusive packages, while Composer separates
`require`/`require-dev` and `autoload`/`autoload-dev`.

**Decision**

The local release builder prepares a runner-owned source copy and runs only
`composer dump-autoload --no-dev --no-scripts --no-plugins --no-interaction`
with network disabled. It validates generated PSR-4, classmap, and autoload-file
targets before packaging. It never runs Composer against the repository or an
installed application, and it does not weaken the strict package inspector.

The builder does not infer full dev-package removal from `installed.json`.
`composer install --no-dev` or direct generated-inventory rewriting requires a
separate approved release dependency-materialization policy.

**Consequences**

The broken production autoload boundary is corrected: actual-source package
verification reports zero missing targets and no root `Tests\\` mapping. A
fully pruned development dependency tree is not yet claimed; release authority
must approve how installed Composer metadata and dev package materialization are
made consistent before that boundary is closed.

**Evidence**

`platform/Updates/LocalReleaseChannelBuilderService.php`,
`platform/Updates/tests/probe_production_composer_autoload_portability.php`,
and `docs/architecture/oh-auto1-062-production-composer-autoload-portability-2026-10-15.md`.

### 2026-10-15 — OH-AUTO1-063 immutable release artifact finalization
**Status:** Accepted and implemented

**Context**

The release builder used `ZipArchive::OVERWRITE` on the identity-derived ZIP
path. That allowed a later payload to replace an existing artifact or collide
through build-ID normalization.

**Decision**

Treat an existing package or sidecar path as occupied, reject incomplete
records, and validate existing channel references before building. Produce and
inspect the package in a runner-owned temporary directory, then use
non-overwriting finalization for the package and sidecar. Channel JSON is
written to a temporary file and atomically renamed only after both artifacts
are finalized. Builder-owned temporary material is removed on every path.

**Consequences**

Release identities are append-only at the artifact path. Rebuilding an
identity, colliding after build-ID normalization, or encountering an
incomplete record fails closed without changing the prior valid artifact or
channel state. Replacing the channel's current pointer for a genuinely new
identity remains the existing channel behavior.

**Evidence**

`platform/Updates/LocalReleaseChannelBuilderService.php`,
`platform/Updates/tests/probe_release_artifact_immutability.php`, and
`docs/architecture/oh-auto1-063-immutable-release-artifact-protection-2026-10-15.md`.

### 2026-10-15 — OH-AUTO1-064 offline release verification foundation
**Status:** Accepted implementation; production integration remains disabled

**Decision**

Freeze a conservative canonical release statement before any apply integration:
UTF-8 LF-only bytes, one final newline, fixed field order, ASCII-safe values,
lowercase SHA-256 fields, canonical decimal byte size, strict UTC timestamps,
and fixed `susankhya-os`/`app` constraints. Verify detached Ed25519 signatures
only with PHP Sodium and caller-supplied in-memory trust records. Validate key
ID uniqueness, public-key fingerprints, status, and validity windows; revoked
keys and unavailable cryptography fail closed.

**Boundary**

The verifier has no private-key, signing, filesystem, package, installed-state,
database, network, trust-persistence, preview, apply, or deployment authority.
An optional artifact-binding input accepts independently computed hashes/size
for a future adapter; it does not read or authorize artifacts. No existing
release acceptance behavior is changed.

**Evidence**

`platform/Updates/ReleaseStatementCanonicalizer.php`,
`platform/Updates/OfflineReleaseSignatureVerifier.php`,
`platform/Updates/tests/probe_offline_release_signature_verifier.php`, and
`docs/architecture/oh-auto1-064-offline-release-signature-verification-2026-10-15.md`.

### 2026-10-15 — OH-AUTO1-065 artifact-bound verification boundary
**Status:** Accepted implementation; readiness integration remains separately authorized

**Decision**

Add a read-only `OfflineReleaseArtifactVerifier` adapter that reads exact
co-located package and sidecar bytes, validates their identity, invokes the
unchanged strict package inspector against an exact temporary package snapshot,
rechecks source file identity/bytes, and passes exact hash/size bindings to the
offline Ed25519 verifier.

**Finding**

`ReleaseReadinessEvidenceService::validate()` returned `eligible=true` when
caller-supplied checks passed and `authenticity` equaled
`authorized-verifier`; this was reproduced without cryptographic evidence.

### OH-AUTO1-066 correction

The existing service now always returns `eligible=false`. Structural evidence,
artifact binding, execution diagnostics, and error reporting remain unchanged.
No cryptographic verification is interpreted as sufficient authority by this
correction; a later separately approved integration must establish that policy.

### OH-AUTO1-067 read-only preview diagnostics

The local release preview may expose independently verified authenticity as an
additive diagnostic when the caller explicitly supplies detached signature
material, a key identifier, in-memory trust records, and a verification time.
The preview delegates exact ZIP/sidecar binding to
`OfflineReleaseArtifactVerifier`; it does not trust release metadata
authenticity and does not convert a verified signature into readiness or apply
eligibility. Trust records remain process-local fixtures until a separate
release-authority decision approves operational provisioning.

**Boundary**

The adapter does not read or persist trust configuration, does not grant
readiness/apply authority, and does not interpret readiness metadata. A future
integration must replace self-declared authenticity with independently produced
cryptographic evidence under a separate approval gate.

**Evidence**

`platform/Updates/OfflineReleaseArtifactVerifier.php`,
`platform/Updates/tests/probe_offline_release_artifact_verifier.php`, and
`docs/architecture/oh-auto1-065-offline-artifact-bound-release-verification-2026-10-15.md`.
