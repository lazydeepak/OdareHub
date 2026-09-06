# cloud.odarehub.com — Hestia VPS preparation and operations

Prepared 2026-09-06. Nothing has been deployed, pushed, or changed on the VPS beyond
the approved layout scaffold. The commands below are operator instructions for a
future approved deployment.

## Findings and scope

Repository: `https://github.com/lazydeepak/OdareHub.git`, local branch `main`.
This is the application, distinct from `OdareHubWeb` (the static marketing site).
Custom PHP framework, mysqli/MySQL or MariaDB, server-rendered views; no Node build
or Node/PM2 application start command.

The live stack is Hestia CP (`https://51.79.167.213:8083/`) on Ubuntu 26.04.1 LTS:
nginx (www-data) on 80/443 with the TLS cert, Apache (www-data) on 8080/8443
FCGI-proxying `*.php` to PHP-FPM 8.5, MariaDB 11.8.9, disk ~38G free, RAM ~1951MB
available. `systemctl is-active nginx apache2 php8.5-fpm mariadb` → all `active`.

The Hestia-generated vhosts and pool are authoritative and are "DO NOT MODIFY"
files. Hestia rebuilds them. This plan adds a one-time root/panel override for the
Apache `<Directory>` block and a one-time `public_html` re-point; everything else is
owned by the `lazy` panel user and runs without root.

### Approved Hestia-native layout (authoritative)

On the VPS at `/home/lazy/web/cloud.odarehub.com/`:

```
private/odarehub/
    incoming/                 # CI staging, 0711 lazy, locked by deploy.lock
    releases/<release-id>/    # immutable installs, 0751-owner lazy
    shared/storage/           # instance state (db_config.php), 0750 lazy
    shared/packages/          # package exports/installs, 0750 lazy
    current -> releases/<release-id>    # atomic code pointer, flipped by CI
    deploy.lock               # flock guard
public_html -> private/odarehub/current/public    # one-time panel symlink
```

The Hestia docroot stays `public_html`. Changing it to a symlink is the ONLY
docroot change. `v-change-web-domain-docroot` requires the target to live under
`public_html/`, which is exactly why the symlink design is used.

PHP-FPM runs as `lazy` (pool `[cloud.odarehub.com]`, socket
`/run/php/php8.5-fpm-cloud.odarehub.com.sock`, ondemand). Its `open_basedir`
already includes `/home/lazy/web/cloud.odarehub.com/private`, verified with
realpath on `private/odarehub`, `releases`, and `shared`. No pool edit is needed.
`lazy` has no sudo, no Hestia CLI, and no MySQL login.

### Served-by model (verified live)

- nginx static-expires block: root = `public_html` (follows the symlink),
  `try_files $uri @fallback`; nginx follows symlinks (no `disable_symlinks`).
  Static delivery needs www-data read/traverse only on the served public tree.
- `@fallback` → Apache `:8080`.
- Apache `<FilesMatch \.php$>` `SetHandler proxy:unix:/run/php/php8.5-fpm-cloud.odarehub.com.sock|fcgi://localhost`.
  Apache matches `<Directory>` against the REAL path, so once `public_html` is a
  symlink the generated `AllowOverride All` on `<Directory .../public_html>` does
  NOT cover `.../private/odarehub/current/public`. A root/panel override is required
  (see section 4). FPM reads sources as `lazy`; no www-data access to sources.
- ACLs (ext4, setfacl available): activate.sh grants `u:www-data:x` traversal on the
  release root and `u:www-data:rX` on the `public/` subtree per release. `shared/`
  stays `0750 lazy` so storage secrets are never web-visible.

### Build and migration notes (kept from the original plan)

Build: clean Composer install, PHP syntax checks, existing deployment-readiness
orchestrator (blocking), production Composer install without dev dependencies,
`publish_registered_css.php --apply`, `compile_first_boot_css.php --apply`.
The repository tracks vendor and substantial storage/rehearsal material: neither
is shipped from the checkout. Build an explicit runtime payload and regenerate vendor.

`storage/` and `packages/` are instance-owned shared paths, never synced or deleted.
`public/assets/` is release-local and writable for documented CSS self-healing.
Do not edit production source via Studio or install apps directly into release source.

Optional Ratchet realtime service is intentionally not enabled: documented fallback
uses refresh. Do not copy the existing realtime systemd unit with its old paths/port.

`odarehub.com` (static Astro) is untouched. `server.odarehub.com` is a stale pool
leftover; do not reuse it.

## Local validation and current blockers

Workflow syntax (`actionlint`), shell syntax/ShellCheck, all 1,738 application PHP
files, production Composer install, generated CSS build and complete payload
checksum verification passed locally. Nginx/Apache/FPM are Hestia-owned and were
inspected, not modified. Local PHP is 8.5.3; CI pins PHP 8.5 and the VPS runs
PHP 8.5 only. Composer reports pre-existing PSR-4 warnings in generated/legacy
classes; these are not repaired by this deployment change. The existing
architecture readiness suite reports nine known failures in the current source
snapshot (Shell CSS ownership, localization, Studio/style boundaries, theme
fallback, etc.); these are documented blockers. The workflow intentionally blocks
deployment on those failures. No GitHub Actions run has been executed.

## 1. Inspect the VPS (already done; re-verify before activation)

The inspection in section 1 of the prior design was completed. Re-confirm before
activation:

```bash
ssh -i ~/.ssh/odarehub_deploy -o BatchMode=yes -o StrictHostKeyChecking=yes -o IdentitiesOnly=yes lazy@51.79.167.213 \
  'systemctl is-active nginx apache2 php8.5-fpm mariadb; ls -la /home/lazy/web/cloud.odarehub.com'
```

Do not change generated vhosts, DB users already in use, firewall policy, SSH
access, or global PHP alternatives. Do not install a second DB server or another
PHP version. Expose no MySQL or PHP-FPM socket publicly.

## 2. Isolated database and configuration (root/panel, activation-gated)

Create a cloud-only database and user in an interactive privileged SQL session
(MariaDB console via `sudo mariadb`, or the panel). Replace password with a unique
secret; avoid shell history:

```sql
CREATE DATABASE odarehub_cloud CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'odarehub_cloud'@'127.0.0.1' IDENTIFIED BY 'REPLACE_WITH_UNIQUE_SECRET';
GRANT ALL PRIVILEGES ON odarehub_cloud.* TO 'odarehub_cloud'@'127.0.0.1';
```

As `lazy`, write the instance config (shared storage is lazy-owned 0750):

```bash
install -o lazy -g lazy -m 640 /dev/null /home/lazy/web/cloud.odarehub.com/private/odarehub/shared/storage/db_config.php
# edit with one line per key; do not echo it into logs
php8.5 -l /home/lazy/web/cloud.odarehub.com/private/odarehub/shared/storage/db_config.php
```

```php
<?php
return [
    'host' => '127.0.0.1', 'port' => 3306,
    'name' => 'odarehub_cloud', 'user' => 'odarehub_cloud',
    'pass' => 'REPLACE_WITH_UNIQUE_SECRET',
    'charset' => 'utf8mb4', 'timezone' => 'Asia/Tokyo',
];
```

No global privileges or reuse of the odarehub.com database. Do not import a
production dump into this database as part of CI. SMTP/API/WebAuthn settings are
configured separately for the cloud domain during setup.

## 3. One-time panel/root re-point of the docroot (activation-gated)

`lazy` cannot write the `cloud.odarehub.com/` parent (owner mode is r-x) and cannot
write `/home/lazy/conf/web` (root-owned). These two steps are panel/root actions.
The Hestia panel file manager or a root shell can back up and replace `public_html`.

Plan (as root, or via panel):

```bash
base=/home/lazy/web/cloud.odarehub.com
sudo mv "$base/public_html" "$base/public_html.placeholder-backup"
sudo ln -s "$base/private/odarehub/current/public" "$base/public_html"
sudo chown -h lazy:lazy "$base/public_html"
```

The symlink target path does not need to exist yet; Hestia's docroot checks follow
the symlink. Before first CI, the site serves the placeholder backup content only by
directing vhost docroot temporarily if needed; Nginx `try_files $uri @fallback`
falls through to Apache which returns the app 404/503 until `current` exists.

Keep the placeholder backup until first boot is verified; do not delete it.

## 4. Apache Directory override (one-time panel/root, activation-gated)

Create a custom Apache config in the Hestia user-conf include (`root:root`, survives
rebuilds). For example at `/home/lazy/conf/web/cloud.odarehub.com/apache2.conf_odarehub`:

```apache
<Directory /home/lazy/web/cloud.odarehub.com/private/odarehub/current/public>
    Options +FollowSymLinks -Indexes +ExecCGI
    AllowOverride All
    Require all granted
</Directory>
```

Stable real path; no per-release retouch. Verify with `sudo apache2ctl configtest` or
the panel rebuild. Apache applies it for HTTP (8080) only; the HTTPS (8443) vhost is
not used by nginx. Do not modify generated vhosts directly.

## 5. GitHub settings — leave disabled for now

Review and commit only the files for this task, preserving all other staged and
unstaged work. Do not use `git add .`. Deployment always builds committed GitHub
content; local unfinished changes are not implicitly included.

Create GitHub Environment `cloud.odarehub.com`. Restrict deployment branches to
`main`, configure required reviewers and prevent self-review where the repository
plan supports them. Protect main with CI checks and code review.

Set environment secrets: `CLOUD_HOST` (51.79.167.213 or hostname), `CLOUD_PORT`
(explicit numeric SSH port for `lazy`), `CLOUD_SSH_KEY` (the deployment private key
whose public half unlocks `lazy`), `CLOUD_KNOWN_HOSTS` (verified entry).
The workflow username/destination are fixed as `lazy@` + the Hestia base path, so
they cannot point at the marketing site. Set repository variable
`CLOUD_DEPLOY_ENABLED=false` or leave it absent. It must be a repository variable
because the job-level condition is evaluated before environment variables become
available. No push or PR can execute the deployment job.

Before enabling, pass CI on the intended commit and review the payload. Resolve any
existing readiness failures in separately authorized work; do not bypass them. Then,
only when deployment is authorized, set the variable to `true` and manually run this
workflow on `main` with `deploy=true`. Manual runs with the default false run CI only.

## 6. Release, verification and rollback

The deploy job:
1. builds the payload in CI and stores committed content only;
2. SSHs as `lazy`, creates `incoming/<release>` (0711), rsyncs with D750/F640;
3. runs `activate.sh` which verifies SHA/checksums/extensions, links `storage` and
   `packages`, grants www-data read ACLs on `public/`, flips `current` atomically
   (lazy-owned, no root), and confirms the exact static `cloud-release.txt` over
   HTTPS before reporting success; on static-check failure it restores the previous
   pointer (or removes `current` on first deploy) and exits non-zero.

Before EVERY activation: record current release and take a coordinated backup of the
cloud DB, shared state, release-local assets and source customizations. Use existing
recovery tooling with a verified restore rehearsal, or an equivalent tested process.
Example after DB credentials are installed in a root-only MySQL option file
`/root/odarehub-cloud-backup.cnf` and the site is quiesced/traffic gated:

```bash
sudo install -d -m 700 /var/backups/odarehub-cloud
sudo mkdir -m 700 /var/backups/odarehub-cloud/PRE_RELEASE_ID
sudo sh -c 'mysqldump --defaults-extra-file=/root/odarehub-cloud-backup.cnf --single-transaction --no-tablespaces odarehub_cloud > /var/backups/odarehub-cloud/PRE_RELEASE_ID/database.sql'
sudo tar -czf /var/backups/odarehub-cloud/PRE_RELEASE_ID/files.tgz -C /home/lazy/web/cloud.odarehub.com/private/odarehub shared releases
readlink /home/lazy/web/cloud.odarehub.com/private/odarehub/current
```

Validate dump exit status, table-engine consistency, backup contents, checksums and
restoration into an isolated rehearsal DB/directory. Keep the gated docroot and
generate a valid `approved-sha` as part of the pre-activation review; the workflow
level gate (deploy=true + CLOUD_DEPLOY_ENABLED=true) is the execution approval.
First boot with an empty DB still requires a verified empty baseline/config backup.

`activate.sh` needs a `shared/storage/db_config.php` to exist and refuses otherwise.
Its HTTPS marker check proves serving the artifact, NOT PHP/DB health. Perl/PDO warm
caching is per-process; no FPM restart is required for new release paths but a
`sudo systemctl reload php8.5-fpm` is harmless if opcache staleness is suspected.

After workflow success, visit `https://cloud.odarehub.com/setup` for FIRST BOOT ONLY,
finish database bootstrap/admin/2FA as needed, verify login, DB access, CSS, an
authenticated page, upload/download and session isolation. Confirm setup cannot be
taken over by an unauthenticated visitor after completion. Do not run the installer
again on upgrades. Check Nginx/Apache/FPM logs and apex health.

If a check fails, keep the site gated and inspect logs. For manual code-only rollback
(after confirming DB compatibility and no source changes) as `lazy`:

```bash
base=/home/lazy/web/cloud.odarehub.com/private/odarehub
ln -s "$base/releases/PREVIOUS_RELEASE" "$base/current.rollback"
mv -Tf "$base/current.rollback" "$base/current"
```

If schema/shared data changed, restore the matched database, shared state and code
from the rehearsed recovery point; do not blindly run this symlink rollback. Keep
failed releases/logs for diagnosis. Retention/pruning is manual and must never
remove `current`, a required rollback release, or shared state.

References: [GitHub deployment controls](https://docs.github.com/en/actions/how-tos/deploy/configure-and-manage-deployments/control-deployments),
[workflow syntax](https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-syntax),
[PHP 8.5 migration guidance](https://www.php.net/manual/en/migration85.php).