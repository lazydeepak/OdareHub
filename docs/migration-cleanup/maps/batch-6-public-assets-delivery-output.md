# Batch 6 Public Assets Delivery-Output Diagnostic

Status: executed as read-only diagnostic and documentation only.

No public assets were deleted or moved. Runtime behavior, Core behavior, and CSS
behavior were not changed.

## Architecture Basis

- App/module/plugin owners own source CSS and assets for their surfaces.
- Shell owns shared runtime globals and wrapper composition assets.
- `public/assets/apps/...` is delivery output/cache, not source truth.
- Public assets must not become hidden app-specific source truth.

## Diagnostic Script

- script: `scripts/architecture/check_public_assets_delivery_output.sh`
- mode: read-only diagnostic
- current result: pass

Local diagnostic evidence:

- 48 public asset files were classified.
- 24 owner style entries are declared by app/generated manifests.
- 24 app-scoped public CSS delivery targets match owner source CSS exactly.
- 0 app-scoped files under `public/assets/apps/...` lack a manifest-declared
  owner source.
- classification counts: 15 DELIVERY_OUTPUT, 9 GENERATED_OUTPUT, 11
  SHARED_PLATFORM_ASSET, 6 KEEP_COMPAT, 5 SOURCE_ASSET, and 2 BRANDING_ASSET.

## Direct Answers

1. Which public assets are generated/published output?
   - `public/assets/apps/...` CSS files that are declared by manifests and
     produced by `scripts/assets/publish_registered_css.php`.
   - Generated app targets under `public/assets/apps/{generated_app}/...` are
     GENERATED_OUTPUT.
   - First-class app/module targets under `public/assets/apps/{app}/...` are
     DELIVERY_OUTPUT.

2. Which public assets are true source assets?
   - Shared global runtime CSS in `public/assets/normalize.css`,
     `public/assets/theme.css`, `public/assets/layout.css`, and
     `public/assets/wrapper-shared.css`.
   - Branding assets under `public/assets/branding/...`.
   - Operator realtime/offline static assets under `public/assets/operator-*`
     and the root service worker compatibility file `public/operator-sw.js`.
   - Deprecated shim files such as `public/assets/admin-surface.css` remain
     public compatibility assets until their references are retired.

3. Which owner owns each asset source?
   - Shell owns shared global/runtime CSS and Shell app surface CSS.
   - Manufacturing, Platform, Procurement, SBAIO, and generated app owners own
     their manifest-declared CSS sources under `apps/...`.
   - Platform/Organization branding owns branding assets.
   - Shell/operator runtime owns operator realtime/offline public assets until
     a relocation policy exists.

4. Are any app-specific styles living only in public?
   - Current answer: no app-scoped CSS under `public/assets/apps/...` lacks a
     manifest-declared owner source.
   - Compatibility shims in `public/assets/*.css` still exist, but they point to
     owner CSS or global Shell assets and are KEEP_COMPAT, not deletion
     candidates in this batch.

5. Are public assets duplicated from app-owned CSS?
   - Yes. `public/assets/apps/...` intentionally duplicates app-owned source CSS
     as delivery output.
   - The publisher reports all 24 delivery targets are up to date and match
     their owner sources.

6. What must be true before public asset cleanup/move/delete?
   - Owner source parity must pass through the publisher and asset integrity
     gate.
   - Public references must be inventoried and updated.
   - No app-specific public-only asset may remain without an owner source.
   - Compatibility shims must have zero runtime references or a safe redirect
     path.
   - Branding/operator realtime assets need explicit owner relocation or
     retention policy before moves.

## Classification

| Asset group | Classification | Owner | Cleanup decision |
|---|---|---|---|
| `public/assets/apps/*` | DELIVERY_OUTPUT / GENERATED_OUTPUT | owning app/module/generated app | keep; regenerate from owner source, do not edit by hand |
| `public/assets/branding/platform/odarehub/*` | SHARED_PLATFORM_ASSET | Platform branding | keep as canonical public platform branding |
| `public/assets/branding/ipm-logo.*` | BRANDING_ASSET / KEEP_COMPAT | Platform/Organization branding compatibility | keep until branding source policy exists |
| `public/assets/normalize.css` | SHARED_PLATFORM_ASSET | Shell shared runtime | keep as global source asset |
| `public/assets/theme.css` | SHARED_PLATFORM_ASSET | Shell shared runtime | keep as global source asset |
| `public/assets/layout.css` | SHARED_PLATFORM_ASSET | Shell shared runtime | keep as global source asset |
| `public/assets/wrapper-shared.css` | SHARED_PLATFORM_ASSET | Shell shared runtime | keep as wrapper source asset |
| `public/assets/admin-surface.css` | KEEP_COMPAT | Shell compatibility shim | keep until references are retired |
| `public/assets/operator-surface.css` | KEEP_COMPAT | Shell compatibility shim | keep until service worker/reference cleanup |
| `public/assets/display-floor.css` | KEEP_COMPAT | Manufacturing display shim | keep until references are retired |
| `public/assets/procurement.css` | KEEP_COMPAT | Procurement shim | keep until references are retired |
| `public/assets/work-entry.css` | KEEP_COMPAT | Manufacturing work-entry shim | keep until references are retired |
| `public/assets/app.css` | KEEP_COMPAT | Shell compatibility stub | keep until legacy reference check is complete |
| `public/assets/operator-realtime.css` | SOURCE_ASSET | Shell/operator realtime | keep until owner relocation policy exists |
| `public/assets/operator-realtime.js` | SOURCE_ASSET | Shell/operator realtime | keep until owner relocation policy exists |
| `public/assets/operator-sw.js`, `public/operator-sw.js` | SOURCE_ASSET / KEEP_COMPAT | Shell/operator runtime | keep until service worker path policy exists |
| `public/assets/operator-offline.html` | SOURCE_ASSET | Shell/operator offline runtime | keep until owner relocation policy exists |

## Runtime Coupling Summary

- Header/layout surfaces consume CSS through `StyleRegistryService`.
- `StyleRegistryService` loads shared globals from root public CSS and app
  surface styles from `/assets/apps/...`.
- `public/index.php` and `public/router.php` also resolve app-scoped CSS URLs
  directly from owner source paths, while the publisher keeps delivery files
  current for deployment/static serving.
- Service worker precache still references compatibility assets such as
  `/assets/operator-surface.css`.

## Future Safe Work

1. Add a focused compatibility-shim reference report for root
   `public/assets/*.css`.
2. Decide whether Shell global CSS should remain public source or move behind a
   Shell-owned source/publish path.
3. Define branding asset source and retention policy before moving branding
   files.
4. Keep generated/public asset cleanup blocked on storage/appstudio provenance
   and generated app archive policy.
