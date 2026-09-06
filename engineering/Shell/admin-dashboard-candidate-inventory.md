# Admin Dashboard Candidate Inventory

The canonical machine-readable inventory is `admin-dashboard-candidate-inventory.json`.

## Current coverage

- 17 active code-first candidates after removal of both parity-complete legacy admin dashboards
- 17 promoted canonical capability destinations
- 0 implementing candidates
- Platform-admin catalog at `/admin/system-tools/upgrade-catalog` with destination status
- Platform mode and environment
- Platform health, logs, and diagnostics
- Apps/package lifecycle
- Setup and release workflows
- Schema/Base operations
- Access, users, profiles, audit, and notifications
- Organization configuration
- Routes and architecture health
- Governed data control
- Widget/design builders
- Unified read-only backup/export/restore activity monitoring

## Management rules

1. Candidates originate from services, controllers, routes, actions, permissions, and tests—not navigation labels.
2. Current UI is evidence only; target UI is designed from capability and workflow reality.
3. Every mutation must retain authorization, CSRF, confirmation, validation, audit, and error behavior.
4. Source surfaces remain accessible until candidate parity and owner approval; approved duplicate sources are then removed from the active inventory.
5. A candidate advances with evidence recorded in its `parity_requirements`; `promoted` means the canonical destination is active, not that specialist owner workflows should be deleted.
6. Cleanup is a separate owner-approved state, never an automatic consequence of promotion.

## First managed candidate

`ADM-MODE-001` — Platform behavior mode.

Platform Admin Status tracks this candidate and links to the complete catalog. The canonical selector and feedback now live only in the System Tools Platform Mode workspace.
