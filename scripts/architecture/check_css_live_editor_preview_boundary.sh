#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

failures=0

pass() {
  printf '  ok: %s\n' "$1"
}

fail() {
  printf '  fail: %s\n' "$1"
  failures=$((failures + 1))
}

require_file() {
  if [[ -f "$1" ]]; then
    pass "file exists: $1"
  else
    fail "missing file: $1"
  fi
}

if command -v rg &>/dev/null; then
  SEARCH='rg -q'
  REJECT_SEARCH='rg -n'
else
  SEARCH='grep -qE'
  REJECT_SEARCH='grep -nE'
fi

contains() {
  local pattern="$1"
  local file="$2"
  local message="$3"
  if $SEARCH "$pattern" "$file"; then
    pass "$message"
  else
    fail "$message"
  fi
}

rejects() {
  local pattern="$1"
  local path="$2"
  local message="$3"
  if $REJECT_SEARCH "$pattern" "$path" >/dev/null 2>&1; then
    fail "$message"
  else
    pass "$message"
  fi
}

echo "[architecture] check_css_live_editor_preview_boundary"
echo "- validating provider-backed, non-live CSS Live Editor preview feeds"

provider="apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/Services/CssLiveEditorTargetProvider.php"
feed_service="apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/Services/CssLiveEditorPreviewFeedService.php"
target_service="apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/Services/CssLiveEditorTargetService.php"
template_service="apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/Services/CssLiveEditorTemplateTargetService.php"
css_source_service="apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/Services/CssLiveEditorCssSourceService.php"
controller="apps/Studio/Controllers/StudioController.php"
view="apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/cssliveditor.php"
js="apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/assets/css_live_editor.js"

require_file "$provider"
require_file "$feed_service"
require_file "$target_service"
require_file "$template_service"
require_file "$css_source_service"
require_file "$controller"
require_file "$view"
require_file "$js"

contains "'data_mode' => 'static_fixture'" "$provider" "provider declares static fixture data mode"
contains "'eligible' => false" "$provider" "provider can expose unavailable targets"
contains "No owner-provided mock or sanitized preview feed" "$provider" "unavailable target includes a reason"
contains "CssLiveEditorTargetProvider::find" "$controller" "preview endpoint resolves provider target"
contains "CssLiveEditorPreviewFeedService::render" "$controller" "preview endpoint renders registered feed"
contains "form-action 'none'" "$controller" "preview response blocks form actions through CSP"
contains "data-css-live-editor-target-select" "$view" "view consumes provider target options"
contains "data-css-live-editor-template-search" "$view" "view exposes direct PHP template search"
contains "'target_type' => 'php_template'" "$template_service" "template catalog declares PHP template target type"
contains "'id' => \\\$relativePath" "$template_service" "template identity uses canonical relative path"
contains "approvedViewRoots" "$template_service" "template lookup is restricted to approved view roots"
contains "CssLiveEditorTemplateTargetService::find" "$controller" "preview endpoint revalidates template target through catalog"
contains "CssLiveEditorCssSourceService::catalog" "$controller" "controller exposes approved CSS source resolution catalog"
contains "approvedOwnerAbsolutePath" "$css_source_service" "CSS candidates are confined to approved owner roots"
contains "isForbiddenPath" "$css_source_service" "CSS candidate discovery rejects forbidden source paths"
contains "data-css-live-editor-source-resolution" "$view" "view exposes read-only CSS source resolution catalog"
contains "data-css-live-editor-resolution-status" "$view" "view renders CSS source resolution status"
contains "renderSourceResolution" "$js" "client resolves selected component against approved candidates"
contains "params.set\\('target_id'" "$js" "client sends provider target id"
contains "params.set\\('feed'" "$js" "client sends provider feed id"
contains "params.set\\('theme'" "$js" "client sends preview-only theme"

contains "sandbox=\"allow-same-origin\"" "$view" "preview iframe is sandboxed with allow-same-origin (no scripts)"
rejects "sandbox=\".*allow-scripts" "$view" "preview iframe sandbox does not allow scripts"
rejects "sandbox=\".*allow-top-navigation" "$view" "preview iframe sandbox does not allow top navigation"
rejects "sandbox=\".*allow-popups" "$view" "preview iframe sandbox does not allow popups"
rejects "sandbox=\".*allow-forms" "$view" "preview iframe sandbox does not allow forms"

contains "css-live-editor__declaration" "$view" "declaration preview uses dedicated read-only container"
contains "document.createElement\('pre'\)" "$js" "declaration preview creates pre via JS for matched CSS declarations"
rejects "css-live-editor__declaration[^>]*contenteditable" "$view" "declaration preview container is not contenteditable"
rejects "css-live-editor__declaration.*<textarea" "$view" "declaration preview container has no textarea"

rejects "contenteditable|onclick=\"[^\"]*save|onclick=\"[^\"]*edit|onclick=\"[^\"]*apply" "apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/assets/css_live_editor.js" "selector highlighting and declaration preview have no edit, save, or apply behavior"

rejects "header\\(['\"]Location:" "apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor" "CSS Live Editor does not redirect iframe to runtime routes"
rejects "DB::|mysqli|PDO|SELECT[[:space:]]|INSERT[[:space:]]|UPDATE[[:space:]]|DELETE[[:space:]]" "apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor" "CSS Live Editor preview services contain no database access"
rejects "fetch\\(|XMLHttpRequest|localStorage|sessionStorage" "apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/assets/css_live_editor.js" "client has no persistence or network API outside iframe navigation"

if (( failures > 0 )); then
  printf 'RESULT: FAIL (%d issue(s))\n' "$failures"
  exit 1
fi

echo "RESULT: PASS"
