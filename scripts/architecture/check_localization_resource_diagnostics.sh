#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

PHP_BIN="${PHP_BIN:-php}"

echo "[architecture] check_localization_resource_diagnostics"
echo "- read-only locale file content diagnostics"
echo ""

failures=0
warnings=0

record_fail() {
  local label="$1"
  echo "  FAIL: $label" >&2
  failures=$((failures + 1))
}

record_warn() {
  local label="$1"
  echo "  warning: $label"
  warnings=$((warnings + 1))
}

record_pass() {
  local label="$1"
  echo "  ok: $label"
}

# ── Find all locale files ────────────────────────────────────────────

LOCALE_GLOBS=(
  "apps/*/Resources/lang/*.php"
  "apps/*/modules/*/Resources/lang/*.php"
  "apps/Studio/Tools/*/Resources/lang/*.php"
  "plugins/*/Resources/lang/*.php"
)

declare -a LOCALE_FILES=()

for glob in "${LOCALE_GLOBS[@]}"; do
  while IFS= read -r -d '' f; do
    LOCALE_FILES+=("$f")
  done < <(find . -path "./${glob}" -type f -print0 2>/dev/null || true)
done

if [[ ${#LOCALE_FILES[@]} -eq 0 ]]; then
  record_pass "no locale files found — nothing to validate"
  echo ""
  echo "== Result =="
  echo "RESULT: PASS (no files to check)"
  exit 0
fi

record_pass "found ${#LOCALE_FILES[@]} locale file(s) to validate"

# ── Validate each file via PHP inline ────────────────────────────

VALID_LOCALES=("en" "ja" "ne")
OWNER_FILES=()  # associative: owner_dir -> file list (simulated with arrays)

DIAGNOSTIC_SCRIPT=$(cat <<'PHPEOF'
<?php
$root = $argv[1] ?? '.';
$file_path = $argv[2] ?? '';
$relative_path = str_replace($root . '/', '', $file_path);
$results = [];

if (!is_file($file_path)) {
    echo json_encode(['error' => 'file_not_found']);
    exit(1);
}

// Detect locale from filename
$basename = basename($file_path);
$locale = str_replace('.php', '', $basename);

// Validate locale code
$valid_locales = ['en', 'ja', 'ne'];
$locale_issues = [];
if (!in_array($locale, $valid_locales, true)) {
    $locale_issues[] = "unsupported_locale:$locale";
}

// Check file ownership boundary (path must match known patterns)
$allowed_patterns = [
    '#^apps/[^/]+/Resources/lang/[a-z]{2}\.php$#',
    '#^apps/[^/]+/modules/[^/]+/Resources/lang/[a-z]{2}\.php$#',
    '#^apps/Studio/Tools/[^/]+/Resources/lang/[a-z]{2}\.php$#',
    '#^plugins/[^/]+/Resources/lang/[a-z]{2}\.php$#',
];
$boundary_ok = false;
foreach ($allowed_patterns as $pattern) {
    if (preg_match($pattern, $relative_path)) {
        $boundary_ok = true;
        break;
    }
}
if (!$boundary_ok) {
    $results[] = ['severity' => 'error', 'check' => 'boundary', 'message' => "File outside known ownership path: $relative_path"];
}

// Parse the locale file safely
$data = @include $file_path;
if (!is_array($data)) {
    $results[] = ['severity' => 'error', 'check' => 'parse', 'message' => "File does not return a valid array"];
    echo json_encode(['locale' => $locale, 'locale_issues' => $locale_issues, 'results' => $results, 'keys' => []]);
    exit(0);
}

// Flatten keys and detect duplicates
$flat_keys = [];
$duplicates = [];
$convention_issues = [];
$dangerous_values = [];
$empty_values = [];

$flatten = function ($arr, $prefix = '') use (&$flatten, &$flat_keys, &$duplicates, &$convention_issues, &$dangerous_values, &$empty_values) {
    foreach ($arr as $k => $v) {
        $full = $prefix !== '' ? $prefix . '.' . $k : (string)$k;

        // Check key naming convention
        if (!preg_match('/^[a-z0-9._]+$/', $full)) {
            $convention_issues[] = $full;
        }

        if (is_array($v)) {
            $flatten($v, $full);
        } else {
            // Track duplicate keys
            if (isset($flat_keys[$full])) {
                $duplicates[$full] = ($duplicates[$full] ?? 1) + 1;
            } else {
                $flat_keys[$full] = true;
            }

            // Check for dangerous values
            $sv = (string)$v;
            if (preg_match('/\b(exec|eval|system|passthru|shell_exec|popen|assert|superglobal|\$_GET|\$_POST|\$_SERVER|\$_REQUEST|\$_COOKIE|\$_FILES)\b/i', $sv)) {
                $dangerous_values[] = $full;
            }

            // Empty value check
            if (trim($sv) === '') {
                $empty_values[] = $full;
            }
        }
    }
};

$flatten($data);

foreach ($convention_issues as $ck) {
    $results[] = ['severity' => 'warning', 'check' => 'naming', 'message' => "Key naming convention violation: $ck"];
}

foreach ($duplicates as $dk => $dc) {
    $results[] = ['severity' => 'warning', 'check' => 'duplicate', 'message' => "Duplicate key ($dc occurrences): $dk"];
}

foreach ($dangerous_values as $dv) {
    $results[] = ['severity' => 'warning', 'check' => 'dangerous', 'message' => "Potentially dangerous value in key: $dv"];
}

foreach ($empty_values as $ev) {
    $results[] = ['severity' => 'info', 'check' => 'empty_value', 'message' => "Empty value for key: $ev"];
}

if (count($flat_keys) === 0) {
    $results[] = ['severity' => 'info', 'check' => 'empty_file', 'message' => 'Locale file has zero keys (empty or metadata-only)'];
}

echo json_encode([
    'locale' => $locale,
    'locale_issues' => $locale_issues,
    'results' => $results,
    'keys' => array_keys($flat_keys),
]);
PHPEOF
)

TMPDIR="${TMPDIR:-/tmp}"
KEY_STORE="$TMPDIR/ls-resource-diag-keys-$$"
rm -rf "$KEY_STORE" && mkdir -p "$KEY_STORE"
trap 'rm -rf "$KEY_STORE"' EXIT

for file in "${LOCALE_FILES[@]}"; do
  rel="${file#./}"

  # Derive owner key from path
  if echo "$rel" | grep -qE '^apps/[^/]+/modules/[^/]+/'; then
    owner=$(echo "$rel" | sed -E 's|^apps/([^/]+)/modules/([^/]+)/.*|\1/\2|')
  elif echo "$rel" | grep -qE '^apps/Studio/Tools/[^/]+/'; then
    owner=$(echo "$rel" | sed -E 's|^apps/Studio/Tools/([^/]+)/.*|Studio/Tools/\1|')
  elif echo "$rel" | grep -qE '^apps/[^/]+/'; then
    owner=$(echo "$rel" | sed -E 's|^apps/([^/]+)/.*|\1|')
  elif echo "$rel" | grep -qE '^plugins/[^/]+/'; then
    owner=$(echo "$rel" | sed -E 's|^plugins/([^/]+)/.*|Plugin/\1|')
  else
    owner="unknown"
  fi

  # Store file reference for owner
  echo "$rel" >> "$KEY_STORE/owner_files_${owner//\//_}.txt"

  # Run PHP diagnostic
  diag_output=$("$PHP_BIN" -r "$DIAGNOSTIC_SCRIPT" "$ROOT_DIR" "$file" 2>/dev/null || echo '{"error":"diagnostic_failed"}')
  diag_result=$(echo "$diag_output" | "$PHP_BIN" -r '$d=json_decode(stream_get_contents(STDIN),true); if(!$d){echo "parse_error"; exit;} echo json_encode($d);' 2>/dev/null || echo '{"error":"json_parse"}')

  if echo "$diag_result" | "$PHP_BIN" -r '$d=json_decode(stream_get_contents(STDIN),true); echo isset($d["error"])?"error":"ok";' 2>/dev/null | grep -q "error"; then
    record_fail "could not parse $rel"
    continue
  fi

  locale=$(echo "$diag_result" | "$PHP_BIN" -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["locale"]??"unknown";' 2>/dev/null)

  # Locale code issues
  locale_issues=$(echo "$diag_result" | "$PHP_BIN" -r '
    $d=json_decode(stream_get_contents(STDIN),true);
    echo implode("|", $d["locale_issues"]??[]);
  ' 2>/dev/null)
  if [[ -n "$locale_issues" ]]; then
    while IFS='|' read -ra issues; do
      for iss in "${issues[@]}"; do
        record_fail "$rel — $iss"
      done
    done <<< "$locale_issues"
  fi

  # Per-file results
  results_json=$(echo "$diag_result" | "$PHP_BIN" -r '
    $d=json_decode(stream_get_contents(STDIN),true);
    echo json_encode($d["results"]??[]);
  ' 2>/dev/null)

  echo "$results_json" | "$PHP_BIN" -r '
    $items = json_decode(stream_get_contents(STDIN), true) ?: [];
    foreach ($items as $item) {
      $sev = $item["severity"] ?? "info";
      $check = $item["check"] ?? "unknown";
      $msg = $item["message"] ?? "";
      echo $sev . "|" . $check . "|" . $msg . "\n";
    }
  ' 2>/dev/null | while IFS='|' read -r sev check msg; do
    if [[ "$sev" == "error" ]]; then
      record_fail "$rel — [$check] $msg"
    elif [[ "$sev" == "warning" ]]; then
      record_warn "$rel — [$check] $msg"
    fi
    # Info-level is silently recorded
  done

  # Collect keys per owner per locale for cross-locale comparison
  keys_json=$(echo "$diag_result" | "$PHP_BIN" -r '
    $d=json_decode(stream_get_contents(STDIN),true);
    echo json_encode($d["keys"]??[]);
  ' 2>/dev/null)
  key_file="$KEY_STORE/keys_${owner//\//_}_${locale}.txt"
  echo "$keys_json" > "$key_file"
done

# ── Cross-locale key parity check ──────────────────────────────

echo ""
echo "== Cross-locale key parity =="

cross_fail=0
for owner_file in "$KEY_STORE"/keys_*.txt; do
  [[ -f "$owner_file" ]] || continue
  basename="${owner_file##*/}"
  # Extract owner (remove keys_ prefix and _locale.txt suffix)
  rest="${basename#keys_}"
  owner_key="${rest%_*.txt}"
  locale="${rest##*_}"; locale="${locale%.txt}"

  # Build unique owner list
  echo "$owner_key" >> "$KEY_STORE/owner_list.tmp"
done

sort -u "$KEY_STORE/owner_list.tmp" 2>/dev/null | while IFS= read -r owner_key; do
  [[ -z "$owner_key" ]] && continue

  en_file="$KEY_STORE/keys_${owner_key}_en.txt"
  ja_file="$KEY_STORE/keys_${owner_key}_ja.txt"
  ne_file="$KEY_STORE/keys_${owner_key}_ne.txt"

  has_en=0; has_ja=0; has_ne=0
  [[ -f "$en_file" ]] && has_en=1
  [[ -f "$ja_file" ]] && has_ja=1
  [[ -f "$ne_file" ]] && has_ne=1

  locale_count=$((has_en + has_ja + has_ne))
  [[ "$locale_count" -lt 2 ]] && continue

  # Use en as reference if available, otherwise first available
  if [[ "$has_en" -eq 1 ]]; then
    ref_file="$en_file"
    ref_name="en"
  elif [[ "$has_ja" -eq 1 ]]; then
    ref_file="$ja_file"
    ref_name="ja"
  else
    ref_file="$ne_file"
    ref_name="ne"
  fi

  for target_file in "$ja_file" "$ne_file"; do
    [[ "$target_file" == "$ref_file" ]] && continue
    [[ ! -f "$target_file" ]] && continue

    target_name="${target_file##*_}"
    target_name="${target_name%.txt}"

    # Compare: parse both as JSON arrays, find missing keys
    missing_count=$("$PHP_BIN" -r '
      $ref = json_decode(file_get_contents($argv[1]), true) ?: [];
      $tgt = json_decode(file_get_contents($argv[2]), true) ?: [];
      $refSet = array_flip($ref);
      $tgtSet = array_flip($tgt);
      $missing = array_diff_key($refSet, $tgtSet);
      echo count($missing);
    ' "$ref_file" "$target_file" 2>/dev/null || echo 0)

    if [[ "$missing_count" -gt 0 ]]; then
      record_warn "$owner_key: $missing_count key(s) present in $ref_name but missing in $target_name"
    fi
  done
done

# Cleanup temp list
rm -f "$KEY_STORE/owner_list.tmp"

record_pass "cross-locale key parity checked — issues reported as warnings"

# ── Result ─────────────────────────────────────────────────────

echo ""
echo "== Result =="
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL ($failures error(s), $warnings warning(s))" >&2
  exit 1
fi

echo "RESULT: PASS ($warnings warning(s))"
