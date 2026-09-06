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
invariant_groups=10

contract="docs/architecture/rendered-admin-proof-planning-contract.md"
harness_dir="scripts/platform/rendered-admin-proof"
proof_dir="platform/Style/Proof"
allowed_harness_file="$harness_dir/rendered_admin_proof.php"
allowed_proof_file="$proof_dir/RenderedAdminProof.php"
runtime_file="platform/Style/Runtime/RuntimeStyleApplication.php"
insertion_file="platform/Style/ShellInsertion/ShellInsertion.php"

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

  ok "$label absent; valid before proof implementation ($path)"
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
  tmp_all="$(mktemp /tmp/rap-all-XXXXXX)"
  tmp_nc="$(mktemp /tmp/rap-nc-XXXXXX)"

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

audit_disabled_method() {
  local file="$1"
  local method="$2"

  php -r '
    $source = file_get_contents($argv[1]);
    $method = preg_quote($argv[2], "/");
    preg_match(
        "/public\\s+function\\s+{$method}\\s*\\(\\s*\\)\\s*:\\s*bool\\s*\\{(.*?)\\}/s",
        $source,
        $match
    );
    echo preg_match("/^return\\s+false\\s*;$/", trim($match[1] ?? "")) === 1
        ? "disabled"
        : "enabled-or-ambiguous";
  ' "$file" "$method"
}

echo "[architecture] check_rendered_admin_proof_boundary"
echo "- read-only rendered admin proof boundary diagnostics"

echo ""
echo "== 1. Required planning contract =="
check_required_path "$contract" "Rendered Admin Proof Planning Contract"

echo ""
echo "== 2. Optional proof directories =="
proof_exists=false
proof_targets=()

if check_optional_path "$proof_dir" "Platform proof directory"; then
  while IFS= read -r file; do
    proof_targets+=("$file")
    if [[ "$file" != "$allowed_proof_file" ]]; then
      fail "unexpected file in Platform proof directory: $file"
    fi
  done < <(find "$proof_dir" -type f -print 2>/dev/null)
fi

if check_optional_path "$harness_dir" "Rendered proof harness directory"; then
  while IFS= read -r file; do
    proof_targets+=("$file")
    if [[ "$file" != "$allowed_harness_file" ]]; then
      fail "unexpected file in rendered proof harness directory: $file"
    fi
  done < <(find "$harness_dir" -type f -print 2>/dev/null)
fi

if [[ -f "$allowed_proof_file" || -f "$allowed_harness_file" ]]; then
  proof_exists=true
fi

if [[ "$proof_exists" == true ]]; then
  if [[ -f "$allowed_proof_file" && -f "$allowed_harness_file" ]]; then
    ok "both allowlisted proof artifacts are present"
  else
    fail "proof implementation must contain both allowlisted artifacts"
  fi
else
  ok "proof artifacts absent; valid before implementation"
fi

echo ""
echo "== 3. Exact future proof scope =="
if [[ "$proof_exists" == true ]]; then
  check_has_match "namespace Platform\\Style\\Proof" 'namespace Platform\\Style\\Proof' "$allowed_proof_file"
  check_has_match "RenderedAdminProof class" 'final\s+class\s+RenderedAdminProof' "$allowed_proof_file"
  check_has_match "admin scope" '\badmin\b' "$allowed_proof_file"
  check_has_match "layout-main target" 'layout-main' "$allowed_proof_file"
  check_has_match "radius.scale socket" 'radius\.scale' "$allowed_proof_file"
  check_has_match "--corner-radius property" '--corner-radius' "$allowed_proof_file"
  check_has_match "sharp identifier" '\bsharp\b' "$allowed_proof_file"
  check_has_match "soft identifier" '\bsoft\b' "$allowed_proof_file"
  check_has_match "round identifier" '\bround\b' "$allowed_proof_file"
  check_has_match "8px fallback" '\b8px\b' "$allowed_proof_file"
  check_has_match "RuntimeStyleApplication dependency" 'RuntimeStyleApplication' "$allowed_proof_file"
  check_has_match "ShellInsertion dependency" 'ShellInsertion' "$allowed_proof_file"

  scope_audit="$(
    php <<'PHP'
<?php
$source = file_get_contents('platform/Style/Proof/RenderedAdminProof.php')
    . "\n"
    . file_get_contents('scripts/platform/rendered-admin-proof/rendered_admin_proof.php');
preg_match_all('/--[a-z0-9-]+/i', $source, $propertyMatches);
$properties = array_values(array_unique($propertyMatches[0] ?? []));
sort($properties);
preg_match_all('/\b[0-9]+px\b/', $source, $valueMatches);
$values = array_values(array_unique($valueMatches[0] ?? []));
sort($values, SORT_NATURAL);
preg_match_all('/\b[a-z][a-z0-9_-]*\.[a-z][a-z0-9_.-]*\b/i', $source, $socketMatches);
$sockets = array_values(array_unique($socketMatches[0] ?? []));
sort($sockets);
echo 'properties=' . implode(',', $properties) . PHP_EOL;
echo 'values=' . implode(',', $values) . PHP_EOL;
echo 'sockets=' . implode(',', $sockets) . PHP_EOL;
PHP
  )"

  scope_value() {
    local key="$1"
    printf '%s\n' "$scope_audit" | sed -n "s/^${key}=//p"
  }

  [[ "$(scope_value properties)" == "--corner-radius" ]] \
    && ok "only CSS custom property is --corner-radius" \
    || fail "proof contains additional CSS custom properties"
  [[ "$(scope_value values)" == "4px,8px,16px" ]] \
    && ok "only concrete CSS values are 4px, 8px, and 16px" \
    || fail "proof contains missing or additional concrete CSS values"
  [[ "$(scope_value sockets)" == "radius.scale" ]] \
    && ok "only socket is radius.scale" \
    || fail "proof contains missing or additional sockets"
else
  ok "proof absent; exact scope checks deferred"
fi

echo ""
echo "== 4. Scope expansion blocked =="
if [[ "$proof_exists" == true ]]; then
  check_no_matches \
    "operator, display, workspace, navigation, and topbar expansion remains blocked" \
    'operator|display|workspace[-_]?surface|navigation[-_]?surface|topbar[-_]?surface' \
    "${proof_targets[@]}"
  check_no_matches \
    "generic token/style/CSS maps remain blocked" \
    'tokenMap|styleMap|cssMap|TokenMap|StyleMap|CssMap|generic.*css|generic.*variable|multiple.*socket|multiple.*value' \
    "${proof_targets[@]}"
else
  ok "proof absent; expansion checks deferred"
fi

echo ""
echo "== 5. Forbidden dependencies blocked =="
if [[ "$proof_exists" == true ]]; then
  if [[ -f "$allowed_proof_file" ]]; then
    check_no_matches \
      "proof object must not import registry, reader, adapter, or consumer internals" \
      'ApprovedStyleRegistry|ApprovedStyleReaderContract|ApprovedStyleReaderAdapter|ResolvedStyleConsumer' \
      "$allowed_proof_file"
  fi
  if [[ -f "$allowed_harness_file" ]]; then
    check_no_matches \
      "harness must not import registry, reader, or adapter internals" \
      'ApprovedStyleRegistry|ApprovedStyleReaderContract|ApprovedStyleReaderAdapter' \
      "$allowed_harness_file"
  fi
  check_no_matches \
    "must not reference direct registry storage" \
    'storage/platform/style-registry|approved-values' \
    "${proof_targets[@]}"
  check_no_matches \
    "must not import Studio or Shell owners" \
    'Apps\\Studio|Apps\\Shell' \
    "${proof_targets[@]}"
else
  ok "proof absent; dependency checks deferred"
fi

echo ""
echo "== 6. Mutations blocked =="
if [[ "$proof_exists" == true ]]; then
  check_no_matches \
    "must not reference CSS, theme, asset, or Shell style mutation paths" \
    'resources/themes|theme\.css|public/assets|apps/Shell/styles' \
    "${proof_targets[@]}"
  check_no_matches \
    "must not perform filesystem writes" \
    'file_put_contents\s*\(|fwrite\s*\(|mkdir\s*\(|unlink\s*\(|rename\s*\(' \
    "${proof_targets[@]}"
else
  ok "proof absent; mutation checks deferred"
fi

echo ""
echo "== 7. Side effects blocked =="
if [[ "$proof_exists" == true ]]; then
  check_no_matches \
    "must not execute commands" \
    'exec\s*\(|shell_exec\s*\(|proc_open\s*\(|system\s*\(' \
    "${proof_targets[@]}"
  check_no_matches \
    "must not access databases" \
    '\bPDO\b|mysqli\s*::|new\s+mysqli|mysqli_' \
    "${proof_targets[@]}"
  check_no_matches \
    "must not make HTTP calls" \
    'curl(_|\b)' \
    "${proof_targets[@]}"
  check_no_matches \
    "must not register or reference routes" \
    'Route::|routes\.php' \
    "${proof_targets[@]}"
else
  ok "proof absent; side-effect checks deferred"
fi

echo ""
echo "== 8. Global runtime protection =="
check_required_path "$runtime_file" "RuntimeStyleApplication skeleton"
check_required_path "$insertion_file" "ShellInsertion skeleton"

if [[ -f "$runtime_file" ]]; then
  [[ "$(audit_disabled_method "$runtime_file" isRuntimeApplicationEnabled)" == "disabled" ]] \
    && ok "RuntimeStyleApplication remains hardcoded disabled" \
    || fail "RuntimeStyleApplication is enabled or ambiguous"
fi

if [[ -f "$insertion_file" ]]; then
  [[ "$(audit_disabled_method "$insertion_file" isShellInsertionEnabled)" == "disabled" ]] \
    && ok "ShellInsertion remains hardcoded disabled" \
    || fail "ShellInsertion is enabled or ambiguous"
fi

if [[ "$proof_exists" == true ]]; then
  check_no_matches \
    "proof must not globally enable runtime or Shell insertion" \
    'isRuntimeApplicationEnabled\s*\([^)]*\)\s*(===|==)?\s*true|isShellInsertionEnabled\s*\([^)]*\)\s*(===|==)?\s*true|runtime[_A-Za-z]*enabled[^[:space:]]*\s*(=>|=)\s*true|insertion[_A-Za-z]*enabled[^[:space:]]*\s*(=>|=)\s*true' \
    "${proof_targets[@]}"
else
  ok "proof absent; proof-side enablement checks deferred"
fi

echo ""
echo "== 9. Diagnostics expectations =="
if [[ "$proof_exists" == true ]]; then
  check_has_match "RAP-P diagnostics" 'RAP-P[0-9]{3}' "$allowed_proof_file"
  check_has_match "RAP-W diagnostics" 'RAP-W[0-9]{3}' "$allowed_proof_file"
  check_has_match "RAP-F diagnostics" 'RAP-F[0-9]{3}' "$allowed_proof_file"
  check_has_match "RAP-E diagnostics" 'RAP-E[0-9]{3}' "$allowed_proof_file"
  check_no_matches \
    "proof must not use RSA, RSI, or RSC-S as primary diagnostics" \
    'RSA-[A-Z][0-9]{3}|RSI-[A-Z][0-9]{3}|RSC-S[0-9]{3}' \
    "${proof_targets[@]}"
else
  ok "proof absent; diagnostic checks deferred"
fi

echo ""
echo "== 10. Production boundary remains untouched =="
production_targets=()
while IFS= read -r file; do
  production_targets+=("$file")
done < <(find apps/Shell public/views/layouts -type f -name '*.php' -print 2>/dev/null)

check_no_matches \
  "Shell and layout PHP must not reference RenderedAdminProof or Platform proof namespace" \
  'RenderedAdminProof|Platform\\Style\\Proof' \
  "${production_targets[@]}"

echo "  note: proof implementation remains absent and no fragment is rendered"
echo "  note: global runtime and Shell insertion flags remain false"
echo "  note: future proof is confined to CLI/test infrastructure"

echo ""
echo "== Invariant summary =="
echo "  groups: $invariant_groups"
echo "  passes: $passes"
echo "  warnings: $warnings"
echo "  failures: $failures"

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (Rendered Admin Proof boundary violations found)" >&2
  exit 1
fi

echo "RESULT: PASS ($passes invariant(s) checked)"
