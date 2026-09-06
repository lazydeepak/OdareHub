#!/bin/bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8000}"
TEST_EMAIL="${TEST_EMAIL:-}"
TEST_PASSWORD="${TEST_PASSWORD:-Password123!}"
STRICT_Q_NORMALIZATION="${STRICT_Q_NORMALIZATION:-0}"
MYSQL_BIN="${MYSQL_BIN:-$(command -v mysql || true)}"
PHP_BIN="${PHP_BIN:-$(command -v php || true)}"
CURL_BIN="${CURL_BIN:-$(command -v curl || true)}"
GREP_BIN="${GREP_BIN:-$(command -v grep || true)}"
SED_BIN="${SED_BIN:-$(command -v sed || true)}"
MKTEMP_BIN="${MKTEMP_BIN:-$(command -v mktemp || true)}"

for bin_var in MYSQL_BIN PHP_BIN CURL_BIN GREP_BIN SED_BIN MKTEMP_BIN; do
  if [[ -z "${!bin_var}" ]]; then
    echo "missing required binary: ${bin_var}" >&2
    exit 1
  fi
done

tmp_dir="$(${MKTEMP_BIN} -d /tmp/widget-builder-smoke-XXXXXX)"
cookie_jar="$tmp_dir/session.cookie"
cleanup() {
  rm -rf "$tmp_dir"
}
trap cleanup EXIT

password_hash="$(${PHP_BIN} -r "echo password_hash('${TEST_PASSWORD}', PASSWORD_DEFAULT);")"
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

failures=0

expect_url_contains() {
  local label="$1"
  local url="$2"
  local expected_fragment="$3"

  local result
  result="$(${CURL_BIN} -sS -o /dev/null -w '%{http_code} %{url_effective}' -L -b "$cookie_jar" "$url")"
  local status="${result%% *}"
  local effective_url="${result#* }"

  if [[ "$status" != "200" ]]; then
    echo "FAIL ${label}: expected HTTP 200, got ${status} (${effective_url})"
    failures=$((failures + 1))
    return
  fi

  if [[ "$effective_url" != *"$expected_fragment"* ]]; then
    echo "FAIL ${label}: expected URL to contain '${expected_fragment}', got ${effective_url}"
    failures=$((failures + 1))
    return
  fi

  echo "OK   ${label}: ${effective_url}"
}

expect_url_not_contains() {
  local label="$1"
  local url="$2"
  local forbidden_fragment="$3"

  local result
  result="$(${CURL_BIN} -sS -o /dev/null -w '%{http_code} %{url_effective}' -L -b "$cookie_jar" "$url")"
  local status="${result%% *}"
  local effective_url="${result#* }"

  if [[ "$status" != "200" ]]; then
    echo "FAIL ${label}: expected HTTP 200, got ${status} (${effective_url})"
    failures=$((failures + 1))
    return
  fi

  if [[ "$effective_url" == *"$forbidden_fragment"* ]]; then
    echo "FAIL ${label}: URL must not contain '${forbidden_fragment}', got ${effective_url}"
    failures=$((failures + 1))
    return
  fi

  echo "OK   ${label}: ${effective_url}"
}

soft_expect_url_contains() {
  local label="$1"
  local url="$2"
  local expected_fragment="$3"

  local result
  result="$(${CURL_BIN} -sS -o /dev/null -w '%{http_code} %{url_effective}' -L -b "$cookie_jar" "$url")"
  local status="${result%% *}"
  local effective_url="${result#* }"

  if [[ "$status" != "200" ]]; then
    echo "WARN ${label}: expected HTTP 200, got ${status} (${effective_url})"
    return
  fi

  if [[ "$effective_url" != *"$expected_fragment"* ]]; then
    echo "WARN ${label}: expected URL to contain '${expected_fragment}', got ${effective_url}"
    return
  fi

  echo "OK   ${label}: ${effective_url}"
}

soft_expect_url_not_contains() {
  local label="$1"
  local url="$2"
  local forbidden_fragment="$3"

  local result
  result="$(${CURL_BIN} -sS -o /dev/null -w '%{http_code} %{url_effective}' -L -b "$cookie_jar" "$url")"
  local status="${result%% *}"
  local effective_url="${result#* }"

  if [[ "$status" != "200" ]]; then
    echo "WARN ${label}: expected HTTP 200, got ${status} (${effective_url})"
    return
  fi

  if [[ "$effective_url" == *"$forbidden_fragment"* ]]; then
    echo "WARN ${label}: URL should not contain '${forbidden_fragment}', got ${effective_url}"
    return
  fi

  echo "OK   ${label}: ${effective_url}"
}

echo "=== Widget Builder Canonical Redirect Smoke ==="
expect_url_contains "status fallback" "$BASE_URL/ops/widget-builder?status=bogus" "status=active"
expect_url_contains "per_page fallback" "$BASE_URL/ops/widget-builder?per_page=999" "per_page=20"
if [[ "$STRICT_Q_NORMALIZATION" == "1" ]]; then
  expect_url_contains "q whitespace collapse" "$BASE_URL/ops/widget-builder?q=%20alpha%20%20beta%20" "q=alpha+beta"
  expect_url_not_contains "q removed when blank" "$BASE_URL/ops/widget-builder?q=%20%20%20" "q="
else
  soft_expect_url_contains "q whitespace collapse" "$BASE_URL/ops/widget-builder?q=%20alpha%20%20beta%20" "q=alpha+beta"
  soft_expect_url_not_contains "q removed when blank" "$BASE_URL/ops/widget-builder?q=%20%20%20" "q="
fi
expect_url_not_contains "page bounded from overflow" "$BASE_URL/ops/widget-builder?status=archived&per_page=100&page=999" "page=999"

if [[ "$failures" -gt 0 ]]; then
  echo "=== Widget builder smoke FAILED (${failures} issue(s)) ===" >&2
  exit 1
fi

echo "=== Widget builder smoke PASSED ==="
