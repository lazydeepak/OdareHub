#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_system_app_contracts"
echo "- read-only validation for Shell and Platform system-app contracts"

failures=0
contract_note="scripts/architecture/system-app-contract-enforcement.md"
route_truth_note="scripts/architecture/system-app-route-truth-policy-anchors.md"

check_file() {
  local path="$1"
  local label="$2"

  if [[ -f "$path" ]]; then
    echo "  ok: $label ($path)"
    return 0
  fi

  echo "  fail: missing $label ($path)" >&2
  failures=$((failures + 1))
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

  if grep -Fq "$needle" "$path"; then
    echo "  ok: $label"
  else
    echo "  fail: $label" >&2
    failures=$((failures + 1))
  fi
}

manifest_value() {
  local manifest="$1"
  local field="$2"

  php -r '
    $manifest = $argv[1];
    $field = $argv[2];
    $data = json_decode((string)file_get_contents($manifest), true);
    if (!is_array($data)) {
        fwrite(STDERR, "invalid json: {$manifest}\n");
        exit(2);
    }
    $value = $data[$field] ?? null;
    if (is_bool($value)) {
        echo $value ? "true" : "false";
    } elseif (is_array($value)) {
        echo count($value);
    } elseif ($value === null) {
        echo "";
    } else {
        echo (string)$value;
    }
  ' "$manifest" "$field"
}

validate_contract() {
  local manifest="$1"
  local app_name="$2"
  local expected_app_key="$3"
  local expected_type="$4"
  local expected_owner_layer="$5"
  local expected_runtime_role="$6"

  php <<'PHP' "$manifest" "$app_name" "$expected_app_key" "$expected_type" "$expected_owner_layer" "$expected_runtime_role"
<?php
$manifest = $argv[1];
$appName = $argv[2];
$expectedAppKey = $argv[3];
$expectedType = $argv[4];
$expectedOwnerLayer = $argv[5];
$expectedRuntimeRole = $argv[6];

$raw = file_get_contents($manifest);
try {
    $data = json_decode((string)$raw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, "  fail: {$appName} manifest invalid JSON: {$e->getMessage()}\n");
    exit(1);
}

$failures = 0;
$check = static function (bool $condition, string $ok, string $fail) use (&$failures): void {
    if ($condition) {
        echo "  ok: {$ok}\n";
    } else {
        fwrite(STDERR, "  fail: {$fail}\n");
        $failures++;
    }
};
$contains = static function (array $items, string $needle): bool {
    foreach ($items as $item) {
        if (is_string($item) && str_contains($item, $needle)) {
            return true;
        }
    }
    return false;
};
$hasExact = static function (array $items, string $needle): bool {
    foreach ($items as $item) {
        if ($item === $needle) {
            return true;
        }
    }
    return false;
};

$expectedSourcePolicy = $appName === 'Shell'
    ? 'compose_resolved_contracts_only'
    : 'govern_and_diagnose_without_owning_runtime_business_meaning';
$expectedCanonicalPrefixes = $appName === 'Shell'
    ? ['/u/{username}', '/admin/{username}', '/displays']
    : ['/apps/platform', '/ops/*', '/admin/*'];
$expectedCompatibilitySurfaces = $appName === 'Shell'
    ? ['/me', '/me2']
    : ['/ops/dashboard'];

$check(($data['app_key'] ?? '') === $expectedAppKey, "{$appName} app_key {$expectedAppKey}", "{$appName} app_key must be {$expectedAppKey}");
$check(($data['type'] ?? '') === $expectedType, "{$appName} type {$expectedType}", "{$appName} type must be {$expectedType}");
$check(($data['entry'] ?? '') === 'routes.php', "{$appName} entry routes.php", "{$appName} entry must be routes.php");

foreach (['can_disable', 'can_uninstall', 'can_export'] as $field) {
    $check(array_key_exists($field, $data), "{$appName} {$field} compatibility flag declared", "{$appName} missing {$field} compatibility flag");
}

$systemContract = $data['system_app_contract'] ?? null;
$check(is_array($systemContract), "{$appName} system_app_contract present", "{$appName} system_app_contract must be declared");
if (is_array($systemContract)) {
    $check(($systemContract['version'] ?? '') === 'system-app-contract.v1', "{$appName} system_app_contract.version", "{$appName} system_app_contract.version must be system-app-contract.v1");
    $check(($systemContract['owner_layer'] ?? '') === $expectedOwnerLayer, "{$appName} owner_layer {$expectedOwnerLayer}", "{$appName} owner_layer must be {$expectedOwnerLayer}");
    $check(($systemContract['runtime_role'] ?? '') === $expectedRuntimeRole, "{$appName} runtime_role {$expectedRuntimeRole}", "{$appName} runtime_role must be {$expectedRuntimeRole}");
    $check(($systemContract['source_of_truth_policy'] ?? '') === $expectedSourcePolicy, "{$appName} source_of_truth_policy anchored", "{$appName} source_of_truth_policy must be {$expectedSourcePolicy}");

    $lifecycle = $systemContract['lifecycle_policy'] ?? null;
    $check(is_array($lifecycle), "{$appName} lifecycle_policy present", "{$appName} lifecycle_policy must be declared");
    if (is_array($lifecycle)) {
        $check(($lifecycle['disable'] ?? '') === 'restricted_contract_only', "{$appName} lifecycle disable restricted", "{$appName} lifecycle_policy.disable must be restricted_contract_only");
        $check(($lifecycle['uninstall'] ?? '') === 'restricted_contract_only', "{$appName} lifecycle uninstall restricted", "{$appName} lifecycle_policy.uninstall must be restricted_contract_only");
        $check(($lifecycle['export'] ?? '') === 'metadata_and_assets_only', "{$appName} lifecycle export metadata/assets only", "{$appName} lifecycle_policy.export must be metadata_and_assets_only");
    }

    $routePolicy = $systemContract['route_policy'] ?? null;
    $check(is_array($routePolicy), "{$appName} route_policy present", "{$appName} route_policy must be declared");
    if (is_array($routePolicy)) {
        $check(($routePolicy['must_not_reinterpret_routes'] ?? null) === true, "{$appName} route reinterpretation denied", "{$appName} route_policy.must_not_reinterpret_routes must be true");
        $canonicalPrefixes = $routePolicy['canonical_prefixes'] ?? [];
        $compatibilitySurfaces = $routePolicy['compatibility_surfaces'] ?? [];
        $check(is_array($canonicalPrefixes) && count($canonicalPrefixes) > 0, "{$appName} canonical_prefixes declared", "{$appName} route_policy.canonical_prefixes must be non-empty");
        $check(is_array($compatibilitySurfaces), "{$appName} compatibility_surfaces declared", "{$appName} route_policy.compatibility_surfaces must be declared as an array");
        if (is_array($canonicalPrefixes)) {
            foreach ($expectedCanonicalPrefixes as $prefix) {
                $check($hasExact($canonicalPrefixes, $prefix), "{$appName} canonical prefix {$prefix}", "{$appName} route_policy.canonical_prefixes must include {$prefix}");
            }
        }
        if (is_array($compatibilitySurfaces)) {
            foreach ($expectedCompatibilitySurfaces as $surface) {
                $check($hasExact($compatibilitySurfaces, $surface), "{$appName} compatibility surface {$surface}", "{$appName} route_policy.compatibility_surfaces must include {$surface}");
            }
        }
    }
}

$ownership = $data['ownership_contract'] ?? null;
$check(is_array($ownership), "{$appName} ownership_contract present", "{$appName} ownership_contract must be declared");
if (is_array($ownership)) {
    foreach (['owns', 'must_not_own', 'contributes_to', 'depends_on'] as $field) {
        $check(isset($ownership[$field]) && is_array($ownership[$field]) && count($ownership[$field]) > 0, "{$appName} ownership_contract.{$field} non-empty", "{$appName} ownership_contract.{$field} must be a non-empty array");
    }

    $owns = $ownership['owns'] ?? [];
    $mustNotOwn = $ownership['must_not_own'] ?? [];
    $dependsOn = $ownership['depends_on'] ?? [];

    if ($appName === 'Shell') {
        $check($contains($owns, 'runtime frame'), 'Shell owns runtime frame', 'Shell ownership_contract.owns must include runtime frame responsibility');
        $check($contains($owns, 'wrapper'), 'Shell owns wrapper composition', 'Shell ownership_contract.owns must include wrapper composition responsibility');
        $check($contains($mustNotOwn, 'Core primitives'), 'Shell must not own Core primitives', 'Shell ownership_contract.must_not_own must include Core primitives');
        $check($contains($mustNotOwn, 'business logic'), 'Shell must not own business logic', 'Shell ownership_contract.must_not_own must include business logic');
        $check($contains($mustNotOwn, 'ACL authorization policy'), 'Shell must not own ACL policy', 'Shell ownership_contract.must_not_own must include ACL authorization policy');
        $check($contains($mustNotOwn, 'Workspace Profile policy'), 'Shell must not own Workspace Profile policy', 'Shell ownership_contract.must_not_own must include Workspace Profile policy');
        $check($contains($mustNotOwn, 'Studio drafts'), 'Shell must not own Studio drafts/runtime truth', 'Shell ownership_contract.must_not_own must include Studio drafts/runtime truth');
        $check($contains($dependsOn, 'resolved experience contracts'), 'Shell depends on resolved experience contracts', 'Shell ownership_contract.depends_on must include resolved experience contracts');
    }

    if ($appName === 'Platform') {
        $check($contains($owns, 'platform governance'), 'Platform owns governance surfaces', 'Platform ownership_contract.owns must include platform governance surfaces');
        $check($contains($owns, 'System Tools'), 'Platform owns System Tools entry points/diagnostics', 'Platform ownership_contract.owns must include System Tools entry points and diagnostics');
        $check($contains($mustNotOwn, 'Core primitives'), 'Platform must not own Core primitives', 'Platform ownership_contract.must_not_own must include Core primitives');
        $check($contains($mustNotOwn, 'Shell runtime chrome'), 'Platform must not own Shell runtime chrome', 'Platform ownership_contract.must_not_own must include Shell runtime chrome');
        $check($contains($mustNotOwn, 'app/module business workflows'), 'Platform must not own app/module business workflows', 'Platform ownership_contract.must_not_own must include app/module business workflows');
        $check($contains($mustNotOwn, 'Studio editor'), 'Platform must not own Studio editor/runtime truth', 'Platform ownership_contract.must_not_own must include Studio editor/builder implementation');
        $check($contains($mustNotOwn, 'direct permission bypasses'), 'Platform must not own permission bypasses', 'Platform ownership_contract.must_not_own must include direct permission bypasses');
        $check($contains($dependsOn, 'Core auth'), 'Platform depends on Core auth/audit/permission primitives', 'Platform ownership_contract.depends_on must include Core auth/audit/permission primitives');
    }
}

exit($failures > 0 ? 1 : 0);
PHP
}

check_note() {
  echo ""
  echo "== System app contract note =="

  check_file "$contract_note" "system app contract enforcement note"
  check_file "$route_truth_note" "system app route/truth anchor note"
  check_text "$contract_note" "apps/Shell/manifest.json" "note references Shell manifest"
  check_text "$contract_note" "apps/Platform/manifest.json" "note references Platform manifest"
  check_text "$contract_note" "app_key: shell" "note documents Shell app key"
  check_text "$contract_note" "type: framework" "note documents Shell type"
  check_text "$contract_note" "system_app_contract.owner_layer: Shell" "note documents Shell owner layer"
  check_text "$contract_note" "system_app_contract.runtime_role: generic_runtime_shell" "note documents Shell runtime role"
  check_text "$contract_note" "app_key: platform" "note documents Platform app key"
  check_text "$contract_note" "type: system" "note documents Platform type"
  check_text "$contract_note" "system_app_contract.owner_layer: Platform" "note documents Platform owner layer"
  check_text "$contract_note" "system_app_contract.runtime_role: platform_governance" "note documents Platform runtime role"
  check_text "$contract_note" "disable: restricted_contract_only" "note documents lifecycle disable policy"
  check_text "$contract_note" "uninstall: restricted_contract_only" "note documents lifecycle uninstall policy"
  check_text "$contract_note" "export: metadata_and_assets_only" "note documents lifecycle export policy"
  check_text "$contract_note" "route_policy.must_not_reinterpret_routes: true" "note documents route reinterpretation rule"
  check_text "$contract_note" "owns: runtime frame" "note documents Shell runtime frame ownership"
  check_text "$contract_note" "owns: wrapper composition" "note documents Shell wrapper ownership"
  check_text "$contract_note" "must_not_own: Core primitives" "note documents Core primitive boundary"
  check_text "$contract_note" "must_not_own: business logic" "note documents Shell business-logic boundary"
  check_text "$contract_note" "must_not_own: ACL authorization policy" "note documents Shell ACL boundary"
  check_text "$contract_note" "must_not_own: Workspace Profile policy" "note documents Shell Workspace Profile boundary"
  check_text "$contract_note" "must_not_own: Studio drafts / runtime truth" "note documents Shell Studio truth boundary"
  check_text "$contract_note" "depends_on: resolved experience contracts" "note documents Shell resolved-experience dependency"
  check_text "$contract_note" "owns: platform governance" "note documents Platform governance ownership"
  check_text "$contract_note" "owns: System Tools entry points / diagnostics" "note documents Platform System Tools ownership"
  check_text "$contract_note" "must_not_own: Shell runtime chrome" "note documents Platform Shell-runtime boundary"
  check_text "$contract_note" "must_not_own: app/module business workflows" "note documents Platform business-workflow boundary"
  check_text "$contract_note" "must_not_own: Studio editor / runtime truth" "note documents Platform Studio truth boundary"
  check_text "$contract_note" "must_not_own: direct permission bypasses" "note documents Platform permission-bypass boundary"
  check_text "$contract_note" "depends_on: Core auth/audit/permission primitives" "note documents Platform Core dependency"

  check_text "$route_truth_note" "source_of_truth_policy: compose_resolved_contracts_only" "route/truth note documents Shell source-of-truth anchor"
  check_text "$route_truth_note" "canonical_prefix: /u/{username}" "route/truth note documents Shell /u prefix"
  check_text "$route_truth_note" "canonical_prefix: /admin/{username}" "route/truth note documents Shell /admin username prefix"
  check_text "$route_truth_note" "canonical_prefix: /displays" "route/truth note documents Shell displays prefix"
  check_text "$route_truth_note" "compatibility_surface: /me" "route/truth note documents Shell /me compatibility"
  check_text "$route_truth_note" "compatibility_surface: /me2" "route/truth note documents Shell /me2 compatibility"
  check_text "$route_truth_note" "source_of_truth_policy: govern_and_diagnose_without_owning_runtime_business_meaning" "route/truth note documents Platform source-of-truth anchor"
  check_text "$route_truth_note" "canonical_prefix: /apps/platform" "route/truth note documents Platform app prefix"
  check_text "$route_truth_note" "canonical_prefix: /ops/*" "route/truth note documents Platform ops prefix"
  check_text "$route_truth_note" "canonical_prefix: /admin/*" "route/truth note documents Platform admin prefix"
  check_text "$route_truth_note" "compatibility_surface: /ops/dashboard" "route/truth note documents Platform ops dashboard compatibility"
  check_text "$route_truth_note" "must_not_reinterpret_routes: true" "route/truth note documents route reinterpretation denial"
}

check_app() {
  local app_name="$1"
  local expected_app_key="$2"
  local expected_type="$3"
  local expected_owner_layer="$4"
  local expected_runtime_role="$5"
  local app_dir="apps/$app_name"
  local manifest="$app_dir/manifest.json"

  echo ""
  echo "== $app_name =="

  check_file "$manifest" "manifest"
  check_file "$app_dir/AGENTS.md" "local ownership guidance"
  check_file "$app_dir/routes.php" "route registration file"
  check_file "$app_dir/navigation.php" "navigation contract file"

  if [[ ! -f "$manifest" ]]; then
    return
  fi

  local app_key type entry can_disable can_uninstall can_export route_count
  app_key="$(manifest_value "$manifest" "app_key")"
  type="$(manifest_value "$manifest" "type")"
  entry="$(manifest_value "$manifest" "entry")"
  can_disable="$(manifest_value "$manifest" "can_disable")"
  can_uninstall="$(manifest_value "$manifest" "can_uninstall")"
  can_export="$(manifest_value "$manifest" "can_export")"
  route_count="$(manifest_value "$manifest" "routes")"

  echo "  manifest.app_key: ${app_key:-<missing>}"
  echo "  manifest.type: ${type:-<missing>}"
  echo "  manifest.entry: ${entry:-<missing>}"
  echo "  manifest.routes: ${route_count:-0}"
  echo "  manifest.can_disable: ${can_disable:-<missing>}"
  echo "  manifest.can_uninstall: ${can_uninstall:-<missing>}"
  echo "  manifest.can_export: ${can_export:-<missing>}"

  if validate_contract "$manifest" "$app_name" "$expected_app_key" "$expected_type" "$expected_owner_layer" "$expected_runtime_role"; then
    echo "  ok: $app_name system-app contract validation passed"
  else
    failures=$((failures + 1))
  fi
}

check_note
check_app "Shell" "shell" "framework" "Shell" "generic_runtime_shell"
check_app "Platform" "platform" "system" "Platform" "platform_governance"

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (system-app contract validation gaps found)" >&2
  exit 1
fi

echo "RESULT: PASS"
