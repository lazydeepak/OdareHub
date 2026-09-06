# Localization Studio v2.1 Readiness Diagnostic Plan

**Status**: Planning/diagnostics only — no implementation.
**Date**: 2026-06-01
**Based on**: Localization Studio v2 contract at `92680501`

> This document converts the approved v2 edit/apply architecture contract into
> future enforceable gate requirements. It defines what must be detected and blocked
> at each readiness level before any implementation phase may begin.
>
> **No implementation is authorized by this document**. It is diagnostic planning only.

---

## 1. Purpose

The v2.1 readiness diagnostic plan serves as the bridge between the v2 architecture
contract and enforceable gate invariants. Its goals:

1. Classify every v2 contract constraint into an existing or future gate invariant.
2. Define readiness levels that gate when each implementation phase may begin.
3. Provide a checklist for future gate authors.
4. Ensure no mutation/write/apply behavior appears before its readiness level is
   reached and its gates pass.

---

## 2. Required Future Diagnostic Categories

Each category is a theme of invariants that future gates must enforce. They are derived
from the v2 contract forbidden shortcuts (section 13) and apply rules (section 4).

### 2.1 No Direct File Writes from Localization Studio UI

| Invariant | Existing | Future |
|---|---|---|
| No `file_put_contents` in LocalizationStudio PHP/JS | ✅ `check_localization_studio_boundaries.sh` | — |
| No `fwrite` in LocalizationStudio PHP/JS | ✅ `check_localization_studio_boundaries.sh` | — |
| No `fopen` in LocalizationStudio PHP/JS | ✅ `check_localization_studio_boundaries.sh` | — |
| No `localStorage` in LocalizationStudio PHP/JS | ✅ `check_localization_studio_boundaries.sh` | — |
| No `navigator.clipboard.write()` beyond read-only export | ⚠️ Partially (allowed for TSV copy) | Must add write-to-file prohibition |
| No file save dialog triggered from browser UI | ❌ Not checked | Future: no `download` attribute on anchors, no `Blob` URL creation for locale files |

### 2.2 No Save/Apply Endpoints Without Approved Apply Contract

| Invariant | Existing | Future |
|---|---|---|
| No POST routes for localization-studio | ✅ `check_localization_studio_boundaries.sh` | — |
| No `function save(` in Services | ✅ `check_localization_studio_boundaries.sh` | — |
| No `function apply(` in Services | ✅ `check_localization_studio_boundaries.sh` | — |
| No `function write(` in Services | ✅ `check_localization_studio_boundaries.sh` | — |
| No `curl_setopt.*CURLOPT_CUSTOMREQUEST.*PUT` in Services | ✅ `check_localization_studio_boundaries.sh` | — |
| No `curl_setopt.*CURLOPT_CUSTOMREQUEST.*PATCH` in Services | ✅ `check_localization_studio_boundaries.sh` | — |
| No save/apply route patterns in `routes.php` | ⚠️ Partially (POST checked, but catch-all route check needed) | Future: scan for any route with save/apply/submit/write/update/delete in name or handler |
| No form action pointing to save/apply endpoints | ❌ Not checked | Future: scan form `action` attributes in views |

### 2.3 No Runtime Consumption of Studio Draft/Proposal State

| Invariant | Existing | Future |
|---|---|---|
| No Shell/Platform/Core references to LocalizationStudio | ✅ `check_localization_studio_boundaries.sh` | — |
| No runtime locale loading from Studio-managed paths | ✅ `check_localization_studio_boundaries.sh` | — |
| No runtime cache of Studio draft state | ❌ Not checked | Future: scan for cache paths referencing LocalizationStudio or draft |
| No runtime translation override from Studio data | ❌ Not checked | Future: scan for `Locale::` or translation loader references to Studio |

### 2.4 No Cross-Owner Locale Writes

| Invariant | Existing | Future |
|---|---|---|
| No locale files stored inside LocalizationStudio folder | ✅ `check_localization_studio_boundaries.sh` | — |
| No locale files moved to LocalizationStudio | ✅ `check_localization_studio_boundaries.sh` | — |
| All locale files remain at owner-owned paths | ✅ confirmed by v1 | — |
| No write path that targets a different owner than the proposal | ❌ Not checked | Future: must verify target owner == proposal owner |
| No write path that targets Core without explicit approval | ❌ Not checked | Future: must block all Core writes unless explicit flag set |

### 2.5 No Apply Without Diff/Preview

| Invariant | Existing | Future |
|---|---|---|
| Diff UI not yet present (no apply possible) | ✅ read-only v1 | — |
| Apply action must require diff generation | ❌ Not checked | Future: apply endpoint must reject if no diff record exists |
| Apply action must require preview generation | ❌ Not checked | Future: apply endpoint must reject if no preview record exists |

### 2.6 No Apply Without Approval

| Invariant | Existing | Future |
|---|---|---|
| No approval workflow exists (safe default) | ✅ read-only v1 | — |
| Apply action must require approved proposal status | ❌ Not checked | Future: apply must check `status == approved` and `approver_id` is set |
| Self-approval must be blocked for medium+ risk | ❌ Not checked | Future: apply must verify approver != author for risk >= medium |

### 2.7 No Apply Without Snapshot

| Invariant | Existing | Future |
|---|---|---|
| No snapshot mechanism exists (safe default) | ✅ read-only v1 | — |
| Apply action must verify snapshot exists | ❌ Not checked | Future: apply must check snapshot exists for target file |
| Apply action must verify snapshot matches current file state | ❌ Not checked | Future: apply must compare pre-apply hash with snapshot |

### 2.8 No Apply Without Post-Apply Diagnostics

| Invariant | Existing | Future |
|---|---|---|
| v1 resource diagnostic exists | ✅ `check_localization_resource_diagnostics.sh` | — |
| Post-apply diagnostic not yet required | ✅ read-only v1 | Future: apply must trigger diagnostic run |
| Apply must fail if post-apply diagnostic shows new warnings | ❌ Not checked | Future: apply must block on critical post-apply failures |

### 2.9 No AI Auto-Apply

| Invariant | Existing | Future |
|---|---|---|
| No AI functionality exists (safe default) | ✅ read-only v1 | — |
| AI output must never set status to approved | ❌ Not checked | Future: AI-sourced proposals must always start as `draft` |
| AI output must never skip validation | ❌ Not checked | Future: AI proposals must pass same validation lifecycle |
| AI output must clearly label source | ❌ Not checked | Future: proposals must record `proposal_source` field |

### 2.10 No Generated Hidden Locale Source of Truth

| Invariant | Existing | Future |
|---|---|---|
| No generated locale cache files | ✅ `check_localization_studio_boundaries.sh` | — |
| No Studio-owned locale file cache | ✅ v1 resource diagnostic | — |
| No merged/compiled locale bundles from Studio | ❌ Not checked | Future: scan for combined locale file generation |
| No locale cache in `var/`, `storage/`, `public/assets/` | ✅ `check_localization_studio_boundaries.sh` | — |

### 2.11 No DB/Cache Side Effects Unless Contract-Approved

| Invariant | Existing | Future |
|---|---|---|
| No locale tables in DB | ✅ confirmed by v1 | — |
| No cache path referencing locale drafts | ❌ Not checked | Future: scan cache paths for locale/draft/translation patterns |
| No DB queries storing locale proposals | ❌ Not checked | Future: scan for DB `INSERT/UPDATE` referencing translation/proposal tables |

### 2.12 No Routes/Endpoints for Mutation Before v2 Implementation Approval

| Invariant | Existing | Future |
|---|---|---|
| No POST routes for localization-studio | ✅ `check_localization_studio_boundaries.sh` | — |
| No save/apply/update/delete routes | ⚠️ Partially (POST checked, but not route name patterns) | Future: expanded route pattern scan |
| No route handler references to save/apply logic | ❌ Not checked | Future: scan route handler files for save/apply/update function references |

---

## 3. Diagnostic Classification

### Already Enforced by Existing Gates (12 categories)

| # | Category | Gate |
|---|---|---|
| 1 | No `file_put_contents`/`fwrite`/`fopen` in LocalizationStudio | `check_localization_studio_boundaries.sh` |
| 2 | No `localStorage` in LocalizationStudio | `check_localization_studio_boundaries.sh` |
| 3 | No POST routes for localization-studio | `check_localization_studio_boundaries.sh` |
| 4 | No `function save/apply/write` in Services | `check_localization_studio_boundaries.sh` |
| 5 | No PUT/PATCH curl in Services | `check_localization_studio_boundaries.sh` |
| 6 | No Shell/Platform/Core references to LocalizationStudio | `check_localization_studio_boundaries.sh` |
| 7 | No locale files in LocalizationStudio folder | `check_localization_studio_boundaries.sh` |
| 8 | No locale files moved to LocalizationStudio | `check_localization_studio_boundaries.sh` |
| 9 | No generated locale cache files | `check_localization_studio_boundaries.sh` |
| 10 | Core locale files untouched | `check_localization_studio_boundaries.sh` |
| 11 | No Content-Disposition/download/attachment/csv headers | `check_localization_studio_boundaries.sh` |
| 12 | Locale file content integrity | `check_localization_resource_diagnostics.sh` |

### Partially Enforced (3 categories)

| # | Category | Gap |
|---|---|---|
| 1 | Write download/file save from browser UI | TSV clipboard copy is allowed as read-only export; future must block file-download patterns for locale data |
| 2 | Save/apply route patterns in routes.php | POST is blocked but route name patterns (save, apply, submit, write, update, delete) are not scanned |
| 3 | Form action save targets | Forms are scanned for POST but not for action attributes pointing to save endpoints |

### Not Yet Enforceable Until Implementation Exists (8 categories)

These cannot be checked until the corresponding implementation phase begins:

| # | Category | Blocks |
|---|---|---|
| 1 | Apply without diff/preview | v2.4 diff/preview implementation |
| 2 | Apply without approval | v2.5 approval implementation |
| 3 | Apply without snapshot | v2.6 apply implementation |
| 4 | Apply without post-apply diagnostics | v2.6 apply implementation |
| 5 | Cross-owner write protection | v2.6 apply implementation |
| 6 | Core write protection | v2.6 apply implementation |
| 7 | AI auto-apply prevention | Future AI-assisted mode |
| 8 | Stale proposal detection | v2.6 apply implementation |

### Future Implementation-Blocking Gates (8 categories)

These must be implemented **before** their corresponding implementation phase:

| # | Gate | Blocks Phase | Must Check |
|---|---|---|---|
| 1 | Diff/preview required before apply | v2.4 | Apply endpoint rejects without diff |
| 2 | Approval required before apply | v2.5 | Apply endpoint rejects without approval |
| 3 | Snapshot required before apply | v2.6 | Apply endpoint rejects without snapshot |
| 4 | Post-apply diagnostic required | v2.6 | Apply fails on diagnostic failure |
| 5 | Owner boundary verification on write | v2.6 | Target owner matches proposal owner |
| 6 | Core write approval flag | v2.6 | Core write requires explicit flag |
| 7 | AI proposal source marking | Future | AI proposals must be marked and never auto-approved |
| 8 | Stale proposal detection | v2.6 | Source value must match before apply |

---

## 4. Readiness Levels

Each level gates what is allowed. A level is reached when all its gates pass.

| Level | Name | Allowed | Gates Required |
|---|---|---|---|
| 0 | v1 read-only complete | v1 inspection, reporting, clipboard-only export | All 24 architecture gates pass, deployment readiness pass |
| 1 | v2 contract archived | v2 architecture design document | Contract document exists on `main` |
| 2 | **Diagnostic plan approved** (this level) | v2.1 readiness diagnostic plan, no implementation | This document approved, no code changes |
| 3 | Read-only draft model placeholder | Draft data structure (class/interface), in-memory mocks, no storage, no persistence, no UI | Draft model gate: no DB writes, no file writes, no localStorage, no routes, no UI mutation |
| 4 | Diff/preview allowed | In-memory diff/preview UI, no approval state, no apply, no file writes | Diff/preview gate: no POST routes, no file writes, no approval state changes, runtime isolation verified |
| 5 | Approval/snapshot/apply implementation | Full workflow, only after separate approved implementation plan | Apply gate: all 12 existing invariants + 8 new implementation-blocking invariants, separate approval document |

### Level Transition Rules

- Each level must be independently validated by architecture gates.
- No level may be skipped.
- Level 5 requires a **separate implementation approval document** — the v2 contract
  and this diagnostic plan are not sufficient authorization.
- If any gate fails, the level is blocked until the failure is resolved and the
  gate passes.
- A level may declare new invariants that retrofit into lower-level gates.

---

## 5. Future Gate Checklist

This checklist is for authors of future diagnostic gates. Each gate should implement
as many of the following checks as applicable to its scope.

### What Each Gate Should Scan

- All PHP and JS files under the LocalizationStudio tool directory
- All PHP and JS files under `apps/Studio/routes.php`
- All PHP, JS, and CSS files under `apps/Shell/`, `apps/Platform/`, `app/` for runtime
  coupling to LocalizationStudio
- Git diff for changes under `app/lang/`, `apps/*/lang/`, `plugins/*/lang/`
- Locale cache artifacts under `var/`, `storage/`, `public/assets/`

### Allowed False Positives

- Read-only clipboard TSV export (`navigator.clipboard.writeText()`) — allowed
- Pre-existing locale files in owner paths — not Studio-generated
- Pre-existing locale cache files tracked in git — not Studio-generated
- Tool manifest and registration files — not mutation

### Forbidden Patterns

- `file_put_contents` in any Studio PHP/JS
- `fwrite` in any Studio PHP/JS
- `fopen` in any Studio PHP/JS (read-only access for inspection must use file wrappers
  or DI, not bare `fopen`)
- `localStorage` in any Studio JS
- `navigator.clipboard.write()` for locale file content (writeText is allowed for TSV)
- `download` attribute on anchor elements pointing to locale data
- `Blob` + `URL.createObjectURL` for locale file download
- POST routes for localization-studio
- Route names containing `save`, `apply`, `submit`, `write`, `update`, `delete`
- Function names containing `save(`, `apply(`, `write(` in Services
- Curl PUT/PATCH in Services
- Runtime file reads from Studio-managed draft paths
- Translation loader overrides referencing Studio data
- DB INSERT/UPDATE referencing locale proposal/translation tables
- Cache writes referencing locale draft/translation keys
- Combined/merged locale file generation
- AI-assisted translation without `proposal_source` marking

### Owner Boundary Checks

- Verify locale file paths belong to a recognized owner pattern
- Verify locale keys belong to the owner's namespace
- Verify no locale files exist under Studio tool directories
- Verify no locale files have been moved from owner paths to Studio paths
- Verify Core locale files are only modified with explicit flag
- Verify cross-owner writes are only allowed with platform admin authorization

### Route/Endpoint Checks

- Scan `apps/Studio/routes.php` for any route matching `localization-studio` with
  POST/PUT/DELETE/PATCH methods
- Scan for any route handler that calls `save`, `apply`, `write`, `update`, `delete`
  functions
- Scan for any route name containing `save`, `apply`, `submit`, `write`, `update`,
  `delete`
- Scan form `action` attributes in view templates for save/apply endpoints

### Storage/Write Checks

- Scan for DB table names containing `locale`, `translation`, `proposal`, `draft`
- Scan for DB INSERT/UPDATE statements referencing locale data
- Scan for cache store/put/set calls with locale-related keys
- Scan for file writes to paths outside owner locale file patterns
- Scan for temp file creation (`tempnam`, `tmpfile`, sys_get_temp_dir)
- Scan for generated locale bundle file creation

### Runtime Loading Checks

- Scan Shell/Platform/Core for `require`/`include` of Studio locale paths
- Scan for `Locale::` class method calls that reference Studio-managed data
- Scan for translation loader overrides in runtime bootstrap
- Scan for runtime config changes that point translation loading to Studio paths
- Scan for `public/assets/` entries that are locale bundles from Studio

---

## 6. Contract Dependencies

| Document | Relationship |
|---|---|
| `docs/architecture/localization-studio-v2-edit-apply-contract.md` | Source contract that defines allow/forbidden rules |
| `docs/architecture/localization-studio-v1-foundation.md` | Defines ownership model and v1 boundary |
| `docs/architecture/localization-studio-v1-checkpoint.md` | Documents v1 completion state |
| `docs/architecture/studio-change-lifecycle-apply-contract.md` | Defines lifecycle stages for apply |
| `docs/architecture/studio-approval-risk-validation-policy.md` | Defines approval requirements by risk |
| `scripts/architecture/check_localization_studio_boundaries.sh` | Primary gate for LocalizationStudio boundaries |
| `scripts/architecture/check_localization_resource_diagnostics.sh` | Resource content validation diagnostic |

---

## 7. Key Architecture Laws

1. **No implementation before gate readiness** — each v2 sub-phase requires its
   readiness level's gates to pass before any code is written.
2. **Levels are not skippable** — v2.3 draft model requires v2.2 diagnostic plan
   approval; v2.6 apply requires all prior levels.
3. **Gates are additive** — new invariants extend existing gates; existing gates
   are not weakened when new levels are reached.
4. **Read-only is the default state** — unless a level explicitly permits mutation
   and its gates pass, the system remains read-only.
5. **Runtime isolation is permanent** — no level may permit runtime consumption of
   Studio draft state.
6. **Diagnostics are read-only** — readiness gates must not mutate any file, DB,
   cache, or runtime state. They are inspection only.
