#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${1:-http://127.0.0.1:8087}"
PASSWORD="${2:-Test@1234}"

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

declare -a USERS=(
  "test.production.leader@erp.local|Production Leader|/ops/production-dashboard|no|/manufacturing/production-queue|/ops/dashboard-assignments|Production Dashboard"
  "test.assembly.leader@erp.local|Assembly Leader|/ops/assembly-dashboard|no|/manufacturing/assembly-queue|/ops/dashboard-assignments|Assembly Dashboard"
  "test.qc.leader@erp.local|QC Leader|/ops/qc-dashboard|no|/qc-entries|/ops/dashboard-assignments|QC Dashboard"
  "test.dispatch.leader@erp.local|Dispatch Leader|/ops/dispatch-dashboard|no|/manufacturing/dispatch-ops|/ops/dashboard-assignments|Dispatch Dashboard"
  "test.admin@erp.local|Platform Operations|/ops/platform-admin-dashboard|yes|/ops/approval-inbox|/ops/dashboard-assignments|Platform Admin Dashboard"
  "test.itadmin@erp.local|Platform Operations|/ops/platform-admin-dashboard|yes|/admin/routes|/ops/dashboard-assignments|Platform Admin Dashboard"
  "test.sysadmin@erp.local|Platform Security|/ops/platform-admin-dashboard|yes|/admin/base|/ops/dashboard-assignments|Platform Admin Dashboard"
  "test.accountadmin@erp.local|App Administration|/ops/app-admin-dashboard|no|/ops/app-admin-dashboard|/ops/dashboard-assignments|App Admin Dashboard"
  "test.viewer@erp.local|Readonly Observer|/ops/my-work|no|/manufacturing/production-queue|/ops/dashboard-assignments|"
)

extract_csrf() {
  local html="$1"
  printf "%s" "$html" | sed -n 's/.*name="csrf" value="\([^"]*\)".*/\1/p' | head -n1
}

http_status() {
  local url="$1"
  local cookie="$2"
  curl -sS -o /dev/null -w "%{http_code}" -b "$cookie" "$url"
}

echo "Validation base URL: $BASE_URL"
echo "Password used: $PASSWORD"
echo "---------------------------------------------------------------------------------------------"
printf "%-32s | %-14s | %-8s | %-8s | %-9s | %-9s | %-8s | %s\n" "user" "role" "login" "2fa" "landing" "board" "sidebar" "notes"
echo "---------------------------------------------------------------------------------------------"

for entry in "${USERS[@]}"; do
  IFS='|' read -r email role expected_landing can_board expected_route forbidden_route expected_title <<< "$entry"

  cookie="$TMP_DIR/cookie_$(echo "$email" | tr '@.' '__').txt"
  headers="$TMP_DIR/headers.txt"

  login_page="$(curl -sS -c "$cookie" "$BASE_URL/login")"
  csrf="$(extract_csrf "$login_page")"
  if [[ -z "$csrf" ]]; then
    printf "%-32s | %-14s | %-8s | %-8s | %-9s | %-9s | %s\n" "$email" "$role" "FAIL" "n/a" "n/a" "n/a" "no csrf on /login"
    continue
  fi

  curl -sS -D "$headers" -o /dev/null -b "$cookie" -c "$cookie" \
    -X POST "$BASE_URL/login" \
    --data-urlencode "csrf=$csrf" \
    --data-urlencode "redirect=/ops/dashboard" \
    --data-urlencode "email=$email" \
    --data-urlencode "password=$PASSWORD"

  location="$(grep -i '^Location:' "$headers" | tail -n1 | awk '{print $2}' | tr -d '\r')"
  login_ok="OK"
  twofa="NO"
  notes=""

  if [[ -z "$location" ]]; then
    login_ok="FAIL"
    notes="no redirect after login"
  fi

  if [[ "$location" == "/2fa" ]]; then
    twofa="YES"
  fi

  dash_headers="$TMP_DIR/dash_headers.txt"
  curl -sS -D "$dash_headers" -o /dev/null -b "$cookie" "$BASE_URL/ops/dashboard"
  dash_location="$(grep -i '^Location:' "$dash_headers" | tail -n1 | awk '{print $2}' | tr -d '\r')"

  landing_ok="NO"
  if [[ "$dash_location" == "$expected_landing" ]]; then
    landing_ok="OK"
  else
    notes="${notes} landing=$dash_location"
  fi

  board_status="$(http_status "$BASE_URL$forbidden_route" "$cookie")"
  board_ok="NO"
  if [[ "$can_board" == "yes" && "$board_status" == "200" ]]; then
    board_ok="OK"
  elif [[ "$can_board" == "no" && "$board_status" != "200" ]]; then
    board_ok="OK"
  else
    notes="${notes} board_status=$board_status"
  fi

  route_status="$(http_status "$BASE_URL$expected_route" "$cookie")"
  if [[ "$route_status" != "200" && "$route_status" != "302" ]]; then
    notes="${notes} route_status=$route_status"
  fi

  sidebar_ok="OK"
  landing_html="$(curl -sS -b "$cookie" "$BASE_URL$expected_landing")"
  if [[ -n "$expected_title" ]] && ! grep -q "$expected_title" <<< "$landing_html"; then
    notes="${notes} missing_title"
  fi
  if [[ "$can_board" == "yes" ]]; then
    if ! grep -q "Access Control Board" <<< "$landing_html"; then
      sidebar_ok="NO"
      notes="${notes} missing_user_control_link"
    fi
  else
    if grep -q "Access Control Board" <<< "$landing_html"; then
      sidebar_ok="NO"
      notes="${notes} unexpected_user_control_link"
    fi
  fi

  if [[ "$twofa" == "YES" ]]; then
    notes="${notes} unexpected-2fa"
  fi

  printf "%-32s | %-14s | %-8s | %-8s | %-9s | %-9s | %-8s | %s\n" "$email" "$role" "$login_ok" "$twofa" "$landing_ok" "$board_ok" "$sidebar_ok" "$notes"
done
