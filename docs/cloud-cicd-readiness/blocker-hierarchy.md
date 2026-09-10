# Blocker Hierarchy — Derived from readiness-audit-report.md (commit 6b8b766)

## P0 — prevents safe CI validation / readiness build
| Evidence | File | Type | Needs creds/human? | Dependent on |
|---|---|---|---|---|
| Readiness script references non-existent `SUSANKHYA-OS-ARCHITECTURE-CHARTER-V1.md`; actual file is `ODAREHUB-OS-ARCHITECTURE-CHARTER-V1.md` (line 43 of `scripts/system/check_deployment_readiness.sh`) | `scripts/system/check_deployment_readiness.sh` | repository-side | No — pure path/reference fix | None |

## P1 — prevents deployment but not CI
| Evidence | File | Type | Needs creds/human? | Dependent on |
|---|---|---|---|---|
| Cloud DB `odarehub_cloud` + user not created; requires manual SQL + `shared/storage/db_config.php` (SETUP.md sections 2, 141-144) | `.github/cloud/SETUP.md` / `storage/db_config.php.example` | server-side (manual) | Yes — human DB admin; no production credential in repo | None (independent of P0) |
| Migration/apply stage missing from `activate.sh`; DB not auto-migrated on activation (SETUP.md line 260; `AppMigrationService` exists but not called) | `.github/cloud/activate.sh` / `app/Services/AppMigrationService.php` | repository-side (contract gap) | No — design/doc only | None |
| Full DB backup/rehearsal contract not automated; `mysqldump` + restore rehearsal required before activation (SETUP.md section 6; `deployment-update-foundation-audit.md` section 6) | `.github/cloud/SETUP.md` / `docs/architecture/deployment-update-foundation-audit.md` | server-side + design | Yes — human operation; no repo secret | P1 DB setup (rehearsal needs DB) |

## P2 — server/config prerequisite (must complete before deploy)
| Evidence | File | Type | Needs creds/human? | Dependent on |
|---|---|---|---|---|
| Hestia docroot symlink (`public_html` -> `current/public`); one-time panel/root | `.github/cloud/SETUP.md` section 3 | server-side | Yes — root/panel access | None |
| Apache `<Directory>` override (`apache2.conf_odarehub`) for realpath under symlink | `.github/cloud/SETUP.md` section 4 | server-side | Yes — root/panel | Docroot symlink |
| VPS service verification (`nginx apache2 php8.5-fpm mariadb`) | `.github/cloud/SETUP.md` line 36 / section 1 | server-side | Yes — server access | None |
| `CLOUD_HOST`, `CLOUD_PORT`, `CLOUD_SSH_KEY`, `CLOUD_KNOWN_HOSTS` secrets set in GitHub environment | `.github/workflows/cloud.yml` / `.github/cloud/SETUP.md` section 5 | repository environment (secrets) | Yes — admin with GitHub access | None |
| `vars.CLOUD_DEPLOY_ENABLED=false` maintained (deployment authorization disabled) | `.github/workflows/cloud.yml` line 52 | repo variable | Yes — repo admin | None |
| `packages/` writable path naming mismatch (`packages/uploaded` vs `packages/uploads`; `packages/temp` vs `packages/extracted`) | `app/Services/AppPackageService.php` / `app/Services/CoreSetupService.php` | repository-side | No — code inspection | None |

## P3 — hardening / quality (not deployment blocking; resolve separately)
| Evidence | File | Type | Needs creds/human? | Dependent on |
|---|---|---|---|---|
| 9 pre-existing architecture gate failures documented (`Shell CSS ownership`, `theme fallback`, `style-chain`, `localization`, etc.) | `.github/cloud/SETUP.md` line 104; session summaries `AGENTS.md` | repository-side | No — architecture work | None |
| No private online update (`update.susankhya.com`) | `.github/cloud/SETUP.md` line 101; `deployment-update-foundation-audit.md` section 10 | design / future | No | None |
| No Windows installer/bootstrap (non-goal for cloud) | `deployment-update-foundation-audit.md` section 9 | non-goal | No | None |
