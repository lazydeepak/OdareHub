# App Platform manifest.json Spec

This platform uses JSON manifests for installable apps.

## File name

- `manifest.json`

## Required keys

- `id` (string, lowercase app key)
- `name` (string)
- `version` (string)
- `type` (string: business, extension, shared)
- `min_core_version` (string)
- `dependencies` (array of app ids)
- `entry` (string path to app entry PHP file)
- `migrations_path` (string path to SQL migrations directory)
- `permissions` (array of permission keys)
- `can_disable` (boolean)
- `can_uninstall` (boolean)
- `can_export` (boolean)

## Optional keys

- `modules` (array of strings or objects `{ key, name }`)
- `hooks` (array of objects for runtime hook registration)
- `menus` (array for menu metadata)
- `widgets` (array for dashboard/widget metadata)

### Operator focus_views hook contract: `focus_tokens`

Applies ONLY to hooks with `type=operator_surface`, `surface=operator`,
`region=focus_views`.

```json
{
  "type": "operator_surface",
  "key": "{app}.operator.focus_views",
  "surface": "operator",
  "region": "focus_views",
  "provider": "Apps\\{App}\\Services\\...::contribute",
  "provider_file": "Services/...php",
  "focus_tokens": ["production", "processing", "qc"],
  "order": 20
}
```

Rules:

- `focus_tokens`: array of strings; each token lowercase `[a-z0-9_-]+`; each
  token identifies an actual contributed operator focus capability.
- A single focus_views hook MAY declare multiple tokens (one hook, many views).
- The hook KEY identifies the hook and is NEVER a focus token source.
- The provider's returned `focus_views.view_map` MUST expose exactly the same
  declared capability set (declaration/provider parity is continuously enforced
  by `scripts/architecture/check_operator_focus_declaration_parity.sh`).
- The declaring app owns those focus capabilities. Declaration grants NO ACL
  authority and does not place the capability into any user's experience;
  assignment/profile/override/permission gates still apply at runtime.
- Lifecycle: a disabled or uninstalled owning app contributes no active
  capability (materialized hook rows are joined against app status).
- Compatibility: built-in Shell operator views remain authoritative on token
  collisions; contributed tokens may not redefine them.

## Example

```json
{
  "id": "manufacturing",
  "name": "Manufacturing",
  "version": "1.0.0",
  "type": "business",
  "min_core_version": "3.0.0",
  "dependencies": [],
  "entry": "routes.php",
  "migrations_path": "migrations",
  "permissions": ["manufacturing.view", "manufacturing.manage"],
  "can_disable": true,
  "can_uninstall": true,
  "can_export": true
}
```

## Lifecycle states

`uploaded`, `installed`, `enabled`, `disabled`, `broken`, `upgrade_pending`, `uninstalled`

## Safe schema sync policy (default)

Only additive SQL is allowed in app migrations by default:

- `CREATE TABLE`
- `ALTER TABLE ... ADD COLUMN`
- `ALTER TABLE ... ADD INDEX|KEY|UNIQUE|CONSTRAINT|FOREIGN KEY`

Destructive actions are blocked by default:

- `DROP TABLE`, `DROP COLUMN`, `DROP INDEX`
- `RENAME TABLE`, column rename mutations
- any non-additive schema mutation unless explicitly enabled by a future destructive migration mode.
