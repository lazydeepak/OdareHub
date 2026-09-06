#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_navigation_composition_duplicates"
echo "- read-only diagnostic for navigation composition ownership and duplicate signals"

failures=0
warnings=0

NAV_FILES=()
while IFS= read -r path; do
  NAV_FILES+=("$path")
done < <(find apps plugins -type f -name 'navigation.php' | sort)

if [[ ${#NAV_FILES[@]} -eq 0 ]]; then
  echo "  fail: no navigation.php files found under apps/plugins"
  exit 1
fi

echo "  info: discovered ${#NAV_FILES[@]} navigation contribution files"

PHP_BIN="${PHP_BIN:-$(command -v php || true)}"
if [[ -z "$PHP_BIN" ]]; then
  echo "  fail: php binary not found" >&2
  exit 2
fi

report_file="$(mktemp /tmp/nav-dup-report.XXXXXX)"
trap 'rm -f "$report_file"' EXIT

set +e
"$PHP_BIN" /dev/stdin "${NAV_FILES[@]}" > "$report_file" <<'PHP'
<?php
$root = getcwd();
$files = array_slice($argv, 1);

$failures = [];
$warnings = [];
$metrics = [
    "file_count" => 0,
    "item_count" => 0,
    "suppressed_count" => 0,
    "invalid_files" => 0,
];

$urlMap = [];
$sourceMap = [];
$menuMap = [];
$suppressedItems = [];

$normalize = static function(string $value): string {
    return strtolower(trim($value));
};

$ownerToken = static function(string $value): string {
    return preg_replace('/[^a-z0-9]+/', '', strtolower(trim($value))) ?? '';
};

$expectedOwnersForPath = static function(string $rel) use ($normalize): array {
    if (preg_match("#^apps/([^/]+)/navigation\\.php$#", $rel, $m) === 1) {
        return [$normalize($m[1])];
    }
    if (preg_match("#^apps/([^/]+)/modules/([^/]+)/navigation\\.php$#", $rel, $m) === 1) {
        return array_values(array_unique([$normalize($m[1]), $normalize($m[2])]));
    }
    if (preg_match("#^apps/Generated/([^/]+)/([^/]+)/navigation\\.php$#", $rel, $m) === 1) {
        return array_values(array_unique(["generated", $normalize($m[1]), $normalize($m[2])]));
    }
    if (preg_match("#^plugins/([^/]+)/navigation\\.php$#", $rel, $m) === 1) {
        return [$normalize($m[1])];
    }
    return [];
};

$pushMap = static function(array &$map, string $value, string $owner, string $ownerTokenValue, string $ref, string $file): void {
    if ($value === "") {
        return;
    }
    if (!isset($map[$value])) {
        $map[$value] = [];
    }
    $map[$value][] = ["owner" => $owner, "owner_token" => $ownerTokenValue, "ref" => $ref, "file" => $file];
};

foreach ($files as $file) {
    $rel = str_replace($root . "/", "", $file);
    $metrics["file_count"]++;
    $expectedOwners = $expectedOwnersForPath($rel);

    try {
        $data = require $file;
    } catch (Throwable $e) {
        $metrics["invalid_files"]++;
        $failures[] = "invalid file {$rel}: " . $e->getMessage();
        continue;
    }

    if (!is_array($data)) {
        $metrics["invalid_files"]++;
        $failures[] = "invalid file {$rel}: must return array";
        continue;
    }

    $contract = (string)($data["contract"] ?? "");
    if ($contract !== "navigation.v1") {
        $failures[] = "{$rel}: contract must be navigation.v1";
    }

    $fileOwner = $normalize((string)($data["owner"] ?? ""));
    $expectedOwnerTokens = array_values(array_unique(array_filter(array_map($ownerToken, $expectedOwners))));
    if ($fileOwner !== "" && $expectedOwners !== [] && !in_array($ownerToken($fileOwner), $expectedOwnerTokens, true)) {
        $warnings[] = "{$rel}: file owner '{$fileOwner}' does not match expected path owner(s): " . implode(", ", $expectedOwners);
    }

    $items = $data["items"] ?? [];
    if (!is_array($items)) {
        $failures[] = "{$rel}: items must be an array";
        continue;
    }

    foreach ($items as $idx => $item) {
        if (!is_array($item)) {
            $failures[] = "{$rel}: items[{$idx}] must be an array";
            continue;
        }

        $metrics["item_count"]++;
        $itemOwner = $normalize((string)($item["owner"] ?? $fileOwner));
        $itemOwner = $itemOwner !== "" ? $itemOwner : "unknown";
        $itemOwnerToken = $ownerToken($itemOwner);
        $itemKey = trim((string)($item["key"] ?? "item_{$idx}"));
        $ref = "{$rel}#item[{$idx}]({$itemKey})";

        $allowedItemOwnerTokens = $expectedOwnerTokens;
        $moduleOwner = $normalize((string)($item["module"] ?? ""));
        if ($moduleOwner !== "") {
            $allowedItemOwnerTokens[] = $ownerToken($moduleOwner);
        }
        $allowedItemOwnerTokens = array_values(array_unique(array_filter($allowedItemOwnerTokens)));

        $isPluginPath = preg_match("#^plugins/#", $rel) === 1;
        if ($isPluginPath && $itemOwner !== "unknown" && $allowedItemOwnerTokens !== [] && !in_array($itemOwnerToken, $allowedItemOwnerTokens, true)) {
            $failures[] = "cross-owner item {$ref}: owner '{$itemOwner}' not in expected owner scope [" . implode(", ", $expectedOwners) . "]";
        } elseif (!$isPluginPath && $itemOwner !== "unknown" && $allowedItemOwnerTokens !== [] && !in_array($itemOwnerToken, $allowedItemOwnerTokens, true)) {
            $warnings[] = "owner mismatch {$ref}: owner '{$itemOwner}' outside primary app scope [" . implode(", ", $expectedOwners) . "]";
        }

        $url = trim((string)($item["url"] ?? ""));
        $source = trim((string)($item["source_key"] ?? ""));
        $menu = trim((string)($item["menu_key"] ?? ""));

        $pushMap($urlMap, $url, $itemOwner, $itemOwnerToken, $ref, $rel);
        $pushMap($sourceMap, $source, $itemOwner, $itemOwnerToken, $ref, $rel);
        $pushMap($menuMap, $menu, $itemOwner, $itemOwnerToken, $ref, $rel);

        if (($item["nav_visible"] ?? true) === false) {
            $metrics["suppressed_count"]++;
            $suppressedItems[] = $ref . ($url !== "" ? " -> {$url}" : "");
        }
    }
}

$inspectDuplicate = static function(string $label, array $map, array &$failures, array &$warnings): void {
    foreach ($map as $value => $rows) {
        if (count($rows) < 2) {
            continue;
        }

        $owners = [];
        $files = [];
        foreach ($rows as $row) {
            $owners[$row["owner_token"]] = true;
            $files[$row["file"]] = true;
        }

        if (count($owners) > 1 && count($files) > 1) {
            $refs = array_map(static fn(array $row): string => $row["owner"] . " @ " . $row["ref"], $rows);
            $failures[] = "duplicate {$label} across owners '{$value}': " . implode(" | ", $refs);
            continue;
        }

        if (count($files) > 1) {
            $refs = array_map(static fn(array $row): string => $row["ref"], $rows);
            $warnings[] = "duplicate {$label} across files '{$value}': " . implode(" | ", $refs);
        }
    }
};

$inspectDuplicate("url", $urlMap, $failures, $warnings);
$inspectDuplicate("source_key", $sourceMap, $failures, $warnings);
$inspectDuplicate("menu_key", $menuMap, $failures, $warnings);

echo "metrics.file_count=" . $metrics["file_count"] . PHP_EOL;
echo "metrics.item_count=" . $metrics["item_count"] . PHP_EOL;
echo "metrics.suppressed_count=" . $metrics["suppressed_count"] . PHP_EOL;
echo "metrics.invalid_files=" . $metrics["invalid_files"] . PHP_EOL;

if ($suppressedItems !== []) {
    echo "info.nav_visible_false=" . count($suppressedItems) . PHP_EOL;
    foreach ($suppressedItems as $row) {
        echo "suppressed: {$row}" . PHP_EOL;
    }
}

if ($warnings !== []) {
    foreach ($warnings as $warning) {
        echo "warning: {$warning}" . PHP_EOL;
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo "failure: {$failure}" . PHP_EOL;
    }
    exit(1);
}
PHP
php_status=$?
set -e

cat "$report_file"

if grep -q '^warning:' "$report_file"; then
  warnings=$(grep -c '^warning:' "$report_file")
fi
if grep -q '^failure:' "$report_file"; then
  failures=$(grep -c '^failure:' "$report_file")
fi

echo ""
echo "== Composition inventory labels =="
echo "path|current_role|final_owner|decision"

print_inventory_row() {
  local path="$1"
  local current_role="$2"
  local final_owner="$3"
  local decision="$4"

  if [[ -e "$path" ]]; then
    echo "${path}|${current_role}|${final_owner}|${decision}"
  fi
}

print_inventory_row "app/Core/SidebarBuilder.php" "compatibility runtime menu composer" "Shell runtime composer after Core extraction" "CORE_LOCKED"

for path in app/Navigation/*.php app/Navigation/sidebar_sources/*.php; do
  [[ -f "$path" ]] || continue
  print_inventory_row "$path" "legacy compatibility bridge" "Shell compatibility bridge" "KEEP_COMPAT"
done

print_inventory_row "apps/Shell/sidebar.php" "Shell-owned composition taxonomy" "Shell" "KEEP"
for path in apps/Shell/sidebar_sources/*.php; do
  [[ -f "$path" ]] || continue
  print_inventory_row "$path" "Shell-owned source placeholder" "Shell" "KEEP"
done

while IFS= read -r path; do
  print_inventory_row "$path" "owner navigation contribution" "owning app" "KEEP"
done < <(find apps -maxdepth 2 -type f -name 'navigation.php' ! -path 'apps/Generated/*' | sort)

while IFS= read -r path; do
  print_inventory_row "$path" "owner module navigation contribution" "owning app/module" "KEEP"
done < <(find apps -path 'apps/*/modules/*/navigation.php' -type f | sort)

while IFS= read -r path; do
  print_inventory_row "$path" "generated navigation contribution" "generated app/module artifact owner" "KEEP_COMPAT"
done < <(find apps/Generated -type f -name 'navigation.php' 2>/dev/null | sort)

while IFS= read -r path; do
  print_inventory_row "$path" "plugin navigation contribution" "owning plugin" "KEEP"
done < <(find plugins -maxdepth 2 -type f -name 'navigation.php' | sort)

echo ""
echo "== DB menu fallback usage =="
db_menu_findings="$(grep -RInE "FROM[[:space:]]+menus|SELECT[^;]*menu_key" app apps plugins public scripts 2>/dev/null || true)"
if [[ -n "$db_menu_findings" ]]; then
  echo "$db_menu_findings"
  echo "  warning: DB menus remain runtime fallback/search inputs; classify before removal"
  warnings=$((warnings + 1))
else
  echo "  ok: no DB menus fallback usage found in scanned runtime/tool paths"
fi

echo ""
echo "== Legacy bridge protection (app/Navigation) =="
legacy_findings="$(grep -RInE "url[[:space:]]*=>[[:space:]]*'/(apps|ops|admin|u|displays)(/|')" app/Navigation 2>/dev/null || true)"
if [[ -n "$legacy_findings" ]]; then
  echo "$legacy_findings"
  echo "  fail: app/Navigation declares business/runtime URLs; bridge must stay compatibility-only"
  failures=$((failures + 1))
else
  echo "  ok: no direct business/runtime URL declarations in app/Navigation"
fi

echo ""
if [[ "$php_status" -ne 0 || "$failures" -ne 0 ]]; then
  echo "[architecture] navigation duplicate check: FAIL (failures=${failures}, warnings=${warnings})"
  exit 1
fi

echo "[architecture] navigation duplicate check: PASS (warnings=${warnings})"
