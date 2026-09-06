#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

GREP_BIN="${GREP_BIN:-$(command -v grep || true)}"
AWK_BIN="${AWK_BIN:-$(command -v awk || true)}"

if [[ -z "$GREP_BIN" || -z "$AWK_BIN" ]]; then
  echo "missing required binaries: grep and awk" >&2
  exit 2
fi

failures=0
contract_doc="docs/architecture/surface-contribution-contract.md"
expected_scan_anchors=(
  "apps/Shell/Views/operator"
  "apps/Shell/Views/display"
  "apps/Shell/Composers"
  "apps/Shell/Services"
  "apps/Manufacturing/Views/operator"
  "apps/Manufacturing/Views/display"
  "apps/Manufacturing/modules/*/Views/operator"
  "apps/Manufacturing/modules/*/Views/display"
  "apps/SBAIO/Views/operator"
  "apps/SBAIO/Views/display"
  "apps/SBAIO/modules/*/Views/operator"
  "apps/SBAIO/modules/*/Views/display"
  "apps/Platform/Views/operator"
  "apps/Platform/Views/display"
  "apps/Platform/modules/*/Views/operator"
  "apps/Platform/modules/*/Views/display"
  "apps/Shell/Views"
  "apps/Shell/Composers/DisplaySurfaceComposer.php"
  "apps/Shell/Services/DisplayLayerService.php"
  "apps/*/manifest.json"
  "apps/*/navigation.php"
  "apps/*/modules/*/plugin.json"
  "apps/*/modules/*/navigation.php"
  "wrapper_pattern"
  "normalization_pattern"
  "display_pattern"
  "css_pattern"
  "visibility_pattern"
  "manifest_metadata"
  "navigation_metadata"
  "hook_metadata"
  "report_metadata"
  "style_metadata"
)
active_scan_anchors=(
  "apps/Shell/Views/operator"
  "apps/Shell/Views/display"
  "apps/Shell/Composers"
  "apps/Shell/Services"
  "apps/Manufacturing/Views/operator"
  "apps/Manufacturing/Views/display"
  "apps/Manufacturing/modules/*/Views/operator"
  "apps/Manufacturing/modules/*/Views/display"
  "apps/SBAIO/Views/operator"
  "apps/SBAIO/Views/display"
  "apps/SBAIO/modules/*/Views/operator"
  "apps/SBAIO/modules/*/Views/display"
  "apps/Platform/Views/operator"
  "apps/Platform/Views/display"
  "apps/Platform/modules/*/Views/operator"
  "apps/Platform/modules/*/Views/display"
  "apps/Shell/Views"
  "apps/Shell/Composers/DisplaySurfaceComposer.php"
  "apps/Shell/Services/DisplayLayerService.php"
  "apps/*/manifest.json"
  "apps/*/navigation.php"
  "apps/*/modules/*/plugin.json"
  "apps/*/modules/*/navigation.php"
  "wrapper_pattern"
  "normalization_pattern"
  "display_pattern"
  "css_pattern"
  "visibility_pattern"
  "manifest_metadata"
  "navigation_metadata"
  "hook_metadata"
  "report_metadata"
  "style_metadata"
)

echo "[architecture] check_surface_contribution_contracts"

tmp_raw="$(mktemp /tmp/surface-contract-raw-XXXXXX)"
tmp_filtered="$(mktemp /tmp/surface-contract-filtered-XXXXXX)"
tmp_runtime_additions="$(mktemp /tmp/surface-contract-runtime-additions-XXXXXX)"
trap 'rm -f "$tmp_raw" "$tmp_filtered" "$tmp_runtime_additions"' EXIT
LAST_COUNT="0"

check_file() {
  local path="$1"
  local label="$2"

  if [[ -f "$path" ]]; then
    echo "  ok: $label ($path)"
  else
    echo "  fail: missing $label ($path)" >&2
    failures=$((failures + 1))
  fi
}

check_text() {
  local path="$1"
  local needle="$2"
  local label="$3"

  if [[ ! -f "$path" ]]; then
    echo "  fail: cannot inspect $label; missing file $path" >&2
    failures=$((failures + 1))
    return
  fi

  if "$GREP_BIN" -Fq -- "$needle" "$path"; then
    echo "  ok: $label"
  else
    echo "  fail: $label" >&2
    failures=$((failures + 1))
  fi
}

append_existing_targets() {
  local out_name="$1"
  shift
  local candidate
  for candidate in "$@"; do
    for path in $candidate; do
      if [[ -e "$path" ]]; then
        eval "$out_name+=(\"$path\")"
      fi
    done
  done
}

scan_and_filter() {
  local pattern="$1"
  local allowlist="$2"
  local section_label="$3"
  local count
  shift 3

  : > "$tmp_raw"
  : > "$tmp_filtered"

  if "$GREP_BIN" -RInE -- "$pattern" "$@" 2>/dev/null > "$tmp_raw"; then
    if [[ -n "$allowlist" ]]; then
      "$GREP_BIN" -n -vE -- "$allowlist" "$tmp_raw" > "$tmp_filtered" || true
    else
      cat "$tmp_raw" > "$tmp_filtered"
    fi
  fi

  count="$("$GREP_BIN" -c . "$tmp_filtered" 2>/dev/null || true)"
  if [[ "$count" != "0" ]]; then
    echo "  findings: $section_label"
    cat "$tmp_filtered"
  fi
  LAST_COUNT="$count"
}

build_runtime_additions_index() {
  {
    git diff --unified=0 --diff-filter=ACMRT HEAD -- app apps plugins public 2>/dev/null \
      | "$AWK_BIN" '
        /^diff --git / {
          file = $4
          sub(/^b\//, "", file)
          next
        }
        /^\+\+\+ / { next }
        /^\+/ && $0 !~ /^\+\+\+/ {
          if (file ~ /^public\/assets\/apps\//) {
            next
          }
          print file ":" substr($0, 2)
        }
      '

    while IFS= read -r file; do
      case "$file" in
        public/assets/apps/*)
          # Published app CSS assets are runtime delivery copies. The owner source
          # remains apps/<Owner>/styles or apps/<Owner>/modules/<Module>/styles.css.
          # Do not treat delivery copies as source-level contribution changes.
          ;;
        app/*|apps/*|plugins/*|public/*)
          "$AWK_BIN" -v file="$file" '{ print file ":" FNR ":" $0 }' "$file"
          ;;
      esac
    done < <(git ls-files --others --exclude-standard -- app apps plugins public 2>/dev/null)
  } > "$tmp_runtime_additions"
}

scan_runtime_additions() {
  local pattern="$1"
  local allowlist="$2"
  local section_label="$3"
  local count

  : > "$tmp_filtered"

  if "$GREP_BIN" -nEi -- "$pattern" "$tmp_runtime_additions" > "$tmp_raw" 2>/dev/null; then
    if [[ -n "$allowlist" ]]; then
      "$GREP_BIN" -n -vE -- "$allowlist" "$tmp_raw" > "$tmp_filtered" || true
    else
      cat "$tmp_raw" > "$tmp_filtered"
    fi
  fi

  count="$("$GREP_BIN" -c . "$tmp_filtered" 2>/dev/null || true)"
  if [[ "$count" != "0" ]]; then
    echo "  findings: $section_label"
    cat "$tmp_filtered"
  fi
  LAST_COUNT="$count"
}

echo "- verifying surface contribution scan contract"
if [[ "${#active_scan_anchors[@]}" -ne "${#expected_scan_anchors[@]}" ]]; then
  echo "  fail: surface contribution scan anchor count changed; expected ${#expected_scan_anchors[@]}, found ${#active_scan_anchors[@]}" >&2
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

echo "- verifying surface contribution contract baseline coverage"
check_file "$contract_doc" "surface contribution contract baseline"
check_text "$contract_doc" "navigation and sidebar contributions" "contract covers navigation/sidebar contributions"
check_text "$contract_doc" "operator widgets and cards" "contract covers operator widgets/cards"
check_text "$contract_doc" "admin cards and blocks" "contract covers admin cards/blocks"
check_text "$contract_doc" "display panels" "contract covers display panels"
check_text "$contract_doc" "reports" "contract covers reports"
check_text "$contract_doc" "actions, buttons, and links" "contract covers actions/buttons/links"
check_text "$contract_doc" "CSS and assets" "contract covers CSS/assets"
check_text "$contract_doc" "permissions" "contract covers permissions"
check_text "$contract_doc" "workspace and landing contributions" "contract covers workspace/landing contributions"
check_text "$contract_doc" "integration and capability contributions" "contract covers integration/capability contributions"
check_text "$contract_doc" "Apps contribute; Shell composes" "contract protects apps-contribute Shell-composes law"
check_text "$contract_doc" "Runtime consumes resolved or compiled contracts" "contract protects resolved runtime truth law"
check_text "$contract_doc" "No hidden duplicate source of truth" "contract protects no-hidden-truth law"
check_text "$contract_doc" "URL and action normalization" "contract names URL/action normalization boundary"
check_text "$contract_doc" "Display contributions must be readonly" "contract names display readonly boundary"
check_text "$contract_doc" "CSS and asset contributions must remain owner-scoped" "contract names CSS ownership boundary"
check_text "$contract_doc" "Contract Shape Readiness Diagnostics" "contract documents metadata readiness diagnostics"
check_text "$contract_doc" "Owner and target metadata must be explicit" "contract documents explicit owner/target metadata"
check_text "$contract_doc" "scripts/architecture/check_surface_contribution_contracts.sh" "contract references this enforcement gate"

echo "- verifying manifest and navigation contribution metadata shape"
if ! php <<'PHP'
<?php
declare(strict_types=1);

$failures = [];

$requireKeys = static function (array $item, array $keys, string $where) use (&$failures): void {
    foreach ($keys as $key) {
        if (!array_key_exists($key, $item) || $item[$key] === '' || $item[$key] === []) {
            $failures[] = "{$where}: missing {$key}";
        }
    }
};

$requireArray = static function (array $item, string $key, string $where) use (&$failures): void {
    if (!array_key_exists($key, $item) || !is_array($item[$key])) {
        $failures[] = "{$where}: {$key} must be an array";
    }
};

$appManifestFiles = glob('apps/*/manifest.json') ?: [];
sort($appManifestFiles);
if (!$appManifestFiles) {
    $failures[] = 'apps/*/manifest.json: no app manifests found';
}

foreach ($appManifestFiles as $file) {
    $data = json_decode((string)file_get_contents($file), true);
    if (!is_array($data)) {
        $failures[] = "{$file}: invalid json";
        continue;
    }

    $appKey = (string)($data['app_key'] ?? $data['id'] ?? '');
    if ($appKey === '') {
        $failures[] = "{$file}: missing app_key/id owner metadata";
    }

    foreach (($data['hooks'] ?? []) as $index => $hook) {
        if (!is_array($hook)) {
            $failures[] = "{$file}: hooks[{$index}] must be an object";
            continue;
        }

        $type = (string)($hook['type'] ?? '');
        if ($type === 'boot') {
            $requireKeys($hook, ['key', 'handler_file'], "{$file}: hooks[{$index}]");
            continue;
        }

        $requireKeys($hook, ['type', 'key', 'provider', 'provider_file', 'order'], "{$file}: hooks[{$index}]");
        if (str_contains($type, 'surface')) {
            $requireKeys($hook, ['surface', 'region'], "{$file}: hooks[{$index}]");
        }

        if ($appKey !== 'shell' && str_contains((string)($hook['provider'] ?? ''), 'Apps\\Shell\\')) {
            $failures[] = "{$file}: hooks[{$index}] provider implies Shell owns {$appKey} contribution";
        }
    }

    foreach (($data['menus'] ?? []) as $index => $menu) {
        if (!is_array($menu)) {
            $failures[] = "{$file}: menus[{$index}] must be an object";
            continue;
        }

        $requireKeys($menu, ['key', 'url', 'order'], "{$file}: menus[{$index}]");
        if (!array_key_exists('perm', $menu) && !array_key_exists('visible_if', $menu) && !array_key_exists('always_visible', $menu) && ($menu['parent'] ?? '') !== 'apps.root' && ($menu['nav_visible'] ?? null) !== false) {
            $failures[] = "{$file}: menus[{$index}] missing visibility policy marker";
        }
    }

    foreach (($data['widgets'] ?? []) as $index => $widget) {
        if (!is_array($widget)) {
            $failures[] = "{$file}: widgets[{$index}] must be an object";
            continue;
        }

        $requireKeys($widget, ['key', 'url', 'order'], "{$file}: widgets[{$index}]");
        if (!array_key_exists('domain', $widget) && !array_key_exists('zone', $widget) && !array_key_exists('module_key', $widget) && !array_key_exists('source_plugin', $widget)) {
            $failures[] = "{$file}: widgets[{$index}] missing owner domain/module metadata";
        }
    }

    foreach (($data['admin_parent_surfaces'] ?? []) as $index => $surface) {
        if (!is_array($surface)) {
            $failures[] = "{$file}: admin_parent_surfaces[{$index}] must be an object";
            continue;
        }

        $requireKeys($surface, ['url', 'label', 'description', 'priority', 'group'], "{$file}: admin_parent_surfaces[{$index}]");
    }

    foreach (($data['styles'] ?? []) as $index => $style) {
        if (!is_array($style)) {
            $failures[] = "{$file}: styles[{$index}] must be an object";
            continue;
        }

        $requireKeys($style, ['key', 'path', 'scope', 'order'], "{$file}: styles[{$index}]");
        $requireArray($style, 'surfaces', "{$file}: styles[{$index}]");

        $path = (string)($style['path'] ?? '');
        if ($path !== '' && (str_starts_with($path, '/') || str_starts_with($path, 'public/assets/'))) {
            $failures[] = "{$file}: styles[{$index}] path must be owner-relative source, not delivery output";
        }

        if (($style['scope'] ?? '') === 'module' && !array_key_exists('module', $style)) {
            $failures[] = "{$file}: styles[{$index}] module-scoped style missing module metadata";
        }
    }
}

$moduleManifestFiles = glob('apps/*/modules/*/plugin.json') ?: [];
sort($moduleManifestFiles);
foreach ($moduleManifestFiles as $file) {
    $data = json_decode((string)file_get_contents($file), true);
    if (!is_array($data)) {
        $failures[] = "{$file}: invalid json";
        continue;
    }

    $requireKeys($data, ['package_type', 'owner_app', 'module_key', 'declared_capabilities', 'lifecycle_contract'], $file);
    if (($data['package_type'] ?? '') !== 'module') {
        $failures[] = "{$file}: package_type must be module";
    }
    if (!is_array($data['declared_capabilities'] ?? null)) {
        $failures[] = "{$file}: declared_capabilities must be an array";
    }
    if (!is_array($data['lifecycle_contract'] ?? null)) {
        $failures[] = "{$file}: lifecycle_contract must be an object";
    }

    foreach (($data['reports'] ?? []) as $index => $report) {
        if (!is_array($report)) {
            $failures[] = "{$file}: reports[{$index}] must be an object";
            continue;
        }

        $requireKeys($report, ['report_key', 'title', 'view', 'owner', 'scope', 'permission', 'lifecycle'], "{$file}: reports[{$index}]");
        if (($report['owner'] ?? '') !== 'module') {
            $failures[] = "{$file}: reports[{$index}] owner must identify module ownership";
        }
    }
}

$navigationFiles = array_merge(glob('apps/*/navigation.php') ?: [], glob('apps/*/modules/*/navigation.php') ?: []);
sort($navigationFiles);
if (!$navigationFiles) {
    $failures[] = 'navigation catalogs: no app/module navigation.php files found';
}

foreach ($navigationFiles as $file) {
    $data = require $file;
    if (!is_array($data)) {
        $failures[] = "{$file}: navigation catalog must return an array";
        continue;
    }

    $requireKeys($data, ['contract', 'owner'], $file);
    $requireArray($data, 'items', $file);
    if (($data['contract'] ?? '') !== 'navigation.v1') {
        $failures[] = "{$file}: contract must be navigation.v1";
    }

    foreach (($data['items'] ?? []) as $index => $item) {
        if (!is_array($item)) {
            $failures[] = "{$file}: items[{$index}] must be an object";
            continue;
        }

        $requireKeys($item, ['source_key', 'group', 'module', 'owner', 'key', 'url', 'visible_if', 'order'], "{$file}: items[{$index}]");
        if (!str_starts_with((string)($item['url'] ?? ''), '/')) {
            $failures[] = "{$file}: items[{$index}] url must be an explicit absolute route";
        }
        if (($item['owner'] ?? '') === 'shell' && !str_starts_with((string)($item['source_key'] ?? ''), 'shell.') && !str_starts_with((string)($item['source_key'] ?? ''), 'core.')) {
            $failures[] = "{$file}: items[{$index}] Shell-owned navigation must identify shell/core source only";
        }
    }
}

if ($failures) {
    foreach ($failures as $failure) {
        echo "  fail: {$failure}\n";
    }
    exit(1);
}

echo '  ok: app manifests checked: ' . count($appManifestFiles) . PHP_EOL;
echo '  ok: module manifests checked: ' . count($moduleManifestFiles) . PHP_EOL;
echo '  ok: navigation catalogs checked: ' . count($navigationFiles) . PHP_EOL;
PHP
then
  failures=$((failures + 1))
fi

build_runtime_additions_index

# Contribution-related operator and display contexts.
operator_display_targets=()
append_existing_targets operator_display_targets \
  "apps/Shell/Views/operator" \
  "apps/Shell/Views/display" \
  "apps/Shell/Composers" \
  "apps/Shell/Services" \
  "apps/Manufacturing/Views/operator" \
  "apps/Manufacturing/Views/display" \
  "apps/Manufacturing/modules/*/Views/operator" \
  "apps/Manufacturing/modules/*/Views/display" \
  "apps/SBAIO/Views/operator" \
  "apps/SBAIO/Views/display" \
  "apps/SBAIO/modules/*/Views/operator" \
  "apps/SBAIO/modules/*/Views/display" \
  "apps/Platform/Views/operator" \
  "apps/Platform/Views/display" \
  "apps/Platform/modules/*/Views/operator" \
  "apps/Platform/modules/*/Views/display"

echo "- scanning contribution contexts for wrapper confinement risks"
if [[ "${#operator_display_targets[@]}" -gt 0 ]]; then
  wrapper_pattern='href=["\x27]/(apps|ops|admin)/|action=["\x27]/(apps|ops|admin)/|Location:[[:space:]]*/(apps|ops|admin)/'
  wrapper_allowlist='switch_to_admin|/admin/<\?php echo \$uenc; \?>|AdminLayerWrapperComposer\.php|check_operator_confinement\.sh|check_admin_route_contract\.sh'
  scan_and_filter "$wrapper_pattern" "$wrapper_allowlist" "unsafe raw /apps|/ops|/admin links in operator/display contexts" "${operator_display_targets[@]}"
  if [[ "$LAST_COUNT" != "0" ]]; then
    failures=$((failures + 1))
  fi
fi

# Shell render boundaries should avoid direct contribution URL outputs where possible.
shell_render_targets=()
append_existing_targets shell_render_targets \
  "apps/Shell/Views" \
  "apps/Shell/Composers" \
  "apps/Shell/Services"

echo "- scanning Shell render boundaries for direct contribution URL/action output"
if [[ "${#shell_render_targets[@]}" -gt 0 ]]; then
  normalization_pattern='href=[^\n]*\$[A-Za-z_][A-Za-z0-9_]*\[["\x27](url|action|route|target)["\x27]\]|action=[^\n]*\$[A-Za-z_][A-Za-z0-9_]*\[["\x27](url|action|route|target)["\x27]\]'
  normalization_allowlist='normalize|normalized|resolved|sanitize|safeUrl|safe_url|htmlspecialchars|rawurlencode|build|compose|legacy|compat'
  scan_and_filter "$normalization_pattern" "$normalization_allowlist" "direct contribution url/action rendering without obvious normalization markers" "${shell_render_targets[@]}"
  if [[ "$LAST_COUNT" != "0" ]]; then
    failures=$((failures + 1))
  fi
fi

# Display contributions must remain readonly and actionless.
display_targets=()
append_existing_targets display_targets \
  "apps/Shell/Views/display" \
  "apps/Shell/Composers/DisplaySurfaceComposer.php" \
  "apps/Shell/Services/DisplayLayerService.php" \
  "apps/Manufacturing/Views/display" \
  "apps/Manufacturing/modules/*/Views/display" \
  "apps/SBAIO/Views/display" \
  "apps/SBAIO/modules/*/Views/display" \
  "apps/Platform/Views/display" \
  "apps/Platform/modules/*/Views/display"

echo "- scanning display contribution paths for readonly violations"
if [[ "${#display_targets[@]}" -gt 0 ]]; then
  display_pattern='<form\b|method=["\x27]post["\x27]|type=["\x27]submit["\x27]|onclick=|onsubmit=|fetch\(|XMLHttpRequest\(|\b(delete|edit|update|create)(_|-)?(url|action|link)\b'
  display_allowlist='readonly|read only|diagnostic|docs|check_display_readonly\.sh'
  scan_and_filter "$display_pattern" "$display_allowlist" "display contribution readonly violations" "${display_targets[@]}"
  if [[ "$LAST_COUNT" != "0" ]]; then
    failures=$((failures + 1))
  fi
fi

# Contribution-specific styling should remain owner-scoped (regression-focused).
echo "- scanning runtime additions for contribution-specific selector leakage into Shell css"
if [[ -s "$tmp_runtime_additions" ]]; then
  css_pattern='^apps/Shell/styles/[^:]+:[0-9]+:[[:space:]].*(^|[,{}[:space:]])\.(mfg|manufacturing|qc|dispatch|sbaio|platform|procurement|studio|payroll|assembly|timecard|coverage|machine)-'
  css_allowlist='^scripts/architecture/|^docs/|^AGENT-COMPLIANCE-CHECKLIST\.md|^apps/Shell/styles/[^:]+:[0-9]+:[[:space:]]*/?\*|data-dashboard-key='
  scan_runtime_additions "$css_pattern" "$css_allowlist" "new contribution-specific selectors leaking into Shell css"
  if [[ "$LAST_COUNT" != "0" ]]; then
    failures=$((failures + 1))
  fi
else
  echo "  note: no runtime diff additions to scan for css leakage"
fi

# Contribution paths should not use legacy visibility fields as hidden truth (regression-focused).
echo "- scanning runtime additions for hidden visibility-truth fields in contribution contexts"
if [[ -s "$tmp_runtime_additions" ]]; then
  visibility_pattern='(assigned_apps|module_visibility|operator_views|dashboard_type)'
  visibility_allowlist='^scripts/architecture/|^docs/|^AGENT-COMPLIANCE-CHECKLIST\.md|active_assigned_apps|resolved|compat|legacy|diagnostic|migration|debt|inventory|schema|payload|row|check_migration_debt_regressions\.sh|check_surface_contribution_contracts\.sh'
  scan_runtime_additions "$visibility_pattern" "$visibility_allowlist" "new legacy visibility-truth field usage in contribution paths"
  if [[ "$LAST_COUNT" != "0" ]]; then
    failures=$((failures + 1))
  fi
else
  echo "  note: no runtime diff additions to scan for visibility-truth regressions"
fi

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (surface contribution contract risks found)" >&2
  exit 1
fi

echo "RESULT: PASS"
