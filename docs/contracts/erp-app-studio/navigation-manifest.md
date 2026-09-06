# ERP App Studio Navigation Manifest Contract

Schema: `studio.navigation-manifest.v1`

Status: draft contract only

Defines a future navigation contribution. It does not update runtime navigation files.

Required sections:

- `navigation`: scope, owner app, key, label key, URL, section, order.
- `active_patterns`: exact and prefix route patterns.
- `visibility`: role and permission intent.
- `governance`: wrapper confinement and no cross-layer escape flags.

Navigation labels must remain localization-driven.
