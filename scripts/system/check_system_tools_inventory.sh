#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[system] check_system_tools_inventory"
echo "- read-only inventory check for registered system tool entry points"

failures=0
registry="scripts/system/tools.registry.json"
tmp_php_lint="/tmp/system-tool-php-lint.out"
tmp_portability="/tmp/system-tool-portability.out"
tmp_governance="/tmp/system-tool-governance.out"
expected_scan_anchors=(
  "scripts/system/tools.registry.json"
  "scripts/system/*.sh"
  "scripts/system/README.md"
  "scripts/system/deployment-readiness-portability.md"
  "scripts/system/deployment-readiness-orchestration-contract.md"
  "scripts/architecture/README.md"
  "scripts/assets/README.md"
  "docs/architecture/ODAREHUB-OS-ARCHITECTURE-CHARTER-V1.md"
  "docs/architecture/studio-operating-contract.md"
  "docs/architecture/studio-change-lifecycle-apply-contract.md"
  "docs/architecture/resolved-runtime-contract-pipeline.md"
  "registered-tool-registry-shape"
  "registered-tool-syntax"
  "registered-tool-portability"
  "registered-tool-governance-boundary"
)
active_scan_anchors=(
  "scripts/system/tools.registry.json"
  "scripts/system/*.sh"
  "scripts/system/README.md"
  "scripts/system/deployment-readiness-portability.md"
  "scripts/system/deployment-readiness-orchestration-contract.md"
  "scripts/architecture/README.md"
  "scripts/assets/README.md"
  "docs/architecture/ODAREHUB-OS-ARCHITECTURE-CHARTER-V1.md"
  "docs/architecture/studio-operating-contract.md"
  "docs/architecture/studio-change-lifecycle-apply-contract.md"
  "docs/architecture/resolved-runtime-contract-pipeline.md"
  "registered-tool-registry-shape"
  "registered-tool-syntax"
  "registered-tool-portability"
  "registered-tool-governance-boundary"
)

fail() {
  echo "  fail: $1" >&2
  failures=$((failures + 1))
}

ok() {
  echo "  ok: $1"
}

check_file() {
  local path="$1"
  local label="$2"

  if [[ -f "$path" ]]; then
    ok "$label ($path)"
  else
    fail "missing $label ($path)"
  fi
}

check_text() {
  local path="$1"
  local needle="$2"
  local label="$3"

  if [[ ! -f "$path" ]]; then
    fail "cannot inspect $label; missing file $path"
    return
  fi

  if grep -Fq "$needle" "$path"; then
    ok "$label"
  else
    fail "$label"
  fi
}

registered_paths() {
  php -r '
    $data = json_decode((string)file_get_contents("scripts/system/tools.registry.json"), true);
    foreach (($data["tools"] ?? []) as $tool) {
        if (isset($tool["path"])) {
            echo $tool["path"], "\n";
        }
    }
  '
}

registered_shell_paths() {
  registered_paths | grep -E '\.sh$' || true
}

registered_php_paths() {
  registered_paths | grep -E '\.php$' || true
}

is_registered_path() {
  local needle="$1"
  php -r '
    $registry = json_decode((string)file_get_contents("scripts/system/tools.registry.json"), true);
    $needle = $argv[1];
    foreach (($registry["tools"] ?? []) as $tool) {
        if (($tool["path"] ?? "") === $needle) {
            exit(0);
        }
    }
    exit(1);
  ' "$needle"
}

echo ""
echo "== Inventory scan contract =="
if [[ "${#active_scan_anchors[@]}" -ne "${#expected_scan_anchors[@]}" ]]; then
  fail "System Tools inventory scan anchor count changed; expected ${#expected_scan_anchors[@]}, found ${#active_scan_anchors[@]}"
else
  for index in "${!expected_scan_anchors[@]}"; do
    if [[ "${active_scan_anchors[$index]}" == "${expected_scan_anchors[$index]}" ]]; then
      ok "scan-anchor[$index] ${active_scan_anchors[$index]}"
    else
      fail "scan-anchor[$index] changed; expected ${expected_scan_anchors[$index]}, found ${active_scan_anchors[$index]}"
    fi
  done
fi

echo ""
echo "== Registry =="
check_file "$registry" "system tools registry"

if [[ -f "$registry" ]]; then
  php <<'PHP'
<?php
$registry = 'scripts/system/tools.registry.json';
$raw = file_get_contents($registry);
try {
    $data = json_decode((string)$raw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, "  fail: invalid registry JSON: {$e->getMessage()}\n");
    exit(1);
}

$failures = 0;
$allowedTopLevelFields = [
    'schema' => true,
    'owner' => true,
    'source_of_truth' => true,
    'tools' => true,
];
$allowedToolFields = [
    'key' => true,
    'path' => true,
    'kind' => true,
    'mode' => true,
    'preferred_entrypoint' => true,
    'description' => true,
];
$allowedKinds = [
    'orchestrator' => true,
    'diagnostic' => true,
    'diagnostic_orchestrator' => true,
    'asset_publisher' => true,
];
$allowedModes = [
    'validation' => true,
    'read_only' => true,
    'guarded_apply' => true,
];
$expectedContracts = [
    'deployment_readiness' => [
        'path' => 'scripts/system/check_deployment_readiness.sh',
        'kind' => 'orchestrator',
        'mode' => 'validation',
        'preferred_entrypoint' => true,
    ],
  'deployment_readiness_portable' => [
    'path' => 'scripts/system/check_deployment_readiness_portable.php',
    'kind' => 'orchestrator',
    'mode' => 'validation',
    'preferred_entrypoint' => false,
  ],
    'system_tools_inventory' => [
        'path' => 'scripts/system/check_system_tools_inventory.sh',
        'kind' => 'diagnostic',
        'mode' => 'read_only',
        'preferred_entrypoint' => false,
    ],
    'backfill_utility_aging' => [
        'path' => 'scripts/system/check_backfill_utility_aging.sh',
        'kind' => 'diagnostic',
        'mode' => 'read_only',
        'preferred_entrypoint' => false,
    ],
    'recovery_point' => [
        'path' => 'scripts/system/recovery_point.php',
        'kind' => 'orchestrator',
        'mode' => 'guarded_apply',
        'preferred_entrypoint' => false,
    ],
    'architecture_gates' => [
        'path' => 'scripts/architecture/run_architecture_gates.sh',
        'kind' => 'diagnostic_orchestrator',
        'mode' => 'read_only',
        'preferred_entrypoint' => false,
    ],
    'registered_css_publisher' => [
        'path' => 'scripts/assets/publish_registered_css.php',
        'kind' => 'asset_publisher',
        'mode' => 'guarded_apply',
        'preferred_entrypoint' => false,
    ],
    'first_boot_css_compiler' => [
        'path' => 'scripts/assets/compile_first_boot_css.php',
        'kind' => 'asset_publisher',
        'mode' => 'guarded_apply',
        'preferred_entrypoint' => false,
    ],
];

foreach (array_keys($data) as $field) {
    if (!isset($allowedTopLevelFields[$field])) {
        fwrite(STDERR, "  fail: unexpected registry top-level field: {$field}\n");
        $failures++;
    }
}

$schema = $data['schema'] ?? '';
if ($schema === 'susankhya.system_tools_registry.v1') {
    echo "  ok: registry schema {$schema}\n";
} else {
    fwrite(STDERR, "  fail: unexpected registry schema\n");
    $failures++;
}

if (($data['owner'] ?? '') === 'System Tools') {
    echo "  ok: registry owner is System Tools\n";
} else {
    fwrite(STDERR, "  fail: registry owner must be System Tools\n");
    $failures++;
}

if (($data['source_of_truth'] ?? '') === $registry) {
    echo "  ok: registry source_of_truth points to itself\n";
} else {
    fwrite(STDERR, "  fail: registry source_of_truth must be {$registry}\n");
    $failures++;
}

$tools = $data['tools'] ?? null;
if (!is_array($tools) || $tools === []) {
    fwrite(STDERR, "  fail: registry tools list missing or empty\n");
    exit(1);
}

$keys = [];
$paths = [];
$preferred = 0;
$preferredKey = '';
foreach ($tools as $index => $tool) {
    if (!is_array($tool)) {
        fwrite(STDERR, "  fail: tool entry {$index} is not an object\n");
        $failures++;
        continue;
    }

    foreach (array_keys($tool) as $field) {
        if (!isset($allowedToolFields[$field])) {
            fwrite(STDERR, "  fail: unexpected field in tool entry {$index}: {$field}\n");
            $failures++;
        }
    }

    $key = trim((string)($tool['key'] ?? ''));
    $path = trim((string)($tool['path'] ?? ''));
    $kind = trim((string)($tool['kind'] ?? ''));
    $mode = trim((string)($tool['mode'] ?? ''));
    $description = trim((string)($tool['description'] ?? ''));
    $isPreferred = (bool)($tool['preferred_entrypoint'] ?? false);

    if ($key === '' || $path === '' || $kind === '' || $mode === '') {
        fwrite(STDERR, "  fail: tool entry {$index} missing required key/path/kind/mode\n");
        $failures++;
        continue;
    }
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
        fwrite(STDERR, "  fail: tool key must be lowercase snake_case: {$key}\n");
        $failures++;
    }
    if (!array_key_exists('preferred_entrypoint', $tool)) {
        fwrite(STDERR, "  fail: tool {$key} missing preferred_entrypoint\n");
        $failures++;
    }
    if ($description === '') {
        fwrite(STDERR, "  fail: tool {$key} missing description\n");
        $failures++;
    }
    if (isset($keys[$key])) {
        fwrite(STDERR, "  fail: duplicate tool key {$key}\n");
        $failures++;
    }
    $keys[$key] = true;

    if (isset($paths[$path])) {
        fwrite(STDERR, "  fail: duplicate registered tool path {$path}\n");
        $failures++;
    }
    $paths[$path] = true;

    if (!isset($allowedKinds[$kind])) {
        fwrite(STDERR, "  fail: unsupported kind for {$key}: {$kind}\n");
        $failures++;
    }
    if (!isset($allowedModes[$mode])) {
        fwrite(STDERR, "  fail: unsupported mode for {$key}: {$mode}\n");
        $failures++;
    }
    if ($mode === 'guarded_apply' && !in_array($key, ['registered_css_publisher', 'first_boot_css_compiler', 'recovery_point'], true)) {
        fwrite(STDERR, "  fail: guarded_apply is currently allowed only for approved CSS publishers and recovery_point\n");
        $failures++;
    }
    if (isset($expectedContracts[$key])) {
        foreach ($expectedContracts[$key] as $field => $expectedValue) {
            $actualValue = $field === 'preferred_entrypoint' ? $isPreferred : ($tool[$field] ?? null);
            if ($actualValue !== $expectedValue) {
                $expectedDisplay = is_bool($expectedValue) ? ($expectedValue ? 'true' : 'false') : (string)$expectedValue;
                $actualDisplay = is_bool($actualValue) ? ($actualValue ? 'true' : 'false') : (string)$actualValue;
                fwrite(STDERR, "  fail: baseline tool {$key} field {$field} drifted; expected {$expectedDisplay}, found {$actualDisplay}\n");
                $failures++;
            }
        }
    }
    if (str_starts_with($path, '/') || str_contains($path, '..')) {
        fwrite(STDERR, "  fail: unsafe path for {$key}: {$path}\n");
        $failures++;
        continue;
    }
    if (!preg_match('/\.(sh|php)$/', $path)) {
        fwrite(STDERR, "  fail: registered tool path must end with .sh or .php: {$path}\n");
        $failures++;
    }
    if (!preg_match('/^scripts\/(system|architecture|assets)\//', $path)) {
        fwrite(STDERR, "  fail: registered tool path must live under scripts/system, scripts/architecture, or scripts/assets: {$path}\n");
        $failures++;
    }
    if (!is_file($path)) {
        fwrite(STDERR, "  fail: registered tool missing for {$key}: {$path}\n");
        $failures++;
    } else {
        echo "  ok: {$key} -> {$path}\n";
    }
    if ($isPreferred) {
        $preferred++;
        $preferredKey = $key;
    }
}

foreach (array_keys($expectedContracts) as $expectedKey) {
    if (!isset($keys[$expectedKey])) {
        fwrite(STDERR, "  fail: expected registry key missing: {$expectedKey}\n");
        $failures++;
    }
}

if ($preferred !== 1) {
    fwrite(STDERR, "  fail: expected exactly one preferred entrypoint, found {$preferred}\n");
    $failures++;
} elseif ($preferredKey !== 'deployment_readiness') {
    fwrite(STDERR, "  fail: preferred entrypoint must be deployment_readiness, found {$preferredKey}\n");
    $failures++;
} else {
    echo "  ok: deployment_readiness is the only preferred entrypoint\n";
}

exit($failures > 0 ? 1 : 0);
PHP
  php_status=$?
  if [[ "$php_status" -ne 0 ]]; then
    failures=$((failures + 1))
  fi
fi

echo ""
echo "== System script registry drift =="
if [[ -f "$registry" ]]; then
  while IFS= read -r script_path; do
    [[ -z "$script_path" ]] && continue
    if is_registered_path "$script_path"; then
      ok "system script registered: $script_path"
    else
      fail "unregistered system script: $script_path"
    fi
  done < <(find scripts/system -maxdepth 1 -type f -name '*.sh' | sort)
fi

echo ""
echo "== Registered tool syntax =="
if [[ -f "$registry" ]]; then
  while IFS= read -r path; do
    [[ -z "$path" ]] && continue
    case "$path" in
      *.sh)
        if bash -n "$path"; then
          ok "shell syntax: $path"
        else
          fail "shell syntax failed: $path"
        fi
        ;;
      *.php)
        if php -l "$path" >"$tmp_php_lint" 2>&1; then
          ok "PHP syntax: $path"
        else
          cat "$tmp_php_lint" >&2 || true
          fail "PHP syntax failed: $path"
        fi
        rm -f "$tmp_php_lint"
        ;;
      *)
        fail "unsupported registered tool extension: $path"
        ;;
    esac
  done < <(registered_paths)
fi

echo ""
echo "== Registered shell conventions =="
if [[ -f "$registry" ]]; then
  while IFS= read -r path; do
    [[ -z "$path" ]] && continue
    first_line="$(head -n 1 "$path")"
    if [[ "$first_line" == '#!/bin/bash' ]]; then
      ok "Bash shebang: $path"
    else
      fail "registered shell tool must start with #!/bin/bash: $path"
    fi

    if grep -Fq 'set -euo pipefail' "$path"; then
      ok "strict mode: $path"
    else
      fail "registered shell tool missing set -euo pipefail: $path"
    fi
  done < <(registered_shell_paths)
fi

echo ""
echo "== Registered PHP conventions =="
if [[ -f "$registry" ]]; then
  while IFS= read -r path; do
    [[ -z "$path" ]] && continue
    first_line="$(head -n 1 "$path")"
    if [[ "$first_line" == '<?php' ]]; then
      ok "PHP opening tag: $path"
    else
      fail "registered PHP tool must start with <?php: $path"
    fi

    if grep -Fq 'declare(strict_types=1);' "$path"; then
      ok "PHP strict types: $path"
    else
      fail "registered PHP tool missing declare(strict_types=1);: $path"
    fi
  done < <(registered_php_paths)
fi

echo ""
echo "== Registered shell portability =="
if [[ -f "$registry" ]]; then
  while IFS= read -r path; do
    [[ -z "$path" ]] && continue
    if grep -nE '(^|[[:space:];])(mapfile|readarray)([[:space:];]|$)|declare[[:space:]]+-[AAn]' "$path" >"$tmp_portability" 2>/dev/null; then
      cat "$tmp_portability" >&2 || true
      fail "Bash 4-only feature found in registered shell tool: $path"
    else
      ok "Bash 3 portability: $path"
    fi
    rm -f "$tmp_portability"
  done < <(registered_shell_paths)
fi

echo ""
echo "== Registered tool governance boundary =="
if [[ -f "$registry" ]]; then
  while IFS= read -r path; do
    [[ -z "$path" ]] && continue
    if grep -nEi '(phpmyadmin|direct[ _-]*db[ _-]*(editor|admin)|uncontrolled admin shortcut|bypass (core|acl|audit|approval|snapshot|migration|ownership)|runtime source of truth|Studio drafts? as runtime|mysql[[:space:]]+-|psql[[:space:]]|sqlite3[[:space:]]|DB::|new PDO|mysqli_)' "$path" 2>/dev/null \
      | grep -vE "grep -nEi|check_text |=> 'system README (documents no-bypass governance|blocks direct DB/admin shortcuts)'" >"$tmp_governance"; then
      cat "$tmp_governance" >&2 || true
      fail "registered tool contains forbidden governance-bypass wording or direct DB/admin shortcut pattern: $path"
    else
      ok "governance boundary scan: $path"
    fi
    rm -f "$tmp_governance"
  done < <(registered_paths)
fi

echo ""
echo "== Documentation =="
check_file "scripts/system/README.md" "system scripts README"
check_file "scripts/system/deployment-readiness-portability.md" "deployment readiness portability note"
check_file "scripts/system/deployment-readiness-orchestration-contract.md" "deployment readiness orchestration contract"
check_file "scripts/architecture/README.md" "architecture gates README"
check_file "scripts/assets/README.md" "asset tools README"

check_text "scripts/system/README.md" "scripts/system/tools.registry.json" "system README lists tools registry"
check_text "scripts/system/README.md" "scripts/system/deployment-readiness-portability.md" "system README links deployment readiness portability note"
check_text "scripts/system/README.md" "scripts/system/deployment-readiness-orchestration-contract.md" "system README links deployment readiness orchestration contract"
check_text "scripts/system/README.md" "System Tools are governed maintenance/validation/diagnostic workers, not owners." "system README documents System Tools as governed non-owners"
check_text "scripts/system/README.md" "System Tools must not bypass Core, ACL, ownership boundaries, approvals, snapshots, migrations, or audit policy." "system README documents no-bypass governance"
check_text "scripts/system/README.md" "System Tools must not become phpMyAdmin-style direct DB editors or uncontrolled admin shortcuts." "system README blocks direct DB/admin shortcuts"
check_text "scripts/system/README.md" "Runtime must not consume temporary System Tool working state as source of truth." "system README blocks temporary System Tool runtime truth"
check_text "scripts/system/README.md" "Studio authors and edits through governed workflows; System Tools validate, maintain, diagnose, publish reproducible delivery assets, and orchestrate readiness." "system README separates Studio and System Tools"
check_text "scripts/system/deployment-readiness-portability.md" "bash scripts/architecture/run_architecture_gates.sh" "portability note documents bash architecture gate invocation"
check_text "scripts/system/deployment-readiness-orchestration-contract.md" "readiness is a governed validation orchestrator, not a runtime owner" "orchestration contract documents readiness non-ownership"
check_text "scripts/system/deployment-readiness-orchestration-contract.md" "must not bypass Core, ACL, owner contracts, Studio approval/snapshot rules, migrations, or audit policy" "orchestration contract documents no-bypass policy"
check_text "scripts/system/deployment-readiness-orchestration-contract.md" "generated CSS delivery remains a reproducible output, not source truth" "orchestration contract documents generated asset truth boundary"
check_text "docs/architecture/ODAREHUB-OS-ARCHITECTURE-CHARTER-V1.md" "System Tools maintain/validate/repair but do not bypass governance." "charter documents System Tools no-bypass law"
check_text "docs/architecture/studio-operating-contract.md" "Studio and System Tools are complementary workers with different authority; neither may bypass ownership, policy, or runtime truth contracts." "Studio contract separates Studio and System Tools"
check_text "docs/architecture/studio-change-lifecycle-apply-contract.md" "System Tools do not approve hidden bypasses." "Studio lifecycle contract blocks System Tools bypass approvals"
check_text "docs/architecture/resolved-runtime-contract-pipeline.md" "System Tools validate/rebuild/diagnose contract artifacts; System Tools do not bypass policy or ownership boundaries." "resolved runtime contract documents System Tools validation-only role"

for stage in \
  "bash scripts/system/check_system_tools_inventory.sh" \
  "bash scripts/system/check_backfill_utility_aging.sh" \
  "php -l scripts/assets/publish_registered_css.php" \
  "php scripts/assets/publish_registered_css.php --apply" \
  "bash scripts/architecture/run_architecture_gates.sh" \
  "git diff --check" \
  "git diff --quiet -- public/assets/apps"; do
  check_text "scripts/system/deployment-readiness-orchestration-contract.md" "$stage" "orchestration contract documents stage: $stage"
done

if [[ -f "$registry" ]]; then
  while IFS= read -r path; do
    [[ -z "$path" ]] && continue
    check_text "scripts/system/README.md" "$path" "system README lists registered tool: $path"
  done < <(registered_paths)

  php <<'PHP'
<?php
$readmePath = 'scripts/system/README.md';
$readme = (string) file_get_contents($readmePath);
$expectedRows = [
    '| `deployment_readiness` | `scripts/system/check_deployment_readiness.sh` | `orchestrator` | `validation` | `true` |',
    '| `deployment_readiness_portable` | `scripts/system/check_deployment_readiness_portable.php` | `orchestrator` | `validation` | `false` |',
    '| `system_tools_inventory` | `scripts/system/check_system_tools_inventory.sh` | `diagnostic` | `read_only` | `false` |',
    '| `backfill_utility_aging` | `scripts/system/check_backfill_utility_aging.sh` | `diagnostic` | `read_only` | `false` |',
    '| `recovery_point` | `scripts/system/recovery_point.php` | `orchestrator` | `guarded_apply` | `false` |',
    '| `architecture_gates` | `scripts/architecture/run_architecture_gates.sh` | `diagnostic_orchestrator` | `read_only` | `false` |',
    '| `registered_css_publisher` | `scripts/assets/publish_registered_css.php` | `asset_publisher` | `guarded_apply` | `false` |',
];
$failures = 0;
foreach ($expectedRows as $row) {
    if (str_contains($readme, $row)) {
        echo "  ok: README baseline contract row present: {$row}\n";
    } else {
        fwrite(STDERR, "  fail: README baseline contract row missing: {$row}\n");
        $failures++;
    }
}
exit($failures > 0 ? 1 : 0);
PHP
  php_status=$?
  if [[ "$php_status" -ne 0 ]]; then
    failures=$((failures + 1))
  fi
fi

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (system tool inventory gaps found)" >&2
  exit 1
fi

echo "RESULT: PASS"
