#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

failures=0

require_php_lint() {
  local file="$1"
  local label="$2"
  if ! php -l "$file" > /dev/null 2>&1; then
    echo "FAIL: ${label} — PHP lint failed for ${file}"
    failures=$((failures + 1))
  fi
}

require_file_exists() {
  local file="$1"
  local label="$2"
  if [[ ! -f "$file" ]]; then
    echo "FAIL: ${label} — file not found: ${file}"
    failures=$((failures + 1))
  fi
}

require_pattern() {
  local file="$1"
  local pattern="$2"
  local label="$3"

  if ! grep -Eq "$pattern" "$file"; then
    echo "FAIL: ${label}"
    echo "      missing pattern ${pattern} in ${file}"
    failures=$((failures + 1))
  fi
}

forbid_pattern() {
  local file="$1"
  local pattern="$2"
  local label="$3"

  if grep -Eq "$pattern" "$file"; then
    echo "FAIL: ${label}"
    echo "      forbidden pattern ${pattern} found in ${file}"
    failures=$((failures + 1))
  fi
}

SCANNER="apps/Studio/Tools/LocalizationScanExtraction/Services/LocalizationScanService.php"
SCANNER_WRAPPER="apps/Studio/Tools/LocalizationScanExtraction/Services/LocalizationScanExtractionScanner.php"
CONTROLLER="apps/Studio/Controllers/StudioController.php"
ROUTES="apps/Studio/routes.php"
VIEW="apps/Studio/Tools/LocalizationScanExtraction/Views/preview.php"
LANG_EN="apps/Studio/Tools/LocalizationScanExtraction/Resources/lang/en.php"
LANG_JA="apps/Studio/Tools/LocalizationScanExtraction/Resources/lang/ja.php"
LANG_NE="apps/Studio/Tools/LocalizationScanExtraction/Resources/lang/ne.php"
JS="apps/Studio/Tools/LocalizationScanExtraction/assets/lse-tool.js"
TOOL_JSON="apps/Studio/Tools/LocalizationScanExtraction/tool.json"
MANIFEST="apps/Studio/Tools/LocalizationScanExtraction/manifest.php"
FIXTURE_TEST="scripts/tests/test_localization_scan_extraction_scanner.php"
CORRECTION_SERVICE="apps/Studio/Tools/LocalizationScanExtraction/Services/LocalizationScanCorrectionService.php"
CORRECTION_TEST="scripts/tests/test_localization_scan_extraction_corrections.php"
REPORT_VO="apps/Studio/Tools/LocalizationScanExtraction/ValueObjects/CorrectionReport.php"
ROLLBACK_SERVICE="apps/Studio/Tools/LocalizationScanExtraction/Services/RollbackService.php"
HISTORY_STORE="apps/Studio/Tools/LocalizationScanExtraction/Services/HistoryStore.php"
FULL_SCAN_SERVICE="apps/Studio/Tools/LocalizationScanExtraction/Services/FullSystemScanService.php"
INTEGRITY_VALIDATOR="apps/Studio/Tools/LocalizationScanExtraction/Services/FileIntegrityValidator.php"
APPLY_SERVICE="apps/Studio/Tools/LocalizationScanExtraction/Services/InlineMigrationApplyService.php"
CSS="apps/Studio/Tools/LocalizationScanExtraction/assets/lse-tool.css"

# ── Locale file structure ──────────────────────────────────────────
require_pattern "$LANG_EN" "page_title" 'en.php exists with page_title key'
require_pattern "$LANG_EN" "status_ready" 'en.php has status_ready key'
require_pattern "$LANG_EN" "extraction_not_ready" 'en.php has extraction_not_ready key'
require_pattern "$LANG_EN" "read_only" 'en.php has read_only key'
require_pattern "$LANG_EN" "no_file_writes" 'en.php has no_file_writes key'
require_pattern "$LANG_EN" "no_runtime_changes" 'en.php has no_runtime_changes key'
require_pattern "$LANG_EN" "action_open_localization_studio" 'en.php has handoff action key'
require_pattern "$LANG_EN" "safety_bar_title" 'en.php has safety bar title key'
require_pattern "$LANG_EN" "next_step_human_facing" 'en.php has human-facing next-step text'
require_pattern "$LANG_EN" "next_step_missing_owner" 'en.php has missing-owner next-step text'
require_pattern "$LANG_EN" "next_step_internal" 'en.php has internal next-step text'
require_pattern "$LANG_EN" "correction_ready_badge" 'en.php has correction mode badge key'
require_pattern "$LANG_EN" "correction_success_title" 'en.php has correction success key'
require_pattern "$LANG_EN" "correction_error_title" 'en.php has correction error key'
require_pattern "$LANG_EN" "correction_add_missing_keys" 'en.php has add missing keys action key'
require_pattern "$LANG_EN" "correction_extract_btn" 'en.php has extract button key'
require_pattern "$LANG_EN" "correction_confirm_add_missing" 'en.php has add missing keys confirm key'
require_pattern "$LANG_EN" "correction_confirm_extract" 'en.php has extract confirm key'
require_pattern "$LANG_EN" "operation_report_title" 'en.php has operation report title key'
require_pattern "$LANG_EN" "operation_label" 'en.php has operation label key'
require_pattern "$LANG_EN" "status_label" 'en.php has status label key'
require_pattern "$LANG_EN" "keys_added_label" 'en.php has keys added label key'
require_pattern "$LANG_EN" "re_scan_title" 'en.php has re-scan title key'
require_pattern "$LANG_EN" "snapshots_label" 'en.php has snapshots label key'
require_pattern "$LANG_EN" "rollback_button" 'en.php has rollback button key'
require_pattern "$LANG_EN" "rollback_confirm" 'en.php has rollback confirm key'
require_pattern "$LANG_EN" "operation_history_title" 'en.php has operation history title key'
require_pattern "$LANG_EN" "full_scan_title" 'en.php has full scan title key'
require_pattern "$LANG_EN" "full_scan_desc" 'en.php has full scan desc key'
require_pattern "$LANG_EN" "full_scan_button" 'en.php has full scan button key'
require_pattern "$LANG_EN" "full_scan_results_title" 'en.php has full scan results title key'
require_pattern "$LANG_EN" "full_scan_results_desc" 'en.php has full scan results desc key'
require_pattern "$LANG_EN" "full_owners_label" 'en.php has full owners label key'
require_pattern "$LANG_EN" "full_findings_label" 'en.php has full findings label key'
require_pattern "$LANG_EN" "full_owner_results_title" 'en.php has full owner results title key'
require_pattern "$LANG_EN" "full_scan_errors_label" 'en.php has full scan errors label key'
require_pattern "$LANG_EN" "add_missing_keys_label" 'en.php has add missing keys label'
require_pattern "$LANG_EN" "add_selected_keys_btn" 'en.php has add selected keys button label'
require_pattern "$LANG_EN" "add_selected_none_selected" 'en.php has none selected label'
require_pattern "$LANG_EN" "select_all_checkbox" 'en.php has select all checkbox label'
require_pattern "$LANG_EN" "select_all_label" 'en.php has select all label'
require_pattern "$LANG_EN" "selected_keys_count" 'en.php has selected keys count label'
require_pattern "$LANG_EN" "correction_add_selected_keys" 'en.php has add selected keys confirm'
require_pattern "$LANG_EN" "extract_inline_label" 'en.php has extract inline label'
require_pattern "$LANG_JA" "page_title" 'ja.php exists with page_title key'
require_pattern "$LANG_JA" "correction_ready_badge" 'ja.php has correction mode badge key'
require_pattern "$LANG_JA" "correction_success_title" 'ja.php has correction success key'
require_pattern "$LANG_JA" "correction_extract_btn" 'ja.php has extract button key'
require_pattern "$LANG_JA" "operation_report_title" 'ja.php has operation report title key'
require_pattern "$LANG_JA" "rollback_button" 'ja.php has rollback button key'
require_pattern "$LANG_JA" "full_scan_title" 'ja.php has full scan title key'
require_pattern "$LANG_JA" "operation_history_title" 'ja.php has operation history title key'
require_pattern "$LANG_JA" "add_selected_keys_btn" 'ja.php has add selected keys button'
require_pattern "$LANG_JA" "correction_add_selected_keys" 'ja.php has add selected keys confirm'
require_pattern "$LANG_NE" "page_title" 'ne.php exists with page_title key'
require_pattern "$LANG_NE" "correction_ready_badge" 'ne.php has correction mode badge key'
require_pattern "$LANG_NE" "correction_success_title" 'ne.php has correction success key'
require_pattern "$LANG_NE" "correction_extract_btn" 'ne.php has extract button key'
require_pattern "$LANG_NE" "add_selected_keys_btn" 'ne.php has add selected keys button'
require_pattern "$LANG_NE" "correction_add_selected_keys" 'ne.php has add selected keys confirm'

# ── Locale loading pattern in view (externalized from inline dict) ──
require_pattern "$VIEW" 'require [$]langPath' 'View loads locale file via require'
require_pattern "$VIEW" '\$lseLang\[\$key\] \?\? \$key' 'View uses locale array with key fallback'
require_pattern "$VIEW" "current_lang" 'View detects current language'
forbid_pattern "$VIEW" "page_title.*=>.*Localization" 'View no longer contains inline locale dict'

# ── View uses locale key references (not hardcoded English values) ──
require_pattern "$VIEW" "lse\\('scanner_status'\\)" 'View references scanner_status locale key'
require_pattern "$VIEW" "lse\\('correction_ready_badge'\\)" 'View references correction_ready_badge locale key'
require_pattern "$VIEW" "lse\\('action_open_localization_studio'\\)" 'View references handoff action locale key'
require_pattern "$VIEW" "lse\\('correction_workspace_title'\\)" 'View references correction workspace title key'
require_pattern "$VIEW" "lse\\('review_plan_copy'\\)" 'View references review plan copy key'
require_pattern "$VIEW" "lse\\('review_plan_advisory'\\)" 'View references review plan advisory key'
require_pattern "$VIEW" "lse\\('review_plan_empty'\\)" 'View references review plan empty key'
require_pattern "$VIEW" "lse\\('missing_key_summary_cta'\\)" 'View references missing key CTA key'
require_pattern "$VIEW" "lse\\('detail_extractable_no'\\)" 'View references extraction-unavailable badge key'
require_pattern "$VIEW" "lse\\('next_step_human_facing'\\)" 'View references human-facing next-step key'
require_pattern "$VIEW" "lse\\('next_step_missing_owner'\\)" 'View references missing-owner next-step key'
require_pattern "$VIEW" "lse\\('next_step_internal'\\)" 'View references internal next-step key'

# ── View structural anchors ────────────────────────────────────────
require_pattern "$VIEW" 'id="lse-review-plan-data"' 'View exposes review plan TSV for copy'
require_pattern "$VIEW" 'id="lse-copy-review-plan"' 'View has copy review plan button'
require_pattern "$VIEW" 'detail_suggested_english' 'Finding detail can show advisory suggested English value'
require_pattern "$VIEW" 'id="lse-detail-copy"' 'Detail panel exposes category-specific copy to JS'
require_pattern "$VIEW" 'id="lse-detail-placeholder"' 'Finding detail has one JS placeholder'

# ── Persistent safety indicator bar ────────────────────────────────
require_pattern "$VIEW" 'lse-safety-bar' 'View has persistent safety indicator bar'
require_pattern "$VIEW" 'lse-safety-bar-item' 'Safety bar has item containers'
require_pattern "$VIEW" 'lse-safety-bar-label' 'Safety bar has label spans'
require_pattern "$VIEW" 'lse-safety-bar-note' 'Safety bar has italic read-only note'
require_pattern "$VIEW" 'position: sticky' 'Safety bar uses sticky positioning'
require_pattern "$VIEW" 'correction_ready_badge' 'View references correction_ready_badge locale key'
require_pattern "$VIEW" 'correction_flash' 'View has correction flash handling'
require_pattern "$VIEW" 'lse-btn-extract' 'View has extract button class'
require_pattern "$VIEW" 'lse-flash-detail' 'View has flash detail class'
require_pattern "$VIEW" 'correction_confirm_add_missing' 'View has add-missing-keys confirm dialog'
require_pattern "$VIEW" 'correction_confirm_extract' 'View has extract confirm dialog'
require_pattern "$VIEW" 'lse-extract-finding-index' 'View has extract finding index hidden field'
require_pattern "$VIEW" 'add-selected-keys' 'View has add-selected-keys form action'
require_pattern "$VIEW" 'lse-add-selected-form' 'View has add-selected-keys form ID'
require_pattern "$VIEW" 'lse-selected-keys-container' 'View has selected keys container'
require_pattern "$VIEW" 'lse-add-selected-btn' 'View has add selected button'
require_pattern "$VIEW" 'lse-selected-count' 'View has selection count display'
require_pattern "$VIEW" 'lse-key-checkbox' 'View has key checkbox class'
require_pattern "$VIEW" 'lse-select-all-checkbox' 'View has select-all checkbox class'
require_pattern "$VIEW" 'lse-col-select' 'View has select column class'
require_pattern "$VIEW" 'lse-action-form-row' 'View has action form row'
require_pattern "$VIEW" 'add_selected_keys_btn' 'View references add selected button locale key'
require_pattern "$VIEW" 'correction_add_selected_keys' 'View references add selected keys confirm'
require_pattern "$VIEW" 'selected_keys\[[]]' 'View has selected_keys[] hidden input generation'

# ── Migration apply form invariants ────────────────────────────────
require_pattern "$VIEW" 'apply-inline-migration' 'View has apply-inline-migration form action'
require_pattern "$VIEW" 'lse-action-form' 'View has action form class for migration apply'
require_pattern "$VIEW" 'migration_preview_title' 'View references migration preview title locale key'
require_pattern "$VIEW" 'migration_preview_desc' 'View references migration preview desc locale key'
require_pattern "$VIEW" 'migration_apply_button' 'View references migration apply button locale key'
require_pattern "$VIEW" 'migration_apply_confirm' 'View references migration apply confirm locale key'
require_pattern "$VIEW" 'migration_apply_note' 'View references migration apply note locale key'
require_pattern "$VIEW" 'lse-mig-ready_to_migrate' 'View has ready-to-migrate row class'
require_pattern "$VIEW" 'lse-migration-note' 'View has migration note disclaimer'
require_pattern "$VIEW" 'no_corrections_findings_available' 'View has empty state for no corrections'
require_pattern "$CSS" 'lse-action-form-row' 'CSS has action form row flex layout'
require_pattern "$CSS" 'lse-selected-count' 'CSS has selected count display'
require_pattern "$CSS" 'lse-col-select' 'CSS has select column width'

# ── Governance and new history section invariants ────────────────────
require_pattern "$VIEW" 'lse-gov-grid' 'View has governance grid'
require_pattern "$VIEW" 'lse-gov-card' 'View has governance card'
require_pattern "$VIEW" 'lse-gov-card-title' 'View has governance card title'
require_pattern "$VIEW" 'lse-history-icon' 'View has history status icon'
require_pattern "$VIEW" 'lse-history-body' 'View has history body'
require_pattern "$VIEW" 'lse-history-meta' 'View has history meta'
require_pattern "$VIEW" 'governance_title' 'View references governance locale key'
require_pattern "$VIEW" 'history_rollback_available' 'View references rollback available badge key'
require_pattern "$VIEW" 'history_verified' 'View references verified badge key'
require_pattern "$VIEW" 'governance_snapshots' 'View references governance snapshots key'
require_pattern "$CSS" 'lse-gov-grid' 'CSS has governance grid'
require_pattern "$CSS" 'lse-gov-card' 'CSS has governance card'
require_pattern "$CSS" 'lse-history-icon' 'CSS has history status icon'
require_pattern "$CSS" 'lse-history-body' 'CSS has history body'
require_pattern "$CSS" 'lse-history-meta' 'CSS has history meta'

# ── View report/rollback/history/full-scan invariants ───────────────
require_pattern "$VIEW" 'lse-report' 'View has operation report panel'
require_pattern "$VIEW" 'lse-report-meta' 'View has report meta section'
require_pattern "$VIEW" 'lse-report-table' 'View has report added-keys table'
require_pattern "$VIEW" 'lse-report-diags' 'View has report re-scan diagnostics'
require_pattern "$VIEW" 'lse-report-snapshots' 'View has report snapshot listing'
require_pattern "$VIEW" 'lse-report-details' 'View has report details element'
require_pattern "$VIEW" 'lse-history' 'View has history section'
require_pattern "$VIEW" 'lse-history-list' 'View has history list'
require_pattern "$VIEW" 'lse-history-item' 'View has history item'
require_pattern "$VIEW" 'lse-full-scan-owners' 'View has full scan owner table'
require_pattern "$VIEW" 'lse-full-scan-errors' 'View has full scan errors section'
require_pattern "$VIEW" 'operation_report_title' 'View references operation report title locale key'
require_pattern "$VIEW" 'rollback_button' 'View references rollback button locale key'
require_pattern "$VIEW" 'rollback_confirm' 'View references rollback confirm locale key'
require_pattern "$VIEW" 'full_scan_button' 'View references full scan button locale key'
require_pattern "$VIEW" 'full_scan_results_title' 'View references full scan results locale key'
require_pattern "$VIEW" 'operation_history_title' 'View references operation history locale key'

# ── Scanner service invariants ─────────────────────────────────────
require_pattern "$SCANNER_WRAPPER" 'final class LocalizationScanExtractionScanner' 'Phase 1 scanner wrapper exists'
require_pattern "$SCANNER_WRAPPER" 'LocalizationScanService::scan' 'Scanner wrapper delegates to read-only scanner'
require_pattern "$SCANNER" 'LocalizationStudioEditService::getOwnersWithPaths' 'Owner roots come from Localization Studio discovery'
require_pattern "$SCANNER" 'realpath\(APP_ROOT\)' 'Scanner validates paths under APP_ROOT'
require_pattern "$SCANNER" 'pathGuardProbe' 'Path guard probe is available'
require_pattern "$SCANNER" 'vendor.*storage.*cache.*snapshots.*\.backups.*node_modules' 'Scanner excludes generated and dependency directories'
require_pattern "$SCANNER" '\$t|__|trans|lang|i18n' 'Scanner detects common translation helper calls'
require_pattern "$SCANNER" 'ownerKeyAliases' 'Scanner supports owner namespace aliases'
require_pattern "$SCANNER" 'isAttributeFragment' 'Scanner filters attribute fragments'
require_pattern "$SCANNER" 'isSqlFragment' 'Scanner filters SQL fragments'
require_pattern "$SCANNER" 'buildEditorHandoffUrl' 'Scanner builds editor handoff URL metadata'
require_pattern "$SCANNER" 'safeInternalReturnTo' 'Scanner sanitizes internal return paths'
require_pattern "$SCANNER" 'buildMissingKeyReviewPlan' 'Scanner builds read-only missing key review plan'
require_pattern "$SCANNER" 'missingKeyGroup' 'Scanner groups missing keys by prefix'
require_pattern "$SCANNER" 'suggestEnglishValueForMissingKey' 'Scanner computes advisory suggested English values'
require_pattern "$SCANNER" 'missingKeyReviewPlanTsv' 'Scanner creates copy-only TSV review plan'
require_pattern "$SCANNER" 'open_localization_studio' 'Scanner marks missing keys with editor handoff action'
require_pattern "$SCANNER" 'view_details' 'Scanner keeps non-missing findings view-details only'
require_pattern "$SCANNER" 'human_facing_candidate' 'Scanner emits human-facing candidate category'
require_pattern "$SCANNER" 'missing_owner_key' 'Scanner emits missing owner key category'
require_pattern "$SCANNER" 'external_shared_key_usage' 'Scanner emits shared/external key category'
require_pattern "$SCANNER" 'possibly_unused_key' 'Scanner emits possibly unused key category'
require_pattern "$SCANNER" 'extractable.*false' 'Scanner findings are not extractable in Phase 1'
forbid_pattern "$SCANNER" 'file_put_contents|fwrite|rename\s*\(|copy\s*\(|unlink\s*\(' 'Scanner service remains read-only'

# ── Controller invariants ──────────────────────────────────────────
require_pattern "$CONTROLLER" 'LocalizationScanExtractionScanner::scan' 'Controller uses scanner wrapper'
require_pattern "$CONTROLLER" 'studioSafeInternalReturnTo' 'Localization Studio return_to is sanitized server-side'
require_pattern "$CONTROLLER" 'LocalizationScanCorrectionService' 'Controller imports correction service'
require_pattern "$CONTROLLER" 'localizationScanExtractionAddMissingKeys' 'Controller has add-missing-keys handler'
require_pattern "$CONTROLLER" 'localizationScanExtractionAddSelectedKeys' 'Controller has add-selected-keys handler'
require_pattern "$CONTROLLER" 'localizationScanExtractionExtractInlineText' 'Controller has extract-inline-text handler'
require_pattern "$CONTROLLER" 'localizationScanExtractionRollback' 'Controller has rollback handler'
require_pattern "$CONTROLLER" 'localizationScanExtractionFullScan' 'Controller has full-scan handler'
require_pattern "$CONTROLLER" 'localizationScanExtractionApplySafeCorrectionsAsync' 'Controller has async safe-corrections handler'
require_pattern "$CONTROLLER" 'localizationScanExtractionApplyInlineMigration' 'Controller has inline migration apply handler'
require_pattern "$CONTROLLER" 'InlineMigrationApplyService' 'Controller imports InlineMigrationApplyService'
require_pattern "$CONTROLLER" 'InlineMigrationApplyService::getReadyCandidatesFromFindings' 'Controller uses getReadyCandidatesFromFindings'
require_pattern "$CONTROLLER" 'InlineMigrationApplyService::apply' 'Controller calls InlineMigrationApplyService::apply'
require_pattern "$CONTROLLER" 'RollbackService::rollbackFromReport' 'Controller handles rollback on apply failure'
require_pattern "$CONTROLLER" 'HistoryStore::store' 'Controller stores report to history'
require_pattern "$CONTROLLER" 'studio_lse_correction_report' 'Controller stores correction report in session'
require_pattern "$CONTROLLER" 'LocalizationScanCorrectionService::addMissingEnglishKeysSelectedWithReport' 'Controller uses selected-keys service method'
require_pattern "$CONTROLLER" 'Auth::requireCsrf' 'Controller has CSRF enforcement'
require_pattern "$CONTROLLER" "studio_lse_correction_flash" 'Controller uses correction flash'
require_pattern "$CONTROLLER" 'LocalizationScanCorrectionService' 'Controller imports CorrectionService'
require_pattern "$CONTROLLER" 'RollbackService' 'Controller imports RollbackService'
require_pattern "$CONTROLLER" 'HistoryStore' 'Controller imports HistoryStore'
require_pattern "$CONTROLLER" 'FullSystemScanService' 'Controller imports FullSystemScanService'
forbid_pattern "$CONTROLLER" 'LocalizationScanExtractionService' 'Controller does not load extraction service'
forbid_pattern "$CONTROLLER" 'localizationScanExtractionApply\(' 'Controller has no legacy generic apply handler'
forbid_pattern "$CONTROLLER" 'localizationScanExtractionPreviewDiff' 'Controller has no extraction preview handler'

# ── Route invariants ───────────────────────────────────────────────
require_pattern "$ROUTES" 'localization-scan-extraction/add-missing-keys' 'POST add-missing-keys route registered'
require_pattern "$ROUTES" 'localization-scan-extraction/add-selected-keys' 'POST add-selected-keys route registered'
require_pattern "$ROUTES" 'localization-scan-extraction/extract-inline-text' 'POST extract-inline-text route registered'
require_pattern "$ROUTES" 'localization-scan-extraction/rollback' 'POST rollback route registered'
require_pattern "$ROUTES" 'localization-scan-extraction/full-scan' 'POST full-scan route registered'
require_pattern "$ROUTES" 'localization-scan-extraction/apply-safe-corrections' 'POST apply-safe-corrections route registered'
require_pattern "$ROUTES" 'localization-scan-extraction/apply-inline-migration' 'POST apply-inline-migration route registered'
forbid_pattern "$ROUTES" "localization-scan-extraction/apply['\"]" 'No legacy generic apply route is registered'
forbid_pattern "$ROUTES" 'localization-scan-extraction/preview-diff' 'No extraction preview route is registered'

# ── JS invariants ──────────────────────────────────────────────────
require_pattern "$JS" 'detailPlaceholder\.hidden = true' 'Selected detail hides empty state with hidden attribute'
require_pattern "$JS" 'detailContent\.hidden = false' 'Selected detail shows detail content with hidden attribute'
require_pattern "$JS" 'detailNextStepText\.textContent = detailCopy\[cat\]' 'Detail next-step text is category-specific'
require_pattern "$JS" 'navigator\.clipboard\.writeText' 'Review plan copy uses clipboard only'
require_pattern "$JS" 'detailReviewMeta\.hidden = false' 'Missing key detail shows review metadata'
require_pattern "$JS" 'detailHandoffLink\.setAttribute\(.href., f\.handoff_url\)' 'Detail panel uses finding handoff URL'
require_pattern "$JS" 'f\.category === .missing_owner_key.' 'Detail handoff is limited to missing owner keys'
require_pattern "$JS" 'detailExtractableBadge' 'JS references extractable badge element'
require_pattern "$JS" 'detailExtractAction' 'JS references extract action element'
require_pattern "$JS" 'extractFindingIndex' 'JS references extract finding index field'
require_pattern "$JS" 'detailExtractableBadge\.innerHTML' 'JS updates extractable badge on finding selection'
require_pattern "$JS" 'detailExtractAction\.hidden = true' 'JS hides extract action for non-extractable findings'
require_pattern "$JS" 'detailExtractAction\.hidden = false' 'JS shows extract action for extractable findings'
forbid_pattern "$JS" 'showFindingDetail\(0\)' 'Finding detail is not auto-selected before user selection'

# ── Fixture test invariants ────────────────────────────────────────
require_pattern "$FIXTURE_TEST" 'Coverage Report' 'Fixture test covers visible heading text'
require_pattern "$FIXTURE_TEST" 'mfg\.cov\.missing' 'Fixture test covers owner-local missing keys'
require_pattern "$FIXTURE_TEST" 'common\.save' 'Fixture test covers shared/global key usage'
require_pattern "$FIXTURE_TEST" 'data-row' 'Fixture test covers data-* false positives'
require_pattern "$FIXTURE_TEST" 'COALESCE' 'Fixture test covers SQL false positives'
require_pattern "$FIXTURE_TEST" 'open_localization_studio' 'Fixture test covers missing key handoff action'
require_pattern "$FIXTURE_TEST" 'https://example.com/bad' 'Fixture test rejects absolute return_to'
require_pattern "$FIXTURE_TEST" 'protocol-relative return_to' 'Fixture test rejects protocol-relative return_to'
require_pattern "$FIXTURE_TEST" 'summary count should match findings' 'Fixture test covers summary consistency'
require_pattern "$FIXTURE_TEST" 'mfg\.cov\.kpi\.open_orders' 'Fixture test covers review plan grouping'
require_pattern "$FIXTURE_TEST" 'usage_count' 'Fixture test covers duplicate missing key usage count'
require_pattern "$FIXTURE_TEST" 'suggested_english_value' 'Fixture test covers advisory suggested English values'
require_pattern "$FIXTURE_TEST" 'suggestion_confidence' 'Fixture test covers advisory suggestion confidence'
require_pattern "$FIXTURE_TEST" 'review plan TSV should not contain write/apply actions' 'Fixture test forbids review plan write/apply actions'

# ── Value object invariants ────────────────────────────────────────
require_pattern "$REPORT_VO" 'final class CorrectionReport' 'CorrectionReport value object exists'
require_pattern "$REPORT_VO" 'public function toArray' 'CorrectionReport has toArray'
require_pattern "$REPORT_VO" 'public static function fromArray' 'CorrectionReport has fromArray'

# ── Rollback service invariants ───────────────────────────────────
require_pattern "$ROLLBACK_SERVICE" 'class RollbackService' 'RollbackService exists'
require_pattern "$ROLLBACK_SERVICE" 'function rollbackFromReport' 'RollbackService has rollbackFromReport'
require_pattern "$ROLLBACK_SERVICE" 'inferTargetPath' 'RollbackService has inferTargetPath'

# ── History store invariants ───────────────────────────────────────
require_pattern "$HISTORY_STORE" 'class HistoryStore' 'HistoryStore exists'
require_pattern "$HISTORY_STORE" 'function store' 'HistoryStore has store method'
require_pattern "$HISTORY_STORE" 'function getRecent' 'HistoryStore has getRecent method'
require_pattern "$HISTORY_STORE" 'function count' 'HistoryStore has count method'

# ── Full system scan invariants ────────────────────────────────────
require_pattern "$FULL_SCAN_SERVICE" 'class FullSystemScanService' 'FullSystemScanService exists'
require_pattern "$FULL_SCAN_SERVICE" 'function scanAll' 'FullSystemScanService has scanAll'

# ── File integrity validator invariants ────────────────────────────
require_pattern "$INTEGRITY_VALIDATOR" 'class FileIntegrityValidator' 'FileIntegrityValidator exists'
require_pattern "$INTEGRITY_VALIDATOR" 'validatePhpSyntax' 'FileIntegrityValidator validates PHP syntax'
require_pattern "$INTEGRITY_VALIDATOR" 'validatePhpSyntaxOnLocaleKeys' 'FileIntegrityValidator validates locale file keys'
require_pattern "$INTEGRITY_VALIDATOR" 'validateSourceFileIntegrity' 'FileIntegrityValidator validates source file integrity'
require_pattern "$INTEGRITY_VALIDATOR" 'PHP_BINARY' 'FileIntegrityValidator prefers current PHP binary over PATH lookup'
forbid_pattern "$INTEGRITY_VALIDATOR" "'php -l " 'FileIntegrityValidator does not shell out to bare php'

# ── InlineMigrationApplyService invariants ─────────────────────────
require_pattern "$APPLY_SERVICE" 'class InlineMigrationApplyService' 'InlineMigrationApplyService exists'
require_pattern "$APPLY_SERVICE" 'function apply' 'InlineMigrationApplyService has apply method'
require_pattern "$APPLY_SERVICE" 'function getReadyCandidatesFromFindings' 'InlineMigrationApplyService has getReadyCandidatesFromFindings'
require_pattern "$APPLY_SERVICE" 'function isSafeReplacementTarget' 'InlineMigrationApplyService has safety rule checker'
require_pattern "$APPLY_SERVICE" 'function replaceInSourceFile' 'InlineMigrationApplyService has source replacement method'
require_pattern "$APPLY_SERVICE" 'function checkPhpLint' 'InlineMigrationApplyService validates PHP syntax after replacement'
require_pattern "$APPLY_SERVICE" 'function snapshotFile' 'InlineMigrationApplyService takes file snapshots'
require_pattern "$APPLY_SERVICE" 'function writeLocaleFile' 'InlineMigrationApplyService writes locale files'
require_pattern "$APPLY_SERVICE" 'function rollbackSnapshots' 'InlineMigrationApplyService supports rollback'
require_pattern "$APPLY_SERVICE" 'CorrectionReport' 'InlineMigrationApplyService returns CorrectionReport'
require_pattern "$APPLY_SERVICE" 'file_put_contents' 'InlineMigrationApplyService uses file writes (apply service)'

# ── Locale keys for migration apply UI ─────────────────────────────
require_pattern "$LANG_EN" 'migration_preview_title' 'en.php has migration preview title key'
require_pattern "$LANG_EN" 'migration_preview_desc' 'en.php has migration preview desc key'
require_pattern "$LANG_EN" 'migration_apply_button' 'en.php has migration apply button key'
require_pattern "$LANG_EN" 'migration_apply_note' 'en.php has migration apply note key'
require_pattern "$LANG_EN" 'migration_apply_confirm' 'en.php has migration apply confirm key'
require_pattern "$LANG_EN" 'migration_apply_success' 'en.php has migration apply success key'
require_pattern "$LANG_EN" 'migration_apply_failed' 'en.php has migration apply failed key'
require_pattern "$LANG_JA" 'migration_apply_button' 'ja.php has migration apply button key'
require_pattern "$LANG_JA" 'migration_apply_confirm' 'ja.php has migration apply confirm key'
require_pattern "$LANG_NE" 'migration_apply_button' 'ne.php has migration apply button key'
require_pattern "$LANG_NE" 'migration_apply_confirm' 'ne.php has migration apply confirm key'

# ── Metadata invariants ────────────────────────────────────────────
require_pattern "$TOOL_JSON" '"scan": true' 'Tool metadata advertises scanner capability'
require_pattern "$TOOL_JSON" '"extract": true' 'Tool metadata advertises extraction capability'
require_pattern "$TOOL_JSON" '"write": true' 'Tool metadata advertises write capability'
require_pattern "$MANIFEST" 'can_modify.*true' 'Manifest allows modification'
require_pattern "$MANIFEST" 'writes_to_owner_artifact.*true' 'Manifest writes to owner artifact'
require_pattern "$MANIFEST" 'supports_snapshot.*true' 'Manifest supports snapshots'

# ── File integrity check via FileIntegrityValidator ────────────────
if [[ -f "$LANG_EN" ]]; then
  INTEGRITY_OUTPUT=$(php -r '
    require_once "'"$ROOT_DIR"'/apps/Studio/Tools/LocalizationScanExtraction/Services/FileIntegrityValidator.php";
    $result = Apps\Studio\Tools\LocalizationScanExtraction\Services\FileIntegrityValidator::validatePhpSyntaxOnLocaleKeys("'"$ROOT_DIR/$LANG_EN"'");
    echo json_encode($result);
  ' 2>/dev/null || echo '{"valid":false,"error":"CLI probe failed"}')
  INTEGRITY_VALID=$(echo "$INTEGRITY_OUTPUT" | php -r 'echo json_decode(file_get_contents("php://stdin"), true)["valid"] ? "YES" : "NO";' 2>/dev/null || echo "NO")
  if [[ "$INTEGRITY_VALID" != "YES" ]]; then
    echo "FAIL: FileIntegrityValidator check on en.php failed"
    failures=$((failures + 1))
  fi
fi

# ── PHP lint all tool PHP files ────────────────────────────────────
PHP_FILES=(
  "$SCANNER"
  "$SCANNER_WRAPPER"
  "$CONTROLLER"
  "$ROUTES"
  "$VIEW"
  "$LANG_EN"
  "$LANG_JA"
  "$LANG_NE"
  "$CORRECTION_SERVICE"
  "$REPORT_VO"
  "$ROLLBACK_SERVICE"
  "$HISTORY_STORE"
  "$FULL_SCAN_SERVICE"
  "$INTEGRITY_VALIDATOR"
  "$APPLY_SERVICE"
  "$MANIFEST"
  "$CORRECTION_TEST"
  "$FIXTURE_TEST"
)
for f in "${PHP_FILES[@]}"; do
  if [[ -f "$f" ]]; then
    require_php_lint "$f" "PHP lint: $(basename "$f")"
  fi
done

# ── Exact-count invariants ─────────────────────────────────────────
if [[ "$(grep -c "action_desc" "$LANG_EN")" -ne 2 ]]; then
  echo "FAIL: action_desc and correction_action_desc should both exist in en.php"
  failures=$((failures + 1))
fi
if [[ "$(grep -c "next_step_missing_owner" "$LANG_EN")" -ne 1 ]]; then
  echo "FAIL: Detail missing-key guidance key should exist exactly once in en.php"
  failures=$((failures + 1))
fi
if [[ "$(grep -c 'lse-safety-bar' "$VIEW")" -ge 1 ]]; then
  : # safety bar present
else
  echo "FAIL: Safety bar element should exist in view"
  failures=$((failures + 1))
fi

if [[ "$failures" -gt 0 ]]; then
  echo "Localization Scan & Extraction boundary gate failed (${failures} failure(s))."
  exit 1
fi

echo "Localization Scan & Extraction boundary gate passed."
