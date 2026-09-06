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

customization_root="apps/Studio/Tools/CustomizationStudio"
socket_catalog_root="apps/Shell/DesignSystem/Resources/socket-catalog"

expected_scan_anchors=(
  "apps/Studio/Tools/CustomizationStudio"
  "apps/Shell"
  "apps/Shell/DesignSystem/Resources/socket-catalog"
  "public/assets"
  "app"
  "apps/Studio/routes.php"
  "customization-studio-route-write-pattern"
  "customization-studio-runtime-write-pattern"
  "studio-local-draft-write-allowlist"
  "shell-studio-draft-read-pattern"
  "core-customization-studio-pattern"
)

active_scan_anchors=(
  "apps/Studio/Tools/CustomizationStudio"
  "apps/Shell"
  "apps/Shell/DesignSystem/Resources/socket-catalog"
  "public/assets"
  "app"
  "apps/Studio/routes.php"
  "customization-studio-route-write-pattern"
  "customization-studio-runtime-write-pattern"
  "studio-local-draft-write-allowlist"
  "shell-studio-draft-read-pattern"
  "core-customization-studio-pattern"
)

expected_catalog_files=(
  "accessibility.json"
  "core-tokens.json"
  "data-display.json"
  "diagrams-graphs.json"
  "feedback.json"
  "forms-editors.json"
  "layout.json"
  "media-assets.json"
  "motion-transform.json"
  "navigation.json"
  "overlays.json"
  "primitives.json"
  "print-export.json"
  "responsive.json"
  "root-mode.json"
  "shell-chrome.json"
  "states.json"
  "tables-grids.json"
  "visualization.json"
  "workflow-operations.json"
)

echo "[architecture] check_customization_studio_boundaries"
echo "- read-only Customization Studio and Shell style boundary diagnostics"

check_required_path() {
  local path="$1"
  local label="$2"

  if [[ -e "$path" ]]; then
    echo "  ok: $label exists ($path)"
  else
    echo "  fail: missing $label ($path)" >&2
    failures=$((failures + 1))
  fi
}

check_no_matches() {
  local label="$1"
  local pattern="$2"
  shift 2
  local targets=("$@")
  local tmp_matches

  if [[ "${#targets[@]}" -eq 0 ]]; then
    echo "  ok: $label (no files to scan)"
    return
  fi

  tmp_matches="$(mktemp /tmp/customization-studio-boundary-XXXXXX)"
  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$pattern" "${targets[@]}" > "$tmp_matches" 2>/dev/null; then
    echo "  fail: $label" >&2
    cat "$tmp_matches" >&2
    failures=$((failures + 1))
  else
    echo "  ok: $label"
  fi
  rm -f "$tmp_matches"
}

check_no_unapproved_matches() {
  local label="$1"
  local pattern="$2"
  local allow_pattern="$3"
  shift 3
  local targets=("$@")
  local tmp_matches
  local tmp_unapproved

  if [[ "${#targets[@]}" -eq 0 ]]; then
    echo "  ok: $label (no files to scan)"
    return
  fi

  tmp_matches="$(mktemp /tmp/customization-studio-boundary-XXXXXX)"
  tmp_unapproved="$(mktemp /tmp/customization-studio-boundary-unapproved-XXXXXX)"
  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$pattern" "${targets[@]}" > "$tmp_matches" 2>/dev/null; then
    grep -Ev "$allow_pattern" "$tmp_matches" > "$tmp_unapproved" || true
    if [[ -s "$tmp_unapproved" ]]; then
      echo "  fail: $label" >&2
      cat "$tmp_unapproved" >&2
      failures=$((failures + 1))
    else
      echo "  ok: $label"
    fi
  else
    echo "  ok: $label"
  fi
  rm -f "$tmp_matches" "$tmp_unapproved"
}

echo ""
echo "== Scan contract =="
if [[ "${#active_scan_anchors[@]}" -ne "${#expected_scan_anchors[@]}" ]]; then
  echo "  fail: scan-anchor count changed; expected ${#expected_scan_anchors[@]}, found ${#active_scan_anchors[@]}" >&2
  failures=$((failures + 1))
else
  for index in "${!expected_scan_anchors[@]}"; do
    if [[ "${active_scan_anchors[$index]}" == "${expected_scan_anchors[$index]}" ]]; then
      echo "  ok: scan-anchor[$index] ${active_scan_anchors[$index]}"
    else
      echo "  fail: scan-anchor[$index] changed; expected ${expected_scan_anchors[$index]}, found ${active_scan_anchors[$index]}" >&2
      failures=$((failures + 1))
    fi
  done
fi

echo ""
echo "== Required owner homes =="
check_required_path "$customization_root" "Customization Studio owner directory"
check_required_path "$socket_catalog_root" "Shell socket catalog directory"

shell_runtime_files=()
while IFS= read -r file; do
  shell_runtime_files+=("$file")
done < <(find apps/Shell -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' \) -not -path '*/Tests/*' -not -path '*/tests/*' -print)

customization_runtime_files=()
while IFS= read -r file; do
  customization_runtime_files+=("$file")
done < <(find "$customization_root" \
  \( -path "$customization_root/Diagnose" -o -path "$customization_root/DesignSystem" -o -path "$customization_root/Advanced" \) -prune \
  -o -type f \( -name '*.php' -o -name '*.js' \) -not -path '*/Tests/*' -not -path '*/tests/*' -print)

core_runtime_files=()
while IFS= read -r file; do
  core_runtime_files+=("$file")
done < <(find app -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' -o -name '*.json' \) -print)

echo ""
echo "== Customization Studio source home =="
unexpected_paths="$(mktemp /tmp/customization-studio-paths-XXXXXX)"
find . \
  \( -path "./.git" -o -path "./storage/manual-rehearsal-test-*" -o -path "./engineering/*" -o -path "./docs/*" -o -name "*.md" \) -prune -o \
  -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' -o -name '*.json' \) -path "*/CustomizationStudio*" -print \
  | while IFS= read -r path; do
      case "$path" in
        "./$customization_root"|"./$customization_root"/*)
          ;;
        *)
          printf '%s\n' "$path"
          ;;
      esac
    done > "$unexpected_paths"

if [[ -s "$unexpected_paths" ]]; then
  echo "  fail: CustomizationStudio implementation paths must stay under $customization_root" >&2
  cat "$unexpected_paths" >&2
  failures=$((failures + 1))
else
  echo "  ok: CustomizationStudio implementation paths are confined to $customization_root"
fi
rm -f "$unexpected_paths"

echo ""
echo "== Shell draft-read boundary =="
check_no_unapproved_matches \
  "Shell PHP/JS/CSS must not read or reference Customization Studio drafts, fixtures, or implementation paths" \
  'CustomizationStudio|Studio/Tools/CustomizationStudio|customization-studio|preview-fixtures|studio_draft|studio-draft|Studio drafts' \
  'apps/Shell/Services/AppearanceReaderInventoryService\.php:[0-9]+:[[:space:]]*'\''file_path'\'' => '\''apps/Studio/Tools/CustomizationStudio/(Effects/SpecialEffects|Advanced/CssLiveEditor|DesignSystem/DesignTokenEditor|Diagnose/ThemeDoctor)' \
  "${shell_runtime_files[@]}"

echo ""
echo "== public/assets delivery-output boundary =="
if [[ -d "public/assets" ]]; then
  check_no_matches \
    "public/assets must not contain Customization Studio source truth or generated output in this slice" \
    'CustomizationStudio|Customization Studio|customization-studio|preview-fixtures|style socket|socket catalog' \
    public/assets
else
  echo "  ok: public/assets directory not present"
fi

echo ""
echo "== Socket catalog runtime status =="
for file in "${expected_catalog_files[@]}"; do
  path="$socket_catalog_root/$file"
  if [[ ! -f "$path" ]]; then
    echo "  fail: missing socket catalog file $path" >&2
    failures=$((failures + 1))
    continue
  fi

  if ! php -r '$path=$argv[1]; $data=json_decode(file_get_contents($path), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } if (($data["runtime_status"] ?? null) !== "catalog_only_not_consumed") { fwrite(STDERR, "runtime_status must be catalog_only_not_consumed".PHP_EOL); exit(2); }' "$path"; then
    echo "  fail: $path must be valid JSON with runtime_status=catalog_only_not_consumed" >&2
    failures=$((failures + 1))
  else
    echo "  ok: $path is catalog_only_not_consumed"
  fi
done

actual_catalog_count="$(find "$socket_catalog_root" -maxdepth 1 -type f -name '*.json' | wc -l | tr -d ' ')"
if [[ "$actual_catalog_count" == "${#expected_catalog_files[@]}" ]]; then
  echo "  ok: socket catalog file count is $actual_catalog_count"
else
  echo "  fail: expected ${#expected_catalog_files[@]} socket catalog files, found $actual_catalog_count" >&2
  failures=$((failures + 1))
fi

echo ""
echo "== Socket catalog content integrity =="
known_value_types="color|spacing|font-size|font-weight|radius|opacity|duration|easing|shadow|z-index|border-width|line-height|letter-spacing|transform|transition|filter|backdrop-filter|background|display|position|overflow|cursor|pointer-events|user-select|object-fit|breakpoint|grid-column|grid-row|gap|padding|margin|width|height|min-width|min-height|max-width|max-height|inset|top|right|bottom|left|choice"

report=$(php -r '
  $catalogDir = $argv[1];
  $knownTypes = explode("|", $argv[2]);

  $files = glob($catalogDir . "/*.json");
  if ($files === false || $files === []) { echo json_encode(["error" => "no files"]); exit(0); }
  sort($files);

  $totalCatalogs = 0;
  $totalSockets = 0;
  $allIds = [];
  $duplicates = [];
  $missingLabel = 0;
  $missingCategory = 0;
  $missingScope = 0;
  $missingValueType = 0;
  $invalidValueType = 0;

  foreach ($files as $path) {
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === "") continue;
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data["sockets"]) || !is_array($data["sockets"])) continue;
    $totalCatalogs++;
    foreach ($data["sockets"] as $s) {
      if (!is_array($s)) continue;
      $id = trim((string)($s["socket"] ?? ""));
      if ($id === "") continue;
      $totalSockets++;

      if (isset($allIds[$id])) { $duplicates[] = $id; }
      else { $allIds[$id] = true; }

      if (trim((string)($s["label"] ?? "")) === "") $missingLabel++;
      if (trim((string)($s["category"] ?? "")) === "") $missingCategory++;
      if (trim((string)($s["scope"] ?? "")) === "") $missingScope++;
      $vt = trim((string)($s["value_type"] ?? ""));
      if ($vt === "") { $missingValueType++; }
      elseif (!in_array($vt, $knownTypes)) { $invalidValueType++; }
    }
  }

  echo json_encode([
    "total_catalogs" => $totalCatalogs,
    "total_sockets" => $totalSockets,
    "duplicate_ids" => $duplicates,
    "missing_label" => $missingLabel,
    "missing_category" => $missingCategory,
    "missing_scope" => $missingScope,
    "missing_value_type" => $missingValueType,
    "invalid_value_type" => $invalidValueType,
  ]);
' "$ROOT_DIR/$socket_catalog_root" "$known_value_types" 2>/dev/null)

if [[ -z "$report" ]]; then
  echo "  fail: content integrity check failed to execute" >&2
  failures=$((failures + 1))
else
  total_catalogs=$(echo "$report" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["total_catalogs"] ?? "0";')
  total_sockets=$(echo "$report" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["total_sockets"] ?? "0";')
  dup_ids=$(echo "$report" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo json_encode($d["duplicate_ids"] ?? []);')
  missing_label=$(echo "$report" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["missing_label"] ?? "0";')
  missing_category=$(echo "$report" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["missing_category"] ?? "0";')
  missing_scope=$(echo "$report" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["missing_scope"] ?? "0";')
  missing_value_type=$(echo "$report" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["missing_value_type"] ?? "0";')
  invalid_value_type=$(echo "$report" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["invalid_value_type"] ?? "0";')

  echo "  ok: $total_catalogs catalogs checked"
  echo "  ok: $total_sockets total sockets checked"

  dup_count=$(echo "$dup_ids" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo is_array($d) ? count($d) : 0;')
  if [[ "$dup_count" -eq 0 ]]; then
    echo "  ok: socket IDs are unique across all catalogs"
  else
    echo "  fail: found $dup_count duplicate socket IDs: $(echo "$dup_ids" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo implode(", ", $d);')" >&2
    failures=$((failures + 1))
  fi

  if [[ "$missing_label" -eq 0 ]]; then echo "  ok: all sockets have label field"; else echo "  fail: $missing_label sockets missing label field" >&2; failures=$((failures + 1)); fi
  if [[ "$missing_category" -eq 0 ]]; then echo "  ok: all sockets have category field"; else echo "  fail: $missing_category sockets missing category field" >&2; failures=$((failures + 1)); fi
  if [[ "$missing_scope" -eq 0 ]]; then echo "  ok: all sockets have scope field"; else echo "  fail: $missing_scope sockets missing scope field" >&2; failures=$((failures + 1)); fi
  if [[ "$missing_value_type" -eq 0 ]]; then echo "  ok: all sockets have value_type field"; else echo "  fail: $missing_value_type sockets missing value_type field" >&2; failures=$((failures + 1)); fi
  if [[ "$invalid_value_type" -eq 0 ]]; then echo "  ok: all value_type values are known types"; else echo "  fail: $invalid_value_type sockets have unrecognized value_type" >&2; failures=$((failures + 1)); fi
fi

echo ""
echo "== Special Effects read-only foundation =="
check_required_path "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" "Special Effects read-only registry service"
check_required_path "$customization_root/Effects/SpecialEffects/Views/index.php" "Special Effects workspace view"
check_required_path "$customization_root/Effects/SpecialEffects/Tests/probe_special_effects.php" "Special Effects probe"

if grep -Fq "\$router->get('/apps/studio/tools/customization-studio/effects'" apps/Studio/routes.php \
  && ! grep -Fq "\$router->post('/apps/studio/tools/customization-studio/effects" apps/Studio/routes.php \
  && grep -Fq "customizationStudioSpecialEffects" apps/Studio/Controllers/StudioController.php; then
  echo "  ok: Special Effects is exposed as a GET-only Customization Studio workspace"
else
  echo "  fail: Special Effects must be a GET-only Customization Studio workspace" >&2
  failures=$((failures + 1))
fi

if grep -Fq "'mutation_endpoint' => ''" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'write_enabled' => false" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'persistent_settings_enabled' => false" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'snapshot_enabled' => false" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'rollback_enabled' => false" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php"; then
  echo "  ok: Special Effects declares no mutation, persistence, snapshot, or rollback surface"
else
  echo "  fail: Special Effects safety contract must remain read-only in V1" >&2
  failures=$((failures + 1))
fi

if grep -Fq "'proposal_class' => 'future_tool_handoff'" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "MIGRATION_STATE_FUTURE_HANDOFF" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'governance_domain' => 'special_effect'" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'scanner_reclassified_here' => false" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php"; then
  echo "  ok: Special Effects consumes Style Compliance future handoff contract without reclassification"
else
  echo "  fail: Special Effects must consume Style Compliance future handoff records directly" >&2
  failures=$((failures + 1))
fi

if grep -Fq "'handoff_reconciliation' =>" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'handoff_id' => 'se-handoff-'" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'finding_id' =>" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'file_path' =>" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'detection_confidence' =>" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'reason_code' =>" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'profile_relevance' =>" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php"; then
  echo "  ok: Special Effects exposes normalized V1.1 handoff adapter fields"
else
  echo "  fail: Special Effects V1.1 handoff adapter fields are incomplete" >&2
  failures=$((failures + 1))
fi

if grep -Fq "'available' => \$available" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'displayed' => count(\$rows)" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'excluded' => count(\$excluded)" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'unmapped' => \$unmapped" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'blocked_by_missing_profile_mapping' => \$missingProfile" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'source_context_used' => 'single_normalized_style_compliance_context'" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php"; then
  echo "  ok: Special Effects exposes Style Compliance handoff reconciliation counts"
else
  echo "  fail: Special Effects must expose available/displayed/excluded/unmapped reconciliation counts" >&2
  failures=$((failures + 1))
fi

if grep -Fq "'scan_context' =>" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'handoff_parity' =>" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "style_compliance_handoff_contract_v1_2" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "scanContextDifferenceReason" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "mappingRuleRegistry" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php"; then
  echo "  ok: Special Effects exposes V1.2 scan context, parity, and mapping registry"
else
  echo "  fail: Special Effects V1.2 context/parity/mapping contract is incomplete" >&2
  failures=$((failures + 1))
fi

if grep -Fq "'runtime_readiness' =>" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'combined_mode' =>" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'derived_palette' =>" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'derived_effect_profile' =>" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'resolver_source' => 'derived_from_combined_theme_preference_read_only'" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php"; then
  echo "  ok: Special Effects exposes active runtime readiness evidence"
else
  echo "  fail: Special Effects runtime readiness evidence is incomplete" >&2
  failures=$((failures + 1))
fi

if grep -Fq "\$router->get('/apps/studio/tools/customization-studio/effects/preview'" apps/Studio/routes.php \
  && ! grep -Fq "\$router->post('/apps/studio/tools/customization-studio/effects/preview" apps/Studio/routes.php \
  && grep -Fq "customizationStudioSpecialEffectsPreview" apps/Studio/Controllers/StudioController.php \
  && grep -Fq "renderPreviewDocument" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "previewStateFromQuery" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "data-se-preview=\"isolated\"" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "sandbox=\"allow-same-origin\"" "$customization_root/Effects/SpecialEffects/Views/index.php"; then
  echo "  ok: Special Effects V1.3 preview is GET-only, isolated, and service-rendered"
else
  echo "  fail: Special Effects V1.3 preview route/isolation contract is incomplete" >&2
  failures=$((failures + 1))
fi

if grep -Fq "buildFutureControlsModel" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "futureSettingsContract" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "resolveFutureEffectState" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "futureScopeAuthorityContract" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "futureControlPlans" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'persistence_enabled' => false" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'contract_phase' => 'read_only_design_only'" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php"; then
  echo "  ok: Special Effects V1.4 defines read-only future persistent control contract"
else
  echo "  fail: Special Effects V1.4 future control contract is incomplete or not explicitly read-only" >&2
  failures=$((failures + 1))
fi

if grep -Fq "'system_defaults'" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'organization_effect_policy'" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'user_effect_preference'" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'runtime_constraints'" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'resolved_effect_state'" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "accessibility_constraints" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "runtime_readiness_and_capability" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "safe_fallback" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php"; then
  echo "  ok: Special Effects V1.4 preserves resolution hierarchy and settings contract fields"
else
  echo "  fail: Special Effects V1.4 hierarchy/settings contract fields are incomplete" >&2
  failures=$((failures + 1))
fi

if grep -Fq "'mutation_url' => ''" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'form_action' => ''" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'api_command' => ''" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'write_instruction' => ''" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'preview_contract_status' => 'non_persistent_url_state_only_not_a_draft_not_a_plan'" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php"; then
  echo "  ok: Special Effects V1.4 future plans expose no mutation endpoint or write instruction"
else
  echo "  fail: Special Effects V1.4 future control plans must remain non-mutating" >&2
  failures=$((failures + 1))
fi

if grep -Fq "future_effect_controls_title" "$customization_root/Effects/SpecialEffects/Views/index.php" \
  && grep -Fq "future_control_matrix" "$customization_root/Effects/SpecialEffects/Views/index.php" \
  && grep -Fq "full_resolution_trace" "$customization_root/Effects/SpecialEffects/Views/index.php" \
  && grep -Fq "future_control_plan_records" "$customization_root/Effects/SpecialEffects/Views/index.php" \
  && grep -Fq "required_audit_evidence" "$customization_root/Effects/SpecialEffects/Views/index.php" \
  && grep -Fq "capability_accessibility_constraints" "$customization_root/Effects/SpecialEffects/Views/index.php"; then
  echo "  ok: Special Effects V1.4 renders folded read-only future controls evidence"
else
  echo "  fail: Special Effects V1.4 read-only future controls UI is incomplete" >&2
  failures=$((failures + 1))
fi

if grep -Fq "buildAppearanceIntegrationAudit" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "appearanceStateMap" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "futurePersistenceStrategyEvaluation" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "futureResolverIntegrationRecords" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "themeDoctorDependencyContract" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php"; then
  echo "  ok: Special Effects V1.5 defines read-only appearance integration audit"
else
  echo "  fail: Special Effects V1.5 appearance integration audit is incomplete" >&2
  failures=$((failures + 1))
fi

if grep -Fq "'recommended_future_persistence_strategy' => 'C. Structured appearance state with backward-compatible combined-mode reads'" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'preview_persistence' => 'none'" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'no_persistence_enabled' => true" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'repair_or_compile_triggered_in_this_slice' => false" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php"; then
  echo "  ok: Special Effects V1.5 recommends structured state without enabling persistence or repair"
else
  echo "  fail: Special Effects V1.5 recommendation/persistence boundaries are incomplete" >&2
  failures=$((failures + 1))
fi

if grep -Fq "'mutation_url' => ''" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'form_action' => ''" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'write_command' => ''" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "'persistence_call' => ''" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "no_existing_source_of_truth_for_effects_enabled" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php" \
  && grep -Fq "no_existing_source_of_truth_for_motion_mode" "$customization_root/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php"; then
  echo "  ok: Special Effects V1.5 integration records block premature persistence readiness"
else
  echo "  fail: Special Effects V1.5 readiness records must be non-mutating and conservative" >&2
  failures=$((failures + 1))
fi

if grep -Fq "persistent_controls_readiness" "$customization_root/Effects/SpecialEffects/Views/index.php" \
  && grep -Fq "readiness_by_scope" "$customization_root/Effects/SpecialEffects/Views/index.php" \
  && grep -Fq "full_appearance_state_map" "$customization_root/Effects/SpecialEffects/Views/index.php" \
  && grep -Fq "compatibility_strategy_evaluation" "$customization_root/Effects/SpecialEffects/Views/index.php" \
  && grep -Fq "conflict_prevention_model" "$customization_root/Effects/SpecialEffects/Views/index.php" \
  && grep -Fq "theme_doctor_dependency_evidence" "$customization_root/Effects/SpecialEffects/Views/index.php"; then
  echo "  ok: Special Effects V1.5 renders folded persistent controls readiness evidence"
else
  echo "  fail: Special Effects V1.5 readiness UI is incomplete" >&2
  failures=$((failures + 1))
fi

echo ""
echo "== Disabled/read-only behavior boundary =="
check_no_unapproved_matches \
  "Customization Studio PHP/JS must not introduce unapproved file, database, network, save/apply, or registry-write behavior outside the documented Visual Customizer lifecycle" \
  'method=["'\'']post|<form|XMLHttpRequest|fetch[[:space:]]*\(|\$\.ajax|sendBeacon|file_put_contents|fwrite|fopen[[:space:]]*\([^,]+,[[:space:]]*["'\''][wa]|unlink[[:space:]]*\(|rename[[:space:]]*\(|mkdir[[:space:]]*\(|rmdir[[:space:]]*\(|copy[[:space:]]*\(|DB::|->[[:space:]]*(query|exec|prepare)[[:space:]]*\(|INSERT[[:space:]]+INTO|UPDATE[[:space:]]+[^[:space:]]|DELETE[[:space:]]+FROM|ALTER[[:space:]]+|CREATE[[:space:]]+TABLE|saveDraft[[:space:]]*\(|applyStyle[[:space:]]*\(|applyTheme[[:space:]]*\(|writeRegistry[[:space:]]*\(|registryWrite[[:space:]]*\(|activateStyle[[:space:]]*\(' \
  'apps/Studio/Tools/CustomizationStudio/assets/visual-customizer\.js:[0-9]+:[[:space:]]*fetch\((draftUpdateEndpoint|recheckReadinessEndpoint|createApprovalRequestEndpoint),|apps/Studio/Tools/CustomizationStudio/Views/visual-customizer-request-detail\.php:[0-9]+:[[:space:]]*fetch\((endpoint\[action\]|takeSnapshotEndpoint|applyEndpoint),|apps/Studio/Tools/CustomizationStudio/Services/VisualCustomizerDraftStorageService\.php:[0-9]+:.*(@mkdir\(\$dir, 0775, true\)|@file_put_contents\(\$path, \$encoded \. PHP_EOL\))|apps/Studio/Tools/CustomizationStudio/Services/VisualCustomizerApprovalRequestService\.php:[0-9]+:|apps/Studio/Tools/CustomizationStudio/Services/VisualCustomizerSnapshotService\.php:[0-9]+:|apps/Studio/Tools/CustomizationStudio/Effects/SpecialEffects/Views/index\.php:[0-9]+:[[:space:]]*<form method="get" action="/apps/studio/tools/customization-studio/effects" class="se-preview-controls">|apps/Studio/Tools/CustomizationStudio/Effects/SpecialEffects/Tests/probe_special_effects\.php:[0-9]+:assertProbe\(!preg_match' \
  "${customization_runtime_files[@]}"

check_no_unapproved_matches \
  "Studio routes must not expose unapproved POST/write endpoints for Customization Studio" \
  'post[[:space:]]*\([^)]*customization-studio|route[[:space:]]*\([^)]*POST[^)]*customization-studio|customization-studio[^#\n]*(save|apply|activate|registry|write)' \
  "(apps/Studio/routes\.php:)?[0-9]+:.*(visual-customizer/(draft/update|draft/recheck-readiness|draft/create-approval-request|request/approve|request/reject|request/cancel|request/take-snapshot|request/apply)|design-system/tokens/(verify|save)|diagnose/style-compliance/(scan|repair-readiness|repair-execute|fix-one))" \
  apps/Studio/routes.php

echo ""
echo "== Visual Customizer lifecycle allowance =="
style_chain_checkpoint="docs/architecture/style-customization-chain-checkpoint.md"
apply_review="apps/Studio/Tools/CustomizationStudio/Contracts/visual-customizer-first-apply-implementation-review.md"
if grep -Fq "The full Visual Customizer lifecycle is **proven and DB-backed**" "$style_chain_checkpoint" \
  && grep -Fq "Request artifact creation" "$style_chain_checkpoint" \
  && grep -Fq "Snapshot capture" "$style_chain_checkpoint" \
  && grep -Fq "Apply to Platform StyleRegistry" "$style_chain_checkpoint" \
  && grep -Fq "Shell consumption boundary plan" "$style_chain_checkpoint" \
  && grep -Fq "First Apply Scope" "$apply_review" \
  && grep -Fq "radius.scale only" "$apply_review"; then
  echo "  ok: Visual Customizer lifecycle allowance is documented and radius.scale scoped"
else
  echo "  fail: Visual Customizer lifecycle allowance requires DB-backed lifecycle and radius.scale apply documentation" >&2
  failures=$((failures + 1))
fi

echo ""
echo "== Studio-local draft storage allowance =="
if grep -Fq "/storage/studio/customization/visual-customizer/drafts/user-" "$customization_root/Services/VisualCustomizerDraftStorageService.php" \
  && grep -Fq "EDITABLE_SOCKET_ID = 'radius.scale'" "$customization_root/Services/VisualCustomizerDraftStorageService.php" \
  && grep -Fq "'platform_registry_io_enabled' => false" "$customization_root/Services/VisualCustomizerDraftStorageService.php" \
  && grep -Fq "'shell_consumption_enabled' => false" "$customization_root/Services/VisualCustomizerDraftStorageService.php" \
  && grep -Fq "'runtime_activation_enabled' => false" "$customization_root/Services/VisualCustomizerDraftStorageService.php"; then
  echo "  ok: Visual Customizer draft writes are confined to one Studio-local radius.scale draft artifact"
else
  echo "  fail: Visual Customizer draft storage allowance must remain confined to storage/studio/customization and radius.scale with runtime coupling disabled" >&2
  failures=$((failures + 1))
fi

echo ""
echo "== Core lock boundary =="
check_no_matches \
  "Core must not contain Customization Studio logic or route coupling" \
  'CustomizationStudio|Customization Studio|customization-studio|Studio/Tools/CustomizationStudio|preview-fixtures|style socket catalog' \
  "${core_runtime_files[@]}"

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (Customization Studio boundary violations found)" >&2
  exit 1
fi

echo "RESULT: PASS"
