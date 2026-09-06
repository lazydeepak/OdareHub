#!/bin/bash
set -euo pipefail

cd "$(dirname "$0")/../.."

pkill -f 'bin/operator-realtime-server' || true

exec env OPERATOR_REALTIME_SHOW_DEPRECATIONS="${OPERATOR_REALTIME_SHOW_DEPRECATIONS:-0}" \
  /opt/homebrew/bin/php \
  bin/operator-realtime-server \
  --host="${OPERATOR_REALTIME_HOST:-127.0.0.1}" \
  --port="${OPERATOR_REALTIME_PORT:-8001}"