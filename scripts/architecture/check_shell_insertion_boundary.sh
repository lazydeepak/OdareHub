#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

RG_BIN="${RG_BIN:-$(command -v rg || true)}"
GREP_BIN="${GREP_BIN:-$(command -v grep || true)}"

if [[ -n "$RG_BIN" ]]; then
  SEARCH_TOOL="$RG_BIN"
  SEARCH_ARGS=(-n --no-heading -S)
else
  if [[ -z "$GREP_BIN" ]]; then
    echo "missing required binary: rg or grep" >&2
    exit 2
  fi
  SEARCH_TOOL="$GREP_BIN"
  SEARCH_ARGS=(-RInE)
fi

failures=0
warnings=0
passes=0
invariant_groups=12

contract="docs/architecture/shell-insertion-planning-contract.md"
insertion_dir="platform/Style/ShellInsertion"
allowed_insertion_file="$insertion_dir/ShellInsertion.php"
runtime_file="platform/Style/Runtime/RuntimeStyleApplication.php"

ok() {
  echo "  ok: $1"
  passes=$((passes + 1))
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

check_optional_path() {
  local path="$1"
  local label="$2"

  if [[ -e "$path" ]]; then
    ok "$label ($path)"
    return 0
  fi

  ok "$label absent; valid before implementation ($path)"
  return 1
}

is_comment_line() {
  local file="$1"
  local line="$2"
  local content
  local trimmed

  content="$(sed -n "${line}p" "$file" 2>/dev/null || true)"
  [[ -z "$content" ]] && return 0
  trimmed="$(printf '%s\n' "$content" | sed 's/^[[:space:]]*//')"

  case "$trimmed" in
    \*\/*|\**|\/\/*|\#*|\/\**) return 0 ;;
  esac

  return 1
}

check_no_matches() {
  local label="$1"
  local pattern="$2"
  shift 2
  local targets=("$@")

  if [[ "${#targets[@]}" -eq 0 ]]; then
    ok "$label (no files to scan)"
    return
  fi

  local tmp_all
  local tmp_nc
  tmp_all="$(mktemp /tmp/rsi-all-XXXXXX)"
  tmp_nc="$(mktemp /tmp/rsi-nc-XXXXXX)"

  if ! "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" -- "$pattern" "${targets[@]}" >"$tmp_all" 2>/dev/null; then
    ok "$label"
    rm -f "$tmp_all" "$tmp_nc"
    return
  fi

  while IFS='' read -r match_line; do
    local file_part
    local line_num
    file_part="$(printf '%s\n' "$match_line" | cut -d: -f1)"
    line_num="$(printf '%s\n' "$match_line" | cut -d: -f2)"

    if [[ -n "$file_part" && -n "$line_num" ]] && is_comment_line "$file_part" "$line_num"; then
      continue
    fi
    printf '%s\n' "$match_line" >>"$tmp_nc"
  done <"$tmp_all"

  if [[ -s "$tmp_nc" ]]; then
    fail "$label"
    cat "$tmp_nc" >&2
  else
    ok "$label"
  fi

  rm -f "$tmp_all" "$tmp_nc"
}

check_has_match() {
  local label="$1"
  local pattern="$2"
  local file="$3"

  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" -- "$pattern" "$file" >/dev/null 2>/dev/null; then
    ok "$label"
  else
    fail "$label (expected pattern '$pattern' not found in $file)"
  fi
}

check_exact_value() {
  local label="$1"
  local actual="$2"
  local expected="$3"

  if [[ "$actual" == "$expected" ]]; then
    ok "$label"
  else
    fail "$label (expected '$expected', found '$actual')"
  fi
}

echo "[architecture] check_shell_insertion_boundary"
echo "- read-only future Shell insertion boundary diagnostics"

echo ""
echo "== 1. Required planning contract =="
check_required_path "$contract" "Shell Insertion Planning Contract"

echo ""
echo "== 2. Optional future implementation directory =="
implementation_exists=false
if check_optional_path "$insertion_dir" "Shell insertion implementation directory"; then
  insertion_files=()
  while IFS= read -r file; do
    insertion_files+=("$file")
  done < <(find "$insertion_dir" -type f -print 2>/dev/null)

  for file in "${insertion_files[@]}"; do
    if [[ "$file" != "$allowed_insertion_file" ]]; then
      fail "unexpected file in Shell insertion directory: $file"
    fi
  done

  if [[ -f "$allowed_insertion_file" ]]; then
    implementation_exists=true
    ok "only allowed future implementation file is present"
  elif [[ "${#insertion_files[@]}" -eq 0 ]]; then
    ok "implementation directory is empty; valid before skeleton implementation"
  else
    fail "required future implementation file is missing ($allowed_insertion_file)"
  fi
fi

echo ""
echo "== 3. Exact future scope =="
if [[ "$implementation_exists" == true ]]; then
  implementation_audit=$(
    php <<'PHP'
<?php
$source = file_get_contents('platform/Style/ShellInsertion/ShellInsertion.php');
$tokens = token_get_all($source);
$depth = 0;
$classDepth = null;
$visibility = null;
$publicMethods = [];

foreach ($tokens as $token) {
    if ($token === '{') {
        $depth++;
        continue;
    }
    if ($token === '}') {
        $depth--;
        continue;
    }
    if (!is_array($token)) {
        continue;
    }
    if ($token[0] === T_CLASS) {
        $classDepth = $depth + 1;
        continue;
    }
    if (
        $classDepth !== null
        && $depth === $classDepth
        && in_array($token[0], [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)
    ) {
        $visibility = $token[0];
        continue;
    }
    if ($classDepth !== null && $depth === $classDepth && $token[0] === T_FUNCTION) {
        if ($visibility === T_PUBLIC) {
            $publicMethods[] = '__PENDING__';
        }
        $visibility = null;
        continue;
    }
    if ($publicMethods !== [] && end($publicMethods) === '__PENDING__' && $token[0] === T_STRING) {
        $publicMethods[array_key_last($publicMethods)] = $token[1];
    }
}
sort($publicMethods);

preg_match_all('/^use\s+([^;]+);/m', $source, $importMatches);
$imports = array_values(array_unique($importMatches[1] ?? []));
sort($imports);
preg_match_all('/--[a-z0-9-]+/i', $source, $propertyMatches);
$properties = array_values(array_unique($propertyMatches[0] ?? []));
sort($properties);
preg_match_all('/\b[0-9]+px\b/', $source, $valueMatches);
$values = array_values(array_unique($valueMatches[0] ?? []));
sort($values, SORT_NATURAL);

$constructorExact = preg_match(
    '/public\s+function\s+__construct\s*\(\s*private\s+readonly\s+RuntimeStyleApplication\s+\$runtimeStyleApplication\s*,?\s*\)/s',
    $source
) === 1;

preg_match(
    '/public\s+function\s+adminLayoutMainStyle\s*\(\s*\)\s*:\s*array\s*\{(.*?)\}\s*public\s+function\s+diagnostics/s',
    $source,
    $styleMatch
);
$styleBody = trim($styleMatch[1] ?? '');
$emptyStyle = preg_match('/^return\s*\[\s*\]\s*;$/', $styleBody) === 1;

preg_match(
    '/public\s+function\s+isShellInsertionEnabled\s*\(\s*\)\s*:\s*bool\s*\{(.*?)\}\s*\}/s',
    $source,
    $enabledMatch
);
$enabledBody = trim($enabledMatch[1] ?? '');
$insertionDisabled = preg_match('/^return\s+false\s*;$/', $enabledBody) === 1;

preg_match_all('/["\x27]code["\x27]\s*=>\s*["\x27](RSI-[A-Z][0-9]{3})["\x27]/', $source, $codeMatches);
$diagnosticCodes = array_values(array_unique($codeMatches[1] ?? []));
sort($diagnosticCodes);
$expectedCodes = ['RSI-P001', 'RSI-P002', 'RSI-P003', 'RSI-W001', 'RSI-W002'];
$diagnosticsExact = $diagnosticCodes === $expectedCodes;

$disabledMetadata =
    preg_match('/["\x27]insertion_status["\x27]\s*=>\s*["\x27]skeleton["\x27]/', $source) === 1
    && preg_match('/["\x27]shell_insertion_enabled["\x27]\s*=>\s*\$this->isShellInsertionEnabled\s*\(\s*\)/', $source) === 1;

$activeClaim =
    preg_match('/RSC-S[0-9]{3}/', $source) === 1
    || preg_match('/["\x27]insertion_status["\x27]\s*=>\s*["\x27](active|applied|enabled)["\x27]/i', $source) === 1
    || preg_match('/["\x27]shell_insertion_enabled["\x27]\s*=>\s*true/i', $source) === 1
    || preg_match('/(successfully\s+applied|styles?\s+(is|are|was|were)?\s*applied|insertion\s+is\s+(active|enabled))/i', $source) === 1;

echo 'public_methods=' . implode(',', $publicMethods) . PHP_EOL;
echo 'imports=' . implode(',', $imports) . PHP_EOL;
echo 'properties=' . implode(',', $properties) . PHP_EOL;
echo 'values=' . implode(',', $values) . PHP_EOL;
echo 'constructor_exact=' . ($constructorExact ? 'yes' : 'no') . PHP_EOL;
echo 'empty_style=' . ($emptyStyle ? 'yes' : 'no') . PHP_EOL;
echo 'insertion_disabled=' . ($insertionDisabled ? 'yes' : 'no') . PHP_EOL;
echo 'diagnostic_codes=' . implode(',', $diagnosticCodes) . PHP_EOL;
echo 'diagnostics_exact=' . ($diagnosticsExact ? 'yes' : 'no') . PHP_EOL;
echo 'disabled_metadata=' . ($disabledMetadata ? 'yes' : 'no') . PHP_EOL;
echo 'active_claim=' . ($activeClaim ? 'yes' : 'no') . PHP_EOL;
PHP
  )

  audit_value() {
    local key="$1"
    printf '%s\n' "$implementation_audit" | sed -n "s/^${key}=//p"
  }

  check_has_match "namespace Platform\\Style\\ShellInsertion" 'namespace Platform\\Style\\ShellInsertion' "$allowed_insertion_file"
  check_has_match "admin scope marker" '\badmin\b' "$allowed_insertion_file"
  check_has_match "layout-main target" 'layout-main' "$allowed_insertion_file"
  check_has_match "--corner-radius target" '--corner-radius' "$allowed_insertion_file"
  check_has_match "radius.scale socket" 'radius\.scale' "$allowed_insertion_file"
  check_has_match "sharp identifier" '\bsharp\b' "$allowed_insertion_file"
  check_has_match "soft identifier" '\bsoft\b' "$allowed_insertion_file"
  check_has_match "round identifier" '\bround\b' "$allowed_insertion_file"
  check_has_match "RuntimeStyleApplication dependency" 'RuntimeStyleApplication' "$allowed_insertion_file"
  check_exact_value \
    "only import is Platform\\Style\\Runtime\\RuntimeStyleApplication" \
    "$(audit_value imports)" \
    "Platform\\Style\\Runtime\\RuntimeStyleApplication"
  check_exact_value \
    "only CSS custom property is --corner-radius" \
    "$(audit_value properties)" \
    "--corner-radius"
  check_exact_value \
    "only concrete CSS values are 4px, 8px, and 16px" \
    "$(audit_value values)" \
    "4px,8px,16px"

  check_no_matches \
    "must not contain generic token/style/CSS maps" \
    'tokenMap|styleMap|cssMap|TokenMap|StyleMap|CssMap|generic.*(token|style|css)|arbitrary.*CSS' \
    "$allowed_insertion_file"
else
  ok "implementation absent; exact future scope checks deferred"
fi

echo ""
echo "== 4. Exact public API and constructor dependency =="
if [[ "$implementation_exists" == true ]]; then
  check_exact_value \
    "public API is constructor plus adminLayoutMainStyle(), diagnostics(), isShellInsertionEnabled()" \
    "$(audit_value public_methods)" \
    "__construct,adminLayoutMainStyle,diagnostics,isShellInsertionEnabled"
  check_exact_value \
    "constructor accepts only readonly RuntimeStyleApplication" \
    "$(audit_value constructor_exact)" \
    "yes"
  check_has_match "adminLayoutMainStyle() public method present" 'public\s+function\s+adminLayoutMainStyle\s*\(' "$allowed_insertion_file"
  check_has_match "diagnostics() public method present" 'public\s+function\s+diagnostics\s*\(' "$allowed_insertion_file"
  check_has_match "isShellInsertionEnabled() public method present" 'public\s+function\s+isShellInsertionEnabled\s*\(' "$allowed_insertion_file"
else
  ok "implementation absent; exact API and constructor checks deferred"
fi

echo ""
echo "== 5. Disabled skeleton behavior =="
if [[ "$implementation_exists" == true ]]; then
  check_exact_value \
    "adminLayoutMainStyle() returns only an empty array" \
    "$(audit_value empty_style)" \
    "yes"
  check_exact_value \
    "isShellInsertionEnabled() returns only false" \
    "$(audit_value insertion_disabled)" \
    "yes"
  check_no_matches \
    "disabled skeleton must not return true" \
    'return\s+true\s*;' \
    "$allowed_insertion_file"
  check_no_matches \
    "disabled skeleton must not return active style output" \
    'return\s*\[[^]]*(--corner-radius|CSS_PROPERTY|cornerRadius\s*\()' \
    "$allowed_insertion_file"
else
  ok "implementation absent; disabled behavior checks deferred"
fi

echo ""
echo "== 6. Direct imports and registry paths blocked =="
if [[ "$implementation_exists" == true ]]; then
  check_no_matches \
    "must not import or reference registry, reader, adapter, or consumer internals" \
    'ApprovedStyleRegistry|ApprovedStyleReaderContract|ApprovedStyleReaderAdapter|ResolvedStyleConsumer' \
    "$allowed_insertion_file"
  check_no_matches \
    "must not reference registry storage or approved-values" \
    'storage/platform/style-registry|approved-values' \
    "$allowed_insertion_file"
  check_no_matches \
    "must not bypass RuntimeStyleApplication through StyleConsumptionSurface" \
    'StyleConsumptionSurface' \
    "$allowed_insertion_file"
  check_no_matches \
    "must not import Shell or Studio owners" \
    'Apps\\Shell|Apps\\Studio' \
    "$allowed_insertion_file"
else
  ok "implementation absent; direct import checks deferred"
fi

echo ""
echo "== 7. Shell-wide surfaces blocked =="
if [[ "$implementation_exists" == true ]]; then
  check_no_matches \
    "operator, display, workspace, navigation, and topbar surfaces remain blocked" \
    'operator|display|workspace[-_]?surface|navigation[-_]?surface|topbar[-_]?surface' \
    "$allowed_insertion_file"
else
  ok "implementation absent; blocked surface checks deferred"
fi

echo ""
echo "== 8. Mutation paths and operations blocked =="
if [[ "$implementation_exists" == true ]]; then
  check_no_matches \
    "must not reference CSS, theme, public asset, or Shell style mutation paths" \
    'resources/themes|theme\.css|public/assets|apps/Shell/styles' \
    "$allowed_insertion_file"
  check_no_matches \
    "must not perform filesystem writes" \
    'file_put_contents\s*\(|fwrite\s*\(|mkdir\s*\(|unlink\s*\(|rename\s*\(' \
    "$allowed_insertion_file"
else
  ok "implementation absent; mutation checks deferred"
fi

echo ""
echo "== 9. Runtime side effects blocked =="
if [[ "$implementation_exists" == true ]]; then
  check_no_matches \
    "must not execute commands" \
    'exec\s*\(|shell_exec\s*\(|proc_open\s*\(|system\s*\(' \
    "$allowed_insertion_file"
  check_no_matches \
    "must not access databases" \
    '\bPDO\b|mysqli\s*::|new\s+mysqli|mysqli_' \
    "$allowed_insertion_file"
  check_no_matches \
    "must not make HTTP calls" \
    'curl(_|\b)' \
    "$allowed_insertion_file"
  check_no_matches \
    "must not register or reference routes" \
    'Route::|routes\.php' \
    "$allowed_insertion_file"
  check_no_matches \
    "must not enable runtime or application flags" \
    'return\s+true\s*;|runtime[_A-Za-z]*enabled[^[:space:]]*\s*(=>|=)\s*true|application[_A-Za-z]*enabled[^[:space:]]*\s*(=>|=)\s*true' \
    "$allowed_insertion_file"
else
  ok "implementation absent; side-effect checks deferred"
fi

echo ""
echo "== 10. Runtime enablement and premature Shell wiring blocked =="
check_required_path "$runtime_file" "RuntimeStyleApplication skeleton"

if [[ -f "$runtime_file" ]]; then
  runtime_flag_audit="$(
    php -r '
      $source = file_get_contents($argv[1]);
      preg_match(
          "/public\\s+function\\s+isRuntimeApplicationEnabled\\s*\\(\\s*\\)\\s*:\\s*bool\\s*\\{(.*?)\\}/s",
          $source,
          $match
      );
      $body = trim($match[1] ?? "");
      $disabled = preg_match("/^return\\s+false\\s*;$/", $body) === 1
          && preg_match("/runtime[_A-Za-z]*enabled[^\\n]*(=>|=)\\s*true/i", $source) !== 1
          && preg_match("/application[_A-Za-z]*enabled[^\\n]*(=>|=)\\s*true/i", $source) !== 1;
      echo $disabled ? "disabled" : "enabled-or-ambiguous";
    ' "$runtime_file"
  )"

  if [[ "$runtime_flag_audit" == "disabled" ]]; then
    ok "RuntimeStyleApplication remains hardcoded disabled"
  else
    fail "RuntimeStyleApplication is enabled or its disabled state is ambiguous"
  fi
fi

shell_targets=()
while IFS= read -r file; do
  shell_targets+=("$file")
done < <(find apps/Shell public/views/layouts -type f -name '*.php' -print 2>/dev/null)

check_no_matches \
  "Shell and layout PHP must not reference RuntimeStyleApplication or ShellInsertion" \
  'Platform\\Style\\Runtime\\RuntimeStyleApplication|Platform\\Style\\ShellInsertion|RuntimeStyleApplication|ShellInsertion' \
  "${shell_targets[@]}"

check_no_matches \
  "selected admin insertion target must not bypass RuntimeStyleApplication" \
  'use\s+Platform\\Style\\(Consumption|Registry|Reader)|ApprovedStyleReaderContract|ApprovedStyleReaderAdapter|ApprovedStyleRegistry|ResolvedStyleConsumer|StyleConsumptionSurface' \
  "public/views/layouts/header.php"

echo ""
echo "== 11. Complete RSI skeleton diagnostics =="
if [[ "$implementation_exists" == true ]]; then
  check_exact_value \
    "diagnostic codes are exactly RSI-P001, RSI-P002, RSI-P003, RSI-W001, RSI-W002" \
    "$(audit_value diagnostic_codes)" \
    "RSI-P001,RSI-P002,RSI-P003,RSI-W001,RSI-W002"
  check_exact_value \
    "complete RSI skeleton diagnostic set is present" \
    "$(audit_value diagnostics_exact)" \
    "yes"
  check_exact_value \
    "diagnostic metadata identifies skeleton and uses disabled flag method" \
    "$(audit_value disabled_metadata)" \
    "yes"
  check_exact_value \
    "diagnostics make no active insertion claim" \
    "$(audit_value active_claim)" \
    "no"
  check_no_matches \
    "insertion skeleton must not emit active RSC-S diagnostics" \
    'RSC-S[0-9]{3}' \
    "$allowed_insertion_file"
else
  ok "implementation absent; RSI diagnostics checks deferred"
fi

echo ""
echo "== 12. Coordination boundary =="
echo "  note: selected future target is admin .layout-main only"
echo "  note: this gate does not modify Shell or apply runtime styles"
echo "  note: RuntimeStyleApplication remains disabled"

echo ""
echo "== Invariant summary =="
echo "  groups: $invariant_groups"
echo "  passes: $passes"
echo "  warnings: $warnings"
echo "  failures: $failures"

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (Shell insertion boundary violations found)" >&2
  exit 1
fi

echo "RESULT: PASS ($passes invariant(s) checked)"
