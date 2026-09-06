#!/usr/bin/env bash
set -euo pipefail
out=${1:?Provide a new absolute output directory}
[[ "$out" = /* && ! -e "$out" ]]
mkdir -p "$out"
# Explicit runtime roots; never package tracked storage, local credentials,
# rehearsal copies, package exports, or the repository's vendor directory.
for path in app apps platform plugins public resources scripts bin etc; do
  rsync -a --exclude='.DS_Store' --exclude='.env*' --exclude='AGENTS.md' \
    --exclude='Tests/' --exclude='tests/' --exclude='*.md' "$path" "$out/"
done
cp composer.json composer.lock "$out/"
(
  cd "$out"
  composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts --no-plugins --optimize-autoloader
  composer check-platform-reqs --no-dev
  php scripts/assets/publish_registered_css.php --apply
  php scripts/assets/compile_first_boot_css.php --apply
  # Generated health marker identifies the exact code, not database readiness.
  printf '%s\n' "${GITHUB_SHA:?Missing commit SHA}" > public/cloud-release.txt
  # shellcheck disable=SC2094
  find . -type f ! -name SHA256SUMS -print0 | sort -z | xargs -0 sha256sum > SHA256SUMS
)
