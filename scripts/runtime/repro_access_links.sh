#!/usr/bin/env bash
set -euo pipefail

mode="${1:-links}"
target_email="${TARGET_EMAIL:-}"

tmp="$(mktemp -d)"
jar="$tmp/c.cookie"

cleanup() {
  rm -rf "$tmp"
}
trap cleanup EXIT

if [[ -z "$target_email" ]]; then
  target_email="$(mysql -N -h 127.0.0.1 -u root -D erp_local -e "SELECT email FROM users WHERE authority_role='platform_admin' ORDER BY id LIMIT 1;")"
fi

if [[ -z "$target_email" ]]; then
  echo "error=no_platform_admin_user_found"
  exit 1
fi

hash="$(/opt/homebrew/bin/php -r 'echo password_hash("Password123!", PASSWORD_DEFAULT);')"
mysql -h 127.0.0.1 -u root -D erp_local -e "UPDATE users SET password_hash='${hash}', twofa_enabled=0 WHERE email='${target_email}';"

/usr/bin/curl -sS -c "$jar" http://localhost:8000/login > "$tmp/login.html"
csrf="$(/usr/bin/grep -o 'name="csrf" value="[^"]*"' "$tmp/login.html" | /usr/bin/head -n1 | /usr/bin/sed 's/name="csrf" value="//;s/"$//')"

/usr/bin/curl -sS -L -c "$jar" -b "$jar" -d "csrf=$csrf" \
  --data-urlencode "email=${target_email}" \
  --data-urlencode "password=Password123!" \
  http://localhost:8000/login > /dev/null

uid="$(mysql -N -h 127.0.0.1 -u root -D erp_local -e "SELECT id FROM users WHERE email='${target_email}' LIMIT 1;")"

if [[ -z "$uid" ]]; then
  echo "error=user_not_found_for_target_email"
  echo "target_email=${target_email}"
  exit 1
fi

echo "uid=$uid"
echo "target_email=$target_email"

case "$mode" in
  links)
    /usr/bin/curl -sS -b "$jar" http://localhost:8000/ops/user-control > "$tmp/board.html"
    echo "open_access_link_samples:"
    /usr/bin/grep -Eo "/ops/access-control/detail\?user_id=[0-9]+&full=1" "$tmp/board.html" | /usr/bin/head -n 5 | tee "$tmp/links.txt" || true
    if [[ ! -s "$tmp/links.txt" ]]; then
      echo "no_open_access_links_found"
      exit 0
    fi
    echo "-- checks --"
    while read -r p; do
      [[ -n "$p" ]] || continue
      code="$(/usr/bin/curl -sS -o "$tmp/out.html" -w "%{http_code}" -b "$jar" "http://localhost:8000$p")"
      title="$(/usr/bin/grep -o "<title>[^<]*" "$tmp/out.html" | /usr/bin/head -n1 || true)"
      echo "$p -> $code | $title"
      /usr/bin/grep -nE "Fatal error|Uncaught|Exception|Warning|TypeError" "$tmp/out.html" | /usr/bin/head -n 2 || true
    done < "$tmp/links.txt"
    ;;
  user-detail-link)
    /usr/bin/curl -sS -b "$jar" "http://localhost:8000/ops/user-control/detail?user_id=$uid" > "$tmp/detail_user.html"
    link="$(/usr/bin/grep -Eo "/ops/access-control/detail\?user_id=[0-9]+&full=1" "$tmp/detail_user.html" | /usr/bin/head -n1 || true)"
    echo "link=$link"
    if [[ -n "$link" ]]; then
      code="$(/usr/bin/curl -sS -o "$tmp/out.html" -w "%{http_code}" -b "$jar" "http://localhost:8000$link")"
      title="$(/usr/bin/grep -o "<title>[^<]*" "$tmp/out.html" | /usr/bin/head -n1 || true)"
      echo "target_http=$code"
      echo "target_title=$title"
      /usr/bin/grep -nE "Fatal error|Uncaught|Exception|Warning|TypeError" "$tmp/out.html" | /usr/bin/head -n 5 || true
    fi
    ;;
  find-pattern)
    /usr/bin/curl -sS -b "$jar" "http://localhost:8000/ops/user-control/detail?user_id=$uid" > "$tmp/detail_user.html"
    echo "matches:"
    /usr/bin/grep -Eo '/ops/access-control/detail\?user_id=[0-9]+(&|&amp;)full=1|/ops/access-control[^"]*' "$tmp/detail_user.html" | /usr/bin/head -n 20
    ;;
  auth-detail-checks)
    code_user_full="$(/usr/bin/curl -sS -o "$tmp/user_full.html" -w "%{http_code}" -b "$jar" "http://localhost:8000/ops/access-control/detail?user_id=$uid&full=1")"
    code_user_partial="$(/usr/bin/curl -sS -o "$tmp/user_partial.html" -w "%{http_code}" -b "$jar" "http://localhost:8000/ops/access-control/detail?user_id=$uid")"
    code_item="$(/usr/bin/curl -sS -o "$tmp/item.html" -w "%{http_code}" -b "$jar" "http://localhost:8000/ops/access-control/detail?item_type=app&item_key=manufacturing&full=1")"
    echo "csrf_len=${#csrf}"
    echo "user_full_http=$code_user_full"
    echo "user_partial_http=$code_user_partial"
    echo "item_http=$code_item"
    echo "user_full_title=$(/usr/bin/grep -o "<title>[^<]*" "$tmp/user_full.html" | /usr/bin/head -n1 || true)"
    echo "item_title=$(/usr/bin/grep -o "<title>[^<]*" "$tmp/item.html" | /usr/bin/head -n1 || true)"
    /usr/bin/grep -nE "Fatal error|Uncaught|TypeError|Exception|Warning" "$tmp/user_full.html" | /usr/bin/head -n 5 || true
    ;;
  *)
    echo "error=unsupported_mode"
    echo "mode=$mode"
    exit 2
    ;;
esac
