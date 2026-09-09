# Hospitality Installation / Lifecycle Readiness

Status: Verified. Hospitality behaves correctly as an installable OdareHub app
through the platform's existing generic lifecycle machinery only.

Date: 2026-08-24

## 1. Summary

Hospitality (apps/Hospitality, v0.1.0, package_type bundle) installs, enables,
disables, re-enables, repairs, soft-uninstalls, and recovers entirely through the
generic platform services:

- `AppInstallService::install()` - transactional install: dependency resolution,
  migrations via `AppMigrationService`, runtime artifact materialization via
  `AppRuntimeRegistryService::refreshAppRuntimeArtifacts()`, schema snapshot,
  status `installed`.
- `AppLifecycleService::{enable,disable,uninstallSoft,repair,recoverFromBroken}` -
  status transitions with runtime refresh and menu/permission synchronization.
- `AppInstallService::registerPackageZip()` - generic version rules
  (downgrade rejection, duplicate rejection, newer -> `upgrade_pending`).

No Hospitality-specific installer, setup script, extensions/ directory, or lifecycle
screen exists. The app never references installer/lifecycle classes in its own source.

## 2. Hospitality Lifecycle Contract

Manifest-declared behavior honored by the generic machinery:

| Field | Value | Generic effect |
|---|---|---|
| version | 0.1.0 | ledger + snapshot versioning; package comparison |
| migrations_path | migrations | two additive SQL files through the shared runner |
| can_disable | true | disable button permitted (`AppLifecycleService::disable`) |
| can_uninstall | true | soft uninstall permitted (`uninstallSoft`); purge would also be permitted but is excluded from this milestone |
| can_export | true | export link shown when installed-state |
| dependencies | [] | install resolves nothing external |
| permissions | hospitality.view, hospitality.manage | materialized to core_app_permissions on refresh |
| hooks | 2x operator_surface | materialized to core_app_hooks; active only while enabled |
| modules | five native modules | materialized to core_app_modules |

## 3. Proven Behaviors

All items below are locked by `apps/Hospitality/Tests/probe_install_admin_lifecycle.php`
(45 assertions, all passing against the real services and real database):

- Install: status becomes `installed`; both migrations apply through
  `core_app_migrations` bookkeeping; all six `hosp_` tables are created by the
  generic additive runner; exactly five module rows materialize.
- Enable: status `enabled`; operator surface hooks and permissions activate through
  the runtime registry.
- Disable: status `disabled`; active hook contribution withdrawn; **all hosp_
  business tables and data retained**; source files retained. Disable never becomes
  an uninstall.
- Re-enable: contributions return with zero duplicate module/hook rows; migration
  ledger unchanged (no destructive rerun).
- Repair: idempotent for a valid installation - no new migrations applied, data and
  enabled semantics preserved.
- Soft uninstall: status `uninstalled`; tables, rows, files, ledger, and the
  core_apps registration row are all retained (`destructive:false` logged).
- Recovery/reinstall: the admin Install action (same `install()` path) reinstalls
  from `uninstalled` without duplicating applied migrations; reactivation succeeds.

## 4. Package / Version Rules (synthetic fixture)

Exercised with a disposable fixture bundle (`hospfixtlc`) built into the system temp
directory - never against hospitality's registry row, and fully cleaned up afterwards:

- Same-version duplicate upload rejected ("Duplicate app package upload").
- Older-version upload rejected ("older than installed/registered").
- Genuinely newer upload moves the row to `upgrade_pending`.

Hospitality's own declared version was not bumped.

## 5. Admin App Manager Audit

The generic App Manager (`/admin/app-manager`,
`app/AppManager/Controllers/AppManagerController.php`) lists every registered app
with name/version/status and gates actions by status:

install (uploaded/upgrade_pending/uninstalled), enable (installed/disabled),
disable (enabled), repair (installed/enabled/disabled/broken/upgrade_pending),
recover (broken/upgrade_pending), soft-uninstall confirm
(installed/disabled/broken/upgrade_pending), purge confirm, mark upgrade-pending,
and an export action gated by `can_export`.

Hospitality therefore already surfaces with correct generic actions purely from its
manifest/registry state. No Hospitality-specific lifecycle screen was created, and
the generic controller was not modified. No generic platform defect was found during
this audit.

## 6. Data Safety And Self-Restoration

The lifecycle probe is self-restoring and proves it with explicit before/after
semantic fingerprints (not just counts):

- Every `core_apps` status is snapshotted and restored, including the hospitality
  row restored field-for-field (status, version, install_path, manifest_json,
  checksum, installed/enabled/disabled timestamps, error_text).
- Hospitality-owned rows in `core_app_modules`, `core_app_hooks`,
  `core_app_permissions`, `core_app_migrations`, and `core_schema_snapshots` are
  captured before mutation and restored exactly afterwards - including
  auto-increment ids - so the probe leaves zero net new registry/ledger/snapshot
  rows.
- Per-table row-count fingerprints over all six `hosp_*` tables prove business
  data was never mutated or deleted.
- A content fingerprint of the `apps/Hospitality` source tree proves install/
  package operations never moved or rewrote the real source tree.
- `storage/logs/app-lifecycle.log` and `app-lifecycle-dedupe.json` are snapshotted
  before the run and restored byte-for-byte afterwards; if they did not exist
  pre-run, test-created copies are removed.
- Cleanup runs through try/finally so a failed assertion cannot leave hospitality
  disabled/uninstalled or registry-mutated; restoration errors themselves fail the
  probe.

### Residue disclosure from earlier probe revisions

An earlier revision of this probe (pre-hardening) did not restore semantic state.
Its runs on this machine left residue that was audited and cleaned:

- Six `core_schema_snapshots` rows for hospitality were created at exactly the
  three earlier execution timestamps (2026-08-24 22:46:59 / 22:47:54 / 22:53:27);
  no pre-probe snapshot rows existed. Attribution was certain, and all six rows
  were removed.
- `installed_at/enabled_at/disabled_at` timestamp values on the hospitality
  `core_apps` row were overwritten by those runs. The original pre-probe values
  could not be reconstructed and were not guessed; current values reflect the last
  verified post-probe state (status enabled). Future runs restore these fields
  automatically.
- No lifecycle log or dedupe file existed (the storage/logs directory is absent on
  this machine), so no log residue existed.
- Fixture extraction directories under `packages/temp/` from the earlier revision
  were removed; the hardened probe now cleans its own extractor output.

## 7. Explicit Exclusions

- Destructive purge (`AppLifecycleService::purge`) was deliberately not exercised:
  it drops manifest-declared tables, deletes ledger/snapshot rows, removes the
  install tree, and deletes the registration row. No purge metadata was added to the
  Hospitality manifest.
- Live authenticated browser smoke of the admin App Manager remains deferred,
  non-blocking acceptance evidence; nothing was fabricated.

## 8. Validation Evidence

- New probe: 45/45 PASS (38 lifecycle assertions + 7 self-restoration proof
  assertions), run once from a captured pre-test snapshot with before/after
  fingerprints matching.
- Existing aggregate probes: `run_all_hospitality_probes.php` 8/8 groups PASS.
- Operator probes: slice2 18/18, slice3 29/29 PASS.
- PHP lint touched files: PASS. Manifest JSON untouched this slice (valid).
- Gates: core lock PASS; business app/module contracts PASS; operator confinement
  PASS; asset registry integrity PASS; shell rendering contract PASS; style chain
  parity PASS; `git diff --check` CLEAN.
- Known unrelated baseline failures (shell CSS ownership debt, Customization Studio
  boundary debt) remain identical at HEAD and contain no Hospitality references.
