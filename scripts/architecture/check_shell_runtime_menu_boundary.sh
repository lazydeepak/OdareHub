#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_shell_runtime_menu_boundary"
echo "- enforce Shell-owned runtime menu composition boundary"

header_file="public/views/layouts/header.php"
composer_file="apps/Shell/Services/ShellRuntimeMenuComposer.php"

require_file() {
  local path="$1"
  if [[ -f "$path" ]]; then
    echo "  ok: found $path"
  else
    echo "  fail: missing required file $path" >&2
    exit 1
  fi
}

require_text() {
  local path="$1"
  local needle="$2"
  local label="$3"

  if grep -Fq -- "$needle" "$path"; then
    echo "  ok: $label"
  else
    echo "  fail: $label" >&2
    exit 1
  fi
}

forbid_text() {
  local path="$1"
  local needle="$2"
  local label="$3"

  if grep -Fq -- "$needle" "$path"; then
    echo "  fail: $label" >&2
    exit 1
  else
    echo "  ok: $label"
  fi
}

require_file "$header_file"
require_file "$composer_file"

echo ""
echo "== Header boundary =="
require_text "$header_file" "'/apps/Shell/Services/ShellRuntimeMenuComposer.php'" "header requires Shell runtime composer file"
require_text "$header_file" "class_exists('\\Apps\\Shell\\Services\\ShellRuntimeMenuComposer')" "header checks Shell runtime composer class"
require_text "$header_file" "\\Apps\\Shell\\Services\\ShellRuntimeMenuComposer::compose([" "header composes menu via Shell runtime composer"
forbid_text "$header_file" "class_exists('\\App\\Core\\SidebarBuilder')" "header does not directly invoke Core SidebarBuilder"
forbid_text "$header_file" "\\App\\Core\\SidebarBuilder::build([" "header does not call Core SidebarBuilder"

echo ""
echo "== Composer compatibility bridge =="
require_text "$composer_file" "\\App\\Core\\SidebarBuilder" "Shell composer declares compatibility bridge to Core SidebarBuilder"
require_text "$composer_file" "'compatibility_builder'" "Shell composer emits compatibility metadata"

echo ""
echo "RESULT: PASS"
