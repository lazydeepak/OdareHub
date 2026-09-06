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

runtime_dir="platform/Style/Runtime"
runtime_contract="docs/architecture/runtime-style-application-contract.md"
allowed_runtime_file="$runtime_dir/RuntimeStyleApplication.php"
invariant_groups=11

ok() {
  echo "  ok: $1"
  passes=$((passes + 1))
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

check_optional_path() {
  local path="$1"
  local label="$2"
  if [[ -e "$path" ]]; then
    ok "$label ($path)"
    return 0
  else
    ok "$label absent; valid before implementation ($path)"
    return 1
  fi
}

is_comment_line() {
  local file="$1"
  local line="$2"
  local content
  content="$(sed -n "${line}p" "$file" 2>/dev/null || true)"
  [[ -z "$content" ]] && return 0
  local trimmed
  trimmed="$(echo "$content" | sed 's/^[[:space:]]*//')"
  case "$trimmed" in
    \*\/*)  return 0 ;;
    \**)    return 0 ;;
    \/\/*)  return 0 ;;
    \#*)    return 0 ;;
    \/\**)  return 0 ;;
  esac
  return 1
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
  local tmp_all
  tmp_all="$(mktemp /tmp/rsa-all-XXXXXX)"
  local tmp_nc
  tmp_nc="$(mktemp /tmp/rsa-nc-XXXXXX)"
  if ! "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$pattern" "${targets[@]}" > "$tmp_all" 2>/dev/null; then
    ok "$label"
    rm -f "$tmp_all" "$tmp_nc"
    return
  fi
  while IFS='' read -r match_line; do
    local file_part=""
    local line_num=""
    case "$SEARCH_TOOL" in
      *rg)
        file_part="$(echo "$match_line" | cut -d: -f1)"
        line_num="$(echo "$match_line" | cut -d: -f2)"
        ;;
      *)
        file_part="$(echo "$match_line" | cut -d: -f1)"
        line_num="$(echo "$match_line" | cut -d: -f2)"
        ;;
    esac
    if [[ -n "$file_part" && -n "$line_num" ]]; then
      if ! is_comment_line "$file_part" "$line_num"; then
        echo "$match_line" >> "$tmp_nc"
      fi
    else
      echo "$match_line" >> "$tmp_nc"
    fi
  done < "$tmp_all"
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
  if [[ ! -f "$file" ]]; then
    warn "$label (file not found)"
    return
  fi
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

echo "[architecture] check_runtime_style_application_boundary"
echo "- read-only Runtime Style Application boundary diagnostics"

echo ""
echo "== Required contract existence =="
check_required_path "$runtime_contract" "Runtime Style Application Contract"

echo ""
echo "== Implementation directory (optional — absent is valid pre-implementation) =="
if check_optional_path "$runtime_dir" "Runtime Style Application implementation directory"; then
  runtime_files=()
  while IFS= read -r file; do
    runtime_files+=("$file")
  done < <(find "$runtime_dir" -type f -print 2>/dev/null)

  for f in "${runtime_files[@]}"; do
    if [[ "$f" != "$allowed_runtime_file" ]]; then
      fail "unexpected file in runtime style application directory: $f"
    fi
  done
  if [[ "${#runtime_files[@]}" -gt 0 ]]; then
    ok "only allowed runtime style application files present"
  fi
fi
runtime_implementation_exists=false
if [[ -f "$allowed_runtime_file" ]]; then
  runtime_implementation_exists=true
fi

echo ""
echo "== Allowed scope (if implementation exists) =="
if [[ "$runtime_implementation_exists" == true ]] && [[ -f "$allowed_runtime_file" ]]; then
  php_contract_audit="$(
    php <<'PHP'
<?php
$path = 'platform/Style/Runtime/RuntimeStyleApplication.php';
$source = file_get_contents($path);
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
    if ($classDepth !== null && $depth === $classDepth && in_array($token[0], [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) {
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
$imports = $importMatches[1] ?? [];
sort($imports);

$constructorExact = preg_match(
    '/public\s+function\s+__construct\s*\(\s*private\s+readonly\s+StyleConsumptionSurface\s+\$surface\s*,?\s*\)/s',
    $source
) === 1;

preg_match(
    '/public\s+function\s+cornerRadius\s*\(\s*\)\s*:\s*string\s*\{(.*?)public\s+function\s+diagnostics/s',
    $source,
    $cornerRadiusMatch
);
$cornerRadiusBody = $cornerRadiusMatch[1] ?? '';
$mappingExact =
    substr_count($cornerRadiusBody, '=>') === 4
    && preg_match("/'sharp'\s*=>\s*'4px'/", $cornerRadiusBody) === 1
    && preg_match("/'soft'\s*=>\s*'8px'/", $cornerRadiusBody) === 1
    && preg_match("/'round'\s*=>\s*'16px'/", $cornerRadiusBody) === 1
    && preg_match('/default\s*=>\s*self::FALLBACK_VALUE/', $cornerRadiusBody) === 1
    && preg_match("/private\s+const\s+FALLBACK_VALUE\s*=\s*'8px'\s*;/", $source) === 1;

preg_match(
    '/public\s+function\s+isRuntimeApplicationEnabled\s*\(\s*\)\s*:\s*bool\s*\{(.*?)\}\s*\}/s',
    $source,
    $runtimeFlagMatch
);
$runtimeFlagBody = trim($runtimeFlagMatch[1] ?? '');
$runtimeDisabled =
    preg_match('/^return\s+false\s*;$/', $runtimeFlagBody) === 1
    && preg_match('/runtime_application_enabled[^\n]*=>\s*true/i', $source) !== 1
    && preg_match('/application_enabled[^\n]*=\s*true/i', $source) !== 1;

$placeholderDiagnostics =
    preg_match('/RSC-S\d{3}/', $source) !== 1
    && preg_match('/RSA-P001/', $source) === 1
    && preg_match('/RSA-P002/', $source) === 1
    && preg_match('/RSA-W001/', $source) === 1
    && preg_match('/RSA-W002/', $source) === 1;

echo 'public_methods=' . implode(',', $publicMethods) . PHP_EOL;
echo 'imports=' . implode(',', $imports) . PHP_EOL;
echo 'constructor_exact=' . ($constructorExact ? 'yes' : 'no') . PHP_EOL;
echo 'mapping_exact=' . ($mappingExact ? 'yes' : 'no') . PHP_EOL;
echo 'runtime_disabled=' . ($runtimeDisabled ? 'yes' : 'no') . PHP_EOL;
echo 'placeholder_diagnostics=' . ($placeholderDiagnostics ? 'yes' : 'no') . PHP_EOL;
PHP
  )"

  audit_value() {
    local key="$1"
    printf '%s\n' "$php_contract_audit" | sed -n "s/^${key}=//p"
  }

  echo ""
  echo "== Exact public API =="
  check_exact_value \
    "public API is constructor plus cornerRadius(), diagnostics(), isRuntimeApplicationEnabled()" \
    "$(audit_value public_methods)" \
    "__construct,cornerRadius,diagnostics,isRuntimeApplicationEnabled"
  check_has_match "cornerRadius() public method present" 'public\s+function\s+cornerRadius\s*\(' "$allowed_runtime_file"
  check_has_match "diagnostics() public method present" 'public\s+function\s+diagnostics\s*\(' "$allowed_runtime_file"
  check_has_match "isRuntimeApplicationEnabled() public method present" 'public\s+function\s+isRuntimeApplicationEnabled\s*\(' "$allowed_runtime_file"

  echo ""
  echo "== Exact dependency and constructor =="
  check_exact_value \
    "only import is Platform\\Style\\Consumption\\StyleConsumptionSurface" \
    "$(audit_value imports)" \
    "Platform\\Style\\Consumption\\StyleConsumptionSurface"
  check_exact_value \
    "constructor accepts only readonly StyleConsumptionSurface" \
    "$(audit_value constructor_exact)" \
    "yes"

  check_has_match "radius.scale scope" 'radius\.scale' "$allowed_runtime_file"
  check_has_match "sharp value" 'sharp' "$allowed_runtime_file"
  check_has_match "soft value" 'soft' "$allowed_runtime_file"
  check_has_match "round value" 'round' "$allowed_runtime_file"
  check_has_match "--corner-radius target" '--corner-radius' "$allowed_runtime_file"

  check_no_matches \
    "must not contain generic token engine" \
    'token.?engine|generic.*token|TokenEngine' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not contain arbitrary CSS mapping" \
    'arbitrary.*CSS|css.?map|CssMap|function\s+(mapCss|mapProperty|mapToken|resolveToken)\s*\(' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not contain style map/bundle application" \
    'styleMap|styleBundle|StyleMap|StyleBundle|style_map|style_bundle' \
    "$allowed_runtime_file"

  check_exact_value \
    "mapping is exactly sharp=>4px, soft=>8px, round=>16px with 8px fallback" \
    "$(audit_value mapping_exact)" \
    "yes"
else
  ok "implementation absent; allowed scope checks deferred"
fi

echo ""
echo "== Dependency rules (if implementation exists) =="
if [[ "$runtime_implementation_exists" == true ]] && [[ -f "$allowed_runtime_file" ]]; then
  if grep -q 'use Platform\\Style\\Consumption\\StyleConsumptionSurface' "$allowed_runtime_file" 2>/dev/null; then
    ok "may depend on Platform\\Style\\Consumption\\StyleConsumptionSurface (allowed)"
  else
    warn "does not import StyleConsumptionSurface (optional — may be injected)"
  fi

  check_no_matches \
    "must not import ResolvedStyleConsumer" \
    'ResolvedStyleConsumer' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not import ApprovedStyleReaderContract" \
    'ApprovedStyleReaderContract' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not import ApprovedStyleReaderAdapter" \
    'ApprovedStyleReaderAdapter' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not import ApprovedStyleRegistry" \
    'ApprovedStyleRegistry|Apps\\Platform\\StyleRegistry' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not reference storage/platform/style-registry" \
    'storage/platform/style-registry' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not reference approved-values" \
    'approved-values' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not import Apps\\Studio" \
    'Apps\\Studio|apps/Studio' \
    "$allowed_runtime_file"
else
  ok "implementation absent; dependency checks deferred"
fi

echo ""
echo "== Forbidden operations (if implementation exists) =="
if [[ "$runtime_implementation_exists" == true ]] && [[ -f "$allowed_runtime_file" ]]; then
  check_no_matches \
    "must not perform filesystem writes" \
    'file_put_contents\s*\(|fwrite\s*\(|mkdir\s*\(|unlink\s*\(|rename\s*\(' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not call shell commands" \
    'exec\s*\(|shell_exec\s*\(|proc_open\s*\(|system\s*\(|passthru\s*\(' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not use database connections" \
    'PDO|mysqli\s*::|new\s+mysqli|mysqli_' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not make HTTP calls" \
    'curl(_|\b)|stream_context_create' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not register or reference routes" \
    'Route::|routes\.php' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not reference resources/themes" \
    'resources/themes' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not reference theme.css" \
    'theme\.css' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not reference public/assets" \
    'public/assets' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not reference apps/Shell/styles" \
    'apps/Shell/styles' \
    "$allowed_runtime_file"
else
  ok "implementation absent; forbidden operation checks deferred"
fi

echo ""
echo "== Runtime enablement and Shell wiring blocked =="
shell_runtime_targets=()
while IFS= read -r file; do
  shell_runtime_targets+=("$file")
done < <(find apps/Shell public/views/layouts -type f -name '*.php' -print 2>/dev/null)

check_no_matches \
  "Shell and layout PHP must not import or reference RuntimeStyleApplication" \
  'Platform\\Style\\Runtime|RuntimeStyleApplication' \
  "${shell_runtime_targets[@]}"

if [[ "$runtime_implementation_exists" == true ]] && [[ -f "$allowed_runtime_file" ]]; then
  check_exact_value \
    "isRuntimeApplicationEnabled() returns only false and enabled flags are not true" \
    "$(audit_value runtime_disabled)" \
    "yes"

  check_no_matches \
    "must not return true or set runtime/application enabled flags true" \
    'return\s+true\s*;|runtime[_A-Za-z]*enabled['\''"]?\s*=>\s*true|application[_A-Za-z]*enabled\s*=\s*true' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not import Apps\\Shell" \
    'use Apps\\Shell' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not reference ShellRuntime" \
    'ShellRuntime|ShellRuntimeService' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not reference ShellComposer" \
    'ShellComposer|SurfaceComposer' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not reference header.php" \
    'header\.php' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not reference operator surface" \
    'operator\.css|operator-surface|operator_surface|OperatorSurface' \
    "$allowed_runtime_file"

  check_no_matches \
    "must not reference admin layout" \
    'admin.?layout|admin_layout|AdminLayout|layout-shell' \
    "$allowed_runtime_file"
else
  ok "implementation absent; Platform-side Shell coupling checks deferred"
fi

echo ""
echo "== Positive expectations (if implementation exists) =="
if [[ "$runtime_implementation_exists" == true ]] && [[ -f "$allowed_runtime_file" ]]; then
  check_has_match "namespace Platform\\Style\\Runtime" 'namespace Platform\\Style\\Runtime' "$allowed_runtime_file"
  check_has_match "final class RuntimeStyleApplication" 'final class RuntimeStyleApplication' "$allowed_runtime_file"
  check_has_match "StyleConsumptionSurface" 'StyleConsumptionSurface' "$allowed_runtime_file"
else
  ok "implementation absent; positive expectation checks deferred"
fi

echo ""
echo "== Diagnostics semantics (if implementation exists) =="
if [[ "$runtime_implementation_exists" == true ]] && [[ -f "$allowed_runtime_file" ]]; then
  check_exact_value \
    "disabled skeleton uses RSA placeholder diagnostics and reserves RSC-S codes" \
    "$(audit_value placeholder_diagnostics)" \
    "yes"
  check_no_matches \
    "disabled skeleton must not emit active RSC-S diagnostics" \
    'RSC-S[0-9]{3}' \
    "$allowed_runtime_file"
  check_has_match "RSA-P001 skeleton diagnostic" 'RSA-P001' "$allowed_runtime_file"
  check_has_match "RSA-P002 mapping diagnostic" 'RSA-P002' "$allowed_runtime_file"
  check_has_match "RSA-W001 disabled diagnostic" 'RSA-W001' "$allowed_runtime_file"
  check_has_match "RSA-W002 identifier diagnostic" 'RSA-W002' "$allowed_runtime_file"
else
  ok "implementation absent; diagnostics expectation checks deferred"
fi

echo ""
echo "== Shell coordination note =="
echo "  note: this gate does not wire Shell runtime or enable consumption"
echo "  note: implementation is Platform-owned under platform/Style/Runtime/"
echo "  note: Shell wiring requires explicit authorization in a later Shell integration slice"
echo "  note: see docs/architecture/runtime-style-application-contract.md for the full pipeline definition"

echo ""
echo "== Invariant summary =="
echo "  groups: $invariant_groups"
echo "  passes: $passes"
echo "  warnings: $warnings"
echo "  failures: $failures"

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (Runtime Style Application boundary violations found)" >&2
  exit 1
fi

if [[ "$warnings" -gt 0 ]]; then
  echo "RESULT: PASS WITH WARNINGS ($warnings warning(s))"
  exit 0
fi

echo "RESULT: PASS ($passes invariant(s) checked)"
