#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

RG_BIN="${RG_BIN:-$(command -v rg || true)}"
GREP_BIN="${GREP_BIN:-$(command -v grep || true)}"

if [[ -n "$RG_BIN" ]]; then
  SEARCH_TOOL="$RG_BIN"
  SEARCH_ARGS=(-n -S)
else
  if [[ -z "$GREP_BIN" ]]; then
    echo "missing required binary: rg or grep" >&2
    exit 2
  fi
  SEARCH_TOOL="$GREP_BIN"
  SEARCH_ARGS=(-RIn)
fi

failures=0
warnings=0

style_root="apps/Shell/DesignSystem"
socket_catalog_root="$style_root/Resources/socket-catalog"

required_placeholders=(
  "$style_root/README.md"
  "$style_root/Contracts"
  "$style_root/Services"
  "$style_root/Resources"
  "$style_root/Resources/socket-catalog"
  "$style_root/Views"
  "$style_root/Diagnostics"
)

required_runtime_placeholders=(
  "$style_root/Contracts/StyleSocketContract.php"
  "$style_root/Contracts/StyleConsumerContract.php"
  "$style_root/Contracts/StylePrimitiveContract.php"
  "$style_root/Services/StyleSocketCatalog.php"
  "$style_root/Services/ResolvedStyleConsumer.php"
  "$style_root/Services/StyleAttributeBuilder.php"
  "$style_root/Services/StyleVariableBuilder.php"
  "$style_root/Views/style-attributes.placeholder.php"
  "$style_root/Views/style-variables.placeholder.php"
)

expected_catalog_files=(
  "root-mode.json"
  "core-tokens.json"
  "shell-chrome.json"
  "layout.json"
  "primitives.json"
  "navigation.json"
  "data-display.json"
  "tables-grids.json"
  "forms-editors.json"
  "feedback.json"
  "overlays.json"
  "visualization.json"
  "diagrams-graphs.json"
  "workflow-operations.json"
  "states.json"
  "motion-transform.json"
  "media-assets.json"
  "responsive.json"
  "accessibility.json"
  "print-export.json"
)

ok() {
  echo "  ok: $1"
}

warn() {
  echo "  warn: $1"
  warnings=$((warnings + 1))
}

fail() {
  echo "  fail: $1" >&2
  failures=$((failures + 1))
}

check_required_path() {
  local path="$1"
  local label="$2"

  if [[ -e "$path" ]]; then
    ok "$label ($path)"
  else
    fail "missing $label ($path)"
  fi
}

check_no_matches() {
  local label="$1"
  local pattern="$2"
  shift 2
  local targets=("$@")

  if [[ "${#targets[@]}" -eq 0 ]]; then
    warn "$label (no files to scan)"
    return
  fi

  local tmp_matches
  tmp_matches="$(mktemp /tmp/shell-style-catalog-boundary-XXXXXX)"

  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$pattern" "${targets[@]}" > "$tmp_matches" 2>/dev/null; then
    fail "$label"
    cat "$tmp_matches" >&2
  else
    ok "$label"
  fi

  rm -f "$tmp_matches"
}

echo "[architecture] check_shell_style_catalog_boundaries"
echo "- read-only Shell Style catalog boundary diagnostics"

echo ""
echo "== Required Shell Style home =="
if [[ -d "$style_root" ]]; then
  ok "Shell Style root exists ($style_root)"
else
  fail "missing Shell Style root ($style_root)"
fi

echo ""
echo "== Required placeholder folders/files =="
for path in "${required_placeholders[@]}"; do
  check_required_path "$path" "placeholder path"
done

echo ""
echo "== Required future runtime placeholders =="
for path in "${required_runtime_placeholders[@]}"; do
  check_required_path "$path" "future runtime placeholder"
done

echo ""
echo "== Socket catalog file presence, JSON validity, and runtime status =="
for file in "${expected_catalog_files[@]}"; do
  path="$socket_catalog_root/$file"
  if [[ ! -f "$path" ]]; then
    fail "missing socket catalog file ($path)"
    continue
  fi

  if php -r '$path=$argv[1]; $data=json_decode((string)file_get_contents($path), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } if (($data["runtime_status"] ?? null) !== "catalog_only_not_consumed") { fwrite(STDERR, "runtime_status must be catalog_only_not_consumed".PHP_EOL); exit(2); }' "$path"; then
    ok "$path is valid JSON with runtime_status=catalog_only_not_consumed"
  else
    fail "$path must be valid JSON with runtime_status=catalog_only_not_consumed"
  fi
done

actual_catalog_count="$(find "$socket_catalog_root" -maxdepth 1 -type f -name '*.json' | wc -l | tr -d ' ')"
if [[ "$actual_catalog_count" == "${#expected_catalog_files[@]}" ]]; then
  ok "socket catalog file count is $actual_catalog_count"
else
  fail "expected ${#expected_catalog_files[@]} socket catalog files, found $actual_catalog_count"
fi

echo ""
echo "== Socket catalog metadata integrity =="
known_value_types="choice|color"
integrity_report="$(php -r '
  $catalogDir = $argv[1];
  $knownTypes = array_fill_keys(explode("|", $argv[2]), true);
  $requiredCatalogFields = ["runtime_status", "sockets"];
  $requiredSocketFields = ["socket", "label", "description", "category", "scope", "owner", "value_type", "simple_controls", "advanced_token", "allowed_values", "default_value", "customizer_mode", "runtime_consumption", "notes"];
  $files = glob($catalogDir . "/*.json");
  if ($files === false) {
    $files = [];
  }
  sort($files);

  $report = [
    "catalogs" => 0,
    "sockets" => 0,
    "invalid_json" => [],
    "missing_catalog_fields" => [],
    "missing_socket_fields" => [],
    "duplicate_socket_ids" => [],
    "non_catalog_only_catalogs" => [],
    "non_catalog_only_sockets" => [],
    "missing_value_type" => [],
    "invalid_value_type" => [],
  ];
  $seen = [];

  foreach ($files as $path) {
    $file = basename($path);
    $raw = file_get_contents($path);
    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) {
      $report["invalid_json"][] = $file;
      continue;
    }

    $report["catalogs"]++;
    foreach ($requiredCatalogFields as $field) {
      if (!array_key_exists($field, $decoded) || $decoded[$field] === "" || $decoded[$field] === []) {
        $report["missing_catalog_fields"][] = $file . ":" . $field;
      }
    }

    if (($decoded["runtime_status"] ?? null) !== "catalog_only_not_consumed") {
      $report["non_catalog_only_catalogs"][] = $file;
    }

    $sockets = isset($decoded["sockets"]) && is_array($decoded["sockets"]) ? $decoded["sockets"] : [];
    foreach ($sockets as $index => $socket) {
      if (!is_array($socket)) {
        $report["missing_socket_fields"][] = $file . ":#" . ($index + 1) . ":object";
        continue;
      }

      $id = trim((string)($socket["socket"] ?? ""));
      $displayId = $id !== "" ? $id : "#" . ($index + 1);
      $report["sockets"]++;

      foreach ($requiredSocketFields as $field) {
        if (!array_key_exists($field, $socket) || $socket[$field] === "" || $socket[$field] === []) {
          $report["missing_socket_fields"][] = $file . ":" . $displayId . ":" . $field;
        }
      }

      if ($id !== "") {
        if (isset($seen[$id])) {
          $report["duplicate_socket_ids"][] = $id;
        } else {
          $seen[$id] = $file;
        }
      }

      if (($socket["runtime_consumption"] ?? null) !== "future_shell_socket") {
        $report["non_catalog_only_sockets"][] = $file . ":" . $displayId;
      }

      $valueType = trim((string)($socket["value_type"] ?? ""));
      if ($valueType === "") {
        $report["missing_value_type"][] = $file . ":" . $displayId;
      } elseif (!isset($knownTypes[$valueType])) {
        $report["invalid_value_type"][] = $file . ":" . $displayId . ":" . $valueType;
      }
    }
  }

  echo json_encode($report);
' "$socket_catalog_root" "$known_value_types")"

catalogs_checked="$(echo "$integrity_report" | php -r '$d=json_decode(stream_get_contents(STDIN), true); echo (int)($d["catalogs"] ?? 0);')"
sockets_checked="$(echo "$integrity_report" | php -r '$d=json_decode(stream_get_contents(STDIN), true); echo (int)($d["sockets"] ?? 0);')"
ok "$catalogs_checked socket catalogs checked for metadata integrity"
ok "$sockets_checked sockets checked for metadata integrity"

for field in invalid_json missing_catalog_fields missing_socket_fields duplicate_socket_ids non_catalog_only_catalogs non_catalog_only_sockets missing_value_type invalid_value_type; do
  count="$(echo "$integrity_report" | php -r '$field=$argv[1]; $d=json_decode(stream_get_contents(STDIN), true); echo isset($d[$field]) && is_array($d[$field]) ? count($d[$field]) : 0;' "$field")"
  if [[ "$count" -eq 0 ]]; then
    ok "socket catalog integrity: $field = 0"
  else
    details="$(echo "$integrity_report" | php -r '$field=$argv[1]; $d=json_decode(stream_get_contents(STDIN), true); echo implode(", ", array_slice($d[$field] ?? [], 0, 20));' "$field")"
    fail "socket catalog integrity: $field = $count ($details)"
  fi
done

shell_style_runtime_files=()
while IFS= read -r file; do
  shell_style_runtime_files+=("$file")
done < <(find "$style_root" -type f \( -name '*.php' -o -name '*.json' -o -name '*.js' -o -name '*.css' \) -print)

shell_runtime_consumption_files=()
while IFS= read -r file; do
  shell_runtime_consumption_files+=("$file")
done < <(find apps/Shell -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' \) -not -path "$socket_catalog_root/*" -print)

shell_view_layout_runtime_files=()
while IFS= read -r file; do
  shell_view_layout_runtime_files+=("$file")
done < <(find apps/Shell/Views public/views/layouts apps/Shell/Composers apps/Shell/Services -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' \) -not -path '*/AppearanceReaderInventoryService.php' -print 2>/dev/null)

core_runtime_files=()
while IFS= read -r file; do
  core_runtime_files+=("$file")
done < <(find app -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' -o -name '*.json' \) -print)

echo ""
echo "== Shell Style no draft/fixture path coupling =="
check_no_matches \
  "Shell Style runtime files must not reference Studio draft/fixture homes" \
  'apps/Studio/Tools/CustomizationStudio/Resources/preview-fixtures|apps/Studio/Tools/CustomizationStudio/Resources/drafts|CustomizationStudio[[:space:]]*draft|Studio[[:space:]]*draft' \
  "${shell_style_runtime_files[@]}"

echo ""
echo "== Shell view/layout no Customization Studio consumption =="
check_no_matches \
  "Shell view/layout runtime files must not consume Customization Studio markers yet" \
  'CustomizationStudio|customization-studio|preview-fixtures|catalog_only_not_consumed' \
  "${shell_view_layout_runtime_files[@]}"

echo ""
echo "== Shell runtime no socket catalog consumption =="
check_no_matches \
  "Shell runtime must not read or instantiate socket catalog metadata yet" \
  'file_get_contents[^\n]*socket-catalog|glob[^\n]*socket-catalog|Resources/socket-catalog|StyleSocketCatalog::|new[[:space:]]+StyleSocketCatalog|catalog_only_not_consumed' \
  "${shell_runtime_consumption_files[@]}"

check_no_matches \
  "Shell runtime must not call StyleRegistry getValue for socket values yet" \
  'StyleRegistry[^;\n]*getValue[[:space:]]*\(|getValue[[:space:]]*\([^;\n]*(radius\.scale|socket|style)' \
  "${shell_runtime_consumption_files[@]}"

echo ""
echo "== Shell Style source-truth boundary =="
check_no_matches \
  "Shell Style runtime files must not reference public/assets as authoring/source/registry truth" \
  'public/assets' \
  "${shell_style_runtime_files[@]}"

echo ""
echo "== Core lock boundary for Shell Style customization coupling =="
check_no_matches \
  "Core runtime files must not contain Shell Style or Customization Studio coupling" \
  'CustomizationStudio|customization-studio|apps/Shell/DesignSystem|StyleSocketCatalog|StyleAttributeBuilder|StyleVariableBuilder|socket-catalog|catalog_only_not_consumed' \
  "${core_runtime_files[@]}"

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (Shell Style catalog boundary violations found)" >&2
  exit 1
fi

if [[ "$warnings" -gt 0 ]]; then
  echo "RESULT: PASS WITH WARNINGS ($warnings warning(s))"
  exit 0
fi

echo "RESULT: PASS"
