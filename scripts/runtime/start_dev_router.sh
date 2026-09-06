#!/bin/bash
set -euo pipefail

cd "$(dirname "$0")/../.."

pkill -f 'php -S localhost:8000' || true

exec /opt/homebrew/bin/php \
  -d upload_max_filesize=8M \
  -d post_max_size=8M \
  -S localhost:8000 \
  -t public \
  tools/dev-router.php