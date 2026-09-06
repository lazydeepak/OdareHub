#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

GREP_BIN="${GREP_BIN:-$(command -v grep || true)}"
AWK_BIN="${AWK_BIN:-$(command -v awk || true)}"

if [[ -z "$GREP_BIN" || -z "$AWK_BIN" ]]; then
  echo "missing required binaries: grep and awk" >&2
  exit 2
fi

check_literal() {
  local needle="$1"
  shift
  "$GREP_BIN" -F -q -- "$needle" "$@"
}

failures=0
expected_scan_anchors=(
  "public/index.php"
  "apps/Shell/routes.php"
  "apps/Shell/Services/WorkspaceWrapperRegistry.php"
  "apps/Shell/Composers"
  "apps/Shell/Services"
  "apps/Shell/Views/operator"
  "apps/Shell/Views/admin"
  "admin_username_handoff"
  "admin_canonical_home_template"
  "legacy_me_alias_compatibility_only"
  "primary_me_link_emission_pattern"
)
active_scan_anchors=(
  "public/index.php"
  "apps/Shell/routes.php"
  "apps/Shell/Services/WorkspaceWrapperRegistry.php"
  "apps/Shell/Composers"
  "apps/Shell/Services"
  "apps/Shell/Views/operator"
  "apps/Shell/Views/admin"
  "admin_username_handoff"
  "admin_canonical_home_template"
  "legacy_me_alias_compatibility_only"
  "primary_me_link_emission_pattern"
)

echo "[architecture] check_admin_route_contract"

echo "- verifying admin route scan contract"
if [[ "${#active_scan_anchors[@]}" -ne "${#expected_scan_anchors[@]}" ]]; then
  echo "  fail: admin route scan anchor count changed; expected ${#expected_scan_anchors[@]}, found ${#active_scan_anchors[@]}" >&2
  failures=$((failures + 1))
else
  for index in "${!expected_scan_anchors[@]}"; do
    if [[ "${active_scan_anchors[$index]}" == "${expected_scan_anchors[$index]}" ]]; then
      echo "  ok: scan-anchor[$index] ${active_scan_anchors[$index]}"
    else
      echo "  fail: scan-anchor[$index] changed; expected ${expected_scan_anchors[$index]}, found ${active_scan_anchors[$index]}" >&2
      failures=$((failures + 1))
    fi
  done
fi

echo "- verifying /admin/{username} preprocessing contract"
if ! check_literal "admin_username" public/index.php apps/Shell/routes.php; then
  echo "missing admin_username route handoff between public/index.php and Shell routes"
  failures=$((failures + 1))
fi

echo "- verifying /admin route handler and /me legacy alias contract"
if ! check_literal "\$router->get('/admin'" apps/Shell/routes.php; then
  echo "missing Shell /admin route handler"
  failures=$((failures + 1))
fi
if ! check_literal "\$router->get('/me'" apps/Shell/routes.php; then
  echo "missing Shell /me legacy alias route"
  failures=$((failures + 1))
fi
if ! check_literal "Do NOT emit new /me links" apps/Shell/routes.php; then
  echo "missing /me compatibility-only route guidance"
  failures=$((failures + 1))
fi
if ! check_literal "LandingPageService::getLandingOrLogin(" apps/Shell/routes.php; then
  echo "missing landing service redirect from legacy /me alias"
  failures=$((failures + 1))
fi
if ! "$AWK_BIN" '/Legacy \/admin entrypoint/ { saw_comment=1 } /header\(.Location: \/me/ { saw_redirect=1 } END { exit (saw_comment && saw_redirect) ? 0 : 1 }' public/index.php; then
  echo "missing documented bare /admin compatibility redirect through /me alias"
  failures=$((failures + 1))
fi

echo "- verifying admin wrapper registry contract"
if ! "$AWK_BIN" '/home_template/ && /\/admin\/\{username\}/ { found=1 } END { exit found ? 0 : 1 }' apps/Shell/Services/WorkspaceWrapperRegistry.php; then
  echo "missing canonical admin home_template /admin/{username}"
  failures=$((failures + 1))
fi
if ! "$AWK_BIN" '/route_prefixes/ && /\/admin/ && /\/ops/ && /\/apps/ { found=1 } END { exit found ? 0 : 1 }' apps/Shell/Services/WorkspaceWrapperRegistry.php; then
  echo "missing admin wrapper route prefixes /admin, /ops, /apps"
  failures=$((failures + 1))
fi

echo "- scanning Shell operator/admin emitters for primary /me links"
targets=(
  "apps/Shell/Composers"
  "apps/Shell/Services"
  "apps/Shell/Views/operator"
  "apps/Shell/Views/admin"
)
tmp_file="$(mktemp /tmp/admin-route-contract-XXXXXX)"
trap 'rm -f "$tmp_file"' EXIT

allowlist_pattern='AdminLayerService\.php|AdminSurfaceComposer\.php|home\.php|routes\.php|path === .*/me|Legacy /me|legacy /me|Do NOT emit new /me|currently served at /me|Build all data payloads for the /me surface|Render the full /me admin page'

if "$GREP_BIN" -RInE -- "href=|action=|formaction=|data-href=|Location:|window\.location" "${targets[@]}" 2>/dev/null \
  | "$AWK_BIN" 'match($0, /(^|[^[:alnum:]_])\/me([\/\?"\047[:space:]]|$)/)' > "$tmp_file"; then
  non_allowlisted_count="$("$GREP_BIN" -n -vE "$allowlist_pattern" "$tmp_file" | "$GREP_BIN" -c . || true)"
  if [[ "$non_allowlisted_count" != "0" ]]; then
    "$GREP_BIN" -n -vE "$allowlist_pattern" "$tmp_file" || true
    failures=$((failures + 1))
  fi
fi

echo "- scanned ${#targets[@]} admin route emitter target(s)"

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (admin route contract violations found)" >&2
  exit 1
fi

echo "RESULT: PASS"
