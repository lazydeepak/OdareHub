#!/usr/bin/env bash
set -euo pipefail
release=${1:?Missing release ID}
sha=${2:?Missing SHA}
[[ "$sha" =~ ^[a-f0-9]{40}$ ]]
[[ "$release" =~ ^[a-f0-9]{40}-[0-9]+-[0-9]+$ && "$release" == "$sha-"* ]]
[[ "$(id -un)" == lazy ]]
base="$HOME/web/cloud.odarehub.com/private/odarehub"
exec 9>"$base/deploy.lock"
flock -n 9
# FPM (lazy) reads the shared instance state; PHP-FPM for this domain already
# covers $base via open_basedir, verified against the running pool.
[[ -f "$base/shared/storage/db_config.php" ]]
[[ ! -e "$base/releases/$release" ]]
source_dir="$base/incoming/$release"
[[ -d "$source_dir" ]]
cd "$source_dir"
sha256sum --check --quiet SHA256SUMS
[[ ! -e storage && ! -e packages ]]
[[ "$(cat public/cloud-release.txt)" == "$sha" ]]
php8.5 -r 'if (PHP_VERSION_ID < 80500) exit(1);'
# shellcheck disable=SC2016
php8.5 -r 'foreach (["mysqli","mbstring","dom","xml","zip","gd","curl","fileinfo"] as $e) if (!extension_loaded($e)) {fwrite(STDERR,"Missing $e\n"); exit(1);} require "vendor/autoload.php";'
ln -s "$base/shared/storage" storage
ln -s "$base/shared/packages" packages
# Release-local assets must be writable for preflight/CSS self-healing by FPM (lazy).
find public/assets -type d -exec chmod 2770 {} +
find public/assets -type f -exec chmod 660 {} +
# Nginx (www-data) reads only the served public subtree; sources stay lazy-owned.
setfacl -m u:www-data:x .
setfacl -R -m u:www-data:rX public
find public/assets -type d -exec setfacl -m d:u:www-data:rX {} +
mv "$source_dir" "$base/releases/$release"
previous=$(readlink "$base/current" || true)
if [[ -n "$previous" ]]; then
  [[ "$previous" == "$base/releases/"* && -d "$previous" ]]
fi
ln -s "$base/releases/$release" "$base/current.next"
mv -Tf "$base/current.next" "$base/current"
# Static marker served via nginx static block; does not bootstrap or migrate DB.
# public_html -> private/odarehub/current/public is a one-time panel symlink.
if ! result=$(curl --fail --silent --show-error --max-time 20 \
    --resolve cloud.odarehub.com:443:51.79.167.213 https://cloud.odarehub.com/cloud-release.txt) || [[ "$result" != "$sha" ]]; then
  if [[ -n "$previous" ]]; then
    ln -s "$previous" "$base/current.rollback"
    mv -Tf "$base/current.rollback" "$base/current"
  else
    rm "$base/current"
  fi
  echo 'Static release verification failed; code pointer restored.' >&2
  exit 1
fi
printf 'Activated %s. Previous: %s\n' "$release" "${previous:-none}"