#!/bin/bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8000}"
TEST_EMAIL="${TEST_EMAIL:-}"
TEST_PASSWORD="${TEST_PASSWORD:-Password123!}"
MYSQL_BIN="${MYSQL_BIN:-$(command -v mysql || true)}"
PHP_BIN="${PHP_BIN:-$(command -v php || true)}"
CURL_BIN="${CURL_BIN:-$(command -v curl || true)}"
GREP_BIN="${GREP_BIN:-$(command -v grep || true)}"
SED_BIN="${SED_BIN:-$(command -v sed || true)}"
TR_BIN="${TR_BIN:-$(command -v tr || true)}"
MKTEMP_BIN="${MKTEMP_BIN:-$(command -v mktemp || true)}"

for bin_var in MYSQL_BIN PHP_BIN CURL_BIN GREP_BIN SED_BIN TR_BIN MKTEMP_BIN; do
  if [[ -z "${!bin_var}" ]]; then
    echo "missing required binary: ${bin_var}" >&2
    exit 1
  fi
done

tmp_dir="$(${MKTEMP_BIN} -d /tmp/operator-smoke-XXXXXX)"
cookie_jar="$tmp_dir/operator.cookie"
cleanup() {
  rm -rf "$tmp_dir"
}
trap cleanup EXIT

password_hash="$(${PHP_BIN} -r "echo password_hash('${TEST_PASSWORD}', PASSWORD_DEFAULT);")"
if [[ -z "$TEST_EMAIL" ]]; then
  TEST_EMAIL="$(${MYSQL_BIN} -N -h 127.0.0.1 -u root -D erp_local -e "SELECT email FROM users WHERE authority_role='app_user' ORDER BY id LIMIT 1;")"
fi

if [[ -z "$TEST_EMAIL" ]]; then
  TEST_EMAIL="$(${MYSQL_BIN} -N -h 127.0.0.1 -u root -D erp_local -e "SELECT email FROM users WHERE authority_role='platform_admin' ORDER BY id LIMIT 1;")"
fi

if [[ -z "$TEST_EMAIL" ]]; then
  echo "failed to resolve test email (no platform_admin user found)" >&2
  exit 1
fi

${MYSQL_BIN} -h 127.0.0.1 -u root -D erp_local -e "UPDATE users SET password_hash='${password_hash}', twofa_enabled=0 WHERE email='${TEST_EMAIL}';"

${CURL_BIN} -sS -c "$cookie_jar" "$BASE_URL/login" > "$tmp_dir/login.html"
csrf_token="$(${GREP_BIN} -o 'name="csrf" value="[^"]*"' "$tmp_dir/login.html" | head -1 | ${SED_BIN} 's/.*value="//;s/"//')"

if [[ -z "$csrf_token" ]]; then
  echo "failed to extract login CSRF token" >&2
  exit 1
fi

${CURL_BIN} -sS -L -c "$cookie_jar" -b "$cookie_jar" \
  --data-urlencode "csrf=${csrf_token}" \
  --data-urlencode "email=${TEST_EMAIL}" \
  --data-urlencode "password=${TEST_PASSWORD}" \
  "$BASE_URL/login" > /dev/null

resolved_username="$(${MYSQL_BIN} -N -h 127.0.0.1 -u root -D erp_local -e "SELECT COALESCE(NULLIF(username,''), SUBSTRING_INDEX(email,'@',1)) FROM users WHERE email='${TEST_EMAIL}' LIMIT 1;" | ${TR_BIN} -d '\r')"
resolved_authority_role="$(${MYSQL_BIN} -N -h 127.0.0.1 -u root -D erp_local -e "SELECT COALESCE(authority_role,'') FROM users WHERE email='${TEST_EMAIL}' LIMIT 1;" | ${TR_BIN} -d '\r')"
if [[ -z "$resolved_username" ]]; then
  echo "failed to resolve operator username" >&2
  exit 1
fi

if [[ -z "$resolved_authority_role" ]]; then
  echo "failed to resolve authority role" >&2
  exit 1
fi

# Extract session CSRF from meta tag (always present in every operator page)
${CURL_BIN} -sS -L -b "$cookie_jar" "$BASE_URL/u/${resolved_username}/dashboard" > "$tmp_dir/csrf_page.html"
session_csrf="$(${GREP_BIN} -o 'name="csrf-token" content="[^"]*"' "$tmp_dir/csrf_page.html" | head -1 | ${SED_BIN} 's/.*content="//;s/"//')"

if [[ -z "$session_csrf" ]]; then
  echo "failed to extract operator session CSRF token" >&2
  exit 1
fi

declare -a canonical_routes=()
if [[ "$resolved_authority_role" == "app_user" ]]; then
  canonical_routes=(
    "dashboard"
    "work-entry"
    "critical"
    "recent"
    "parts"
    "production"
    "processing"
    "fulfillment"
    "coverage"
    "demand"
    "orders"
    "qc"
    "machines"
    "assembly"
    "materials"
    "notifications"
    "messages"
    "account"
  )
else
  canonical_routes=(
    "dashboard"
    "account"
  )
fi

failures=0

check_route() {
  local label="$1"
  local url="$2"
  local expected_url_fragment="$3"
  local result
  result="$(${CURL_BIN} -sS -o /dev/null -w '%{http_code} %{url_effective}' -L -b "$cookie_jar" "$url")"
  local status="${result%% *}"
  local effective_url="${result#* }"

  if [[ "$status" != "200" ]]; then
    echo "FAIL ${label}: expected 200, got ${status} (${effective_url})"
    failures=$((failures + 1))
    return
  fi

  if [[ "$effective_url" != *"${expected_url_fragment}"* ]]; then
    echo "FAIL ${label}: expected URL to contain ${expected_url_fragment}, got ${effective_url}"
    failures=$((failures + 1))
    return
  fi

  echo "OK   ${label}: ${effective_url}"
}

check_post_redirect() {
  local label="$1"
  local url="$2"
  local redirect_value="$3"
  local expected_url_fragment="$4"
  local result
  result="$(${CURL_BIN} -sS -o /dev/null -w '%{http_code} %{url_effective}' -L -b "$cookie_jar" -c "$cookie_jar" \
    --data-urlencode "csrf=${session_csrf}" \
    --data-urlencode "redirect_to=${redirect_value}" \
    "$url")"
  local status="${result%% *}"
  local effective_url="${result#* }"

  if [[ "$status" != "200" ]]; then
    echo "FAIL ${label}: expected 200, got ${status} (${effective_url})"
    failures=$((failures + 1))
    return
  fi

  if [[ "$effective_url" != *"${expected_url_fragment}"* ]]; then
    echo "FAIL ${label}: expected URL to contain ${expected_url_fragment}, got ${effective_url}"
    failures=$((failures + 1))
    return
  fi

  echo "OK   ${label}: ${effective_url}"
}

echo "=== Operator canonical routes ==="
echo "role=${resolved_authority_role} email=${TEST_EMAIL} username=${resolved_username}"
for route in "${canonical_routes[@]}"; do
  check_route "canonical:${route}" "$BASE_URL/u/${resolved_username}/${route}" "/u/${resolved_username}/${route}"
done

if [[ "$resolved_authority_role" == "app_user" ]]; then
  echo "=== Operator legacy redirects ==="
  check_route "legacy:/u/qc" "$BASE_URL/u/qc" "/u/${resolved_username}/qc"
  check_route "legacy:/u/dashboard" "$BASE_URL/u/dashboard" "/u/${resolved_username}/dashboard"

  echo "=== Operator aliases ==="
  check_route "alias:dispatch" "$BASE_URL/u/${resolved_username}/dispatch" "/u/${resolved_username}/fulfillment?tab=dispatch"
  check_route "alias:preparation" "$BASE_URL/u/${resolved_username}/preparation" "/u/${resolved_username}/fulfillment"
  check_route "alias:alerts" "$BASE_URL/u/${resolved_username}/alerts" "/u/${resolved_username}/notifications"

  echo "=== Operator POST redirect hardening ==="
  check_post_redirect "post:notifications/read-all" "$BASE_URL/u/${resolved_username}/notifications/read-all" "https://evil.invalid/outside" "/u/${resolved_username}/notifications"
  check_post_redirect "post:messages/read-all" "$BASE_URL/u/${resolved_username}/messages/read-all" "//evil.invalid/outside" "/u/${resolved_username}/messages"
else
  echo "=== Operator legacy/alias/post checks skipped for non-app_user role (${resolved_authority_role}) ==="
fi

if [[ "$failures" -gt 0 ]]; then
  echo "=== Operator smoke FAILED (${failures} issue(s)) ===" >&2
  exit 1
fi

echo "=== Operator smoke PASSED ==="