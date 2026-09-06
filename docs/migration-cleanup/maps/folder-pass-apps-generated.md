# Folder Pass: apps/Generated

Detailed cleanup pass for `apps/Generated/`.

## Snapshot

Generated app inventory extracted from manifests:

- `hardening_app` (modules: 1, routes: 1)
- `inventory_app` (modules: 2, routes: 2)
- `lifecycle_app` (modules: 1, routes: 1)
- `manufacturing_app` (modules: 1, routes: 1)
- `manufacturing_studio` (modules: 2, routes: 2)
- `rollback_app` (modules: 1, routes: 1)
- `runtime` (modules: 0, routes: 0)
- `sample_app` (modules: 1, routes: 1)
- `tmp` (modules: 0, routes: 0)

## Runtime Coupling Findings

`apps/Generated/` is actively referenced by runtime and tooling layers:

- Public runtime loading paths in `public/index.php` and `public/router.php`.
- Studio write/apply/linking services in `apps/Studio/Services/*`.
- Asset and navigation checks in architecture/scripts tooling.

## Registration Evidence

`core_apps` currently contains only:

- `manufacturing`
- `platform`
- `procurement`
- `sbaio`
- `shell`
- `studio`

Implication:

- Generated app keys (`inventory_app`, `sample_app`, etc.) are not first-class installed apps in `core_apps`.
- They are generated artifacts consumed by runtime/tooling contracts and must still be treated as live assets.

## Cleanup Risk

- Blind pruning of generated apps is unsafe.
- Any removal must respect route/nav/style registry and Studio ownership contracts.

## Safe Next Actions

1. Build active/inactive matrix from DB app status + manifest ownership.
2. Separate `tmp` lifecycle policy from persistent generated apps.
3. Introduce explicit archive policy for generated apps that are disabled and unreferenced.
4. Add dry-run audit script before any deletion/move.

## Initial Classification

- Keep as generated runtime/tooling assets until explicit archive policy exists:
	- `inventory_app`, `manufacturing_app`, `manufacturing_studio`, `runtime`
- Review candidates for archive workflow (not direct delete):
	- `sample_app`, `lifecycle_app`, `hardening_app`, `rollback_app`, `tmp`
