# IPM-003 Shared Item Persistence Completion

**Status:** Implemented and disposable-verified
**Branch:** `ipm`
**Scope:** Shared Item persistence only

## Ownership

- `apps/Shared/manifest.json` owns Shared Foundation app registration using the
  existing `shared_contract.kind = shared_app` governance shape. Manufacturing
  is recorded as the future consumer; no runtime consumer was wired in IPM-003.
- `apps/Shared/migrations/20261123_000001_create_shared_items.sql` owns the
  `shared_items` schema migration.
- `shared/Item/Foundation/Contracts/` owns the identity and repository contracts.
- `shared/Item/Foundation/Repositories/MysqlItemRepository.php` owns MySQL
  persistence mechanics.
- `shared/Item/Foundation/Services/DatabaseItemIdentityService.php` owns
  validation, lifecycle transitions, transaction boundaries, and read-back.
- `shared/Item/Foundation/Services/ItemIdentityService.php` remains the
  historical JSON compatibility implementation.

No Product mapping, tenant migration, BOM identity change, IPM table, or
installed/production database operation was included.

## Identity decision

`code_ref` is globally unique through `uniq_shared_items_code_ref`. The
existing contract describes one canonical business reference and no actual
namespace authority exists elsewhere in the repository. A namespace-qualified
uniqueness scheme would therefore invent a framework and could permit duplicate
canonical references. Namespace-qualified mapping remains deferred until a
concrete second consumer establishes the requirement.

## Lifecycle verification

The manifest passes `AppManifestService` validation and uses existing fields:
`id`, `app_key`, `package_type`, `name`, `version`, `type`, `min_core_version`,
`dependencies`, `entry`, `migrations_path`, `permissions`, and lifecycle flags.
It declares no permissions, menus, widgets, hooks, modules, routes, tenant
activation, or licensing fields. Its inert entry file registers no routes.

`AppInstallService` resolves the declared migration directory and calls the
existing `AppMigrationService` inside the existing installation transaction.
There is no second migration runner. Shared has no cross-app migration
dependency; its migration requires only the Core migration ledger, which the
existing install path creates before application migrations.

## Verification

Database-free:

- Shared persistence contract probe: **32/32**
- Historical JSON Item probe: **19/19**
- Isolated source-root runner: **17/17**
- PHP lint and `git diff --check`: passed

Disposable database:

- Fresh runner-owned MySQL 9.6 instance, `--no-defaults`, loopback-only
- Staged source root outside the repository
- Staged `shared/` and existing `scripts/testing` guard tree
- Migration/repository probe: **17/17**
- Installed/development/production database access: none

The existing migration service records the migration SHA-256 and skips an
already-applied ledger row on replay. It does not currently reject a changed
SQL checksum for an already-applied row; that pre-existing general limitation
is reported, not changed, in IPM-003.

## Deferred next slice

IPM-004 should define a bounded, explicitly verified
`manufacturing_product_item_map` only after Manufacturing owner review. It
should preserve `products.id`/`item_ref`, avoid generic polymorphic mapping,
and remain separate from tenant backfill.
