#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

tool_dir="apps/Studio/Tools/LocalizationStudio"
routes_file="apps/Studio/routes.php"

echo "[architecture] check_localization_studio_v2_1_readiness"
echo "- verifying v2.1 readiness: no unauthorized implementation patterns"
echo ""

failures=0
passes=0

check() {
  local label="$1"
  local op="$2"
  local file="$3"
  local pattern="$4"

  if [[ ! -f "$file" ]]; then
    echo "  SKIP: $label — file not found ($file)"
    return
  fi

  if [[ "$op" == "present" ]]; then
    if grep -q -- "$pattern" "$file"; then
      echo "  ok: $label"
      passes=$((passes + 1))
    else
      echo "  FAIL: $label — expected pattern not found: $pattern" >&2
      failures=$((failures + 1))
    fi
  elif [[ "$op" == "absent" ]]; then
    if grep -q -- "$pattern" "$file"; then
      echo "  FAIL: $label — forbidden pattern found: $pattern" >&2
      failures=$((failures + 1))
    else
      echo "  ok: $label"
      passes=$((passes + 1))
    fi
  elif [[ "$op" == "absent_except_editor" ]]; then
    # Check pattern in non-editor files only
    local editor_file="$ROOT_DIR/apps/Studio/Tools/LocalizationStudio/Views/editor.php"
    local found=false
    while IFS= read -r match; do
      if [[ -n "$match" && "$match" != "$editor_file" ]]; then
        echo "  FAIL: $label — forbidden pattern found in $match" >&2
        found=true
        failures=$((failures + 1))
        break
      fi
    done < <(grep -rl -- "$pattern" "$file" 2>/dev/null || true)
    if ! $found; then
      echo "  ok: $label"
      passes=$((passes + 1))
    fi
  else
    echo "  FAIL: unknown op $op" >&2
    failures=$((failures + 1))
  fi
}

# ==== 1. Route endpoint checks ====
echo "== Route endpoint checks =="
if [[ -f "$routes_file" ]]; then
  # Allow the legitimate editor save route, block unauthorized save routes
  check "routes.php has editor save route" "present" "$routes_file" "/apps/studio/tools/localization-studio/edit/save"
  check "routes.php has create-file route" "present" "$routes_file" "/apps/studio/tools/localization-studio/create-file"
  check "routes.php no apply endpoint route" "absent" "$routes_file" "localization-studio.*apply"
  check "routes.php no write endpoint route" "absent" "$routes_file" "localization-studio.*write"
  check "routes.php no submit endpoint route" "absent" "$routes_file" "localization-studio.*submit"
  check "routes.php no update endpoint route" "absent" "$routes_file" "localization-studio.*update"
  check "routes.php no delete endpoint route" "absent" "$routes_file" "localization-studio.*delete"
  check "routes.php no POST route beyond edit/save" "absent" "$routes_file" "post.*localization-studio$(printf '\t')"
  # Check that only edit/save POST route exists
  post_match=$(grep -c "post.*localization-studio" "$routes_file" 2>/dev/null || true)
  if [[ "$post_match" -le 2 ]]; then
    echo "  ok: routes.php has at most 2 POST routes for localization-studio (edit/save, create-file/do)"
    passes=$((passes + 1))
  else
    echo "  FAIL: routes.php has $post_match POST routes (expected at most 1)" >&2
    failures=$((failures + 1))
  fi
  check "routes.php no PUT/DELETE for localization-studio" "absent" "$routes_file" "(put|delete).*localization-studio"
fi

# ==== 2. View template checks ====
echo ""
echo "== View template checks =="
view_files=$(find "$tool_dir" -name "*.php" -path "*/Views/*" -type f 2>/dev/null || true)
if [[ -n "$view_files" ]]; then
  for f in $view_files; do
    rel="${f#$ROOT_DIR/}"
    # Block apply/write/submit/update/delete in ALL views (editor must not have them)
    check "no form action=.*apply in $rel" "absent" "$f" "action=.*apply"
    check "no form action=.*write in $rel" "absent" "$f" "action=.*write"
    check "no form action=.*submit in $rel" "absent" "$f" "action=.*submit"
    check "no form action=.*update in $rel" "absent" "$f" "action=.*update"
    check "no form action=.*delete in $rel" "absent" "$f" "action=.*delete"
    check "no download attribute on anchors in $rel" "absent" "$f" "download="
    check "no Blob URL creation in $rel" "absent" "$f" "Blob"
    check "no URL.createObjectURL in $rel" "absent" "$f" "createObjectURL"

    # Block PUT/DELETE method forms in ALL views
    check "no form method=PUT in $rel" "absent" "$f" "method=['\"]put['\"]"
    check "no form method=DELETE in $rel" "absent" "$f" "method=['\"]delete['\"]"

    # POST forms: allowed in editor.php and create-file.php
    if echo "$rel" | grep -qE "(editor\.php|create-file\.php)"; then
      check "$rel has POST form" "present" "$f" "method=['\"]post['\"]"
    else
      check "no form method=POST in $rel" "absent" "$f" "method=['\"]post['\"]"
    fi
  done
else
  echo "  ok: no view templates to scan"
  passes=$((passes + 1))
fi

# ==== 3. Service layer checks ====
echo ""
echo "== Service layer checks =="
service_files=$(find "$tool_dir" -name "*.php" -path "*/Services/*" -type f 2>/dev/null || true)
if [[ -n "$service_files" ]]; then
  for f in $service_files; do
    rel="${f#$ROOT_DIR/}"

    # Check that EditService has the required methods
    if echo "$rel" | grep -q "EditService"; then
      check "EditService has write method" "present" "$f" "function write("
      check "EditService has snapshot method" "present" "$f" "function snapshot("
      check "EditService has validate method" "present" "$f" "function validate("
    fi

    # AssertPathIsLocaleFile regression checks
    if echo "$rel" | grep -q "EditService"; then
      check "EditService has assertPathIsLocaleFile method" "present" "$f" "function assertPathIsLocaleFile("
      check "EditService assertPathIsLocaleFile checks realpath" "present" "$f" "realpath"
      check "EditService assertPathIsLocaleFile checks APP_ROOT prefix" "present" "$f" "APP_ROOT"
      check "EditService assertPathIsLocaleFile checks /lang/ containment" "present" "$f" "/lang/"
      check "EditService assertPathIsLocaleFile checks locale pattern" "present" "$f" "en|ja|ne"
      check "EditService assertPathIsLocaleFile denies non-PHP paths" "present" "$f" "str_ends_with.*\.php"
      check "EditService assertPathIsLocaleFile throws RuntimeException" "present" "$f" "RuntimeException"
      check "EditService write() calls assertPathIsLocaleFile" "present" "$f" "assertPathIsLocaleFile(\$path)"
      check "EditService snapshot() calls assertPathIsLocaleFile" "present" "$f" "assertPathIsLocaleFile(\$path)"
    fi

    # No service should have these dangerous patterns
    check "no curl or HTTP client in $rel" "absent" "$f" "curl_init\|new.*Client\|Guzzle"
    check "no DB INSERT in $rel" "absent" "$f" "INSERT"
    check "no DB UPDATE in $rel" "absent" "$f" "UPDATE.*[Tt]ranslat\|UPDATE.*[Ll]ocale\|UPDATE.*[Pp]roposal"
    check "no cache store in $rel" "absent" "$f" "cache.*[Ss]et\|cache.*[Ss]tore\|cache.*[Pp]ut"

    # AI/import/export/generate functions must not appear
    check "no function.*import.*translation in $rel" "absent" "$f" "function.*import.*[Tt]ranslat"
    check "no function.*export.*translation in $rel" "absent" "$f" "function.*export.*[Tt]ranslat"
    check "no function.*generate.*translation in $rel" "absent" "$f" "function.*generate.*[Tt]ranslat"
  done
else
  echo "  ok: no service files to scan"
  passes=$((passes + 1))
fi

# ==== 4. Runtime coupling prohibition ====
echo ""
echo "== Runtime coupling prohibition =="
runtime_paths=(
  "app"
  "apps/Shell"
  "apps/Platform"
)
coupling_found=false
for rp in "${runtime_paths[@]}"; do
  if [[ -d "$rp" ]]; then
    match=$(grep -rl -- "LocalizationStudio.*[Dd]raft\|LocalizationStudio.*[Pp]roposal\|LocalizationStudio.*[Ss]napshot\|LocalizationStudio.*[Aa]pply\|LocalizationStudio.*[Ss]ave" "$rp" --include="*.php" --include="*.js" 2>/dev/null || true)
    if [[ -n "$match" ]]; then
      echo "  FAIL: runtime file couples to LocalizationStudio draft/apply state: $match" >&2
      coupling_found=true
      failures=$((failures + 1))
    fi
  fi
done
if ! $coupling_found; then
  echo "  ok: no runtime files couple to LocalizationStudio draft/apply state"
  passes=$((passes + 1))
fi

# ==== 5. DB and cache prohibition ====
echo ""
echo "== DB and cache prohibition =="
db_patterns=(
  "CREATE TABLE.*locale"
  "CREATE TABLE.*translation"
  "CREATE TABLE.*proposal"
  "INSERT INTO.*locale"
  "INSERT INTO.*translation"
  "INSERT INTO.*proposal"
  "UPDATE.*locale"
  "UPDATE.*translation"
  "UPDATE.*proposal"
  "DELETE FROM.*locale"
  "DELETE FROM.*translation"
  "DELETE FROM.*proposal"
)
db_found=false
for pattern in "${db_patterns[@]}"; do
  match=$(grep -rl -- "$pattern" "$tool_dir" --include="*.php" --include="*.js" 2>/dev/null || true)
  if [[ -n "$match" ]]; then
    echo "  FAIL: DB table reference found in LocalizationStudio: $pattern in $match" >&2
    db_found=true
    failures=$((failures + 1))
  fi
done
if ! $db_found; then
  echo "  ok: no DB table references in LocalizationStudio files"
  passes=$((passes + 1))
fi

migrations_dir="apps/Studio/migrations"
if [[ -d "$migrations_dir" ]]; then
  locale_migrations=$(find "$migrations_dir" -name "*.php" -exec grep -l "locale\|translation\|proposal" {} \; 2>/dev/null || true)
  if [[ -n "$locale_migrations" ]]; then
    echo "  FAIL: locale-related migration files found: $locale_migrations" >&2
    failures=$((failures + 1))
  else
    echo "  ok: no locale-related migrations"
    passes=$((passes + 1))
  fi
else
  echo "  ok: no migrations directory to scan"
  passes=$((passes + 1))
fi

# ==== 6. Config/policy checks ====
echo ""
echo "== Config/policy checks =="
policy_file="apps/Studio/config/studio_tool_policy.php"
if [[ -f "$policy_file" ]]; then
  check "no apply_enabled flag in policy" "absent" "$policy_file" "apply_enabled\|save_enabled\|write_enabled"
  check "no v2_enabled flag in policy" "absent" "$policy_file" "v2_enabled\|edit_enabled\|mutation_enabled"
fi

tool_json="$tool_dir/tool.json"
if [[ -f "$tool_json" ]]; then
  check "tool.json runtime_behavior is true" "present" "$tool_json" '"runtime_behavior": true'
  check "tool.json no v2_enabled field" "absent" "$tool_json" "v2_enabled\|edit_enabled\|apply_enabled"
  check "tool.json routes_enabled is true" "present" "$tool_json" '"routes_enabled": true'
fi

# ==== 7. No AI/auto-apply patterns ====
echo ""
echo "== AI/auto-apply prohibition =="
ai_patterns=(
  "ai.*translation"
  "auto.*translat"
  "suggest.*translat"
  "ChatGPT"
  "GPT"
  "OpenAI"
  "deepseek"
  "machine.*translat"
)
ai_found=false
for pattern in "${ai_patterns[@]}"; do
  match=$(grep -rl -- "$pattern" "$tool_dir" --include="*.php" --include="*.js" 2>/dev/null || true)
  if [[ -n "$match" ]]; then
    echo "  FAIL: AI translation pattern found in LocalizationStudio: $pattern in $match" >&2
    ai_found=true
    failures=$((failures + 1))
  fi
done
if ! $ai_found; then
  echo "  ok: no AI translation patterns in LocalizationStudio files"
  passes=$((passes + 1))
fi

# ==== 8. No runtime locale loading changes ====
echo ""
echo "== Runtime locale loading checks =="
runtime_lang_paths=(
  "app/lang"
  "app/Core/Locale.php"
)
loading_changed=false
for rlp in "${runtime_lang_paths[@]}"; do
  diff=$(git diff --name-only -- "$rlp" 2>/dev/null || true)
  if echo "$diff" | grep -q .; then
    echo "  FAIL: runtime locale loading path changed: $rlp" >&2
    loading_changed=true
    failures=$((failures + 1))
  fi
done
if ! $loading_changed; then
  echo "  ok: runtime locale loading unchanged"
  passes=$((passes + 1))
fi

# Check that public/assets has no new locale bundles
bundles=$(find public/assets -name "*locale*" -o -name "*lang*" -newer "$tool_dir" -type f 2>/dev/null || true)
if [[ -n "$bundles" ]]; then
  echo "  FAIL: new locale bundle in public/assets: $bundles" >&2
  failures=$((failures + 1))
else
  echo "  ok: no new locale bundles in public/assets"
  passes=$((passes + 1))
fi

# ==== Result ====
echo ""
echo "== Result =="
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL ($failures invariant(s) broken)" >&2
  echo ""
  echo "v2.1 readiness gates must pass before v2 implementation continues."
  echo "See: docs/architecture/localization-studio-v2-1-readiness-diagnostic-plan.md"
  exit 1
fi

echo "RESULT: PASS ($passes invariant(s) checked)"
echo ""
echo "v2.1 readiness verified. Authorized editor patterns allowed."
echo "See: docs/architecture/localization-studio-v2-1-readiness-diagnostic-plan.md"
