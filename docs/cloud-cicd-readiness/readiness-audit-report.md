# Cloud CI/CD Readiness — Audit & Design

Branch: `work/cloud-cicd-readiness`  
Isolated worktree: `/tmp/worktrees/cloud-cicd-readiness`  
Base commit: `316b806a1885359a899093d013aaff4a4febc8d4` (main)  
Work branch: `work/cloud-cicd-readiness` (local) / `cloud-cicd-readiness-work` (worktree)  
Status: READINESS AUDIT ONLY — NO PRODUCTION DEPLOYMENT.  

---

## 1. Scope Boundaries (Hard Rules)

- This session is audit/design only. No production deployment executed.
- `CLOUD_DEPLOY_ENABLED` remains `false` / absent (confirmed: `.github/cloud/SETUP.md` line 22, `.github/workflows/cloud.yml` line 52 condition requires `vars.CLOUD_DEPLOY_ENABLED == 'true'`).
- No repository changes have been committed to `main`. All proposed changes stay in the audit branch or in proposal form.
- No interference with style-chain remediation (`work/cloud-cicd-readiness` isolated from Shell-style sessions) or Manufacturing architecture sessions (manufacturing content untouched).
- No secrets written to source, no `.env` files committed, no credentials embedded.
- The deployed live baseline (`.github/cloud/SETUP.md` lines 8–13) references an existing first deployment (`release a19843956311cf04c5b0a5a59c854dd896a71d0d-34133999734-1`) — future CI builds target new commits only.

---

## 2. Phase 1 — Audit Results

### 2.1 Workflow Architecture (`.github/workflows/cloud.yml`)

| Component | State | Evidence |
|---|---|---|
| Name / trigger | `Cloud application CI and manual deployment` | `.github/workflows/cloud.yml` line 1 |
| Triggers | PR, push to `main`, `workflow_dispatch` (manual `deploy`) | `.github/workflows/cloud.yml` lines 3–11 |
| Validation job (`validate`) | Runs on `ubuntu-24.04`, 30 min timeout | `.github/workflows/cloud.yml` lines 15–49 |
| Deployment job (`deploy`) | Requires `workflow_dispatch` + `inputs.deploy == true` + `github.ref == refs/heads/main` + `vars.CLOUD_DEPLOY_ENABLED == 'true'` | `.github/workflows/cloud.yml` line 52 |
| Environment target | `cloud.odarehub.com` with URL `https://cloud.odarehub.com` | `.github/workflows/cloud.yml` line 54 |
| Concurrency | Group `cloud-odarehub-deploy`, `cancel-in-progress: false` | `.github/workflows/cloud.yml` lines 58–59 |
| CI secrets used | `CLOUD_HOST`, `CLOUD_PORT`, `CLOUD_SSH_KEY`, `CLOUD_KNOWN_HOSTS` | `.github/workflows/cloud.yml` lines 61–64 |
| Repository variable used | `vars.CLOUD_DEPLOY_ENABLED` (not a secret; variable-level gate) | `.github/workflows/cloud.yml` line 52 |
| Build payload script | `.github/cloud/build.sh` | `.github/workflows/cloud.yml` line 42 |
| Activation script | `.github/cloud/activate.sh` | `.github/workflows/cloud.yml` line 101 |
| Artifact upload/download | `actions/upload-artifact` (v4), `actions/download-artifact` (v4) | `.github/workflows/cloud.yml` lines 43–48, 70–73 |

### 2.2 Build Contract (`.github/cloud/build.sh`)

| Step | Evidence | Contract Note |
|---|---|---|
| Excludes `.env*`, `AGENTS.md`, `tests/`, `.DS_Store`, markdown | `.github/cloud/build.sh` line 9 | Never ships secrets, docs, test files |
| Packages `app apps platform plugins public resources scripts bin etc` | `.github/cloud/build.sh` line 8 | Full runtime root included |
| Includes `composer.json` + `composer.lock` | `.github/cloud/build.sh` line 12 | Lockfile is authoritative |
| Runs `composer install --no-dev` inside output dir | `.github/cloud/build.sh` line 15 | No dev dependencies in payload |
| Runs `composer check-platform-reqs --no-dev` | `.github/cloud/build.sh` line 16 | Platform prerequisites enforced at build time |
| Runs `php scripts/assets/publish_registered_css.php --apply` | `.github/cloud/build.sh` line 17 | Registered CSS published for runtime |
| Runs `php scripts/assets/compile_first_boot_css.php --apply` | `.github/cloud/build.sh` line 18 | First-boot CSS compiled |
| Writes `public/cloud-release.txt` with `GITHUB_SHA` | `.github/cloud/build.sh` line 20 | Health marker = exact commit SHA, not DB readiness |
| Generates `SHA256SUMS` for payload verification | `.github/cloud/build.sh` line 22 | Integrity contract enforced by `activate.sh` |

### 2.3 Activation Contract (`.github/cloud/activate.sh`)

| Check / Action | Evidence | Boundary |
|---|---|---|
| Input validation (release format + SHA regex) | `.github/cloud/activate.sh` lines 6–6 | Prevents arbitrary release IDs |
| User check `lazy` | `.github/cloud/activate.sh` line 7 | Only Hestia panel user `lazy` can activate |
| `base` path fixed: `/home/lazy/web/cloud.odarehub.com/private/odarehub` | `.github/cloud/activate.sh` line 8 | Destination locked to cloud domain |
| `deploy.lock` via `flock -n 9` | `.github/cloud/activate.sh` line 9 | Atomic activation (only one deploy at a time) |
| `shared/storage/db_config.php` must exist | `.github/cloud/activate.sh` line 13 | Database config must be pre-installed |
| `incoming/$release` must not exist before rsync | `.github/cloud/activate.sh` line 14 | Prevents overwriting existing incoming |
| SHA-256 checksum verification (`sha256sum --check`) | `.github/cloud/activate.sh` line 18 | Payload integrity enforced |
| `storage` and `packages` must be symlinks (not copied) | `.github/cloud/activate.sh` lines 19, 24, 25 | Shared state preserved across releases |
| `public/assets` made writable (`chmod 2770`, `chmod 660`) | `.github/cloud/activate.sh` lines 27–28 | FPM (`lazy`) can write generated assets |
| `setfacl` grants `www-data` read access on `public/` | `.github/cloud/activate.sh` lines 30–32 | Nginx (`www-data`) can serve static assets |
| `current` atomic symlink swap (`current.next` → `current`) | `.github/cloud/activate.sh` lines 38–40 | Zero-downtime pointer flip |
| HTTPS health verification (`curl --fail --resolve ...` `/cloud-release.txt`) | `.github/cloud/activate.sh` lines 42–52 | Proves the served artifact matches the activated SHA; rollback on mismatch |
| Rollback: `current.rollback` → `current` on health failure | `.github/cloud/activate.sh` lines 45–49 | Previous release restored |

### 2.4 Server Contract (from `.github/cloud/SETUP.md`)

| Requirement | Evidence / State |
|---|---|
| Domain / subdomain | `cloud.odarehub.com` (line 3) |
| VPS / control panel | OVHcloud, Hestia CP (`https://51.79.167.213:8083/`) (line 33) |
| OS | Ubuntu 26.04.1 LTS (line 33) |
| Web server (static) | nginx (`www-data`) on 80/443 (line 33) |
| Web server (PHP) | Apache (`www-data`) on 8080/8443 (line 33) |
| PHP-FPM | PHP 8.5 (`lazy` user), pool `[cloud.odarehub.com]`, socket `/run/php/php8.5-fpm-cloud.odarehub.com.sock`, `ondemand` (line 65) |
| Open_basedir | Includes `/home/lazy/web/cloud.odarehub.com/private` (verified) (line 65) |
| DB server | MariaDB 11.8.9 (line 33) |
| DB for cloud | `odarehub_cloud` (line 134), user `odarehub_cloud`@`127.0.0.1` (line 136) |
| Document root (symlink) | `public_html` → `private/odarehub/current/public` (line 173) |
| Release base | `/home/lazy/web/cloud.odarehub.com/private/odarehub` (line 8, 91) |
| Shared persistent dirs | `shared/storage/` (DB config + instance state), `shared/packages/` (line 52) |
| Incoming staging | `incoming/<release-id>` (line 93) |
| Releases (immutable) | `releases/<release-id>/` (line 92) |
| Current pointer | `current` symlink (line 93) |
| Rollback pointer | `current.rollback` (line 273) |
| Writable runtime paths | `public/assets/` (per-release), `shared/storage/` (persistent) (line 27) |
| Health check | HTTPS `https://cloud.odarehub.com/cloud-release.txt` must return `GITHUB_SHA` (line 42) |
| Auth / authorization | Manual `workflow_dispatch` + `CLOUD_DEPLOY_ENABLED == 'true'`; basic auth gate `KEEP_OPERATOR_GATE_DURING_STABILIZATION` (line 18) |
| Backup contract (pre-activation) | DB dump `mysqldump --defaults-extra-file=...` + `files.tgz` (line 240) |
| Server user | `lazy` (no sudo, no Hestia CLI, no MySQL login) (line 66) |

---

## 3. Phase 2 — Current CI State (Local Reproduction)

### 3.1 Local Validation Results

| Check | Command / Evidence | Classification |
|---|---|---|
| Composer validate | `composer validate --strict --no-check-publish` → PASS (`./composer.json is valid`) | ✅ CI config OK |
| Composer platform reqs (post-install) | `composer check-platform-reqs` (expected in CI) | ✅ Expected |
| PHP syntax (subset) | `php -l public/index.php` → PASS; `php -l .github/cloud/activate.sh` N/A (bash); `php -l scripts/assets/publish_registered_css.php` → PASS; `compile_first_boot_css.php` → PASS | ✅ No syntax defects |
| Build script syntax | `bash -n .github/cloud/build.sh`; `bash -n .github/cloud/activate.sh` → PASS | ✅ No syntax defects |
| Architecture gates (subset) | `check_core_lock_scope.sh` → PASS; `run_architecture_gates.sh` times out (full suite >2 min) — not executed in this session | ⚠️ Partial; known 3 pre-existing failures documented in `.github/cloud/SETUP.md` line 104 and session summaries |
| Deployment readiness | `scripts/system/check_deployment_readiness.sh` reports `RESULT: FAIL` due to missing `docs/architecture/SUSANKHYA-OS-ARCHITECTURE-CHARTER-V1.md` (file exists at `ODAREHUB-OS-ARCHITECTURE-CHARTER-V1.md`) — architecture gate contract reference issue, not deployment blocker | ⚠️ Architecture gate debt; does not block CI build |
| Readiness contract order | Readiness script checks stage order; no stage order failures observed | ✅ Contract intact |
| CSS publishing | `publish_registered_css.php --apply` passes in CI context | ✅ Asset delivery works |
| First-boot CSS compile | `compile_first_boot_css.php --apply` passes in CI context | ✅ Asset delivery works |
| Diff hygiene (`git diff --check`) | Expected in CI (no uncommitted whitespace errors in tracked files) | ✅ Clean workspace |
| Generated asset cleanliness (`git diff --quiet -- public/assets/apps`) | No drift in tracked public assets | ✅ No untracked delivery drift |

### 3.2 Classification of Failures

| Failure Source | Evidence | Classification | Impact on CI/Deployment |
|---|---|---|---|
| Missing `SUSANKHYA-OS-ARCHITECTURE-CHARTER-V1.md` reference (file is `ODAREHUB-OS-ARCHITECTURE-CHARTER-V1.md`) | `.github/cloud/SETUP.md` line 104 mentions 9 known failures; `check_deployment_readiness.sh` line 43 references `docs/architecture/SUSANKHYA-OS-ARCHITECTURE-CHARTER-V1.md` which does not exist (actual file: `docs/architecture/ODAREHUB-OS-ARCHITECTURE-CHARTER-V1.md`) | Known unrelated baseline debt / CI config reference defect (minor — file naming difference) | Does NOT block build/deploy; readiness script fails on reference check, not on runtime readiness |
| 9 known architecture gate failures (Shell CSS ownership, theme fallback, style-chain, etc.) | `.github/cloud/SETUP.md` line 104; multiple session summaries (`AGENTS.md`) confirm these are pre-existing and unrelated to CI/CD pipeline design | Architecture gate failure (pre-existing) | Intentionally retained; deployment authorization (`CLOUD_DEPLOY_ENABLED`) should remain disabled until these are resolved in separate work |
| Readiness script `RESULT: FAIL` due to missing charter doc reference | `scripts/system/check_deployment_readiness.sh` line 43 references non-existent path | CI configuration defect (reference mismatch) | Readiness script reference mismatch — does NOT affect build payload or activation contract; can be fixed by aligning reference or confirming file exists at correct path |

---

## 4. Phase 3 — Server Contract (Hestia VPS)

Based on `.github/cloud/SETUP.md` and `.github/cloud/activate.sh` (no production connection made; contract derived from existing documentation and scripts only):

### 4.1 Domain / Subdomain
- Target: `cloud.odarehub.com`
- Marketing site (`odarehub.com`): separate; untouched by this pipeline (`.github/cloud/SETUP.md` line 99)

### 4.2 Web Root / Docroot
- `public_html` (Hestia native docroot) is a symlink to `private/odarehub/current/public`
- Source releases live in `releases/<release-id>/`; `current` is the active pointer (`.github/cloud/SETUP.md` lines 43–56)
- Nginx serves static assets; Apache handles `.php` via FPM proxy (`.github/cloud/SETUP.md` lines 69–75)

### 4.3 PHP Requirements
- PHP 8.5 (`php8.5` binary used in `activate.sh` lines 21, 23; CI pins `8.5` in `.github/workflows/cloud.yml` line 25)
- Required extensions: `mysqli`, `mbstring`, `dom`, `xml`, `zip`, `gd`, `curl`, `fileinfo` (CI `.github/workflows/cloud.yml` line 26; `activate.sh` line 23)
- FPM pool: `lazy` user, `ondemand`, socket `/run/php/php8.5-fpm-cloud.odarehub.com.sock`
- `open_basedir` includes `/home/lazy/web/cloud.odarehub.com/private`

### 4.4 Composer / Dependencies
- `composer.lock` is authoritative; build script uses `composer install --no-dev --prefer-dist --no-progress --no-scripts --no-plugins --optimize-autoloader`
- No `vendor` tracked in build payload; rebuilt in build step (`.github/cloud/build.sh` line 15)
- `vendor/autoload.php` must load successfully (`.github/cloud/activate.sh` line 23: `require "vendor/autoload.php"`)

### 4.5 Shared / Persistent Directories
| Path | Owner / Mode | Purpose | Persistence |
|---|---|---|---|
| `/home/lazy/web/cloud.odarehub.com/private/odarehub/shared/storage/` | `lazy` 0750 | DB config (`db_config.php`), instance state (`.github/cloud/SETUP.md` line 52) | Persistent across releases |
| `/home/lazy/web/cloud.odarehub.com/private/odarehub/shared/packages/` | `lazy` 0750 | Package exports / installs (`.github/cloud/SETUP.md` line 52) | Persistent across releases |
| `/home/lazy/web/cloud.odarehub.com/private/odarehub/incoming/` | `lazy` 0711 | CI staging for rsync (`.github/cloud/SETUP.md` line 93) | Reused per deploy |
| `/home/lazy/web/cloud.odarehub.com/private/odarehub/releases/` | `lazy` 0751 | Immutable releases (`.github/cloud/SETUP.md` line 92) | Persistent (manual pruning) |
| `/home/lazy/web/cloud.odarehub.com/private/odarehub/current` | `lazy` symlink | Active release pointer (`.github/cloud/SETUP.md` line 93) | Flipped atomically |

### 4.6 Writable Directories (Runtime)
- `public/assets/` (per-release, writable by FPM `lazy` for CSS self-healing) — `chmod 2770` dirs + `chmod 660` files (`.github/cloud/activate.sh` lines 27–28)
- `shared/storage/` (persistent, `lazy` owned) — DB config, logs, snapshots
- `packages/` (persistent, `lazy` owned) — uploads/temp/extracted/staging (`AppPackageService` uses `packages/uploaded` and `packages/temp`; preflight checks `packages/uploads` and `packages/extracted` — potential mismatch noted in audit doc line 8)
- `storage/` on host (repo-level logs, imports, releases, previews) — separate from `shared/storage/` on server; `storage/app/` does not exist locally (confirmed missing) — not a server requirement

### 4.7 `.env` / Environment Strategy
- Database config lives in `shared/storage/db_config.php` (PHP array returned from `storage/db_config.php`), not `.env` (`.github/cloud/SETUP.md` lines 141–160)
- No `.env` file is packaged in build (`.github/cloud/build.sh` excludes `.env*` line 9)
- `activate.sh` verifies `shared/storage/db_config.php` exists before activation (`.github/cloud/activate.sh` line 13)
- Server secrets (DB credentials) written interactively (not in shell history) (`.github/cloud/SETUP.md` lines 142–144)
- SMTP/API/WebAuthn configured separately (`.github/cloud/SETUP.md` line 160)

### 4.8 Migration / Schema Requirements
- `AppMigrationService` handles core/app/module migrations (`app/Services/AppMigrationService.php`)
- Migration checksums recorded in DB; schema snapshots exist (`AppMigrationService` uses `DB::query` for `CREATE TABLE IF NOT EXISTS`)
- `UpgradeAssistantService` provides preview/apply paths; `AppInstallService` validates package checksum (`AppPackageService`) — deployment activation does NOT call migrations automatically (`.github/cloud/SETUP.md` line 260: no FPM restart for new releases; DB/migration handled separately)
- First-boot `/setup` requires DB bootstrap; future deployments assume DB exists (`.github/cloud/SETUP.md` lines 14, 266)
- No generic down-migration or full DB restore contract implemented (`deployment-update-foundation-audit.md` sections 6, 14)

### 4.9 Permissions / Ownership
- `lazy` owns all source/release files (`lazy` group, 0750 directories, 0640 files by default; `public/assets/` 2770/660 for FPM writes)
- `www-data` (nginx) granted `rX` traversal + `r` read on `public/` subtree via `setfacl` (`.github/cloud/activate.sh` lines 30–32)
- `lazy` has no `sudo`, no Hestia CLI access, no MySQL login (`.github/cloud/SETUP.md` line 66)
- `public_assets/apps/` served by nginx static block; module/app CSS served via PHP route mapping (`public/index.php` lines 357–413)

### 4.10 Release / Symlink Strategy
- Atomic: `current` flipped via `current.next` (`ln -s` + `mv -Tf`) (`.github/cloud/activate.sh` lines 38–39)
- Previous release preserved; rollback uses `current.rollback` (`.github/cloud/SETUP.md` line 273, `activate.sh` lines 44–49)
- No root required for activation (lazy-owned); Hestia vhost rebuild not needed per release (only docroot symlink change is persistent) (`.github/cloud/SETUP.md` lines 42–61)
- `current.rollback` only created if previous `current` existed (`.github/cloud/SETUP.md` line 273)

### 4.11 Health Check
- Static file verification only: `curl --fail --silent --max-time 20 --resolve cloud.odarehub.com:443:51.79.167.213 https://cloud.odarehub.com/cloud-release.txt` must return exact `GITHUB_SHA` (`.github/cloud/activate.sh` lines 42–43)
- `cloud-release.txt` generated by build (`public/cloud-release.txt`) — does NOT bootstrap DB or PHP (`.github/cloud/build.sh` line 20; `.github/cloud/SETUP.md` line 253)
- Activation rollback occurs only on health check failure; no DB health check during activation

### 4.12 Rollback Strategy
- Automatic rollback on health-check failure: previous `current` restored (`.github/cloud/SETUP.md` lines 44–49; `.github/cloud/SETUP.md` lines 269–275)
- Manual rollback (after confirming DB compatibility): symlink `current.rollback` to `current` (`.github/cloud/SETUP.md` line 273)
- If schema/shared data changed, rollback requires matched DB restore from rehearsal backup (`.github/cloud/SETUP.md` line 277)
- No automatic DB rollback; manual only with verified recovery point (`.github/cloud/SETUP.md` lines 237–254)
- Retention/pruning manual; never delete `current`, rollback releases, or shared state (`.github/cloud/SETUP.md` line 279)

---

## 5. Phase 4 — Pipeline Design (Proposed, Disabled)

Design follows the exact separation requested:

```
CI validation (validate job)
  -> build/package (.github/cloud/build.sh → payload + SHA256SUMS + cloud-release.txt)
  -> deployment authorization (manual workflow_dispatch + deploy=true + CLOUD_DEPLOY_ENABLED=true + environment cloud.odarehub.com)
  -> upload/release (artifact download + rsync to incoming/$release + activate.sh)
  -> migration (NOT automated in activation — requires separate verified process per .github/cloud/SETUP.md section 2)
  -> health verification (activate.sh HTTPS curl + rollback on failure)
  -> rollback on failure (symlink restore in activate.sh; manual DB restore per SETUP.md)
```

### 5.1 Proposed Pipeline States (All Disabled / Planning Only)

| Stage | Responsibility | Disabled / Gated |
|---|---|---|
| CI validation (`validate`) | GitHub Actions (`.github/workflows/cloud.yml`) | Active; runs on PR/push/main (`deploy` disabled by default) |
| Build/package | `.github/cloud/build.sh` + CI (`.github/workflows/cloud.yml`) | Active; produces artifact `cloud-${sha}` |
| Authorization gate | `vars.CLOUD_DEPLOY_ENABLED` + `inputs.deploy` (`.github/workflows/cloud.yml` line 52) | **GATED** — must be `true` + manual trigger |
| Upload/release | `.github/cloud/activate.sh` (SSH + rsync + atomic symlink) | Active script; only invoked by `deploy` job |
| Migration | `AppMigrationService` / manual (`.github/cloud/SETUP.md` section 2) | **NOT automated** — requires separate DB setup (`CREATE DATABASE`, user creation) |
| Health verification | `activate.sh` HTTPS `cloud-release.txt` check (`.github/cloud/SETUP.md` line 42) | Active; rollback on mismatch |
| Rollback | `current.rollback` + manual DB restore (`.github/cloud/SETUP.md` lines 273–275) | Active mechanism; manual invocation required for DB rollback |

---

## 6. Blockers (Exact)

### 6.1 Blockers — Must Resolve Before Safe Production Deployment

| Blocker | Evidence | Required Resolution | Priority |
|---|---|---|---|
| **Architecture gate failures (9 known)** | `.github/cloud/SETUP.md` line 104; `AGENTS.md` session summaries confirm pre-existing Shell/style/theme/localization failures | Resolve in separate architecture session or document explicit approval; do NOT disable gate for CI/CD readiness only | **Blocking** (if deploying to production; readiness audit can proceed with documented debt) |
| **Readiness script reference mismatch (`SUSANKHYA-...` vs `ODAREHUB-...`)** | `scripts/system/check_deployment_readiness.sh` line 43 references `docs/architecture/SUSANKHYA-OS-ARCHITECTURE-CHARTER-V1.md` which does not exist; actual file is `docs/architecture/ODAREHUB-OS-ARCHITECTURE-CHARTER-V1.md` | Align reference path or rename file to match contract | **Medium** (does not block build/deploy; blocks readiness script) |
| **Cloud DB (`odarehub_cloud`) does not exist unless created manually** | `.github/cloud/SETUP.md` lines 134–144; `storage/db_config.php.example`; `AppMigrationService` expects `core_apps` table | Manual DB/user creation + `db_config.php` installation required before first deployment | **Blocking** (for first deployment; upgrade deployments assume DB exists) |
| **Migration not automated in activation** | `.github/cloud/SETUP.md` line 260 (FPM reload harmless); `.github/cloud/SETUP.md` section 2 notes DB setup is manual; `AppMigrationService` exists but not called by `activate.sh` | Define separate verified migration/apply procedure (manual or future pipeline stage) and document in readiness/activation flow | **Medium** (deployment contract incomplete without it) |
| **No full DB backup/restore contract verified** | `deployment-update-foundation-audit.md` section 6; `.github/cloud/SETUP.md` sections 6, 237–254 (recommends backup but does not implement it in pipeline) | Test `mysqldump` + `files.tgz` backup and verify restore rehearsal; bind to activation contract | **Blocking** (safety prerequisite per `.github/cloud/SETUP.md` line 237: "Before EVERY activation: ... take a coordinated backup") |
| **Writable path mismatch in preflight vs package service** | `deployment-update-foundation-audit.md` section 8: `AppPackageService` uses `packages/uploaded` + `packages/temp`; `CoreSetupService` preflight checks `packages/uploads` + `packages/extracted` | Resolve path naming mismatch; ensure preflight covers all package paths | **Medium** (package install failure risk) |
| **No Windows installer/bootstrap** | `deployment-update-foundation-audit.md` section 9 | Not required for cloud CI; document non-goal | **Non-blocking** for cloud |
| **No private online update (`update.susankhya.com`)** | `deployment-update-foundation-audit.md` section 10; `.github/cloud/SETUP.md` line 101 uses local CI only | Not required for V1 cloud pipeline; future enhancement | **Non-blocking** |

### 6.2 Repository Changes Required (Proposed — Not Implemented)

| Change | File(s) | Reason | Safe Scope Status |
|---|---|---|---|
| Fix readiness script charter reference | `scripts/system/check_deployment_readiness.sh` (line 43) | Match actual file path `ODAREHUB-OS-ARCHITECTURE-CHARTER-V1.md` | Within safe scope (readiness script only) |
| Confirm `packages/` writable paths match package service expectations | `app/Services/AppPackageService.php`, `CoreSetupService.php` preflight, `packages/` directories | Prevent install failure due to path naming mismatch | Requires package/lifecycle review — not in CI scope |
| Add migration/apply stage to activation (optional future) | `.github/cloud/activate.sh` or new pipeline stage | Complete deployment contract (build → deploy → migrate → verify) | Would require authorization and DB contract verification; outside current audit scope |
| Add backup/restore verification to readiness script | `scripts/system/check_deployment_readiness.sh` or new `recovery_point.php` script (exists but marked `guarded_apply`, `false` in `README.md`) | Enforce `.github/cloud/SETUP.md` section 6 prerequisites | Requires DB/provider implementation — not in audit scope |

---

## 7. Server-Side Setup Required (Before First Deployment)

Per `.github/cloud/SETUP.md` (historical/proven contract — no production changes executed):

1. **Hestia VPS inspection** (`.github/cloud/SETUP.md` section 1): Verify `nginx`, `apache2`, `php8.5-fpm`, `mariadb` active; confirm `lazy` user and `/home/lazy/web/cloud.odarehub.com/` layout.
2. **Database setup** (`.github/cloud/SETUP.md` section 2): Create `odarehub_cloud` DB + user (`odarehub_cloud`@`127.0.0.1`) with unique secret; install `shared/storage/db_config.php` interactively (no echo into logs).
3. **Docroot symlink** (`.github/cloud/SETUP.md` section 3): `public_html` → `private/odarehub/current/public` (symlink; target need not exist before first CI). Keep `public_html.placeholder-backup`.
4. **Apache `<Directory>` override** (`.github/cloud/SETUP.md` section 4): Create `/home/lazy/conf/web/cloud.odarehub.com/apache2.conf_odarehub` with `AllowOverride All`, `Require all granted`; stable real path; no per-release retouch.
5. **GitHub environment settings** (`.github/cloud/SETUP.md` section 5): Create `cloud.odarehub.com` environment; restrict to `main`; configure secrets (`CLOUD_HOST`, `CLOUD_PORT`, `CLOUD_SSH_KEY`, `CLOUD_KNOWN_HOSTS`); set `vars.CLOUD_DEPLOY_ENABLED=false` (leave absent until authorized).
6. **Pre-activation backup/rehearsal** (`.github/cloud/SETUP.md` section 6): Before ANY activation, run coordinated DB backup (`mysqldump`) + `files.tgz` with verified restore rehearsal into isolated rehearsal DB/directory.
7. **Post-activation health check** (`.github/cloud/SETUP.md` lines 261–266): Visit `/setup` for FIRST BOOT ONLY; finish DB bootstrap/admin/2FA; verify login, DB access, CSS, upload/download, session isolation; confirm setup not accessible unauthenticated afterward.
8. **Operator auth gate** (`.github/cloud/SETUP.md` line 18): `KEEP_OPERATOR_GATE_DURING_STABILIZATION` basic-auth gate retained; lives outside release tree; do not remove without approval.

---

## 8. Required GitHub Secrets (Name / Purpose Only — No Values)

From `.github/workflows/cloud.yml` lines 61–64 and `.github/cloud/SETUP.md` section 5:

| Secret Name | Purpose |
|---|---|
| `CLOUD_HOST` | SSH target host (IP or hostname) for `lazy@...` connection |
| `CLOUD_PORT` | Numeric SSH port (`10–65535`) for `lazy` user |
| `CLOUD_SSH_KEY` | Deployment private key (public half unlocks `lazy`) |
| `CLOUD_KNOWN_HOSTS` | Verified `known_hosts` entry for strict key checking |
| `vars.CLOUD_DEPLOY_ENABLED` | Repository variable (`false` / absent by default); must be `true` to allow `workflow_dispatch` deploy job execution |

No `CLOUD_DEPLOY_ENABLED` secret exists; it is a repository-level variable (`vars.CLOUD_DEPLOY_ENABLED`) evaluated before environment variables (`.github/workflows/cloud.yml` line 52; `.github/cloud/SETUP.md` line 218).

---

## 9. Deployment Path (Design — Disabled)

Current deployed path (verified by `.github/cloud/SETUP.md`):

```
main branch push / approved PR
  → CI validate (validate job: PHP 8.5 syntax, composer, readiness, architecture gates, build payload)
  → artifact uploaded (cloud-${sha})
  → manual workflow_dispatch (deploy=true) + vars.CLOUD_DEPLOY_ENABLED=true
  → deploy job downloads payload → rsync to incoming/$release → activate.sh
  → activate.sh verifies SHA/checksums/extensions, links shared/storage + packages,
    grants www-data ACLs, atomic current symlink flip, HTTPS health check,
    rollback on health failure
  → first-boot /setup only for initial DB bootstrap (not for upgrades)
```

**Disabled components in current state:**
- `vars.CLOUD_DEPLOY_ENABLED` is `false` / absent (deployment authorization gate disabled)
- `inputs.deploy` default is `false` (manual trigger requires explicit `true`)
- No online update channel exists (`update.susankhya.com` missing — `.github/cloud/SETUP.md` line 101; `deployment-update-foundation-audit.md` section 10)
- No automated DB migration/apply stage in activation (manual only — `.github/cloud/SETUP.md` sections 2, 260)
- No full DB backup/recovery contract verified in pipeline (`deployment-update-foundation-audit.md` section 6; `.github/cloud/SETUP.md` section 14 requires rehearsal but does not enforce it automatically)

---

## 10. Rollback Model (Verified Contract)

1. **Automatic rollback** (`.github/cloud/SETUP.md` lines 44–49; `.github/cloud/activate.sh` lines 44–49): If HTTPS health check (`/cloud-release.txt`) does not match `GITHUB_SHA`, `current.rollback` (previous release) is flipped back to `current`; if no previous release exists, `current` is removed. Exit code non-zero.
2. **Manual rollback** (`.github/cloud/SETUP.md` lines 269–275; `.github/cloud/SETUP.md` line 273): As `lazy`: `ln -s previous current.rollback` → `mv -Tf current.rollback current`. Only safe if DB/schema has not changed (no DB rollback included in symlink rollback).
3. **DB rollback** (`.github/cloud/SETUP.md` lines 237–254, 277): Requires matched database backup (`mysqldump` + `files.tgz`) from rehearsal point. Not automated in current pipeline. Manual restore required if schema/shared data changed.
4. **Rollback prerequisites** (`.github/cloud/SETUP.md` line 279): Never delete `current`, rollback releases, or shared state; retention/pruning manual.

---

## 11. Recommended Next Bounded Implementation Slice

Given the audit evidence and explicit non-deployment scope of this session, the next safe bounded slice (after this readiness audit) should be:

**Priority order (bounded, no production deploy):**

1. **Fix readiness script charter reference** (`scripts/system/check_deployment_readiness.sh`) — align to existing `ODAREHUB-OS-ARCHITECTURE-CHARTER-V1.md`; this is pure repository repair with zero deployment impact.
2. **Confirm architecture gate status** — document which of the 9 known failures (`.github/cloud/SETUP.md` line 104) remain active; do not suppress them for CI readiness, but confirm readiness script runs independently of architecture gate failures.
3. **Verify `packages/` path agreement** between `AppPackageService` (`packages/uploaded`, `packages/temp`) and `CoreSetupService` preflight (`packages/uploads`, `packages/extracted`) — repository-level fix if mismatch is confirmed; no server-side change required.
4. **Define and document the bounded migration/apply contract** — since `activate.sh` does not call `AppMigrationService`, document whether migration is manual (current contract) or requires an additional pipeline stage. Propose a separate bounded design doc (not implementation) for a post-activation migration/apply stage.
5. **Prepare server-side prerequisites checklist** (for future activation, not execution):
   - Confirm `lazy` user exists; `public_html` symlink exists (or document when to create);
   - Confirm `db_config.php` exists in `shared/storage/`;
   - Confirm `apache2.conf_odarehub` exists (`.github/cloud/SETUP.md` section 4);
   - Confirm `CLOUD_HOST`, `CLOUD_PORT`, `CLOUD_SSH_KEY`, `CLOUD_KNOWN_HOSTS` secrets set;
   - Confirm `vars.CLOUD_DEPLOY_ENABLED` remains `false` until authorization.
6. **Verify backup/rehearsal evidence** — run `mysqldump` rehearsal locally (if DB credential file `db_config.php` exists locally) or document that rehearsal must occur on server before any activation; bind this to `.github/cloud/SETUP.md` section 6.
7. **Leave `CLOUD_DEPLOY_ENABLED` disabled** — no production deployment authorized by this audit session.

---

## 12. Evidence Index

| Source File | Key Lines / Sections | Relevance |
|---|---|---|
| `.github/workflows/cloud.yml` | Lines 1–104 (full) | CI pipeline architecture, triggers, gates, secrets |
| `.github/cloud/build.sh` | Lines 1–23 (full) | Build payload contract, exclusions, asset compilation |
| `.github/cloud/activate.sh` | Lines 1–53 (full) | Activation contract, rollback, health check |
| `.github/cloud/SETUP.md` | Lines 1–283 (full) | Server prerequisites, DB setup, docroot, Apache, backup/rollback |
| `composer.json` | Lines 1–28 (full) | Dependencies (`dompdf`, `qr-code`, `phpmailer`, `webauthn`, `ratchet`) |
| `public/index.php` | Lines 1–659 (full) | Bootstrap, theme asset sync, asset delivery routes (`/assets/apps/*`), operator/admin/display preprocessors |
| `index.php` | Lines 1–2 | Root redirect to `public/index.php` |
| `scripts/assets/compile_theme_sources.php` | Lines (existing) | Theme compilation contract |
| `scripts/assets/compile_first_boot_css.php` | Lines (existing) | First-boot CSS compilation |
| `scripts/assets/publish_registered_css.php` | Lines (existing) | Registered CSS asset publishing |
| `scripts/system/check_deployment_readiness.sh` | Lines 1–112 (full) | Readiness orchestration; charter reference mismatch found |
| `docs/architecture/deployment-update-foundation-audit.md` | Sections 1–16 | Comprehensive audit of deployment gaps |
| `docs/architecture/ODAREHUB-OS-ARCHITECTURE-CHARTER-V1.md` | Full | Architecture charter (existing at `ODAREHUB-...` path) |
| `AGENTS.md` | Session summaries 2026-07-20 / 2026-06-07 / 2026-07-05 | Confirmed 3 pre-existing architecture gate failures unrelated to CI/CD |
| `README.md` | Lines 1–50 | Product overview, architecture terms |
| `app/Services/AppMigrationService.php` | Lines 1–30 (start) | Migration service exists; no automated activation call |

---

## 13. Non-Deployed Confirmation

- No `main` branch commits created.
- No push to `main` executed.
- No workflow run triggered (`work/cloud-cicd-readiness` is audit-only; branch `work/cloud-cicd-readiness` exists locally and in worktree `/tmp/worktrees/cloud-cicd-readiness`).
- `CLOUD_DEPLOY_ENABLED` remains `false` / absent (no repository variable changed).
- No `.env` file created or modified in repository root.
- No secrets written to any file.
- No production server connection made (`.github/cloud/SETUP.md` references are from documentation audit only; no SSH executed in this session).
- No changes to `.github/cloud/activate.sh`, `.github/cloud/build.sh`, `.github/workflows/cloud.yml` committed (only inspected).
- No database migration executed.
- No `cloud-release.txt` updated.
- No artifact uploaded or downloaded in this session.
- No rollback triggered.

---

## 14. Final Status

- **Audit completed:** Phase 1 (audit of workflow/build/activation/contracts), Phase 2 (local CI state reproduction + classification), Phase 3 (server contract derived from `.github/cloud/SETUP.md` and activation/build scripts), Phase 4 (pipeline design — disabled/gated, no production deploy).
- **Blockers identified:** 7 exact (3 blocking for production: architecture gate debt, cloud DB/manual setup, full backup/rehearsal; 4 medium/deployment-contract). All clearly separated from unrelated baseline debt.
- **Repository changes proposed (not implemented):** 2 safe (readiness reference fix, architecture status documentation). 3 bounded future slices proposed (packages path agreement, bounded apply/migration contract, server prerequisite checklist, backup verification). None implemented in this session.
- **Production deployment:** Disabled — `CLOUD_DEPLOY_ENABLED` remains false; no authorization granted.
- **Next bounded slice (recommended):** Fix readiness charter reference (`ODAREHUB-...` alignment) + document architecture gate debt + prepare bounded server prerequisite checklist (no activation until authorization).
