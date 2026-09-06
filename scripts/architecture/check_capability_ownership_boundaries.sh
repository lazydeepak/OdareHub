#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

RG_BIN="${RG_BIN:-$(command -v rg || true)}"
GREP_BIN="${GREP_BIN:-$(command -v grep || true)}"

if [[ -n "$RG_BIN" ]]; then
  SEARCH_TOOL="$RG_BIN"
  SEARCH_ARGS=(-n -i -S)
else
  if [[ -z "$GREP_BIN" ]]; then
    echo "missing required binary: rg or grep" >&2
    exit 2
  fi
  SEARCH_TOOL="$GREP_BIN"
  SEARCH_ARGS=(-nEi)
fi

failures=0
tmp_diff="$(mktemp /tmp/capability-ownership-diff-XXXXXX)"
trap 'rm -f "$tmp_diff"' EXIT
contract_doc="docs/architecture/system-app-plugin-package-boundaries.md"
qr_manifest="apps/Platform/modules/QRCode/plugin.json"
pdf_engine="app/Core/PdfService.php"

expected_scan_anchors=(
  "apps/Platform/modules/QRCode/plugin.json"
  "apps/Platform/modules/QRCode"
  "apps/Manufacturing"
  "apps/SBAIO"
  "app/Core/PdfService.php"
  "app"
  "apps"
  "plugins"
  "packages"
  "public"
  "qr_ownership_pattern"
  "qr_business_semantics_pattern"
  "pdf_engine_semantics_pattern"
  "dompdf_boundary_pattern"
  "package_ownership_pattern"
  "core_coupling_pattern"
)

active_scan_anchors=(
  "apps/Platform/modules/QRCode/plugin.json"
  "apps/Platform/modules/QRCode"
  "apps/Manufacturing"
  "apps/SBAIO"
  "app/Core/PdfService.php"
  "app"
  "apps"
  "plugins"
  "packages"
  "public"
  "qr_ownership_pattern"
  "qr_business_semantics_pattern"
  "pdf_engine_semantics_pattern"
  "dompdf_boundary_pattern"
  "package_ownership_pattern"
  "core_coupling_pattern"
)

qr_ownership_pattern='QRCode.*owner_app.*(manufacturing|sbaio|payroll)|owner_app.*(manufacturing|sbaio|payroll).*QRCode|Plugins\\QRCode.*(owner|owns|surface owner|runtime owner)|package_type.*plugin.*QRCode'
qr_business_semantics_pattern='apps/Platform/modules/QRCode/.*(Manufacturing|SBAIO|Payroll|ProductionEntries|ProductionPlans|QCPlans|Ledger|Coverage|Timecards|manufacturing|sbaio|payroll)'
pdf_engine_semantics_pattern='Manufacturing|SBAIO|Payroll|ProductionPlans|Timecards|production-plan|timecard|/production-plans|/apps/manufacturing|/apps/sbaio|Plugins\\ProductionPlans|Plugins\\Timecards'
dompdf_boundary_pattern='Dompdf\\|dompdf/dompdf|new[[:space:]]+Dompdf'
package_ownership_pattern='^packages/.*(surface_owner|capability_owner|runtime_owner|feature_owner|owns route|owns view|business workflow|manufacturing|sbaio|payroll)'
core_coupling_pattern='^app/.*(QRCode|Plugins\\QRCode|Manufacturing|SBAIO|Payroll|ProductionEntries|ProductionPlans|QCPlans|Ledger|Coverage|Timecards)'

echo "[architecture] check_capability_ownership_boundaries"

check_text() {
  local file="$1"
  local pattern="$2"
  local description="$3"

  if [[ ! -f "$file" ]]; then
    echo "missing required file for $description: $file"
    failures=$((failures + 1))
    return
  fi

  if grep -Fq "$pattern" "$file"; then
    echo "  ok: $description"
  else
    echo "missing $description in $file"
    failures=$((failures + 1))
  fi
}

json_value() {
  local path="$1"
  local key="$2"

  php -r '
    $path = $argv[1];
    $key = $argv[2];
    $data = json_decode((string)file_get_contents($path), true);
    if (!is_array($data)) {
        fwrite(STDERR, "invalid json: {$path}\n");
        exit(2);
    }
    $value = $data[$key] ?? null;
    if (is_bool($value)) {
        echo $value ? "true" : "false";
    } elseif (is_array($value)) {
        echo count($value);
    } elseif ($value !== null) {
        echo (string)$value;
    }
  ' "$path" "$key"
}

diff_additions() {
  git diff --unified=0 --diff-filter=ACMRT HEAD -- app apps plugins packages public 2>/dev/null \
    | awk '
      /^diff --git / {
        file = $4
        sub(/^b\//, "", file)
        next
      }
      /^\+\+\+ / { next }
      /^\+/ && $0 !~ /^\+\+\+/ {
        print file ":" substr($0, 2)
      }
    '
}

diff_additions > "$tmp_diff"

echo "- verifying capability ownership scan contract"
for i in "${!expected_scan_anchors[@]}"; do
  expected="${expected_scan_anchors[$i]}"
  actual="${active_scan_anchors[$i]:-}"
  if [[ "$actual" == "$expected" ]]; then
    echo "  ok: scan-anchor[$i] $expected"
  else
    echo "scan-anchor[$i] drift: expected '$expected' but found '${actual:-<missing>}'"
    failures=$((failures + 1))
  fi
done

if [[ "${#active_scan_anchors[@]}" -ne "${#expected_scan_anchors[@]}" ]]; then
  echo "scan-anchor count drift: expected ${#expected_scan_anchors[@]} but found ${#active_scan_anchors[@]}"
  failures=$((failures + 1))
fi

echo "- verifying capability ownership diagnostic baseline coverage"
check_text "$contract_doc" "scripts/architecture/check_capability_ownership_boundaries.sh" "boundary doc references capability ownership gate"
check_text "$contract_doc" "Capability ownership diagnostics are read-only and scan-scope guarded." "boundary doc documents scan-scope guarded diagnostics"
check_text "$contract_doc" "QR remains a Platform-owned integration capability until an approved owner migration." "boundary doc documents Platform-owned QR capability"
check_text "$contract_doc" 'Legacy `Plugins\QRCode` namespace and plugin vocabulary are compatibility debt, not proof of plugin ownership.' "boundary doc documents legacy QR plugin vocabulary debt"
check_text "$contract_doc" "QR must not own Manufacturing business meaning." "boundary doc documents QR/Manufacturing ownership boundary"
check_text "$contract_doc" "Manufacturing QR references are consumer/integration debt warnings." "boundary doc documents Manufacturing QR consumer debt warnings"
check_text "$contract_doc" "PDF routes, templates, filenames, permissions, and report meaning remain app/module-owned." "boundary doc documents app/module PDF meaning ownership"
check_text "$contract_doc" "PDF rendering engines must remain app-agnostic." "boundary doc documents app-agnostic PDF engine"
check_text "$contract_doc" "Packages remain lifecycle transport, not runtime feature owners." "boundary doc documents package transport boundary"

echo "- checking QR current ownership declaration"
if [[ ! -f "$qr_manifest" ]]; then
  echo "missing documented QR manifest at $qr_manifest"
  failures=$((failures + 1))
else
  qr_owner="$(json_value "$qr_manifest" "owner_app")"
  qr_package_type="$(json_value "$qr_manifest" "package_type")"
  qr_module_type="$(json_value "$qr_manifest" "module_type")"
  echo "  QR owner_app: ${qr_owner:-<missing>}"
  echo "  QR package_type: ${qr_package_type:-<missing>}"
  echo "  QR module_type: ${qr_module_type:-<missing>}"

  if [[ "$qr_owner" != "platform" || "$qr_package_type" != "module" ]]; then
    echo "QR must remain documented as Platform-owned module until an approved migration"
    failures=$((failures + 1))
  fi
fi

echo "- reporting existing QR integration debt"
if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" 'Plugins\\QRCode|/qr/|QRCode' apps/Manufacturing apps/SBAIO 2>/dev/null | head -n 20; then
  echo "  warning: QR is referenced from business app/module surfaces; treat as integration debt, not ownership proof"
else
  echo "  no business-app QR references found"
fi
if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" 'Plugins\\QRCode' apps/Platform/modules/QRCode 2>/dev/null | head -n 20; then
  echo "  warning: QR still uses legacy Plugins\\QRCode namespace vocabulary"
fi

echo "- checking for new QR ownership confusion in runtime diffs"
if [[ -s "$tmp_diff" ]]; then
  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$qr_ownership_pattern" "$tmp_diff" 2>/dev/null; then
    echo "new QR ownership confusion found in runtime diff"
    failures=$((failures + 1))
  fi
  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$qr_business_semantics_pattern" "$tmp_diff" 2>/dev/null \
      | grep -Ev 'docs/|AGENT-COMPLIANCE-CHECKLIST|check_capability_ownership_boundaries|plugin\.json:|routes\.php:|Controllers/QRCodeController\.php:'; then
    echo "new Platform QR additions appear to absorb business-app semantics"
    failures=$((failures + 1))
  fi
else
  echo "  no runtime diff additions to scan for QR drift"
fi

echo "- checking PDF technical engine for app-specific semantics"
if [[ ! -f "$pdf_engine" ]]; then
  echo "missing current PDF technical engine at $pdf_engine"
  failures=$((failures + 1))
else
  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$pdf_engine_semantics_pattern" "$pdf_engine" 2>/dev/null; then
    echo "PdfService contains app/module-specific semantics; PDF engine must stay technical"
    failures=$((failures + 1))
  else
    echo "  PdfService remains app-agnostic"
  fi
fi

echo "- checking PDF templates stay with app/module surface owners"
pdf_templates="$(
  find app apps plugins packages public -type f \( -iname '*pdf*.php' -o -path '*/Views/pdf*.php' \) \
    ! -path 'app/Core/PdfService.php' \
    -print 2>/dev/null | sort
)"
if [[ -n "$pdf_templates" ]]; then
  echo "$pdf_templates"
  bad_pdf_templates="$(
    printf '%s\n' "$pdf_templates" \
      | grep -Ev '^apps/[^/]+/Views/|^apps/[^/]+/modules/[^/]+/Views/' \
      || true
  )"
  if [[ -n "$bad_pdf_templates" ]]; then
    echo "$bad_pdf_templates"
    echo "PDF templates/reports must live with the source app/module owner"
    failures=$((failures + 1))
  else
    echo "  PDF templates are app/module-owned"
  fi
else
  echo "  no PDF templates found"
fi

echo "- checking dompdf stays behind technical renderer boundary"
dompdf_refs="$(
  "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$dompdf_boundary_pattern" app apps plugins packages public 2>/dev/null || true
)"
bad_dompdf_refs="$(
  printf '%s\n' "$dompdf_refs" \
    | grep -Ev '^(app/Core/PdfService\.php:|$)' \
    || true
)"
if [[ -n "$bad_dompdf_refs" ]]; then
  echo "$bad_dompdf_refs"
  echo "PDF libraries must be treated as technical renderers behind PdfService or an approved app-agnostic engine"
  failures=$((failures + 1))
else
  echo "  dompdf references are confined to PdfService/runtime package metadata"
fi

echo "- checking for new package feature ownership confusion in runtime diffs"
if [[ -s "$tmp_diff" ]]; then
  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$package_ownership_pattern" "$tmp_diff" 2>/dev/null; then
    echo "package diff treats package artifacts as feature/runtime owners"
    failures=$((failures + 1))
  fi
else
  echo "  no runtime diff additions to scan for package ownership drift"
fi

echo "- checking for new Core coupling in runtime diffs"
if [[ -s "$tmp_diff" ]]; then
  if "$SEARCH_TOOL" "${SEARCH_ARGS[@]}" "$core_coupling_pattern" "$tmp_diff" 2>/dev/null; then
    echo "Core diff appears to add QR/PDF/business capability coupling; Core remains locked and app-agnostic"
    failures=$((failures + 1))
  fi
else
  echo "  no runtime diff additions to scan for Core capability coupling"
fi

echo "- checking package directory for runtime source files"
package_runtime_files="$(
  find packages -type f \
    ! -name 'AGENTS.md' \
    ! -name '.gitkeep' \
    ! -name '*.zip' \
    ! -name '.DS_Store' \
    -print 2>/dev/null
)"
if [[ -n "$package_runtime_files" ]]; then
  echo "$package_runtime_files"
  echo "packages should contain lifecycle artifacts/docs only, not feature runtime source"
  failures=$((failures + 1))
else
  echo "  package directory contains only lifecycle artifacts/docs"
fi

if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL (capability ownership boundary drift found)" >&2
  exit 1
fi

echo "RESULT: PASS"
