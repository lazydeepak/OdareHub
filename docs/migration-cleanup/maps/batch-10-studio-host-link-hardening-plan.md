# Batch 10 Studio Host-Link Hardening Plan

Status: executed as read-only diagnostic and documentation only.

No files were moved, deleted, archived, or mutated. Runtime behavior and Core
behavior were not changed.

Authority:

- Batch 8: `docs/migration-cleanup/maps/batch-8-studio-boundary-priority-inventory.md`
- Batch 9: `docs/migration-cleanup/maps/batch-9-studio-runtime-adjacent-separation-map.md`

## Architecture Basis

- Studio remains an optional system app with conditional host exposure.
- Host links must fail safely when Studio is disabled, absent, or partial.
- `/ops/*` compatibility bridges remain transitional and must not be removed in
  this planning slice.
- Hardening in next slices must preserve current route behavior and ownership.

## Diagnostic Script

- script: `scripts/architecture/check_studio_host_link_hardening.sh`
- mode: read-only diagnostic
- current result: pass

## Inventory And Classification

Legend:

- `CLEAN_OPTIONAL_LINK`
- `GUARDED_COMPAT_BRIDGE`
- `NEEDS_GUARD_HARDENING`
- `ROUTE_COMPATIBILITY`
- `DO_NOT_MOVE`
- `INVESTIGATE`

| Path | Reference type | Current guard state | Owner | Risk | Optional/no-op safe | Required future hardening | Classification |
|---|---|---|---|---|---|---|---|
| `apps/Platform/routes.php` (`/ops/gui-studio` redirect) | Route alias bridge to canonical Studio workspace | No install/enabled check on redirect route itself | Platform | Medium: alias still assumes `/apps/studio` exists | Partially safe: falls through to runtime if canonical missing | Add pre-redirect enabled/install guard with same redirect contract and non-breaking fallback | `ROUTE_COMPATIBILITY`, `NEEDS_GUARD_HARDENING`, `DO_NOT_MOVE` |
| `apps/Platform/routes.php` (`$studioBridgeEnabled` + `studio_register_gui_studio_routes`) | Compatibility route registration bridge | DB status check (`core_apps.status=enabled`) + file existence + function existence | Platform + Studio | Low | Yes | Keep guard chain; later centralize guard helper to reduce drift | `GUARDED_COMPAT_BRIDGE`, `ROUTE_COMPATIBILITY`, `DO_NOT_MOVE` |
| `apps/Platform/routes.php` (`/ops/design-studio*`) | Legacy design-studio compatibility routes | Env flag `OPS_ENABLE_LEGACY_DESIGN_STUDIO` + auth/role checks + redirect-to-canonical | Platform with Base legacy views | Low to Medium | Yes when env flag disabled (redirect path) | Keep compatibility routes; later isolate to dedicated compatibility router wrapper | `GUARDED_COMPAT_BRIDGE`, `ROUTE_COMPATIBILITY`, `DO_NOT_MOVE` |
| `apps/Platform/Views/ops/platform_operations.php` | Host operations card link to `/apps/studio` | Input guard from controller (`$studioAppEnabled`) before card emission | Platform | Low | Yes | Keep conditional rendering; later derive from shared host-link policy provider | `CLEAN_OPTIONAL_LINK` |
| `apps/Platform/Services/DashboardAggregatorService.php` | Studio-driven generated task aggregation | Guarded `loadStudioServiceIfEnabled()` with DB check + file/class exists | Platform + Studio runtime bridge | Low | Yes | Extract shared Studio guard utility (no behavior change) | `CLEAN_OPTIONAL_LINK`, `INVESTIGATE` |
| `apps/Platform/Services/RouteViewBridgeService.php` | Studio open-link generation for route diagnostics | Guarded `loadStudioServiceIfEnabled()` with DB check + file/class exists | Platform + Studio runtime bridge | Low | Yes | Keep behavior; later reuse centralized guard utility | `CLEAN_OPTIONAL_LINK`, `INVESTIGATE` |
| `apps/Shell/Services/AdminLayerWrapperComposer.php` | Admin sidebar Studio exposure and Studio entry discovery | Guarded `isStudioSystemAppEnabled()` + file existence + class existence | Shell + Studio | Low | Yes | Keep conditionals; later remove duplicate DB checks by shared guard method | `CLEAN_OPTIONAL_LINK`, `DO_NOT_MOVE` |
| `apps/Shell/Services/StyleRegistryService.php` | Studio-generated app style fallback and disk scan | Disk/path existence checks; no Studio enabled-status check by design | Shell | Medium: can scan generated apps even when Studio app disabled | Mostly safe | Harden with explicit policy toggle or manifest/registry provenance check while preserving output | `INVESTIGATE`, `NEEDS_GUARD_HARDENING` |
| `apps/Shell/Services/BrandIdentityService.php` | Product naming reference (`ERP App Studio`) | Constant only, no runtime coupling | Shell | Low | Yes | None required for host-link safety; keep under watch for labeling consistency | `INVESTIGATE` |
| `plugins/Base/Views/ops/design_studio.php` | Legacy Base-rendered `/ops/design-studio` list surface | Guarded upstream by Platform route env/auth/role checks | Base legacy bridge | Medium if route guards regress | Yes via upstream guards | Keep view as compatibility debt only; do not expand behavior ownership | `GUARDED_COMPAT_BRIDGE`, `DO_NOT_MOVE` |
| `plugins/Base/Views/ops/design_studio_edit.php` | Legacy Base-rendered `/ops/design-studio/edit` surface | Guarded upstream by Platform route env/auth/role checks | Base legacy bridge | Medium if route guards regress | Yes via upstream guards | Keep view as compatibility debt only; do not expand behavior ownership | `GUARDED_COMPAT_BRIDGE`, `DO_NOT_MOVE` |
| `plugins/Base/**` (`/ops/gui-studio`) | Base bridge presence check | No direct `/ops/gui-studio` reference in Base views | Base | Low | Yes | Preserve as absent; keep alias ownership in Platform route layer only | `CLEAN_OPTIONAL_LINK` |
| `apps/Studio/manifest.json` (`hooks`) | Host-surface contribution declaration (`studio.me.*`) | Manifest-level declaration only; runtime load depends on app enablement | Studio | Low | Yes | Keep hook declarations; later validate host contribution contract via shared guard resolver | `CLEAN_OPTIONAL_LINK`, `DO_NOT_MOVE` |
| `apps/Studio/manifest.json` (`routes` alias `/ops/gui-studio`) | Declared legacy alias compatibility metadata | Explicit alias metadata in app manifest | Studio | Low | Yes | Keep alias metadata until compatibility retirement plan is approved | `ROUTE_COMPATIBILITY`, `DO_NOT_MOVE` |
| `apps/Studio/navigation.php` | Studio navigation contribution (`/apps/studio`) | App-owned nav contract; visible-if role gating | Studio | Low | Yes | Keep as owner navigation truth; do not duplicate in host app static menus | `CLEAN_OPTIONAL_LINK`, `DO_NOT_MOVE` |

## Installed/Enabled Guard Check Inventory

| Guard check location | Guard logic | Notes |
|---|---|---|
| `apps/Platform/routes.php` (`$platformStudioSystemAppEnabled`) | `core_apps` enabled-status check for Studio | Controls runtime Studio includes and generated module route registration |
| `apps/Platform/routes.php` (`$studioBridgeEnabled`) | enabled-status + file + function checks | Controls `/ops/gui-studio` compatibility bridge registration |
| `apps/Platform/routes.php` (`/ops/design-studio*`) | env flag + auth + role checks | Keeps legacy design-studio bridges conditional |
| `apps/Platform/Views/ops/platform_operations.php` | conditional render via `$studioAppEnabled` | Keeps operations-card link optional |
| `apps/Platform/Services/DashboardAggregatorService.php` | enabled-status + file/class checks | Prevents Studio task aggregation when unavailable |
| `apps/Platform/Services/RouteViewBridgeService.php` | enabled-status + file/class checks | Prevents Studio route-open link generation when unavailable |
| `apps/Shell/Services/AdminLayerWrapperComposer.php` | enabled-status + file/class checks | Prevents sidebar Studio entries when unavailable |

## Direct Answers

1. Which Studio host links are already clean optional links?
   - `apps/Platform/Views/ops/platform_operations.php` conditional card link.
   - `apps/Platform/Services/DashboardAggregatorService.php` guarded Studio usage.
   - `apps/Platform/Services/RouteViewBridgeService.php` guarded Studio usage.
   - `apps/Shell/Services/AdminLayerWrapperComposer.php` guarded sidebar exposure.
   - `apps/Studio/navigation.php` owner-scoped Studio navigation contribution.
   - `apps/Studio/manifest.json` host hook declarations.

2. Which still assume Studio exists?
   - `apps/Platform/routes.php` `/ops/gui-studio` redirect route currently redirects
     unconditionally to `/apps/studio`.
   - `apps/Shell/Services/StyleRegistryService.php` generated-app fallback logic can
     operate without explicit Studio enabled-status checks.

3. Which `/ops` bridges must remain for compatibility?
   - `/ops/gui-studio` alias bridge and conditional `studio_register_gui_studio_routes`
     registration.
   - `/ops/design-studio`, `/ops/design-studio/save`, `/ops/design-studio/edit`,
     `/ops/design-studio/update` legacy routes while compatibility is still required.

4. Which references can be hardened later without route behavior changes?
   - Add enabled/install pre-check to `/ops/gui-studio` redirect while preserving
     alias and fallback contract.
   - Consolidate duplicate Studio enabled checks across Platform/Shell services
     into one shared helper without changing decisions.
   - Add explicit provenance guard in StyleRegistry fallback path while preserving
     generated-app style loading behavior.

5. What is the safest first code-level hardening slice after this plan?
   - Introduce a single shared `isStudioEnabledAndLoadable()` helper in host
     layers (Platform + Shell service boundary), then replace duplicated guard
     snippets in:
     - `apps/Platform/Services/DashboardAggregatorService.php`
     - `apps/Platform/Services/RouteViewBridgeService.php`
     - `apps/Shell/Services/AdminLayerWrapperComposer.php`
   - Keep return values and fallback behavior exactly unchanged.

## Priority Outcome

1. Host-link ownership and guard posture are explicitly mapped.
2. Compatibility bridges are preserved and tagged as do-not-move.
3. Hardening candidates are isolated to non-behavioral guard unification.
4. Next code slice can proceed with minimal risk and no route contract changes.
