# Repository Scanner - Work

## Current Focus

Transform the current repository tree scanner into the canonical Repository Scanner for OdareHub.

The immediate goal is to build a trustworthy read-only repository inspection platform that becomes the engineering foundation for Repository Doctor, App/Module Upgrade workflows, and future engineering agents.

**Current phase:** Phase 1 - Repository Truth Foundation.

Build deterministic repository discovery without introducing repository mutations or repair capabilities.

## Phase 1 Freeze — Repository Truth Foundation

All Phase 1 items complete. Active development paused. Next phases deferred.

## Completed

- [x] Repository inventory scan.
- [x] Repository tree explorer.
- [x] File type classification.
- [x] Repository scope scanning.
- [x] File inspector.
- [x] Repository search — Search V1: unified client-side search across files, owners, entities, routes, and engineering workspaces. `buildSearchIndex()` in `RepoTreeScannerService`, embedded JSON index, debounced input, type-badged results, inspect_file action opens File Inspector, select_owner action activates Owner Lens filter. Probe: 293/293 pass.
- [x] Suspicious file discovery.
- [x] Unknown file discovery.
- [x] Tree filter subtree hiding — runtime/dependency subtrees now hidden as complete subtrees (not just individual files) in hide-runtime default view.
- [x] Tree rendering improvements V1 — folder-first sorting, root files group compaction, classification badges, enhanced metadata.
- [x] Server-side default runtime hiding — runtime/dependency file and folder nodes emit `hidden` attribute in server-rendered HTML so FOUC-free default view; review-only files inside non-runtime dirs correctly keep parent visible; probe tests verify hidden/non-hidden attribute presence.
- [x] Owner Lens V1 — `discoverOwners()` with owner root heuristics (apps/plugins/platform/engineering), `assignOwnerKey()` longest-prefix path matching, `assignOwnerKeysToTree()` recursive by-reference population, `computeOwnerStats()` per-owner metrics. Owner filter `<select>`, summary cards with workspace status, `data-owner` tree attributes. Probe: 25 owner assertions.
- [x] Entity Discovery V1 — `discoverEntities()` extracts controllers (class declarations), services (class/Services dir), views (Views dir files), routes (Route registration regex). `getEntityTypeRegistry()` and `getEntityTypeKeys()` for type metadata. Per-owner entity maps, `entity_summary` with aggregate counts and distribution. Probe: 40+ entity assertions.
- [x] Repository Search V1 — `buildSearchIndex()` produces unified client-side index across files/owners/entities/routes/workspaces. Debounced search input with type-badged results, `inspect_file` and `select_owner` actions. Owner-only filter checkbox, type chips bar. Probe: 293+ pass.
- [x] Polish V1 — Scan Summary section wraps cards + inventory/file-type collapsible. Review Buckets section replaces Classification Summary table with alert cards (review/runtime/snapshot/unknown). Owner Lens hierarchy collapsed by default. Raw Tree collapsed by default inside `<details>`. Entity Summary absorbed next to Entity Explorer. Locale keys externalized. Probe: 306/306 pass.
- [x] Owner Lens ↔ Engineering Workspace linking V1 — Workspace Status cards render `EngineeringWorkspaceResolver::buildWorkspaceLinks()` for discovered owners; Owner filter highlights cross-highlight matched workspace doc tabs; `probe_owner_lens_ew_linking.php` with 33 assertions.
- [x] Visible rename — All user-facing "Helper Tool" text changed to "Repository Scanner" (locale keys, manifest, preview title/subtitle, EW back link override via `$parentLabelOverrides` in `EngineeringWorkspacePageContextResolver`). Probe: EW parent nav 21/21, helper scan 329/329, strip context 63/63.

## Next (Deferred)

- [ ] Phase 2 - Repository Intelligence: entity relationship discovery, registered surface discovery, and repository dependency mapping.
- [ ] Phase 3 - Repository Doctor Foundation: repository health model, duplicate detection, dead/orphan discovery, contract validation, and architecture smell detection.
- [ ] Phase 4 - Engineering Platform: refactor planning, owner upgrade workflow, app creation guidance, and engineering agent support.

## Evidence

- Repository Scanner can inspect every repository scope through deterministic, read-only analysis while providing trustworthy repository truth for downstream engineering tools.
- Current priority: establish repository truth before introducing diagnosis, repair, or automation.
