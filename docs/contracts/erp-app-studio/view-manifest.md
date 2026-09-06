# ERP App Studio View Manifest Contract

Schema: `studio.view-manifest.v1`

Status: draft contract only

Defines a future rendered view. It does not create PHP, templates, JavaScript, or CSS.

Required sections:

- `view`: app key, module key, view key, route path, surface, wrapper.
- `layout`: view kind such as table, form, detail, dashboard, or queue.
- `data_contract`: adapter intent, filters, fields, and empty state key.
- `security`: auth, roles, CSRF-for-mutations intent.
- `i18n`: title, description, labels, and empty state keys.

Operator-facing views must stay under `/u/{username}/*`. Admin/governance views must not use the operator wrapper.
