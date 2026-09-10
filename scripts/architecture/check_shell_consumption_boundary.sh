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

# Collect Shell PHP source files (runtime code)
shell_php_files=()
while IFS= read -r file; do
  shell_php_files+=("$file")
done < <(find apps/Shell -type f -name '*.php' -not -path '*/Tests/*' -not -path '*/tests/*' -print 2>/dev/null)

# Collect Shell layout/view files under public/views/layouts/
shell_layout_files=()
while IFS= read -r file; do
  shell_layout_files+=("$file")
done < <(find public/views/layouts -type f -name '*.php' -print 2>/dev/null)

# Combined Shell runtime files
all_shell_files=("${shell_php_files[@]}" "${shell_layout_files[@]}")

# Shell files that could implement the planned style-consumption surface.
style_consumption_files=()
while IFS= read -r file; do
  style_consumption_files+=("$file")
done < <(find apps/Shell/DesignSystem -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' \) -print 2>/dev/null)

runtime_theme_option_files=(
  "apps/Shell/Services/ThemePreferenceService.php"
  "public/views/layouts/header.php"
  "public/views/layouts/auth_header.php"
)

# is_comment_line: check if a given line number in a file is a PHP comment
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
  tmp_all="$(mktemp /tmp/scb-all-XXXXXX)"
  local tmp_nc
  tmp_nc="$(mktemp /tmp/scb-nc-XXXXXX)"

  if ! "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$pattern" "${targets[@]}" > "$tmp_all" 2>/dev/null; then
    ok "$label"
    rm -f "$tmp_all" "$tmp_nc"
    return
  fi

  # Filter out matches on PHP comment lines
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

echo "[architecture] check_shell_consumption_boundary"
echo "- read-only Shell consumption boundary diagnostics"

echo ""
echo "== Required contract existence =="
check_required_path "docs/architecture/resolved-style-consumer-contract.md" "ResolvedStyleConsumer Contract"
check_required_path "docs/architecture/registry-read-contract.md" "Registry Read Contract"
check_required_path "docs/architecture/resolved-style-consumer-reader-injection-plan.md" "Reader Injection Plan"
check_required_path "docs/architecture/shell-consumption-contract.md" "Shell Consumption Contract"
check_required_path "apps/Shell/Resources/published-theme-options.json" "published runtime theme options contract"

if php -r '
  $data = json_decode((string)file_get_contents($argv[1]), true);
  if (!is_array($data)
      || ($data["schema"] ?? "") !== "odarehub.shell.published-theme-options.v1"
      || !is_array($data["styles"] ?? null)
      || ($data["styles"] ?? []) === []
  ) {
      exit(1);
  }
' "apps/Shell/Resources/published-theme-options.json"; then
  ok "published runtime theme options contract is valid and non-empty"
else
  fail "published runtime theme options contract must be valid and non-empty"
fi

echo ""
echo "== Shell must not import Platform Style consumer directly =="
if [[ "${#all_shell_files[@]}" -gt 0 ]]; then
  check_no_matches \
    "Shell must not import Platform\\Style\\ResolvedStyleConsumer" \
    'use Platform\\Style\\ResolvedStyleConsumer' \
    "${all_shell_files[@]}"

  check_no_matches \
    "Shell must not import Platform\\Style\\Contracts" \
    'use Platform\\Style\\Contracts' \
    "${all_shell_files[@]}"

  check_no_matches \
    "Shell must not import Platform\\Style\\Adapters" \
    'use Platform\\Style\\Adapters' \
    "${all_shell_files[@]}"

  check_no_matches \
    "Shell must not import Apps\\Platform\\StyleRegistry" \
    'use Apps\\Platform\\StyleRegistry' \
    "${all_shell_files[@]}"
else
  warn "no Shell files to scan for Platform imports"
fi

echo ""
echo "== Shell must not call registry read methods =="
if [[ "${#all_shell_files[@]}" -gt 0 ]]; then
  check_no_matches \
    "Shell must not call getValue()" \
    'getValue\s*\(' \
    "${all_shell_files[@]}"

  check_no_matches \
    "Shell must not call readValue()" \
    'readValue\s*\(' \
    "${all_shell_files[@]}"

  check_no_matches \
    "Shell must not call isReachable()" \
    'isReachable\s*\(' \
    "${all_shell_files[@]}"
else
  warn "no Shell files to scan for registry read calls"
fi

echo ""
echo "== Shell must not access registry storage paths =="
if [[ "${#all_shell_files[@]}" -gt 0 ]]; then
  check_no_matches \
    "Shell must not reference storage/platform/style-registry" \
    'storage/platform/style-registry' \
    "${all_shell_files[@]}"

  check_no_matches \
    "Shell must not reference approved-values" \
    'approved-values' \
    "${all_shell_files[@]}"
else
  warn "no Shell files to scan for storage paths"
fi

echo ""
echo "== Runtime theme options must use the published contract =="
if [[ "${#all_shell_files[@]}" -gt 0 ]]; then
  check_no_matches \
    "runtime theme option consumers must not scan source theme paths" \
    'resources/themes|theme-manifest\.json|RecursiveDirectoryIterator|RecursiveIteratorIterator' \
    "${runtime_theme_option_files[@]}"

  check_no_matches \
    "Shell must not mutate theme.css or source theme paths" \
    '(file_put_contents|fwrite|fopen|mkdir|unlink|rename|copy)[^\n]*(theme\.css|resources/themes)|(theme\.css|resources/themes)[^\n]*(file_put_contents|fwrite|fopen|mkdir|unlink|rename|copy)' \
    "${all_shell_files[@]}"
else
  warn "no Shell files to scan for runtime theme option boundaries"
fi

echo ""
echo "== Style consumption surface must remain side-effect free =="
if [[ "${#style_consumption_files[@]}" -gt 0 ]]; then
  check_no_matches \
    "style consumption files must not call file_put_contents, fwrite, mkdir, unlink, rename" \
    'file_put_contents\s*\(|fwrite\s*\(|mkdir\s*\(|unlink\s*\(|rename\s*\(' \
    "${style_consumption_files[@]}"

  check_no_matches \
    "style consumption files must not call exec, shell_exec, proc_open, system, passthru" \
    'exec\s*\(|shell_exec\s*\(|proc_open\s*\(|system\s*\(|passthru\s*\(' \
    "${style_consumption_files[@]}"

  check_no_matches \
    "style consumption files must not use database connections" \
    'PDO|mysqli\s*::|new\s+mysqli' \
    "${style_consumption_files[@]}"

  check_no_matches \
    "style consumption files must not make HTTP calls" \
    'curl_\w+\s*\(|file_get_contents\s*\(\s*['\''"]https?://|stream_context_create' \
    "${style_consumption_files[@]}"

  check_no_matches \
    "style consumption files must not register routes" \
    'Route::' \
    "${style_consumption_files[@]}"
else
  warn "no Shell style consumption files to scan for side effects"
fi

echo "  note: Shell upload/import storage capabilities are outside this style-consumption gate"

echo ""
echo "== Future allowed shape (documented, not implemented) =="
echo "  note: Shell should eventually import only Platform Style Consumption Surface"
echo "  note: consumption surface must provide typed accessors per socket"
echo "  note: Shell must not import consumer, adapter, contract, or registry directly"

echo ""
echo "== Invariant summary =="
echo "  passes: $passes"
echo "  warnings: $warnings"
echo "  failures: $failures"

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (Shell consumption boundary violations found)" >&2
  exit 1
fi

if [[ "$warnings" -gt 0 ]]; then
  echo "RESULT: PASS WITH WARNINGS ($warnings warning(s))"
  exit 0
fi

echo "RESULT: PASS ($passes invariant(s) checked)"
