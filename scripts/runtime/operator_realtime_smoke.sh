#!/bin/zsh
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
PHP_BIN="${PHP_BIN:-/opt/homebrew/bin/php}"
NODE_BIN="${NODE_BIN:-/opt/homebrew/bin/node}"
HOST="${OPERATOR_REALTIME_HOST:-127.0.0.1}"
PORT="${OPERATOR_REALTIME_PORT:-8011}"
USERNAME="${OPERATOR_REALTIME_SMOKE_USER:-lazy}"
VIEW="${OPERATOR_REALTIME_SMOKE_VIEW:-dashboard}"
AUTH_TOKEN="${OPERATOR_REALTIME_SMOKE_AUTH:-smoke-token}"
LOG_FILE="$(mktemp -t operator-realtime-smoke-log.XXXXXX)"
PID=""

cleanup() {
  if [[ -n "$PID" ]] && kill -0 "$PID" 2>/dev/null; then
    kill "$PID" 2>/dev/null || true
    wait "$PID" 2>/dev/null || true
  fi
  rm -f "$LOG_FILE"
}
trap cleanup EXIT INT TERM

cd "$ROOT_DIR"

OPERATOR_REALTIME_SHOW_DEPRECATIONS=0 "$PHP_BIN" bin/operator-realtime-server --host="$HOST" --port="$PORT" >"$LOG_FILE" 2>&1 &
PID=$!

for _ in {1..20}; do
  if grep -q "Listening on ws://$HOST:$PORT" "$LOG_FILE" 2>/dev/null; then
    break
  fi
  sleep 0.25
done

if ! grep -q "Listening on ws://$HOST:$PORT" "$LOG_FILE" 2>/dev/null; then
  echo "Server did not start"
  cat "$LOG_FILE"
  exit 1
fi

SMOKE_OUTPUT="$($NODE_BIN - <<NODE
const ws = new WebSocket('ws://${HOST}:${PORT}/operator/${USERNAME}/${VIEW}?auth=${AUTH_TOKEN}&username=${USERNAME}&view=${VIEW}');
const messages = [];
const timeout = setTimeout(() => {
  console.error('timeout waiting for realtime messages');
  try { ws.close(); } catch {}
  process.exit(1);
}, 5000);
ws.onopen = () => {
  ws.send(JSON.stringify({
    type: 'subscribe',
    username: '${USERNAME}',
    view: '${VIEW}',
    preferences: {
      refresh_interval_ms: 5000,
      kpi_keys: ['summary', 'critical_orders']
    }
  }));
};
ws.onmessage = (event) => {
  messages.push(event.data);
  console.log(event.data);
  if (messages.length >= 3) {
    clearTimeout(timeout);
    ws.close();
  }
};
ws.onerror = () => {
  clearTimeout(timeout);
  process.exit(1);
};
ws.onclose = () => {
  clearTimeout(timeout);
  process.exit(messages.length >= 3 ? 0 : 1);
};
NODE
)"

echo "$SMOKE_OUTPUT"

grep -q '"type":"connected"' <<< "$SMOKE_OUTPUT"
grep -q '"type":"subscribed"' <<< "$SMOKE_OUTPUT"
grep -q '"type":"kpi_update"' <<< "$SMOKE_OUTPUT"

echo "operator realtime smoke test passed"