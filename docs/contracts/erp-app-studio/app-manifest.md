# ERP App Studio App Manifest Contract

Schema: `studio.app-manifest.v1`

Status: draft contract only

Defines a future System App or Business App. It does not create folders, routes, database rows, migrations, or package artifacts.

Required sections:

- `app`: app key, display name, app taxonomy, route prefix intent, owner.
- `i18n`: title and description key intent.
- `governance`: approval requirement, localization requirement, route confinement flags.
- `lifecycle`: installable, activatable, removable intent.
- `audit`: draft metadata only.

App taxonomy must be one of:

- `system_app`
- `business_app`
