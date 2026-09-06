# Local Deployment and Update Channel Plan

Status: Planning baseline. No runtime behavior. No file moves. No PHP changes.

Date: 2026-08-19

## Context

Before building Hospitality, Susankhya OS needs a boring, repeatable local deployment and update path.

The repository already has deployment and upgrade machinery:

- `DeploymentReadinessService`
- `ReleasePackagingService`
- `ReleaseHistoryService`
- `UpgradeAssistantService`
- `VersionCatalogService`
- `scripts/system/check_deployment_readiness.sh`
- `scripts/system/check_deployment_readiness_portable.php`

The next work should consolidate these into a local-first deployment/update workflow instead of adding new Hospitality surfaces on top of an uncertain delivery model.

## Decision

Focus on **local deployment first** with a **local update channel**.

Do not start with cloud distribution, marketplace distribution, hosted update APIs, or automatic background updates.

The initial channel should be filesystem-backed:

```text
Local Release Source
  -> local release package directory
  -> update channel manifest
  -> readiness check
  -> preview
  -> apply
  -> verify
  -> rollback/recovery notes
```

## Vocabulary

Use these terms for deployment work:

| Term | Meaning |
|---|---|
| Deployment | installing/running Susankhya OS in a target environment |
| Release Package | zip/archive generated from a readiness-approved source state |
| Update Channel | discoverable source of release metadata and packages |
| Local Channel | filesystem-backed update channel for local or controlled installs |
| Preview | read-only compatibility/readiness check before applying |
| Apply | explicit upgrade/install operation |
| Verify | post-apply readiness and health check |

Avoid reintroducing Suite as deployment terminology. Existing code may still use `suite` for older app grouping, but new deployment plans should speak in app/module/package/channel terms unless they explicitly refer to a future first-class Suite.

## Local Update Channel V1

Minimum channel shape:

```text
storage/update-channels/local/
  channel.json
  releases/
    susankhya-os-<version>.zip
    susankhya-os-<version>.json
```

Channel metadata should include:

- channel name, e.g. `local`
- current channel schema version
- release version
- package filename/path
- package checksum
- generated timestamp
- minimum/current compatible app version
- release notes summary
- readiness summary
- migration warnings

## First Milestone

Make local deployment/update usable without Hospitality:

- [ ] document current deployment readiness entrypoints
- [ ] run the local deployment readiness command on a clean working tree
- [ ] inspect existing release package generation flow
- [ ] define local update channel manifest schema
- [ ] generate a local channel manifest from an existing release package
- [ ] preview an update from the local channel without applying it
- [ ] define what apply can safely do in V1
- [ ] define post-update verification and recovery checklist

## V1 Non-Goals

Do not include:

- remote hosted update server
- automatic background update checks
- marketplace/plugin-store distribution
- multi-tenant cloud deployment
- first-class Suite lifecycle
- Hospitality app generation

## Open Questions

- Should local update packages be generated from `main` only, or from any readiness-passing commit?
- Should V1 apply replace source files, or only prove the package/channel/preview path first?
- Should update channel metadata live under `storage/` only, or should a channel template live under `packages/` or `docs/contracts/`?
- What is the minimum rollback guarantee for local installs?
- Should Composer dependency installation be part of apply, or a documented post-pull step for V1?

## Relationship To Hospitality

Hospitality remains blocked behind two foundations:

1. local deployment/update channel
2. app ownership classification

Deployment comes first because it gives the system a reliable way to ship and upgrade before a new major business app is introduced.
