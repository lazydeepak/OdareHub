#!/bin/bash
set -e
TMP=$(mktemp -d)
JAR="$TMP/c.cookie"

# Login
/usr/bin/curl -sS -c "$JAR" http://localhost:8000/login > "$TMP/login.html"
CSRF=$(grep -o 'name="csrf" value="[^"]*"' "$TMP/login.html" | head -1 | sed 's/name="csrf" value="//;s/"$//')
/usr/bin/curl -sS -L -c "$JAR" -b "$JAR" \
  --data-urlencode "csrf=$CSRF" \
  --data-urlencode "email=lazydeepak@outlook.com" \
  --data-urlencode "password=Password123!" \
  http://localhost:8000/login > /dev/null
echo "Logged in"

# Test each focus page
for page in dashboard qc machines assembly alerts coverage dispatch work-entry parts production processing preparation dispatch-detail parts-detail account dispatch-adapter materials; do
  STATUS=$(/usr/bin/curl -sS -o "$TMP/$page.html" -w "%{http_code}" -b "$JAR" "http://localhost:8000/u/lazydeepak/$page")
  if [ "$STATUS" = "200" ]; then
    # Check for PHP fatal error in output
    if grep -q "Fatal error\|Parse error\|Uncaught" "$TMP/$page.html" 2>/dev/null; then
      echo "  $page: $STATUS (PHP ERROR)"
    else
      echo "  $page: $STATUS OK"
    fi
  else
    echo "  $page: $STATUS FAIL"
  fi
done

rm -rf "$TMP"
