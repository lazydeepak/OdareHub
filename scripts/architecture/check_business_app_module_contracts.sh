#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_business_app_module_contracts"
echo "- read-only diagnostic for reference Business App and Module ownership contracts"

failures=0
contract_doc="docs/architecture/business-app-module-ownership-contract.md"
expected_business_apps=(
  "apps/Manufacturing|manufacturing|Manufacturing"
  "apps/SBAIO|sbaio|SBAIO"
)
active_business_apps=(
  "apps/Manufacturing|manufacturing|Manufacturing"
  "apps/SBAIO|sbaio|SBAIO"
)

check_file() {
  local path="$1"
  local label="$2"

  if [[ -f "$path" ]]; then
    echo "  ok: $label ($path)"
    return 0
  fi

  echo "  missing: $label ($path)"
  failures=$((failures + 1))
}

check_text() {
  local path="$1"
  local needle="$2"
  local label="$3"

  if [[ ! -f "$path" ]]; then
    echo "  fail: cannot inspect $label; missing file $path"
    failures=$((failures + 1))
    return
  fi

  if grep -Fq -- "$needle" "$path"; then
    echo "  ok: $label"
  else
    echo "  fail: $label"
    failures=$((failures + 1))
  fi
}

echo ""
echo "== Contract baseline =="
check_file "$contract_doc" "business app/module ownership contract"
check_text "$contract_doc" "Apps own business logic" "contract protects app business ownership law"
check_text "$contract_doc" "Apps contribute; Shell composes" "contract protects apps-contribute Shell-composes law"
check_text "$contract_doc" "Runtime consumes resolved/compiled contracts" "contract protects resolved runtime law"
check_text "$contract_doc" "Studio edits but does not own runtime truth" "contract protects Studio non-owner law"
check_text "$contract_doc" "System Tools maintain and validate but do not bypass governance" "contract protects System Tools non-bypass law"
check_text "$contract_doc" "No hidden duplicate source of truth" "contract protects no-hidden-truth law"
check_text "$contract_doc" "Current diagnostics validate stable contract shape only" "contract documents diagnostic scope"
check_text "$contract_doc" "scripts/architecture/check_business_app_module_contracts.sh" "contract references this enforcement gate"

echo ""
echo "== Reference Business App scan scope =="
if [[ "${#active_business_apps[@]}" -ne "${#expected_business_apps[@]}" ]]; then
  echo "  fail: reference business app scan scope changed; expected ${#expected_business_apps[@]}, found ${#active_business_apps[@]}"
  failures=$((failures + 1))
else
  for index in "${!expected_business_apps[@]}"; do
    if [[ "${active_business_apps[$index]}" == "${expected_business_apps[$index]}" ]]; then
      echo "  ok: business-app[$index] ${active_business_apps[$index]}"
    else
      echo "  fail: business-app[$index] changed; expected ${expected_business_apps[$index]}, found ${active_business_apps[$index]}"
      failures=$((failures + 1))
    fi
  done
fi

php_check_app_manifest() {
  local manifest="$1"
  local expected_key="$2"

  php -r '
    $manifest = $argv[1];
    $expectedKey = $argv[2];
    $data = json_decode((string)file_get_contents($manifest), true);
    if (!is_array($data)) {
        fwrite(STDERR, "invalid json: {$manifest}\n");
        exit(2);
    }

    $failures = [];
    foreach (["app_key", "type", "entry", "modules", "routes", "permissions", "dependencies", "native_modules", "legacy_bridge_plugins"] as $field) {
        if (!array_key_exists($field, $data)) {
            $failures[] = "missing {$field}";
        }
    }

    if (($data["app_key"] ?? "") !== $expectedKey) {
        $failures[] = "app_key must be {$expectedKey}";
    }
    if (($data["type"] ?? "") !== "business") {
        $failures[] = "type must be business";
    }
    if (($data["entry"] ?? "") !== "routes.php") {
        $failures[] = "entry must be routes.php";
    }
    foreach (["modules", "routes", "permissions", "dependencies", "native_modules", "legacy_bridge_plugins"] as $arrayField) {
        if (array_key_exists($arrayField, $data) && !is_array($data[$arrayField])) {
            $failures[] = "{$arrayField} must be an array";
        }
    }
    if (!is_array($data["modules"] ?? null) || count($data["modules"]) === 0) {
        $failures[] = "modules must declare at least one owned module group";
    }
    if (!is_array($data["routes"] ?? null) || count($data["routes"]) === 0) {
        $failures[] = "routes must declare at least one owned route";
    }
    if (!is_array($data["permissions"] ?? null) || count($data["permissions"]) === 0) {
        $failures[] = "permissions must declare at least one app permission";
    }

    $moduleKeys = [];
    foreach (($data["modules"] ?? []) as $index => $module) {
        if (!is_array($module)) {
            $failures[] = "modules[{$index}] must be an object";
            continue;
        }
        foreach (["key", "name"] as $field) {
            if (($module[$field] ?? "") === "") {
                $failures[] = "modules[{$index}] missing {$field}";
            }
        }
        $key = (string)($module["key"] ?? "");
        if ($key !== "") {
            if (isset($moduleKeys[$key])) {
                $failures[] = "duplicate module key {$key}";
            }
            $moduleKeys[$key] = true;
        }
    }

    $routePaths = [];
    $hasCanonicalAppRoute = false;
    foreach (($data["routes"] ?? []) as $index => $route) {
        if (!is_array($route)) {
            $failures[] = "routes[{$index}] must be an object";
            continue;
        }
        $path = (string)($route["path"] ?? "");
        if ($path === "") {
            $failures[] = "routes[{$index}] missing path";
            continue;
        }
        if ($path[0] !== "/") {
            $failures[] = "routes[{$index}] path must be absolute";
        }
        if (isset($routePaths[$path])) {
            $failures[] = "duplicate route path {$path}";
        }
        $routePaths[$path] = true;
        if ($path === "/apps/{$expectedKey}" || str_starts_with($path, "/apps/{$expectedKey}/")) {
            $hasCanonicalAppRoute = true;
        }
    }
    if (!$hasCanonicalAppRoute) {
        $failures[] = "routes must include a canonical /apps/{$expectedKey} route";
    }

    $permissionKeys = [];
    foreach (($data["permissions"] ?? []) as $index => $permission) {
        if (!is_string($permission) || $permission === "") {
            $failures[] = "permissions[{$index}] must be a non-empty string";
            continue;
        }
        if (isset($permissionKeys[$permission])) {
            $failures[] = "duplicate permission {$permission}";
        }
        $permissionKeys[$permission] = true;
    }

    foreach (($data["native_modules"] ?? []) as $index => $moduleKey) {
        if (!is_string($moduleKey) || $moduleKey === "") {
            $failures[] = "native_modules[{$index}] must be a non-empty string";
        }
    }
    foreach (($data["legacy_bridge_plugins"] ?? []) as $index => $pluginKey) {
        if (!is_string($pluginKey) || $pluginKey === "") {
            $failures[] = "legacy_bridge_plugins[{$index}] must be a non-empty string";
        }
    }

    if ($failures) {
        foreach ($failures as $failure) {
            echo "    fail: {$failure}\n";
        }
        exit(1);
    }

    $moduleCount = is_array($data["modules"] ?? null) ? count($data["modules"]) : 0;
    $routeCount = is_array($data["routes"] ?? null) ? count($data["routes"]) : 0;
    $permissionCount = is_array($data["permissions"] ?? null) ? count($data["permissions"]) : 0;
    echo "    app_key: {$data["app_key"]}\n";
    echo "    type: {$data["type"]}\n";
    echo "    modules declared: {$moduleCount}\n";
    echo "    routes declared: {$routeCount}\n";
    echo "    permissions declared: {$permissionCount}\n";
    echo "    native modules declared: " . count($data["native_modules"]) . "\n";
    echo "    legacy bridge plugins declared: " . count($data["legacy_bridge_plugins"]) . "\n";
  ' "$manifest" "$expected_key"
}

php_check_module_manifests() {
  local app_key="$1"
  local modules_dir="$2"

  php -r '
    $appKey = $argv[1];
    $modulesDir = $argv[2];
    $files = glob($modulesDir . "/*/plugin.json") ?: [];
    sort($files);

    if (!$files) {
        echo "    fail: no module plugin.json files found\n";
        exit(1);
    }

    $failures = [];
    $allowedModuleTypes = [
        "business_entity" => true,
        "planning" => true,
        "process_execution" => true,
        "dashboard_only" => true,
        "service_only" => true,
        "governance" => true,
        "integration" => true,
    ];
    $allowedCapabilities = [
        "routes" => true,
        "views" => true,
        "forms" => true,
        "schema" => true,
        "migrations" => true,
        "controllers" => true,
        "services" => true,
        "permissions" => true,
        "menus" => true,
        "widgets" => true,
        "charts" => true,
        "dashboard" => true,
        "reports" => true,
        "exports" => true,
        "dependencies" => true,
        "lifecycle_hooks" => true,
        "localization" => true,
    ];
    $requiredLifecycle = [
        "routes" => "active_only",
        "menus" => "active_only",
        "widgets" => "active_only",
        "reports" => "active_only",
        "dashboards" => "active_only",
        "schema" => "installed_or_active",
    ];
    $moduleTypes = [];
    $moduleCount = 0;
    $capabilityCounts = [];

    foreach ($files as $file) {
        $moduleCount++;
        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data)) {
            $failures[] = "{$file}: invalid json";
            continue;
        }

        foreach (["package_type", "owner_app", "module_key", "module_type", "declared_capabilities", "lifecycle_contract"] as $field) {
            if (!array_key_exists($field, $data)) {
                $failures[] = "{$file}: missing {$field}";
            }
        }

        if (($data["package_type"] ?? "") !== "module") {
            $failures[] = "{$file}: package_type must be module";
        }
        if (($data["owner_app"] ?? "") !== $appKey) {
            $failures[] = "{$file}: owner_app must be {$appKey}";
        }
        if (array_key_exists("declared_capabilities", $data) && !is_array($data["declared_capabilities"])) {
            $failures[] = "{$file}: declared_capabilities must be an array";
        }
        if (array_key_exists("lifecycle_contract", $data) && !is_array($data["lifecycle_contract"])) {
            $failures[] = "{$file}: lifecycle_contract must be an object";
        }
        if (!isset($allowedModuleTypes[(string)($data["module_type"] ?? "")])) {
            $failures[] = "{$file}: module_type must be a documented module type";
        }
        if (!preg_match("/^L[0-4]$/", (string)($data["target_maturity_level"] ?? ""))) {
            $failures[] = "{$file}: target_maturity_level must be L0-L4";
        }

        $capabilities = $data["declared_capabilities"] ?? [];
        if (is_array($capabilities)) {
            $seenCapabilities = [];
            foreach ($capabilities as $capability) {
                if (!is_string($capability) || $capability === "") {
                    $failures[] = "{$file}: declared_capabilities entries must be non-empty strings";
                    continue;
                }
                if (!isset($allowedCapabilities[$capability])) {
                    $failures[] = "{$file}: unknown declared capability {$capability}";
                }
                if (isset($seenCapabilities[$capability])) {
                    $failures[] = "{$file}: duplicate declared capability {$capability}";
                }
                $seenCapabilities[$capability] = true;
                $capabilityCounts[$capability] = ($capabilityCounts[$capability] ?? 0) + 1;
            }

            if (isset($seenCapabilities["forms"]) && !isset($seenCapabilities["views"])) {
                $failures[] = "{$file}: forms capability requires views capability";
            }
            if (isset($seenCapabilities["controllers"]) && !isset($seenCapabilities["routes"])) {
                $failures[] = "{$file}: controllers capability requires routes capability";
            }
            if (isset($seenCapabilities["migrations"]) && !isset($seenCapabilities["schema"])) {
                $failures[] = "{$file}: migrations capability requires schema capability";
            }
            if (isset($seenCapabilities["reports"]) && !is_array($data["reports"] ?? null)) {
                $failures[] = "{$file}: reports capability requires reports declarations";
            }
        }

        $lifecycle = $data["lifecycle_contract"] ?? [];
        if (is_array($lifecycle)) {
            foreach ($requiredLifecycle as $key => $expectedValue) {
                if (($lifecycle[$key] ?? null) !== $expectedValue) {
                    $failures[] = "{$file}: lifecycle_contract.{$key} must be {$expectedValue}";
                }
            }
            foreach ($lifecycle as $key => $value) {
                if (!array_key_exists($key, $requiredLifecycle)) {
                    $failures[] = "{$file}: unknown lifecycle_contract key {$key}";
                }
                if (!is_string($value) || $value === "") {
                    $failures[] = "{$file}: lifecycle_contract.{$key} must be a non-empty string";
                }
            }
        }

        foreach (($data["reports"] ?? []) as $index => $report) {
            if (!is_array($report)) {
                $failures[] = "{$file}: reports[{$index}] must be an object";
                continue;
            }
            foreach (["report_key", "title", "view", "export_view", "owner", "scope", "permission", "lifecycle"] as $field) {
                if (($report[$field] ?? "") === "") {
                    $failures[] = "{$file}: reports[{$index}] missing {$field}";
                }
            }
            if (($report["owner"] ?? "") !== "module") {
                $failures[] = "{$file}: reports[{$index}] owner must be module";
            }
            if (($report["lifecycle"] ?? "") !== "active_only") {
                $failures[] = "{$file}: reports[{$index}] lifecycle must be active_only";
            }
        }

        $moduleType = (string)($data["module_type"] ?? "<missing>");
        $moduleTypes[$moduleType] = ($moduleTypes[$moduleType] ?? 0) + 1;
    }

    if ($failures) {
        foreach ($failures as $failure) {
            echo "    fail: {$failure}\n";
        }
        exit(1);
    }

    ksort($moduleTypes);
    echo "    module manifests checked: {$moduleCount}\n";
    foreach ($moduleTypes as $type => $count) {
        echo "    module_type {$type}: {$count}\n";
    }
    ksort($capabilityCounts);
    foreach ($capabilityCounts as $capability => $count) {
        echo "    capability {$capability}: {$count}\n";
    }
  ' "$app_key" "$modules_dir"
}

check_business_app() {
  local app_dir="$1"
  local app_key="$2"
  local app_name="$3"

  echo ""
  echo "== $app_name =="

  check_file "$app_dir/manifest.json" "business app manifest"
  check_file "$app_dir/AGENTS.md" "local app ownership guidance"
  check_file "$app_dir/routes.php" "app route registration"
  check_file "$app_dir/modules/AGENTS.md" "local module ownership guidance"

  if [[ -f "$app_dir/manifest.json" ]]; then
    if ! php_check_app_manifest "$app_dir/manifest.json" "$app_key"; then
      failures=$((failures + 1))
    fi
  fi

  if [[ -d "$app_dir/modules" ]]; then
    if ! php_check_module_manifests "$app_key" "$app_dir/modules"; then
      failures=$((failures + 1))
    fi
  else
    echo "  missing: modules directory ($app_dir/modules)"
    failures=$((failures + 1))
  fi
}

for business_app in "${active_business_apps[@]}"; do
  IFS='|' read -r app_dir app_key app_name <<< "$business_app"
  check_business_app "$app_dir" "$app_key" "$app_name"
done

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (business app/module contract baseline drift found)" >&2
  exit 1
fi

echo "RESULT: PASS"
