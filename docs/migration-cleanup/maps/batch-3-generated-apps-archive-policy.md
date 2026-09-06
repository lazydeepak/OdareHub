# Batch 3 Generated Apps Archive Policy

Status: executed as diagnostic and policy only.

No generated apps were deleted, archived, or moved. No runtime behavior was
changed.

## Architecture Basis

- `apps/Generated` is runtime-coupled to Studio apply/publish flows.
- Generated navigation files remain KEEP_COMPAT until archive policy exists.
- Generated app keys are not registered in `core_apps` in this checkout, but
  public loaders, Studio services, generated data, snapshots, generated routes,
  generated navigation, and asset checks still reference generated artifacts.
- Delete/archive decisions require evidence, not naming alone.

## Diagnostic Script

- script: `scripts/architecture/check_generated_apps_archive_candidates.sh`
- mode: read-only diagnostic
- current result: pass
- direct DB evidence: all generated app keys checked here are not registered in
  `core_apps`
- Studio registry evidence: `storage/appstudio/apps_registry.json`

## Policy Rules

- DELETE_CANDIDATE requires zero runtime, storage, public, route, navigation,
  script, and docs coupling.
- ARCHIVE_CANDIDATE requires no active registry/runtime use but may preserve
  historical/snapshot evidence.
- TEMP_STAGING must be preserved unless a Studio tmp retention policy says
  otherwise.
- Any uncertainty becomes INVESTIGATE.

## Classification Summary

| App/module | Classification | Registry | core_apps | Safe action |
|---|---|---|---|---|
| `hardening_app/hardening_module` | TEST_FIXTURE | absent | not_registered | keep for now; fixture/hardening evidence exists |
| `inventory_app/parts_master` | ACTIVE_RUNTIME | enabled | not_registered | keep; enabled in Studio registry and has generated data/snapshots |
| `inventory_app/stock_entries` | ACTIVE_RUNTIME | enabled | not_registered | keep; enabled in Studio registry and has route/nav/public/snapshot coupling |
| `lifecycle_app/lifecycle_module` | TEST_FIXTURE | enabled | not_registered | keep for now; lifecycle fixture evidence exists |
| `manufacturing_app/production_plan` | ACTIVE_RUNTIME | enabled | not_registered | keep; enabled in Studio registry and has route/nav/public/snapshot coupling |
| `manufacturing_studio/assemblyentries_studio` | ACTIVE_RUNTIME | enabled | not_registered | keep; enabled in Studio registry and has route/nav/public/snapshot coupling |
| `manufacturing_studio/manufacturing_studio` | ACTIVE_RUNTIME | enabled | not_registered | keep; enabled in Studio registry and has route/nav/public/snapshot coupling |
| `rollback_app/rollback_module` | TEST_FIXTURE | absent | not_registered | keep for now; rollback fixture/snapshot evidence exists |
| `runtime/(app)` | ACTIVE_RUNTIME | absent | not_registered | keep; generated runtime support artifact |
| `sample_app/sample_module` | GENERATED_SAMPLE | enabled | not_registered | keep for now; sample/template registry and storage coupling exist |
| `tmp/(app)` | TEMP_STAGING | absent | not_registered | preserve until Studio tmp retention policy exists |

## Evidence By Artifact

### `hardening_app/hardening_module`

- path: `apps/Generated/hardening_app/hardening_module`
- manifest/module files: app manifest, module manifest, module contract
- route files: present
- navigation files: present
- public asset references: present
- storage generated_data references: none present
- storage snapshot references: none found by diagnostic
- grep references: present in generated code, public assets, docs, and cleanup maps
- recommendation: keep as TEST_FIXTURE; not an archive/delete candidate.

### `inventory_app/parts_master`

- path: `apps/Generated/inventory_app/parts_master`
- manifest/module files: app manifest, module manifest, module contract
- route files: present
- navigation files: present
- public asset references: present
- storage generated_data references: `storage/appstudio/generated_data/inventory_app/parts_master.json`
- storage snapshot references: present
- grep references: present in Studio runtime docs, generated code, public assets,
  storage, and cleanup maps
- recommendation: keep as ACTIVE_RUNTIME.

### `inventory_app/stock_entries`

- path: `apps/Generated/inventory_app/stock_entries`
- manifest/module files: app manifest, module manifest, module contract
- route files: present
- navigation files: present
- public asset references: present
- storage generated_data references: provider points to generated_data path; file
  not present in this checkout
- storage snapshot references: present
- grep references: present in generated code, public assets, storage, and docs
- recommendation: keep as ACTIVE_RUNTIME.

### `lifecycle_app/lifecycle_module`

- path: `apps/Generated/lifecycle_app/lifecycle_module`
- manifest/module files: app manifest, module manifest, module contract
- route files: present
- navigation files: present
- public asset references: present
- storage generated_data references: provider points to generated_data path; file
  not present in this checkout
- storage snapshot references: present
- grep references: present in generated code, public assets, storage, and docs
- recommendation: keep as TEST_FIXTURE because lifecycle evidence exists and
  Studio registry marks it enabled.

### `manufacturing_app/production_plan`

- path: `apps/Generated/manufacturing_app/production_plan`
- manifest/module files: app manifest, module manifest, module contract
- route files: present
- navigation files: present
- public asset references: present
- storage generated_data references: provider points to generated_data path; file
  not present in this checkout
- storage snapshot references: present
- grep references: present in generated code, public assets, storage, and docs
- recommendation: keep as ACTIVE_RUNTIME.

### `manufacturing_studio/assemblyentries_studio`

- path: `apps/Generated/manufacturing_studio/assemblyentries_studio`
- manifest/module files: app manifest, module manifest, module contract
- route files: present
- navigation files: present
- public asset references: present
- storage generated_data references: provider points to generated_data path; file
  not present in this checkout
- storage snapshot references: present
- grep references: present in generated code, public assets, storage, and docs
- recommendation: keep as ACTIVE_RUNTIME.

### `manufacturing_studio/manufacturing_studio`

- path: `apps/Generated/manufacturing_studio/manufacturing_studio`
- manifest/module files: app manifest, module manifest, module contract
- route files: present
- navigation files: present
- public asset references: present
- storage generated_data references: provider points to generated_data path; file
  not present in this checkout
- storage snapshot references: present
- grep references: present in generated code, public assets, storage, and docs
- recommendation: keep as ACTIVE_RUNTIME.

### `rollback_app/rollback_module`

- path: `apps/Generated/rollback_app/rollback_module`
- manifest/module files: app manifest, module manifest, module contract
- route files: present
- navigation files: present
- public asset references: present
- storage generated_data references: provider points to generated_data path; file
  not present in this checkout
- storage snapshot references: present
- grep references: present in generated code, public assets, storage, and docs
- recommendation: keep as TEST_FIXTURE; not an archive/delete candidate.

### `runtime/(app)`

- path: `apps/Generated/runtime`
- manifest/module files: app manifest
- route files: none
- navigation files: none
- public asset references: present
- storage generated_data references: none
- storage snapshot references: none found by diagnostic
- grep references: high, because generated modules and Studio services use the
  generated runtime contract/renderer vocabulary
- recommendation: keep as ACTIVE_RUNTIME support artifact.

### `sample_app/sample_module`

- path: `apps/Generated/sample_app/sample_module`
- manifest/module files: app manifest, module manifest, module contract
- route files: present
- navigation files: present
- public asset references: present
- storage generated_data references: `storage/appstudio/generated_data/sample_app/sample_module.json`
- storage snapshot references: present
- grep references: present in Studio templates, generated code, public assets,
  storage, and docs
- recommendation: keep as GENERATED_SAMPLE; not an archive/delete candidate.

### `tmp/(app)`

- path: `apps/Generated/tmp`
- manifest/module files: app manifest
- route files: none
- navigation files: none
- public asset references: present
- storage generated_data references: none
- storage snapshot references: present across generated compile snapshots
- grep references: present in generated snapshots and tooling temp vocabulary
- recommendation: preserve as TEMP_STAGING until a Studio tmp retention policy
  exists.

## Current Decisions

- ACTIVE_RUNTIME: `inventory_app`, `manufacturing_app`,
  `manufacturing_studio`, `runtime`
- GENERATED_SAMPLE: `sample_app`
- TEST_FIXTURE: `hardening_app`, `lifecycle_app`, `rollback_app`
- TEMP_STAGING: `tmp`
- ARCHIVE_CANDIDATE: none
- DELETE_CANDIDATE: none
- INVESTIGATE: no app/module is classified as the primary INVESTIGATE class,
  but generated_data gaps and fixture intent should remain review items before
  any archive action.

## Next Safe Work

1. Define Studio tmp retention policy before touching `apps/Generated/tmp`.
2. Define generated fixture retention rules for hardening/lifecycle/rollback
   artifacts.
3. Keep public asset cleanup blocked on owner CSS/source cleanup and registered
   asset publishing.
4. Keep generated data cleanup blocked on provider path and snapshot provenance
   review.
5. Do not archive generated apps until a dry-run plan proves no active registry,
   route, navigation, public asset, storage, script, or docs coupling.
