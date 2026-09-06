# Platform/System Style Registry

Status: Contract defined, write ready. No runtime Shell, Studio Apply, or public/assets consumption connected.

Purpose:

- Owner home for approved active/default style registry truth.
- Platform/System governance boundary for approved style metadata and resolution.
- Clear separation from Studio draft workflow and Shell runtime consumption.

Current state:

- `ApprovedStyleRegistryContract` exposes `getValue()`, `setValue()`, `isWritable()`.
- `ApprovedStyleRegistry` implements the contract with allowlist support.
- Allowlist: `radius.scale` only. Allowed values: `sharp`, `soft`, `round`.
- Storage: `storage/platform/style-registry/approved-values/{socketId}.json`.
- Provenance context recorded: `request_id`, `snapshot_id`, `applied_by_user_id`.
- Unknown sockets and invalid values are rejected server-side.

Not connected:

- Customization Studio does not call `setValue()` yet. Visual Customizer Apply is not implemented.
- Shell does not call `getValue()` yet. Runtime consumption is a separate phase.
- No route registration.
- No runtime load or consumption.
- No public/assets source-truth behavior.
- No Core changes.
- No ThemeTool changes.

Boundary enforcement:

- Customization Studio may draft/edit/preview/approve/snapshot only.
- Shell may define sockets/consumers only.
- Platform style registry holds approved values but is not consumed by runtime yet.

Owner paths:

- Contracts: apps/Platform/StyleRegistry/Contracts
- Services: apps/Platform/StyleRegistry/Services
- Resources: apps/Platform/StyleRegistry/Resources
- Diagnostics: apps/Platform/StyleRegistry/Diagnostics
