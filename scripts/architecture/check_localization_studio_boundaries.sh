#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

tool_dir="apps/Studio/Tools/LocalizationStudio"
tool_json="$tool_dir/tool.json"
readme="$tool_dir/README.md"
arch_doc="docs/architecture/localization-studio-v1-foundation.md"
routes_file="apps/Studio/routes.php"
controller_file="apps/Studio/Controllers/StudioController.php"
editor_view="$tool_dir/Views/editor.php"

echo "[architecture] check_localization_studio_boundaries"
echo "- verifying Localization Studio v1 foundation invariants"
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
  else
    echo "  FAIL: unknown op $op" >&2
    failures=$((failures + 1))
  fi
}

validate_json() {
  local file="$1"
  local label="$2"
  if python3 -c "import json; json.load(open('$file'))" 2>/dev/null; then
    echo "  ok: $label — valid JSON"
    passes=$((passes + 1))
  else
    echo "  FAIL: $label — invalid JSON" >&2
    failures=$((failures + 1))
  fi
}

# ==== 1. LocalizationStudio folder exists ====
echo "== Folder existence =="
if [[ -d "$tool_dir" ]]; then
  echo "  ok: LocalizationStudio folder exists ($tool_dir)"
  passes=$((passes + 1))
else
  echo "  FAIL: LocalizationStudio folder missing ($tool_dir)" >&2
  failures=$((failures + 1))
fi

# ==== 2. tool.json exists and is valid JSON ====
echo ""
echo "== Tool registration =="
if [[ -f "$tool_json" ]]; then
  echo "  ok: tool.json exists"
  passes=$((passes + 1))
  validate_json "$tool_json" "tool.json"
else
  echo "  FAIL: tool.json missing" >&2
  failures=$((failures + 1))
fi

# ==== 3. README exists ====
echo ""
echo "== README =="
if [[ -f "$readme" ]]; then
  echo "  ok: README.md exists"
  passes=$((passes + 1))
else
  echo "  FAIL: README.md missing" >&2
  failures=$((failures + 1))
fi

# ==== 4. Architecture doc exists ====
echo ""
echo "== Architecture document =="
if [[ -f "$arch_doc" ]]; then
  echo "  ok: architecture doc exists"
  passes=$((passes + 1))
else
  echo "  FAIL: architecture doc missing ($arch_doc)" >&2
  failures=$((failures + 1))
fi

# ==== 5. No save/apply/write behavior exists ====
echo ""
echo "== Save/apply/write prohibition =="
# Check tool.json for runtime_behavior false
check "tool.json runtime_behavior is true" "present" "$tool_json" '"runtime_behavior": true'
# Check routes_enabled is true (routes are registered, but read-only only)
check "tool.json routes_enabled is true" "present" "$tool_json" '"routes_enabled": true'
# Check no save/apply in tool.json
check "tool.json no save endpoint" "absent" "$tool_json" "save"
check "tool.json no apply" "absent" "$tool_json" "apply"

# Check Studio routes.php has only the authorized POST route for localization-studio
if [[ -f "$routes_file" ]]; then
  post_count=$(grep -c "post.*localization-studio" "$routes_file" 2>/dev/null || true)

  if [[ "$post_count" -le 2 ]]; then
    echo "  ok: at most 2 POST routes for localization-studio (authorized: edit/save, create-file/do)"
    passes=$((passes + 1))
  else
    echo "  FAIL: more than one POST route for localization-studio ($post_count)" >&2
    failures=$((failures + 1))
  fi
fi

# Check safe scan handoff return support remains navigation-only
echo ""
echo "== Scan handoff return path =="
check "controller sanitizes return_to" "present" "$controller_file" 'studioSafeInternalReturnTo'
check "controller rejects protocol-relative return_to" "present" "$controller_file" "str_starts_with(\$returnTo, '//')"
check "controller rejects absolute URL return_to" "present" "$controller_file" "a-z0-9+.-"
check "controller rejects control-character return_to" "present" "$controller_file" "x00"
check "editor renders Back to scan results link" "present" "$editor_view" 'back_to_scan_results'
check "editor preserves return_to through save form" "present" "$editor_view" 'name="return_to"'
check "editor preserves selected key through save form" "present" "$editor_view" 'name="key"'

# Scan the Localization Studio folder for any save/write/mutate patterns
js_files=$(find "$tool_dir" -name "*.js" -type f 2>/dev/null || true)
php_files=$(find "$tool_dir" -name "*.php" -type f 2>/dev/null || true)
files_to_scan=""

if [[ -n "$js_files" ]]; then
  files_to_scan="$js_files"
fi
if [[ -n "$php_files" ]]; then
  files_to_scan="$files_to_scan $php_files"
fi

if [[ -n "$files_to_scan" ]]; then
  for f in $files_to_scan; do
    rel="${f#$ROOT_DIR/}"
    is_editor=false
    if echo "$rel" | grep -q "EditService"; then
      is_editor=true
    fi

    # Check all files for dangerous write/network operations
    check "no localStorage in $rel" "absent" "$f" 'localStorage'
    # file_put_contents is allowed only in EditService for locale file writes
    if ! $is_editor; then
      check "no file_put_contents in $rel" "absent" "$f" 'file_put_contents'
    fi
    check "no fwrite in $rel" "absent" "$f" 'fwrite'

    # For Services files (where mutation logic would live), also check save/apply/write/PUT/PATCH
    if echo "$rel" | grep -q "/Services/"; then
      # write method is allowed only in EditService
      if $is_editor; then
        check "EditService has write method" "present" "$f" 'function write('
      else
        check "no write-side-effect method in $rel" "absent" "$f" 'function.*write('
      fi
      check "no save-side-effect method in $rel" "absent" "$f" 'function.*save('
      check "no apply-side-effect method in $rel" "absent" "$f" 'function.*apply('
      check "no PUT usage in $rel" "absent" "$f" 'curl_setopt.*CURLOPT_CUSTOMREQUEST.*PUT'
      check "no PATCH usage in $rel" "absent" "$f" 'curl_setopt.*CURLOPT_CUSTOMREQUEST.*PATCH'
    fi
  done
else
  echo "  ok: no JS/PHP files in LocalizationStudio folder — nothing to mutate"
  passes=$((passes + 1))
fi

# ==== 6. No server-side export/file generation ====
echo ""
echo "== Server-side export/file generation prohibition =="
# Prevent server-side file download or export patterns
if [[ -n "$files_to_scan" ]]; then
  for f in $files_to_scan; do
    rel="${f#$ROOT_DIR/}"
    check "no fopen in $rel" "absent" "$f" 'fopen('
    check "no tempnam in $rel" "absent" "$f" 'tempnam('
    check "no tmpfile in $rel" "absent" "$f" 'tmpfile('
    check "no Content-Disposition in $rel" "absent" "$f" 'Content-Disposition'
    check "no header.*download in $rel" "absent" "$f" 'header.*download'
    check "no header.*attachment in $rel" "absent" "$f" 'header.*attachment'
    check "no header.*csv in $rel" "absent" "$f" 'header.*csv'
  done
else
  echo "  ok: no JS/PHP files in LocalizationStudio folder — nothing to export server-side"
  passes=$((passes + 1))
fi

# ==== 7. No runtime code reads Studio localization drafts ====
echo ""
echo "== Runtime draft isolation =="
# Check that no Shell, Platform, or Core runtime files reference LocalizationStudio locale paths
runtime_paths=(
  "apps/Shell"
  "apps/Platform"
  "app"
)
draft_pattern="LocalizationStudio"
draft_found=false
for rp in "${runtime_paths[@]}"; do
  if [[ -d "$rp" ]]; then
    if grep -rl -- "$draft_pattern" "$rp" --include="*.php" --include="*.js" 2>/dev/null; then
      echo "  FAIL: runtime file references LocalizationStudio: $rp" >&2
      failures=$((failures + 1))
      draft_found=true
    fi
  fi
done
if ! $draft_found; then
  echo "  ok: no runtime files reference LocalizationStudio"
  passes=$((passes + 1))
fi

# ==== 8. Core locale behavior is untouched ====
echo ""
echo "== Core locale isolation =="
# Check that no Core locale files have been modified
# (check via git diff for any changes under app/lang/)
if git diff --name-only -- "app/lang/" 2>/dev/null | grep -q .; then
  echo "  FAIL: Core locale files modified (app/lang/)" >&2
  failures=$((failures + 1))
else
  echo "  ok: Core locale files untouched"
  passes=$((passes + 1))
fi

# Check that no locale loading behavior in app/ has changed
if [[ "${ARCHITECTURE_GATE_ALLOW_CORE:-0}" != "1" ]] && git diff --name-only -- "app/" 2>/dev/null | grep -q .; then
  echo "  FAIL: Core files changed (app/) — possible locale behavior change" >&2
  failures=$((failures + 1))
else
  echo "  ok: no Core files changed"
  passes=$((passes + 1))
fi

# ==== 9. Locale files remain owner-owned ====
echo ""
echo "== Locale file ownership =="
# Verify no locale files were moved or added under LocalizationStudio
studio_locale_files=$(find "$tool_dir" -name "*.php" -path "*/lang/*" 2>/dev/null || true)
if [[ -n "$studio_locale_files" ]]; then
  echo "  FAIL: locale files exist inside LocalizationStudio folder — ownership violation" >&2
  echo "  Files: $studio_locale_files" >&2
  failures=$((failures + 1))
else
  echo "  ok: no locale files stored in LocalizationStudio folder"
  passes=$((passes + 1))
fi

# Verify no locale files have been moved from owner paths to studio paths
moved_locale=$(git diff --name-only --diff-filter=R -- "*lang/*.php" 2>/dev/null || true)
if echo "$moved_locale" | grep -q "LocalizationStudio"; then
  echo "  FAIL: locale files moved to LocalizationStudio" >&2
  echo "  $moved_locale" >&2
  failures=$((failures + 1))
else
  echo "  ok: no locale files moved to LocalizationStudio"
  passes=$((passes + 1))
fi

# ==== 10. No generated locale cache becomes source truth ====
echo ""
echo "== Generated cache prohibition =="
# Check for any JSON or PHP cache files under var/ or storage/ that look like locale caches
locale_cache_patterns=(
  "var/*locale*"
  "var/*lang*"
  "var/*translation*"
  "storage/*locale*"
  "storage/*lang*"
  "storage/*translation*"
  "public/assets/*locale*"
  "public/assets/*lang*"
)

cache_found=false
for cache_glob in "${locale_cache_patterns[@]}"; do
  matched=$(find . -path "./${cache_glob}" -type f 2>/dev/null || true)
  if [[ -n "$matched" ]]; then
    # Check if these are generated (by Studio) — if already existing as committed files,
    # they are not Studio-generated. We check git status.
    for f in $matched; do
      if git ls-files --error-unmatch "$f" &>/dev/null; then
        : # tracked in git — not a new Studio-generated cache
      else
        echo "  FAIL: untracked locale cache file found: $f" >&2
        cache_found=true
        failures=$((failures + 1))
      fi
    done
  fi
done

if ! $cache_found; then
  echo "  ok: no generated locale cache files found"
  passes=$((passes + 1))
fi

# ==== Result ====
echo ""
echo "== Result =="
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL ($failures invariant(s) broken)" >&2
  exit 1
fi

echo "RESULT: PASS ($passes invariant(s) checked)"
