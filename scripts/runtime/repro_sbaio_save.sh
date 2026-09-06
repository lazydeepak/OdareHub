#!/usr/bin/env bash
set -euo pipefail

tmp="$(mktemp -d)"
jar="$tmp/c.cookie"
target_email="${TARGET_EMAIL:-}"

cleanup() {
  rm -rf "$tmp"
}
trap cleanup EXIT

hash="$(/opt/homebrew/bin/php -r 'echo password_hash("Password123!", PASSWORD_DEFAULT);')"

if [[ -z "$target_email" ]]; then
  target_email="$(mysql -N -h 127.0.0.1 -u root -D erp_local -e "SELECT email FROM users WHERE authority_role='platform_admin' ORDER BY id LIMIT 1;")"
fi

if [[ -z "$target_email" ]]; then
  echo "error=no_platform_admin_user_found"
  exit 1
fi

mysql -h 127.0.0.1 -u root -D erp_local -e "UPDATE users SET password_hash='${hash}', twofa_enabled=0 WHERE email='${target_email}';"

/usr/bin/curl -sS -c "$jar" http://localhost:8000/login > "$tmp/login.html"
csrf="$(/usr/bin/grep -o 'name="csrf" value="[^"]*"' "$tmp/login.html" | /usr/bin/head -n1 | /usr/bin/sed 's/name="csrf" value="//;s/"$//')"

uid="$(mysql -N -h 127.0.0.1 -u root -D erp_local -e "SELECT id FROM users WHERE email='${target_email}' LIMIT 1;")"

if [[ -z "$uid" ]]; then
  echo "error=user_not_found_for_target_email"
  echo "target_email=${target_email}"
  exit 1
fi

/usr/bin/curl -sS -L -c "$jar" -b "$jar" -d "csrf=$csrf" \
  --data-urlencode "email=${target_email}" \
  --data-urlencode "password=Password123!" \
  http://localhost:8000/login > /dev/null

before="$(mysql -N -h 127.0.0.1 -u root -D erp_local -e "SELECT COALESCE(assigned_apps,'') FROM user_dashboard_assignments WHERE user_id=$uid LIMIT 1;")"

save_csrf="$(/usr/bin/curl -sS -b "$jar" "http://localhost:8000/ops/access-control/detail?user_id=$uid&full=1" | /usr/bin/grep -o 'name="csrf" value="[^"]*"' | /usr/bin/head -n1 | /usr/bin/sed 's/name="csrf" value="//;s/"$//')"

/usr/bin/curl -sS -D "$tmp/save.headers" -o "$tmp/save.html" -b "$jar" -c "$jar" -X POST \
  http://localhost:8000/ops/access-control/save \
  --data-urlencode "csrf=$save_csrf" \
  --data-urlencode "user_id=$uid" \
  --data-urlencode "redirect_to=/ops/access-control/detail?user_id=$uid&full=1" \
  --data-urlencode "account_type=platform_admin" \
  --data-urlencode "dashboard_type=platform_admin" \
  --data-urlencode "default_app=platform" \
  --data-urlencode "default_landing_page=/" \
  --data-urlencode "dashboard_mode=auto" \
  --data-urlencode "default_app_mode=auto" \
  --data-urlencode "landing_mode=auto" \
  --data-urlencode "assigned_apps=platform,manufacturing,sbaio" \
  --data-urlencode "account_class=platform_admin" \
  --data-urlencode "operational_profile=Platform Operations" > /dev/null

after="$(mysql -N -h 127.0.0.1 -u root -D erp_local -e "SELECT COALESCE(assigned_apps,'') FROM user_dashboard_assignments WHERE user_id=$uid LIMIT 1;")"
save_http="$(/usr/bin/awk 'toupper($1) ~ /^HTTP\// {print $2; exit}' "$tmp/save.headers")"
location="$(/usr/bin/awk 'tolower($1)=="location:" {print $2}' "$tmp/save.headers" | /usr/bin/tr -d '\r' | /usr/bin/tail -n1)"
flash_err="$(/usr/bin/curl -sS -b "$jar" "http://localhost:8000/ops/access-control/detail?user_id=$uid&full=1" | /usr/bin/grep -o 'Save failed:[^<]*' | /usr/bin/head -n1 || true)"

echo "uid=$uid"
echo "target_email=$target_email"
echo "before=$before"
echo "after=$after"
echo "save_http=$save_http"
echo "location=$location"
echo "flash_err=$flash_err"
