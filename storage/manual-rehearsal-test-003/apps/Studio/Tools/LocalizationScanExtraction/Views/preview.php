<?php
$owners = isset($localizationScanExtractionModel['owners']) && is_array($localizationScanExtractionModel['owners'])
    ? $localizationScanExtractionModel['owners']
    : [];
$ownerDiscoveryWired = !empty($localizationScanExtractionModel['owner_discovery_wired']);
$selectedOwner = trim((string)($localizationScanExtractionModel['selected_owner'] ?? ''));
$selectedScope = trim((string)($localizationScanExtractionModel['selected_scope'] ?? 'full'));
if ($selectedScope === '') {
    $selectedScope = 'full';
}
$selectedLocale = trim((string)($localizationScanExtractionModel['selected_locale'] ?? 'en'));
$scanResults = isset($localizationScanExtractionModel['scan_results']) && is_array($localizationScanExtractionModel['scan_results'])
    ? $localizationScanExtractionModel['scan_results']
    : null;

$correctionReport = isset($localizationScanExtractionModel['correction_report']) && is_array($localizationScanExtractionModel['correction_report'])
    ? $localizationScanExtractionModel['correction_report']
    : null;
$historyReports = isset($localizationScanExtractionModel['history_reports']) && is_array($localizationScanExtractionModel['history_reports'])
    ? $localizationScanExtractionModel['history_reports']
    : [];
$fullScanResults = isset($_SESSION['studio_lse_full_scan']) && is_array($_SESSION['studio_lse_full_scan'])
    ? $_SESSION['studio_lse_full_scan']
    : null;
unset($_SESSION['studio_lse_full_scan']);

$scanOk = $scanResults !== null && !empty($scanResults['scan_ok']);
$scanError = $scanResults !== null && !empty($scanResults['error']) ? $scanResults['error'] : null;
$scanSummary = $scanResults !== null && isset($scanResults['summary']) ? $scanResults['summary'] : [];
$scanFindings = $scanResults !== null && isset($scanResults['findings']) ? $scanResults['findings'] : [];
$missingKeyReviewPlan = $scanResults !== null && isset($scanResults['missing_key_review_plan']) && is_array($scanResults['missing_key_review_plan'])
    ? $scanResults['missing_key_review_plan']
    : ['groups' => [], 'rows' => [], 'tsv' => ''];
$missingKeyReviewGroups = isset($missingKeyReviewPlan['groups']) && is_array($missingKeyReviewPlan['groups']) ? $missingKeyReviewPlan['groups'] : [];
$missingKeyReviewRows = isset($missingKeyReviewPlan['rows']) && is_array($missingKeyReviewPlan['rows']) ? $missingKeyReviewPlan['rows'] : [];
$missingKeyReviewTsv = isset($missingKeyReviewPlan['tsv']) ? (string)$missingKeyReviewPlan['tsv'] : '';
$firstMissingHandoffUrl = '';
foreach ($scanFindings as $candidateFinding) {
    if (($candidateFinding['category'] ?? '') === 'missing_owner_key' && !empty($candidateFinding['handoff_url'])) {
        $firstMissingHandoffUrl = (string)$candidateFinding['handoff_url'];
        break;
    }
}

$migrationPlan = null;
$migrationCandidates = [];
$migrationCounts = ['ready_to_migrate' => 0, 'needs_review' => 0, 'rejected' => 0];
if ($scanOk) {
    require_once APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/Services/InlineMigrationPlannerService.php';
    try {
        $migrationService = new \Apps\Studio\Tools\LocalizationScanExtraction\Services\InlineMigrationPlannerService($selectedOwner);
        $migrationPlan = $migrationService->buildPlan($scanFindings);
        $migrationCandidates = $migrationPlan['candidates'] ?? [];
        $migrationCounts = $migrationPlan['counts'] ?? $migrationCounts;
    } catch (\Throwable $e) {
        $migrationPlan = null;
    }
}

$csrfToken = class_exists('Auth') ? Auth::csrfToken() : ($_SESSION['csrf'] ?? '');

$correctionFlash = isset($_SESSION['studio_lse_correction_flash']) && is_array($_SESSION['studio_lse_correction_flash'])
    ? $_SESSION['studio_lse_correction_flash']
    : null;
unset($_SESSION['studio_lse_correction_flash']);

$lseLang = [];
$langCode = function_exists('current_lang') ? (string)current_lang() : 'en';
$langPath = APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/Resources/lang/' . $langCode . '.php';
$fallbackLangPath = APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/Resources/lang/en.php';
if (is_file($langPath)) {
    $loaded = require $langPath;
    if (is_array($loaded)) {
        $lseLang = $loaded;
    }
}
if ($lseLang === [] && is_file($fallbackLangPath)) {
    $loaded = require $fallbackLangPath;
    if (is_array($loaded)) {
        $lseLang = $loaded;
    }
}
$lse = static function (string $key) use ($lseLang): string {
    return (string)($lseLang[$key] ?? $key);
};
?>
<style><?php require APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/assets/lse-tool.css'; ?></style>

<section class="gui-studio lse-tool" id="lse-tool-root">

  <a class="lse-nav-back" href="/apps/studio/tools/localization-studio">&larr; <?= e($lse('back_to_ls')) ?></a>

  <div class="lse-hero">
    <div>
      <h2><?= e($lse('page_title')) ?></h2>
      <p class="lse-subtitle"><?= e($lse('page_subtitle')) ?></p>
    </div>
    <div class="lse-badge-group">
      <span class="lse-badge <?= $ownerDiscoveryWired ? 'is-safe' : 'is-warn' ?>"><?= $ownerDiscoveryWired ? e($lse('scan_ready_badge')) : e($lse('backend_not_wired')) ?></span>
      <span class="lse-badge is-warn"><?= e($lse('correction_ready_badge')) ?></span>
      <span class="lse-badge is-safe"><?= e($lse('validation_protected_badge')) ?></span>
      <span class="lse-badge is-info"><?= e($lse('rollback_protected_badge')) ?></span>
    </div>
  </div>

  <?php if ($scanError): ?>
    <div class="lse-notice is-error">
      <span class="lse-notice-icon">&#9888;</span>
      <span><strong><?= e($lse('scan_error')) ?>:</strong> <?= e($scanError) ?></span>
    </div>
  <?php elseif ($scanOk): ?>
    <div class="lse-notice is-success">
      <span class="lse-notice-icon">&#10003;</span>
      <span><strong><?= e($lse('scan_success')) ?>.</strong>
        <?= e($lse('scanned_files')) ?>: <?= (int)($scanResults['scanned_files'] ?? 0) ?>,
        <?= e($lse('total_lines')) ?>: <?= (int)($scanResults['total_lines'] ?? 0) ?>,
        <?= e($lse('scanned_at')) ?>: <?= e($scanResults['scanned_at'] ?? '') ?>
      </span>
    </div>
  <?php else: ?>
    <div class="lse-notice">
      <span class="lse-notice-icon">&#9888;</span>
      <span><strong><?= e($ownerDiscoveryWired ? $lse('scanner_ready_title') : $lse('safety_title')) ?>:</strong> <?= e($ownerDiscoveryWired ? $lse('scanner_ready_desc') : $lse('safety_desc')) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($correctionFlash !== null): ?>
    <div class="lse-notice <?= !empty($correctionFlash['success']) ? 'is-success' : 'is-error' ?>">
      <span class="lse-notice-icon"><?= !empty($correctionFlash['success']) ? '&#10003;' : '&#9888;' ?></span>
      <span>
        <strong><?= !empty($correctionFlash['success']) ? e($lse('correction_success_title')) : e($lse('correction_error_title')) ?>:</strong>
        <?php if (!empty($correctionFlash['added_count'])): ?>
          <?= e(strtr($lse('correction_keys_added'), ['{count}' => (string)(int)$correctionFlash['added_count']])) ?>
          <?php if (!empty($correctionFlash['snapshot'])): ?>
            <span class="lse-flash-detail"><?= e($lse('correction_snapshot_taken')) ?> <code><?= e(basename((string)$correctionFlash['snapshot'])) ?></code></span>
          <?php endif; ?>
        <?php elseif (!empty($correctionFlash['key'])): ?>
          <?= e($lse('correction_extracted_key')) ?> <code><?= e((string)$correctionFlash['key']) ?></code>
          <?php if (!empty($correctionFlash['source_snapshot'])): ?>
            <span class="lse-flash-detail"><?= e($lse('correction_snapshot_taken')) ?> <code><?= e(basename((string)$correctionFlash['source_snapshot'])) ?></code></span>
          <?php endif; ?>
        <?php else: ?>
          <?= e((string)($correctionFlash['message'] ?? $correctionFlash['error'] ?? '')) ?>
        <?php endif; ?>
      </span>
    </div>
  <?php endif; ?>

  <form class="lse-panel" method="post" action="/apps/studio/tools/localization-scan-extraction/scan" id="lse-scan-form" data-async-url="/apps/studio/tools/localization-scan-extraction/scan">
    <h3><?= e($lse('target_panel_title')) ?></h3>
    <p class="lse-panel-desc"><?= e($lse('target_panel_desc')) ?></p>
    <input type="hidden" name="csrf" value="<?= e($csrfToken ?? '') ?>">
    <div class="lse-form-row">
      <div class="lse-form-group">
        <label for="lse-owner"><?= e($lse('owner_label')) ?></label>
        <?php if ($ownerDiscoveryWired && $owners !== []): ?>
          <select id="lse-owner" name="owners[]">
            <option value="__all__"<?= $selectedOwner === '' ? ' selected' : '' ?>><?= e($lse('owner_all')) ?></option>
            <?php foreach ($owners as $ok => $olabel): ?>
              <option value="<?= e($ok) ?>"<?= $ok === $selectedOwner ? ' selected' : '' ?>><?= e($olabel) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <select id="lse-owner" disabled>
            <option value=""><?= e($lse('owner_empty')) ?></option>
          </select>
        <?php endif; ?>
      </div>
      <div class="lse-form-group">
        <label for="lse-scope"><?= e($lse('scope_label')) ?></label>
        <select id="lse-scope" name="scope">
          <option value="full"<?= $selectedScope === 'full' ? ' selected' : '' ?>><?= e($lse('scope_full')) ?></option>
          <option value="views"<?= $selectedScope === 'views' ? ' selected' : '' ?>><?= e($lse('scope_views')) ?></option>
          <option value="views_controllers"<?= $selectedScope === 'views_controllers' ? ' selected' : '' ?>><?= e($lse('scope_views_controllers')) ?></option>
        </select>
      </div>
      <div class="lse-form-group">
        <label for="lse-locale"><?= e($lse('reference_label')) ?></label>
        <select id="lse-locale" name="locale">
          <option value="en"<?= $selectedLocale === 'en' ? ' selected' : '' ?>><?= e($lse('reference_default')) ?></option>
          <option value="ja"<?= $selectedLocale === 'ja' ? ' selected' : '' ?>>&#26085;&#26412;&#35486;</option>
          <option value="ne"<?= $selectedLocale === 'ne' ? ' selected' : '' ?>>&#2344;&#2375;&#2346;&#2366;&#2354;&#2368;</option>
        </select>
      </div>
      <div class="lse-form-group">
        <button class="lse-btn" id="lse-run-btn" type="submit" data-full-scan-label="<?= e($lse('full_scan_button')) ?>"<?= !$ownerDiscoveryWired ? ' disabled' : '' ?>><?= e($lse('run_scan')) ?></button>
      </div>
    </div>
    <div class="lse-progress-wrap" id="lse-scan-progress" hidden>
      <div class="lse-progress-meta">
        <span id="lse-progress-label"><?= e($lse('scan_progress_starting')) ?></span>
        <span id="lse-progress-percent">0%</span>
      </div>
      <div class="lse-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
        <div class="lse-progress-fill" id="lse-progress-fill"></div>
      </div>
      <div class="lse-progress-note" id="lse-progress-note"><?= e($lse('scan_progress_note')) ?></div>
    </div>
  </form>

  <section class="lse-panel lse-results-panel" id="lse-results-panel" hidden aria-live="polite">
    <div class="lse-panel-heading-row">
      <div>
        <h3><?= e($lse('async_results_title')) ?></h3>
        <p class="lse-panel-desc"><?= e($lse('async_results_desc')) ?></p>
      </div>
    </div>
    <div id="lse-async-results"></div>
  </section>

  <?php if ($scanOk): ?>
  <div class="lse-panel">
    <h3><?= e($lse('correction_summary_title')) ?></h3>
    <p class="lse-panel-desc"><?= e($lse('correction_summary_desc')) ?></p>
    <?php if ($scanOk): ?>
    <div class="lse-summary-correction">
      <?php
        $csMissing = (int)($scanSummary['missing_owner_keys'] ?? 0);
        $csHuman = (int)($scanSummary['human_facing_candidates'] ?? 0);
        $csAmbiguous = (int)($scanSummary['ambiguous_strings'] ?? 0);
        $csTotal = $csMissing + $csHuman + $csAmbiguous;
        $csPlanFiles = [];
        foreach ($missingKeyReviewRows as $prow) {
            $pf = (string)($prow['first_file'] ?? '');
            if ($pf !== '' && !in_array($pf, $csPlanFiles, true)) $csPlanFiles[] = $pf;
        }
        $csFileCount = count($csPlanFiles);
        $hasCorrectionTargets = $csTotal > 0;
      ?>
      <span class="lse-correction-summary-count"><?= (int)$csTotal ?></span>
      <span class="lse-correction-summary-label"><?= e($lse('correction_summary_targets')) ?></span>
      <span class="lse-correction-summary-sep">&mdash;</span>
      <span class="lse-correction-summary-count"><?= (int)$csFileCount ?></span>
      <span class="lse-correction-summary-label"><?= e($lse('correction_summary_files')) ?></span>
      <?php if ($hasCorrectionTargets): ?>
      <span class="lse-badge is-safe"><?= e($lse('correction_ready_badge')) ?></span>
      <?php endif; ?>
      <?php if ($selectedOwner !== ''): ?>
      <span class="lse-correction-summary-owner"><code><?= e($selectedOwner) ?></code></span>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="lse-summary-empty-state lse-empty-note"><?= e($lse('summary_empty_state')) ?></div>
    <?php endif; ?>
  </div>

  <?php if ($scanOk && $missingKeyReviewRows !== []): ?>
  <div class="lse-panel">
    <h3><?= e($lse('correction_plan_title')) ?></h3>
    <p class="lse-panel-desc"><?= e($lse('correction_plan_desc')) ?></p>
    <?php
      $planFiles = [];
      $planLocales = 0;
      foreach ($missingKeyReviewRows as $prow) {
          $pf = (string)($prow['first_file'] ?? '');
          if ($pf !== '' && !in_array($pf, $planFiles, true)) {
              $planFiles[] = $pf;
          }
      }
      $humanCount = (int)($scanSummary['human_facing_candidates'] ?? 0);
      $ambigCount = (int)($scanSummary['ambiguous_strings'] ?? 0);
      $missingCount = (int)($scanSummary['missing_owner_keys'] ?? 0);
      $hasPlan = $missingCount > 0;
    ?>
    <div class="lse-plan-grid">
      <div class="lse-plan-item">
        <div class="lse-plan-value"><?= $missingCount ?></div>
        <div class="lse-plan-label"><?= e($lse('correction_plan_missing')) ?></div>
      </div>
      <div class="lse-plan-item">
        <div class="lse-plan-value"><?= $humanCount ?></div>
        <div class="lse-plan-label"><?= e($lse('correction_plan_human')) ?></div>
      </div>
      <div class="lse-plan-item">
        <div class="lse-plan-value"><?= $ambigCount ?></div>
        <div class="lse-plan-label"><?= e($lse('correction_plan_ambiguous')) ?></div>
      </div>
      <div class="lse-plan-item">
        <div class="lse-plan-value"><?= count($planFiles) ?></div>
        <div class="lse-plan-label"><?= e($lse('correction_plan_files_affected')) ?></div>
      </div>
    </div>
    <div class="lse-plan-recommendation <?= $hasPlan ? '' : 'is-empty' ?>">
      <?= e($lse('correction_plan_recommended')) ?>: <strong><?= e($lse('correction_plan_missing')) ?></strong>
      <?php if ($selectedOwner !== ''): ?>
        &mdash; <?= e($selectedOwner) ?>
      <?php endif; ?>
    </div>
  </div>
  <?php elseif ($scanOk):
    $scanHumanCount = (int)($scanSummary['human_facing_candidates'] ?? 0);
  ?>
  <div class="lse-panel">
    <h3><?= e($lse('correction_plan_title')) ?></h3>
    <p class="lse-panel-desc"><?= e($lse('correction_plan_desc')) ?></p>
    <div class="lse-plan-grid">
      <div class="lse-plan-item">
        <div class="lse-plan-value">0</div>
        <div class="lse-plan-label"><?= e($lse('correction_plan_missing')) ?></div>
      </div>
    </div>
    <div class="lse-plan-recommendation <?= $scanHumanCount > 0 ? 'is-info' : 'is-empty' ?>">
      <?php if ($scanHumanCount > 0): ?>
        <?= e(sprintf($lse('no_corrections_findings_available'), $scanHumanCount)) ?>
      <?php else: ?>
        <?= e($lse('corrections_empty_none')) ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="lse-panel" id="lse-missing-key-review-plan">
    <div class="lse-panel-heading-row">
      <div>
        <h3><?= e($lse('correction_workspace_title')) ?></h3>
        <p class="lse-panel-desc"><?= e($lse('correction_workspace_desc')) ?></p>
      </div>
      <?php if ($missingKeyReviewRows !== []): ?>
        <button class="lse-btn is-secondary" id="lse-copy-review-plan" type="button" data-copy-label="<?= e($lse('review_plan_copy')) ?>" data-copied-label="<?= e($lse('review_plan_copied')) ?>" data-failed-label="<?= e($lse('review_plan_copy_failed')) ?>"><?= e($lse('review_plan_copy')) ?></button>
      <?php endif; ?>
    </div>
    <?php if ($scanOk && $missingKeyReviewRows === []): ?>
      <div class="lse-empty-state is-compact">
        <div class="lse-empty-state-title"><?= e($lse('review_plan_empty')) ?></div>
      </div>
    <?php elseif (!$scanOk): ?>
      <div class="lse-empty-state is-compact">
        <div class="lse-empty-state-title"><?= e($lse('no_results')) ?></div>
      </div>
    <?php else: ?>
      <div class="lse-action-form-row">
        <form class="lse-action-form" method="post" action="/apps/studio/tools/localization-scan-extraction/add-missing-keys">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="owner" value="<?= e($selectedOwner) ?>">
          <input type="hidden" name="scope" value="<?= e($selectedScope) ?>">
          <input type="hidden" name="locale" value="en">
          <button class="lse-btn" type="submit" onclick="return confirm('<?= e($lse('correction_confirm_add_missing')) ?>')"><?= e($lse('correction_add_missing_keys')) ?></button>
        </form>
        <form class="lse-action-form" method="post" action="/apps/studio/tools/localization-scan-extraction/add-selected-keys" id="lse-add-selected-form">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="owner" value="<?= e($selectedOwner) ?>">
          <input type="hidden" name="scope" value="<?= e($selectedScope) ?>">
          <input type="hidden" name="locale" value="en">
          <div id="lse-selected-keys-container"></div>
          <button class="lse-btn" type="submit" id="lse-add-selected-btn" disabled onclick="return confirm('<?= e($lse('correction_add_selected_keys')) ?>')"><?= e($lse('add_selected_keys_btn')) ?></button>
          <span class="lse-selected-count" id="lse-selected-count"><?= e($lse('add_selected_none_selected')) ?></span>
        </form>
      </div>
      <p class="lse-readonly-note"><?= e($lse('review_plan_advisory')) ?></p>
      <?php foreach ($missingKeyReviewGroups as $group): ?>
        <?php $groupRows = isset($group['rows']) && is_array($group['rows']) ? $group['rows'] : []; ?>
        <div class="lse-review-group">
          <div class="lse-review-group-heading">
            <span class="lse-review-group-name"><?= e((string)($group['group'] ?? '')) ?></span>
            <span class="lse-review-group-count"><?= (int)($group['count'] ?? count($groupRows)) ?></span>
          </div>
          <div class="lse-table-wrap">
            <table class="lse-table lse-review-table" data-group-name="<?= e((string)($group['group'] ?? '')) ?>">
              <thead>
                <tr>
                  <th class="lse-col-select"><input type="checkbox" class="lse-select-all-checkbox" title="<?= e($lse('select_all_checkbox')) ?>" aria-label="<?= e($lse('select_all_label')) ?>"></th>
                  <th><?= e($lse('review_plan_key')) ?></th>
                  <th><?= e($lse('review_plan_file')) ?></th>
                  <th><?= e($lse('review_plan_line')) ?></th>
                  <th><?= e($lse('review_plan_usage_count')) ?></th>
                  <th><?= e($lse('review_plan_group')) ?></th>
                  <th><?= e($lse('review_plan_suggestion')) ?></th>
                  <th><?= e($lse('review_plan_confidence')) ?></th>
                  <th><?= e($lse('review_plan_action')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($groupRows as $row): ?>
                  <?php $rowKey = (string)($row['key'] ?? ''); ?>
                  <?php $suggestedValue = (string)($row['suggested_english_value'] ?? ''); ?>
                  <?php $suggestionConfidence = (string)($row['suggestion_confidence'] ?? 'none'); ?>
                  <tr>
                    <td class="lse-col-select"><input type="checkbox" class="lse-key-checkbox" value="<?= e($rowKey) ?>"></td>
                    <td><code><?= e($rowKey) ?></code></td>
                    <td class="lse-cell-file"><?= e((string)($row['first_file'] ?? '')) ?></td>
                    <td><?= (int)($row['first_line'] ?? 0) > 0 ? (int)($row['first_line'] ?? 0) : '-' ?></td>
                    <td><?= (int)($row['usage_count'] ?? 0) ?></td>
                    <td><code><?= e((string)($row['group'] ?? '')) ?></code></td>
                    <td><?= $suggestedValue !== '' ? e($suggestedValue) : '<span class="lse-muted">' . e($lse('review_plan_no_suggestion')) . '</span>' ?></td>
                    <td><span class="lse-confidence lse-conf-<?= e($suggestionConfidence) ?>"><?= e($lse('confidence_' . $suggestionConfidence)) ?></span></td>
                    <td>
                      <?php if (!empty($row['handoff_url'])): ?>
                        <a class="lse-btn is-link lse-handoff-link" href="<?= e((string)$row['handoff_url']) ?>"><?= e($lse('action_open_localization_studio')) ?></a>
                      <?php else: ?>
                        <span class="lse-muted"><?= e($lse('action_select_hint')) ?></span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endforeach; ?>
      <script>
      (function() {
        var form = document.getElementById('lse-add-selected-form');
        var container = document.getElementById('lse-selected-keys-container');
        var btn = document.getElementById('lse-add-selected-btn');
        var countEl = document.getElementById('lse-selected-count');
        var allCheckboxes = document.querySelectorAll('.lse-key-checkbox');
        var selectAllBoxes = document.querySelectorAll('.lse-select-all-checkbox');

        function updateSelection() {
          var checked = [];
          allCheckboxes.forEach(function(cb) {
            if (cb.checked) checked.push(cb.value);
          });
          container.innerHTML = '';
          checked.forEach(function(key) {
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'selected_keys[]';
            inp.value = key;
            container.appendChild(inp);
          });
          if (checked.length > 0) {
            btn.disabled = false;
            countEl.textContent = checked.length + ' <?= e($lse('keys_label')) ?>';
          } else {
            btn.disabled = true;
            countEl.textContent = '<?= e($lse('add_selected_none_selected')) ?>';
          }
        }

        allCheckboxes.forEach(function(cb) {
          cb.addEventListener('change', updateSelection);
        });

        selectAllBoxes.forEach(function(sa) {
          sa.addEventListener('change', function() {
            var table = sa.closest('table');
            var cbs = table.querySelectorAll('.lse-key-checkbox');
            cbs.forEach(function(cb) { cb.checked = sa.checked; });
            updateSelection();
          });
        });
      })();
      </script>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($correctionReport !== null): ?>
  <div class="lse-report">
    <h3 class="lse-report-title"><?= e($lse('operation_report_title')) ?></h3>
    <div class="lse-report-body">
      <div class="lse-report-meta">
        <span class="lse-report-meta-item">
          <strong><?= e($lse('operation_label')) ?>:</strong>
          <span class="lse-badge lse-badge-sm <?= $correctionReport['success'] ? 'is-safe' : 'is-error' ?>">
            <?php
              $reportActionLabel = $lse('add_missing_keys_label');
              if ($correctionReport['action'] === 'extract_inline_text') {
                  $reportActionLabel = $lse('extract_inline_label');
              } elseif ($correctionReport['action'] === 'add_selected_keys') {
                  $reportActionLabel = $lse('add_selected_keys_label');
              }
            ?>
            <?= e($reportActionLabel) ?>
          </span>
        </span>
        <span class="lse-report-meta-item">
          <strong><?= e($lse('status_label')) ?>:</strong>
          <span class="lse-badge lse-badge-sm <?= $correctionReport['success'] ? 'is-safe' : 'is-error' ?>">
            <?= $correctionReport['success'] ? e($lse('status_success')) : e($lse('status_failed')) ?>
          </span>
        </span>
        <span class="lse-report-meta-item"><strong><?= e($lse('owner_label')) ?>:</strong> <?= e($correctionReport['owner_key']) ?></span>
        <span class="lse-report-meta-item"><strong><?= e($lse('time_label')) ?>:</strong> <?= e($correctionReport['occurred_at']) ?></span>
        <?php if ($correctionReport['added_count'] > 0): ?>
        <span class="lse-report-meta-item"><strong><?= e($lse('keys_added_label')) ?>:</strong> <?= (int)$correctionReport['added_count'] ?></span>
        <?php endif; ?>
        <?php if (!empty($correctionReport['error'])): ?>
        <span class="lse-report-meta-item lse-report-error"><strong><?= e($lse('error_label')) ?>:</strong> <?= e($correctionReport['error']) ?></span>
        <?php endif; ?>
        <?php if (!empty($correctionReport['rollback_status'])): ?>
        <span class="lse-report-meta-item"><strong><?= e($lse('rollback_status_label')) ?>:</strong> <?= e($lse('rollback_status_' . $correctionReport['rollback_status'])) ?></span>
        <?php endif; ?>
      </div>

      <?php if (!empty($correctionReport['added_keys'])): ?>
      <details class="lse-report-details">
        <summary><?= e($lse('added_keys_title')) ?> (<?= count($correctionReport['added_keys']) ?>)</summary>
        <table class="lse-report-table">
          <tr><th><?= e($lse('key_label')) ?></th><th><?= e($lse('value_label')) ?></th></tr>
          <?php foreach ($correctionReport['added_keys'] as $ak): ?>
          <tr><td><code><?= e($ak['key'] ?? '') ?></code></td><td><?= e($ak['value'] ?? '') ?></td></tr>
          <?php endforeach; ?>
        </table>
      </details>
      <?php endif; ?>

      <?php if (!empty($correctionReport['re_scan_diagnostics'])): ?>
      <details class="lse-report-details" <?= !empty($correctionReport['re_scan_diagnostics']['all_clear']) ? '' : 'open' ?>>
        <summary><?= e($lse('re_scan_title')) ?></summary>
        <div class="lse-report-diags">
          <?php $diags = $correctionReport['re_scan_diagnostics']; ?>
          <?php if (!empty($diags['scan_ok'])): ?>
          <p><?= e($lse('total_findings_label')) ?>: <?= (int)($diags['total_findings'] ?? 0) ?></p>
          <p><?= e($lse('inline_remaining_label')) ?>: <?= (int)($diags['inline_text'] ?? 0) ?></p>
          <p><?= e($lse('missing_remaining_label')) ?>: <?= (int)($diags['missing_owner_keys'] ?? 0) ?></p>
          <p><?= e($lse('pending_keys_label')) ?>: <?= (int)($diags['pending_missing_keys'] ?? 0) ?></p>
          <span class="lse-badge lse-badge-sm <?= !empty($diags['all_clear']) ? 'is-safe' : 'is-warn' ?>">
            <?= !empty($diags['all_clear']) ? e($lse('all_clear_label')) : e($lse('issues_remain_label')) ?>
          </span>
          <?php if (array_key_exists('re_scan_confirmed', $correctionReport)): ?>
          <span class="lse-badge lse-badge-sm <?= $correctionReport['re_scan_confirmed'] ? 'is-safe' : 'is-warn' ?>">
            <?= $correctionReport['re_scan_confirmed'] ? e($lse('re_scan_confirmed_badge')) : e($lse('re_scan_not_confirmed_badge')) ?>
          </span>
          <?php endif; ?>
          <?php else: ?>
          <p class="lse-report-error"><?= e($diags['error'] ?? $lse('re_scan_failed_label')) ?></p>
          <?php endif; ?>
        </div>
      </details>
      <?php endif; ?>

      <?php if (!empty($correctionReport['snapshot_paths'])): ?>
      <div class="lse-report-snapshots">
        <strong><?= e($lse('snapshots_label')) ?>:</strong>
        <ul>
          <?php foreach ($correctionReport['snapshot_paths'] as $label => $path): ?>
          <li><code><?= e($label) ?>: <?= e($path ?? '(none)') ?></code></li>
          <?php endforeach; ?>
        </ul>
        <?php if (!$correctionReport['success']): ?>
        <form method="post" action="/apps/studio/tools/localization-scan-extraction/rollback" style="margin-top:8px">
          <input type="hidden" name="csrf" value="<?= e($csrfToken ?? '') ?>">
          <input type="hidden" name="owner" value="<?= e($correctionReport['owner_key']) ?>">
          <input type="hidden" name="scope" value="<?= e($selectedScope) ?>">
          <input type="hidden" name="locale" value="<?= e($selectedLocale) ?>">
          <?php foreach ($correctionReport['snapshot_paths'] as $lk => $lp): ?>
          <?php if ($lp !== null): ?>
          <?php $snapshotBase = APP_ROOT . '/storage/studio-snapshots/localization-scan-extraction/'; ?>
          <?php $snapshotKey = str_starts_with((string)$lp, $snapshotBase) ? substr((string)$lp, strlen($snapshotBase)) : basename((string)$lp); ?>
          <input type="hidden" name="snapshot_keys[]" value="<?= e($snapshotKey) ?>">
          <?php endif; ?>
          <?php endforeach; ?>
          <button type="submit" class="lse-btn lse-btn-sm" onclick="return confirm('<?= e($lse('rollback_confirm')) ?>')"><?= e($lse('rollback_button')) ?></button>
        </form>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($historyReports !== []): ?>
  <div class="lse-history" id="lse-history">
    <details class="lse-collapsible">
      <summary class="lse-collapsible-summary">
        <h3><?= e($lse('operation_history_title')) ?> (<?= count($historyReports) ?>)</h3>
      </summary>
      <ul class="lse-history-list">
        <?php
          $historyGroups = [];
          foreach ($historyReports as $hr) {
              $occurredTs = strtotime($hr->occurredAt) ?: time();
              $bucketTs = (int)(floor($occurredTs / 300) * 300);
              $bucketKey = date('Y-m-d H:i', $bucketTs);
              $groupKey = $hr->ownerKey . '|' . $bucketKey;
              if (!isset($historyGroups[$groupKey])) {
                  $historyGroups[$groupKey] = ['reports' => [], 'count' => 0, 'bucket' => $bucketKey];
              }
              $historyGroups[$groupKey]['reports'][] = $hr;
              $historyGroups[$groupKey]['count']++;
          }
        ?>
        <?php foreach ($historyGroups as $group): ?>
        <?php
          $groupCount = $group['count'];
          $groupReports = $group['reports'];
          $hr = $groupReports[0];
          $groupAddedCount = 0;
          $hasRollbackAvailable = false;
          $hasRollbackApplied = false;
          $hasVerification = false;
          $allVerified = true;
          $hasSuccessfulCorrection = false;
          $hasFailedCorrection = false;
          foreach ($groupReports as $reportItem) {
              if ($reportItem->action !== 'rollback') {
                  $groupAddedCount += (int)$reportItem->addedCount;
                  if ($reportItem->success) {
                      $hasSuccessfulCorrection = true;
                  } else {
                      $hasFailedCorrection = true;
                  }
              }
              if (!empty($reportItem->snapshotPaths)) {
                  $hasRollbackAvailable = true;
              }
              if ($reportItem->action === 'rollback' || !empty($reportItem->rollbackStatus)) {
                  $hasRollbackApplied = true;
              }
              if ($reportItem->reScanConfirmed !== null) {
                  $hasVerification = true;
                  if (!$reportItem->reScanConfirmed) {
                      $allVerified = false;
                  }
              }
          }
          $orderedReports = $groupReports;
          usort($orderedReports, static fn($a, $b): int => strcmp($a->occurredAt, $b->occurredAt));
          $pendingBefore = null;
          $pendingAfter = null;
          foreach ($orderedReports as $reportItem) {
              $diags = is_array($reportItem->reScanDiagnostics) ? $reportItem->reScanDiagnostics : [];
              if ($pendingBefore === null && array_key_exists('before_pending_missing_keys', $diags)) {
                  $pendingBefore = $diags['before_pending_missing_keys'];
              }
              if (array_key_exists('after_pending_missing_keys', $diags)) {
                  $pendingAfter = $diags['after_pending_missing_keys'];
              } elseif (array_key_exists('pending_missing_keys', $diags)) {
                  $pendingAfter = $diags['pending_missing_keys'];
              }
          }
          $outcomeLabel = $lse('history_outcome_successful');
          $outcomeClass = 'is-success';
          if ($hasRollbackApplied) {
              $outcomeLabel = $lse('history_outcome_recovered');
              $outcomeClass = 'is-success';
          } elseif ($hasVerification && !$allVerified) {
              $outcomeLabel = $lse('history_outcome_verification_failed');
              $outcomeClass = 'is-failed';
          } elseif (!$hasSuccessfulCorrection && $hasFailedCorrection) {
              $outcomeLabel = $lse('history_outcome_failed');
              $outcomeClass = 'is-failed';
          }
        ?>
        <li class="lse-history-item">
          <span class="lse-history-icon <?= e($outcomeClass) ?>"><?= $outcomeClass === 'is-success' ? '&#10003;' : '&#10007;' ?></span>
          <div class="lse-history-body">
            <div class="lse-history-title">
              <?= e($outcomeLabel) ?>
              <?php if ($groupCount > 1): ?>
              <span class="lse-badge lse-badge-sm">&#215;<?= (int)$groupCount ?></span>
              <?php endif; ?>
            </div>
            <div class="lse-history-meta">
              <span><?= e($hr->ownerKey) ?></span>
              <span><?= e($group['bucket']) ?></span>
              <?php if ($groupAddedCount > 0): ?>
              <span>+<?= (int)$groupAddedCount ?> <?= e($lse('keys_label')) ?></span>
              <?php endif; ?>
              <?php if ($pendingBefore !== null && $pendingAfter !== null): ?>
              <span class="lse-history-impact"><?= e($lse('pending_keys_label')) ?>: <?= (int)$pendingBefore ?> &rarr; <?= (int)$pendingAfter ?></span>
              <?php endif; ?>
              <?php if ($hasRollbackAvailable || $hasRollbackApplied): ?>
              <span class="lse-badge lse-badge-sm is-info"><?= e($lse('history_rollback_available')) ?></span>
              <?php endif; ?>
              <?php if ($hasVerification): ?>
              <span class="lse-badge lse-badge-sm <?= $allVerified ? 'is-safe' : 'is-warn' ?>">
                <?= $allVerified ? e($lse('history_verified')) : e($lse('history_not_verified')) ?>
              </span>
              <?php endif; ?>
            </div>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    </details>
  </div>
  <?php endif; ?>

  <?php if ($scanOk || $fullScanResults !== null): ?>
  <div class="lse-panel lse-section-advanced">
    <details class="lse-collapsible">
      <summary class="lse-collapsible-summary">
        <h3><?= e($lse('engineering_diagnostics_title')) ?> <span class="lse-section-badge"><?= e($lse('category_filter_advanced')) ?></span></h3>
        <p class="lse-panel-desc"><?= e($lse('engineering_diagnostics_desc')) ?></p>
      </summary>

    <?php if ($fullScanResults !== null): ?>
    <div class="lse-full-scan-subsection">
      <h4><?= e($lse('full_scan_results_title')) ?></h4>
      <p class="lse-panel-desc"><?= e($lse('full_scan_results_desc')) ?></p>
      <div class="lse-summary-grid">
        <div class="lse-summary-card has-data">
          <div class="lse-summary-value"><?= (int)($fullScanResults['totals']['owners'] ?? 0) ?></div>
          <div class="lse-summary-label"><?= e($lse('full_owners_label')) ?></div>
        </div>
        <div class="lse-summary-card has-data">
          <div class="lse-summary-value"><?= (int)($fullScanResults['totals']['total_findings'] ?? 0) ?></div>
          <div class="lse-summary-label"><?= e($lse('full_findings_label')) ?></div>
        </div>
        <div class="lse-summary-card has-data">
          <div class="lse-summary-value"><?= (int)($fullScanResults['totals']['human_facing_candidates'] ?? 0) ?></div>
          <div class="lse-summary-label"><?= e($lse('human_facing')) ?></div>
        </div>
        <div class="lse-summary-card has-data">
          <div class="lse-summary-value"><?= (int)($fullScanResults['totals']['missing_owner_keys'] ?? 0) ?></div>
          <div class="lse-summary-label"><?= e($lse('type_missing_key')) ?></div>
        </div>
        <div class="lse-summary-card has-data">
          <div class="lse-summary-value"><?= (int)($fullScanResults['totals']['ambiguous_strings'] ?? 0) ?></div>
          <div class="lse-summary-label"><?= e($lse('ambiguous_strings')) ?></div>
        </div>
        <div class="lse-summary-card has-data">
          <div class="lse-summary-value"><?= (int)($fullScanResults['totals']['ready_missing_key_corrections'] ?? $fullScanResults['totals']['ready_auto_fix_keys'] ?? 0) ?></div>
          <div class="lse-summary-label"><?= e($lse('ops_ready_missing_total')) ?></div>
        </div>
        <div class="lse-summary-card has-data">
          <div class="lse-summary-value"><?= (int)($fullScanResults['totals']['ready_inline_migrations'] ?? 0) ?></div>
          <div class="lse-summary-label"><?= e($lse('ops_ready_inline_total')) ?></div>
        </div>
        <div class="lse-summary-card has-data">
          <div class="lse-summary-value"><?= (int)($fullScanResults['totals']['needs_review_total'] ?? $fullScanResults['totals']['needs_review_keys'] ?? 0) ?></div>
          <div class="lse-summary-label"><?= e($lse('needs_review')) ?></div>
        </div>
        <div class="lse-summary-card has-data">
          <div class="lse-summary-value"><?= (int)($fullScanResults['totals']['rejected_total'] ?? $fullScanResults['totals']['rejected_unsafe_keys'] ?? 0) ?></div>
          <div class="lse-summary-label"><?= e($lse('migration_rejected_label')) ?></div>
        </div>
      </div>
      <?php
        $fullReviewReasons = isset($fullScanResults['totals']['review_reason_counts']) && is_array($fullScanResults['totals']['review_reason_counts'])
            ? $fullScanResults['totals']['review_reason_counts']
            : [];
        $fullReviewPriorities = isset($fullScanResults['totals']['review_priority_counts']) && is_array($fullScanResults['totals']['review_priority_counts'])
            ? $fullScanResults['totals']['review_priority_counts']
            : [];
      ?>
      <?php if ((int)($fullScanResults['totals']['needs_review_total'] ?? $fullScanResults['totals']['needs_review_keys'] ?? 0) > 0): ?>
      <details class="lse-key-quality-summary lse-review-intelligence lse-system-review-intelligence">
        <summary>
          <span class="lse-intelligence-relevance-title"><?= e($lse('review_intelligence_title')) ?></span>
          <span class="lse-review-summary-chip"><?= e($lse('review_priority_likely_migratable')) ?>: <?= (int)($fullReviewPriorities['likely_migratable'] ?? 0) ?></span>
          <span class="lse-review-summary-chip"><?= e($lse('review_priority_manual_review')) ?>: <?= (int)($fullReviewPriorities['manual_review'] ?? 0) ?></span>
          <span class="lse-review-summary-chip"><?= e($lse('review_priority_blocked')) ?>: <?= (int)($fullReviewPriorities['blocked'] ?? 0) ?></span>
        </summary>
        <div class="lse-relevance-bar">
          <span class="lse-relevance-item lse-priority-likely_migratable"><?= e($lse('review_priority_likely_migratable')) ?>: <?= (int)($fullReviewPriorities['likely_migratable'] ?? 0) ?></span>
          <span class="lse-relevance-item lse-priority-manual_review"><?= e($lse('review_priority_manual_review')) ?>: <?= (int)($fullReviewPriorities['manual_review'] ?? 0) ?></span>
          <span class="lse-relevance-item lse-priority-blocked"><?= e($lse('review_priority_blocked')) ?>: <?= (int)($fullReviewPriorities['blocked'] ?? 0) ?></span>
        </div>
        <div class="lse-review-breakdown">
          <?php foreach (['domain_vocabulary','ambiguous_context','long_description','duplicate_candidate','technical_term','naming_uncertain','reusable_ui_term','mixed_usage','other'] as $reasonKey): ?>
            <?php $reasonCount = (int)($fullReviewReasons[$reasonKey] ?? 0); ?>
            <?php if ($reasonCount > 0): ?>
            <span class="lse-review-breakdown-item"><span><?= e($lse('review_reason_' . $reasonKey)) ?></span><strong><?= $reasonCount ?></strong></span>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </details>
      <?php endif; ?>
      <?php if (!empty($fullScanResults['owner_results'])): ?>
      <details class="lse-full-scan-owners">
        <summary><?= e($lse('full_owner_results_title')) ?> (<?= count($fullScanResults['owner_results']) ?>)</summary>
        <table class="lse-report-table">
          <tr>
            <th><?= e($lse('owner_label')) ?></th>
            <th><?= e($lse('ops_missing_ready_col')) ?></th>
            <th><?= e($lse('ops_inline_ready_col')) ?></th>
            <th><?= e($lse('needs_review')) ?></th>
            <th><?= e($lse('migration_rejected_label')) ?></th>
            <th><?= e($lse('status_label')) ?></th>
            <th><?= e($lse('col_action')) ?></th>
          </tr>
          <?php foreach ($fullScanResults['owner_results'] as $ok => $or): ?>
          <?php
            $readyMissingOps = (int)($or['ready_missing_key_corrections'] ?? $or['ready_auto_fix_keys'] ?? 0);
            $readyInlineOps = (int)($or['inline_ready_to_migrate'] ?? 0);
            $needsReviewOps = (int)($or['needs_review_total'] ?? $or['needs_review_keys'] ?? 0);
            $rejectedOps = (int)($or['rejected_total'] ?? $or['rejected_unsafe_keys'] ?? 0);
            $readyOps = $readyMissingOps + $readyInlineOps;
            $opsStatusLabel = $lse('correction_status_clean');
            $opsStatusClass = 'is-safe';
            if (empty($or['scan_ok'])) {
                $opsStatusLabel = $lse('status_failed');
                $opsStatusClass = 'is-error';
            } elseif ($readyOps > 0 && ($needsReviewOps > 0 || $rejectedOps > 0)) {
                $opsStatusLabel = $lse('ops_status_mixed');
                $opsStatusClass = 'is-warn';
            } elseif ($readyOps > 0) {
                $opsStatusLabel = $lse('ops_status_ready_to_fix');
                $opsStatusClass = 'is-safe';
            } elseif ($needsReviewOps > 0 || $rejectedOps > 0) {
                $opsStatusLabel = $lse('correction_status_review_needed');
                $opsStatusClass = 'is-info';
            }
            $opsActionLabel = $readyMissingOps > 0
                ? $lse('ops_action_correct')
                : ($readyInlineOps > 0 ? $lse('ops_action_migrate') : $lse('ops_action_review'));
          ?>
          <tr>
            <td><code><?= e($ok) ?></code></td>
            <td><?= $readyMissingOps ?></td>
            <td><?= $readyInlineOps ?></td>
            <td>
              <?= $needsReviewOps ?>
              <?php if (!empty($or['review_reason_counts']) && is_array($or['review_reason_counts'])): ?>
              <div class="lse-review-breakdown">
                <?php foreach (['domain_vocabulary','ambiguous_context','long_description','duplicate_candidate','technical_term','naming_uncertain','reusable_ui_term','mixed_usage','other'] as $reasonKey): ?>
                  <?php $reasonCount = (int)($or['review_reason_counts'][$reasonKey] ?? 0); ?>
                  <?php if ($reasonCount > 0): ?>
                  <span class="lse-review-breakdown-item"><span><?= e($lse('review_reason_' . $reasonKey)) ?></span><strong><?= $reasonCount ?></strong></span>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </td>
            <td><?= $rejectedOps ?></td>
            <td><span class="lse-badge lse-badge-sm <?= e($opsStatusClass) ?>"><?= e($opsStatusLabel) ?></span></td>
            <td><?= e($opsActionLabel) ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
      </details>
      <?php endif; ?>
      <?php if (!empty($fullScanResults['errors'])): ?>
      <div class="lse-full-scan-errors">
        <strong><?= e($lse('full_scan_errors_label')) ?>:</strong>
        <ul>
          <?php foreach ($fullScanResults['errors'] as $err): ?>
          <li><code><?= e($err['owner'] ?? '') ?></code>: <?= e($err['error'] ?? '') ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
      $workbenchReadyMissing = 0;
      $workbenchRejectedMissing = 0;
      foreach ($missingKeyReviewRows as $reviewRow) {
          if (!empty($reviewRow['suggestion_ready'])) {
              $workbenchReadyMissing++;
          }
          if ((string)($reviewRow['suggestion_rejection_reason'] ?? '') !== '') {
              $workbenchRejectedMissing++;
          }
      }
      $workbenchReadyInline = (int)($migrationCounts['ready_to_migrate'] ?? 0);
      $workbenchNeedsReview = max(0, count($missingKeyReviewRows) - $workbenchReadyMissing - $workbenchRejectedMissing) + (int)($migrationCounts['needs_review'] ?? 0);
      $workbenchRejected = $workbenchRejectedMissing + (int)($migrationCounts['rejected'] ?? 0);
      $workbenchFiles = [];
      foreach ($scanFindings as $finding) {
          $file = (string)($finding['file'] ?? '');
          if ($file !== '') {
              $workbenchFiles[$file] = true;
          }
      }
      $workbenchFilesAffected = count($workbenchFiles);
    ?>
    <?php if ($scanOk): ?>
    <section class="lse-owner-workbench" id="lse-owner-workbench">
      <div class="lse-async-heading">
        <h4><?= e($lse('owner_workbench_title')) ?>: <code><?= e($selectedOwner) ?></code></h4>
        <span class="lse-badge is-info"><?= e($lse('owner_result_title')) ?></span>
      </div>
      <p class="lse-panel-desc"><?= e($lse('owner_workbench_desc')) ?></p>
      <div class="lse-summary-grid">
        <div class="lse-summary-card has-data lse-cat-human_facing"><div class="lse-summary-value"><?= (int)($scanSummary['human_facing_candidates'] ?? 0) ?></div><div class="lse-summary-label"><?= e($lse('owner_workbench_owner_summary')) ?></div></div>
        <div class="lse-summary-card has-data lse-cat-already_localized"><div class="lse-summary-value"><?= $workbenchReadyMissing ?></div><div class="lse-summary-label"><?= e($lse('owner_workbench_ready_missing')) ?></div></div>
        <div class="lse-summary-card has-data lse-cat-human_facing"><div class="lse-summary-value"><?= $workbenchReadyInline ?></div><div class="lse-summary-label"><?= e($lse('owner_workbench_ready_inline')) ?></div></div>
        <div class="lse-summary-card has-data lse-cat-ambiguous_string"><div class="lse-summary-value"><?= $workbenchNeedsReview ?></div><div class="lse-summary-label"><?= e($lse('needs_review')) ?></div></div>
        <div class="lse-summary-card has-data lse-cat-missing_owner_key"><div class="lse-summary-value"><?= $workbenchRejected ?></div><div class="lse-summary-label"><?= e($lse('migration_rejected_label')) ?></div></div>
        <div class="lse-summary-card has-data"><div class="lse-summary-value"><?= $workbenchFilesAffected ?></div><div class="lse-summary-label"><?= e($lse('owner_workbench_files_affected')) ?></div></div>
      </div>
      <div class="lse-owner-workbench-actions">
        <?php if ($workbenchReadyMissing > 0): ?>
        <a class="lse-btn is-secondary" href="#lse-change-preview"><?= e($lse('apply_safe_corrections')) ?></a>
        <?php endif; ?>
        <?php if ($workbenchReadyInline > 0): ?>
        <a class="lse-btn is-secondary" href="#lse-inline-migration-preview"><?= e($lse('migration_apply_button')) ?></a>
        <?php endif; ?>
        <a class="lse-btn is-secondary" href="#lse-governance"><?= e($lse('owner_workbench_rollback_link')) ?></a>
        <a class="lse-btn is-secondary" href="#lse-history"><?= e($lse('owner_workbench_history_link')) ?></a>
      </div>
    </section>
    <?php endif; ?>

    <h4><?= e($lse('summary_title')) ?></h4>
    <?php if (!$scanOk): ?>
    <div class="lse-summary-empty-state lse-empty-note"><?= e($lse('summary_empty_state')) ?></div>
    <?php endif; ?>
    <div class="lse-summary-grid">
      <div class="lse-summary-card lse-cat-human_facing<?= $scanOk && ($scanSummary['human_facing_candidates'] ?? 0) > 0 ? ' has-data' : '' ?>">
        <div class="lse-summary-value"><?= (int)($scanSummary['human_facing_candidates'] ?? 0) ?></div>
        <div class="lse-summary-label"><?= e($lse('human_facing')) ?></div>
      </div>
      <div class="lse-summary-card lse-cat-already_localized<?= $scanOk && ($scanSummary['already_localized_usages'] ?? 0) > 0 ? ' has-data' : '' ?>">
        <div class="lse-summary-value"><?= (int)($scanSummary['already_localized_usages'] ?? 0) ?></div>
        <div class="lse-summary-label"><?= e($lse('already_localized')) ?></div>
      </div>
      <div class="lse-summary-card lse-cat-missing_owner_key<?= $scanOk && ($scanSummary['missing_owner_keys'] ?? 0) > 0 ? ' has-data' : '' ?>">
        <div class="lse-summary-value"><?= (int)($scanSummary['missing_owner_keys'] ?? 0) ?></div>
        <div class="lse-summary-label"><?= e($lse('missing_keys')) ?></div>
        <?php if ($scanOk && $firstMissingHandoffUrl !== ''): ?>
          <div class="lse-summary-action">
            <a class="lse-inline-link" href="<?= e($firstMissingHandoffUrl) ?>"><?= e($lse('missing_key_summary_cta')) ?></a>
          </div>
        <?php endif; ?>
      </div>
      <div class="lse-summary-card lse-cat-external_shared_key_usage<?= $scanOk && ($scanSummary['external_shared_key_usages'] ?? 0) > 0 ? ' has-data' : '' ?>">
        <div class="lse-summary-value"><?= (int)($scanSummary['external_shared_key_usages'] ?? 0) ?></div>
        <div class="lse-summary-label"><?= e($lse('shared_keys')) ?></div>
      </div>
      <div class="lse-summary-card lse-cat-possibly_unused_key<?= $scanOk && ($scanSummary['possibly_unused_keys'] ?? 0) > 0 ? ' has-data' : '' ?>">
        <div class="lse-summary-value"><?= (int)($scanSummary['possibly_unused_keys'] ?? 0) ?></div>
        <div class="lse-summary-label"><?= e($lse('unused_keys')) ?></div>
      </div>
      <div class="lse-summary-card lse-cat-internal_string<?= $scanOk && ($scanSummary['internal_strings'] ?? 0) > 0 ? ' has-data' : '' ?>">
        <div class="lse-summary-value"><?= (int)($scanSummary['internal_strings'] ?? 0) ?></div>
        <div class="lse-summary-label"><?= e($lse('internal_strings')) ?></div>
      </div>
      <div class="lse-summary-card lse-cat-ambiguous_string<?= $scanOk && ($scanSummary['ambiguous_strings'] ?? 0) > 0 ? ' has-data' : '' ?>">
        <div class="lse-summary-value"><?= (int)($scanSummary['ambiguous_strings'] ?? 0) ?></div>
        <div class="lse-summary-label"><?= e($lse('ambiguous_strings')) ?></div>
      </div>
      <div class="lse-summary-card">
        <div class="lse-summary-value"><?= (int)($scanSummary['ignored_strings'] ?? 0) ?></div>
        <div class="lse-summary-label"><?= e($lse('ignored_strings')) ?></div>
      </div>
    </div>

    <div class="lse-filter-bar" id="lse-category-filters">
      <button class="lse-filter-btn is-active" data-category="all" type="button"><?= e($lse('category_filter_all')) ?></button>
      <button class="lse-filter-btn" data-category="human_facing_candidate" type="button"><?= e($lse('category_filter_human_facing')) ?></button>
      <button class="lse-filter-btn" data-category="already_localized_usage" type="button"><?= e($lse('category_filter_already_localized')) ?></button>
      <button class="lse-filter-btn" data-category="missing_owner_key" type="button"><?= e($lse('category_filter_missing')) ?></button>
      <button class="lse-filter-btn" data-category="external_shared_key_usage" type="button"><?= e($lse('category_filter_shared')) ?></button>
      <button class="lse-filter-btn" data-category="possibly_unused_key" type="button"><?= e($lse('category_filter_possibly_unused')) ?></button>
      <button class="lse-filter-btn" data-category="internal_string" type="button"><?= e($lse('category_filter_internal')) ?></button>
      <button class="lse-filter-btn" data-category="ambiguous_string" type="button"><?= e($lse('category_filter_ambiguous')) ?></button>
    </div>

    <div class="lse-table-wrap">
      <table class="lse-table" id="lse-findings-table">
        <thead>
          <tr>
            <th><?= e($lse('col_type')) ?></th>
            <th><?= e($lse('col_owner')) ?></th>
            <th><?= e($lse('col_file')) ?></th>
            <th><?= e($lse('col_line')) ?></th>
            <th><?= e($lse('col_detected')) ?></th>
            <th><?= e($lse('col_suggested')) ?></th>
            <th><?= e($lse('col_confidence')) ?></th>
            <th><?= e($lse('col_status')) ?></th>
            <th><?= e($lse('col_semantic_category')) ?></th>
            <th><?= e($lse('col_relevance')) ?></th>
            <th><?= e($lse('col_occurrences')) ?></th>
            <th><?= e($lse('col_action')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($scanFindings === []): ?>
            <tr class="lse-empty-row">
              <td colspan="12"><?= e($lse('findings_empty')) ?></td>
            </tr>
          <?php else: ?>
            <?php foreach ($scanFindings as $fi => $finding): ?>
              <tr class="lse-finding-row" data-finding-index="<?= $fi ?>" data-finding-type="<?= e($finding['type'] ?? '') ?>" data-category="<?= e($finding['category'] ?? '') ?>">
                <td><span class="lse-finding-type lse-type-<?= e($finding['type'] ?? 'unknown') ?>"><?= e($lse('type_' . ($finding['type'] ?? 'unknown'))) ?></span></td>
                <td><?= e($finding['owner'] ?? '') ?></td>
                <td class="lse-cell-file"><?= e($finding['file'] ?? '') ?></td>
                <td><?= $finding['line'] > 0 ? (int)$finding['line'] : '-' ?></td>
                <td class="lse-cell-detected"><code><?= e(mb_substr($finding['detected'] ?? '', 0, 80)) ?></code></td>
                <td><?= $finding['suggested_key'] ? '<code>' . e($finding['suggested_key']) . '</code>' : '-' ?></td>
                <td><span class="lse-confidence lse-conf-<?= e($finding['confidence'] ?? 'low') ?>"><?= e($lse('confidence_' . ($finding['confidence'] ?? 'low'))) ?></span></td>
                <td><span class="lse-status-badge lse-status-<?= e($finding['status'] ?? 'pending') ?>"><?= e($lse('status_' . ($finding['status'] ?? 'pending'))) ?></span></td>
                <td><?php if (($finding['type'] ?? '') === 'inline_text' && !empty($finding['semantic_category'])): ?><span class="lse-sem-badge lse-sem-<?= e($finding['semantic_category']) ?>"><?= e($finding['semantic_category']) ?></span><?php else: ?><span class="lse-text-muted">&mdash;</span><?php endif; ?></td>
                <td><?php if (($finding['type'] ?? '') === 'inline_text' && !empty($finding['relevance'])): ?><span class="lse-rel-badge is-<?= e($finding['relevance']) ?>"><?= e($lse('relevance_' . $finding['relevance'])) ?></span><?php else: ?><span class="lse-text-muted">&mdash;</span><?php endif; ?></td>
                <td><?php if (($finding['type'] ?? '') === 'inline_text' && !empty($finding['occurrence_count'])): ?><?= (int)$finding['occurrence_count'] ?><?php else: ?><span class="lse-text-muted">&mdash;</span><?php endif; ?></td>
                <td>
                  <?php if (($finding['category'] ?? '') === 'missing_owner_key' && !empty($finding['handoff_url'])): ?>
                    <a class="lse-btn is-link lse-handoff-link" href="<?= e((string)$finding['handoff_url']) ?>"><?= e($lse('action_open_localization_studio')) ?></a>
                  <?php else: ?>
                    <button class="lse-btn is-link lse-select-finding" data-finding-index="<?= $fi ?>" type="button"><?= e($lse('action_select_hint')) ?></button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="lse-panel" id="lse-detail-panel">
      <h3><?= e($lse('detail_title')) ?></h3>
      <p class="lse-panel-desc"><?= e($lse('detail_desc')) ?></p>

      <div id="lse-detail-placeholder" class="lse-empty-state">
        <div class="lse-empty-state-icon">&#128269;</div>
        <div class="lse-empty-state-title"><?= e($lse('detail_empty')) ?></div>
      </div>

      <div id="lse-detail-content" class="lse-detail-content" hidden>
        <div class="lse-preview-panel">
          <h4><?= e($lse('detail_category')) ?></h4>
          <p><span class="lse-finding-type lse-cat-badge" id="lse-detail-cat-badge"></span></p>
          <p class="lse-panel-desc" id="lse-detail-cat-reason"></p>
        </div>
        <div class="lse-preview-panel">
          <h4><?= e($lse('detail_extractable')) ?></h4>
          <p id="lse-detail-extractable-badge"><span class="lse-badge is-warn"><?= e($lse('detail_extractable_no')) ?></span></p>
          <div id="lse-detail-extract-action" hidden>
            <form class="lse-action-form" method="post" action="/apps/studio/tools/localization-scan-extraction/extract-inline-text" id="lse-extract-form">
              <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
              <input type="hidden" name="owner" value="<?= e($selectedOwner) ?>">
              <input type="hidden" name="scope" value="<?= e($selectedScope) ?>">
              <input type="hidden" name="locale" value="en">
              <input type="hidden" name="finding_index" id="lse-extract-finding-index" value="">
              <button class="lse-btn lse-btn-extract" type="submit" onclick="return confirm('<?= e($lse('correction_confirm_extract')) ?>')"><?= e($lse('correction_extract_btn')) ?></button>
            </form>
          </div>
        </div>
        <div class="lse-preview-panel" id="lse-detail-next-step">
          <h4><?= e($lse('missing_key_next_step_title')) ?></h4>
          <p id="lse-detail-next-step-text"></p>
          <dl class="lse-detail-review-meta" id="lse-detail-review-meta" hidden>
            <div>
              <dt><?= e($lse('detail_review_group')) ?></dt>
              <dd id="lse-detail-review-group"></dd>
            </div>
            <div>
              <dt><?= e($lse('detail_usage_count')) ?></dt>
              <dd id="lse-detail-usage-count"></dd>
            </div>
            <div>
              <dt><?= e($lse('detail_suggested_english')) ?></dt>
              <dd id="lse-detail-suggested-english"></dd>
            </div>
          </dl>
          <p class="lse-detail-action" id="lse-detail-handoff" hidden><a class="lse-btn" id="lse-detail-handoff-link" href="#"><?= e($lse('action_open_localization_studio')) ?></a></p>
        </div>
        <div class="lse-preview-panel" id="lse-detail-occurrence" hidden>
          <h4><?= e($lse('detail_occurrence_title')) ?></h4>
          <p class="lse-panel-desc"><?= e($lse('detail_occurrence_desc')) ?></p>
          <dl class="lse-detail-review-meta">
            <div>
              <dt><?= e($lse('col_relevance')) ?></dt>
              <dd id="lse-detail-occurrence-relevance"></dd>
            </div>
            <div>
              <dt><?= e($lse('col_occurrences')) ?></dt>
              <dd id="lse-detail-occurrence-count"></dd>
            </div>
          </dl>
          <div id="lse-detail-occurrence-files">
            <strong><?= e($lse('detail_occurrence_files')) ?>:</strong>
            <ul id="lse-detail-occurrence-file-list"></ul>
          </div>
        </div>
        <div class="lse-preview-panel">
          <h4><?= e($lse('detail_context')) ?></h4>
          <pre id="lse-detail-context" class="lse-detail-pre"></pre>
        </div>
      </div>
    </div>

    </details>
  </div>
  <?php endif; ?>

  <?php
  $readyCount = (int)($migrationCounts['ready_to_migrate'] ?? 0);
  $migrationQualityCounts = isset($migrationPlan['key_quality_counts']) && is_array($migrationPlan['key_quality_counts'])
      ? $migrationPlan['key_quality_counts']
      : ['excellent' => 0, 'good' => 0, 'acceptable' => 0, 'needs_review' => 0];
  $migrationReviewReasonCounts = isset($migrationPlan['review_reason_counts']) && is_array($migrationPlan['review_reason_counts'])
      ? $migrationPlan['review_reason_counts']
      : [];
  $migrationReviewPriorityCounts = isset($migrationPlan['review_priority_counts']) && is_array($migrationPlan['review_priority_counts'])
      ? $migrationPlan['review_priority_counts']
      : ['likely_migratable' => 0, 'manual_review' => 0, 'blocked' => 0];
  $totalInline = (int)($scanSummary['human_facing_candidates'] ?? 0);
  $readyCandidates = [];
  $reviewCandidates = [];
  $reviewCount = 0;
  foreach ($migrationCandidates as $mc) {
      if (($mc['state'] ?? '') === 'ready_to_migrate') {
          $readyCandidates[] = $mc;
      } elseif (($mc['state'] ?? '') === 'needs_review') {
          $reviewCandidates[] = $mc;
          $reviewCount++;
      }
  }
  ?>
  <?php if ($migrationPlan !== null && $migrationPlan['total'] > 0): ?>
  <div class="lse-panel lse-section-migration">
    <details class="lse-collapsible lse-section-fold">
      <summary class="lse-collapsible-summary lse-section-summary">
        <span class="lse-section-title"><?= e($lse('migration_plan_title')) ?></span>
        <span class="lse-badge is-info"><?= (int)$migrationPlan['total'] ?> <?= e($lse('migration_total_label')) ?></span>
      </summary>

      <p class="lse-panel-desc"><?= e($lse('migration_plan_desc')) ?></p>
      <div class="lse-migration-summary">
        <div class="lse-summary-card has-data lse-mig-ready">
          <div class="lse-summary-value"><?= $readyCount ?></div>
          <div class="lse-summary-label"><?= e($lse('migration_ready_label')) ?></div>
        </div>
        <div class="lse-summary-card has-data lse-mig-review">
          <div class="lse-summary-value"><?= (int)($migrationCounts['needs_review'] ?? 0) ?></div>
          <div class="lse-summary-label"><?= e($lse('migration_review_label')) ?></div>
        </div>
        <div class="lse-summary-card has-data lse-mig-rejected">
          <div class="lse-summary-value"><?= (int)($migrationCounts['rejected'] ?? 0) ?></div>
          <div class="lse-summary-label"><?= e($lse('migration_rejected_label')) ?></div>
        </div>
        <div class="lse-summary-card has-data">
          <div class="lse-summary-value"><?= (int)$migrationPlan['total'] ?></div>
          <div class="lse-summary-label"><?= e($lse('migration_total_label')) ?></div>
        </div>
      </div>
      <details class="lse-key-quality-summary lse-section-fold">
        <summary class="lse-section-summary">
          <span class="lse-section-title"><?= e($lse('migration_key_quality_summary')) ?></span>
          <span class="lse-badge is-safe"><?= (int)($migrationQualityCounts['excellent'] ?? 0) ?> <?= e($lse('migration_quality_excellent')) ?></span>
          <span class="lse-badge is-info"><?= (int)($migrationQualityCounts['good'] ?? 0) ?> <?= e($lse('migration_quality_good')) ?></span>
          <span class="lse-badge is-warn"><?= (int)($migrationQualityCounts['needs_review'] ?? 0) ?> <?= e($lse('migration_quality_needs_review')) ?></span>
        </summary>
        <div class="lse-relevance-bar">
          <span class="lse-relevance-item lse-quality-excellent"><?= e($lse('migration_quality_excellent')) ?>: <?= (int)($migrationQualityCounts['excellent'] ?? 0) ?></span>
          <span class="lse-relevance-item lse-quality-good"><?= e($lse('migration_quality_good')) ?>: <?= (int)($migrationQualityCounts['good'] ?? 0) ?></span>
          <span class="lse-relevance-item lse-quality-acceptable"><?= e($lse('migration_quality_acceptable')) ?>: <?= (int)($migrationQualityCounts['acceptable'] ?? 0) ?></span>
          <span class="lse-relevance-item lse-quality-needs_review"><?= e($lse('migration_quality_needs_review')) ?>: <?= (int)($migrationQualityCounts['needs_review'] ?? 0) ?></span>
        </div>
      </details>
      <?php if ($reviewCount > 0): ?>
      <details class="lse-key-quality-summary lse-review-intelligence lse-section-fold">
        <summary class="lse-section-summary">
          <span class="lse-section-title"><?= e($lse('review_intelligence_title')) ?></span>
          <span class="lse-review-summary-chip"><?= e($lse('review_priority_likely_migratable')) ?>: <?= (int)($migrationReviewPriorityCounts['likely_migratable'] ?? 0) ?></span>
          <span class="lse-review-summary-chip"><?= e($lse('review_priority_manual_review')) ?>: <?= (int)($migrationReviewPriorityCounts['manual_review'] ?? 0) ?></span>
          <span class="lse-review-summary-chip"><?= e($lse('review_priority_blocked')) ?>: <?= (int)($migrationReviewPriorityCounts['blocked'] ?? 0) ?></span>
        </summary>
        <div class="lse-relevance-bar">
          <span class="lse-relevance-item lse-priority-likely_migratable"><?= e($lse('review_priority_likely_migratable')) ?>: <?= (int)($migrationReviewPriorityCounts['likely_migratable'] ?? 0) ?></span>
          <span class="lse-relevance-item lse-priority-manual_review"><?= e($lse('review_priority_manual_review')) ?>: <?= (int)($migrationReviewPriorityCounts['manual_review'] ?? 0) ?></span>
          <span class="lse-relevance-item lse-priority-blocked"><?= e($lse('review_priority_blocked')) ?>: <?= (int)($migrationReviewPriorityCounts['blocked'] ?? 0) ?></span>
        </div>
        <div class="lse-review-breakdown">
          <?php foreach (['domain_vocabulary','ambiguous_context','long_description','duplicate_candidate','technical_term','naming_uncertain','reusable_ui_term','mixed_usage','other'] as $reasonKey): ?>
            <?php $reasonCount = (int)($migrationReviewReasonCounts[$reasonKey] ?? 0); ?>
            <?php if ($reasonCount > 0): ?>
            <span class="lse-review-breakdown-item"><span><?= e($lse('review_reason_' . $reasonKey)) ?></span><strong><?= $reasonCount ?></strong></span>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </details>
      <?php endif; ?>

      <details class="lse-change-preview lse-inline-migration-preview lse-section-fold" id="lse-inline-migration-preview">
        <summary class="lse-section-summary">
          <span class="lse-section-title"><?= e($lse('migration_preview_title')) ?></span>
          <span class="lse-badge <?= $readyCount > 0 ? 'is-safe' : 'is-warn' ?>"><?= $readyCount ?> <?= e($lse('migration_ready_label')) ?></span>
        </summary>
        <p class="lse-panel-desc" style="margin-bottom:12px"><?= e($lse('migration_preview_desc')) ?></p>

      <?php if ($readyCount > 0): ?>
      <div class="lse-table-wrap">
        <table class="lse-table lse-migration-table">
          <thead>
            <tr>
              <th><?= e($lse('migration_col_text')) ?></th>
              <th><?= e($lse('migration_col_suggested_key')) ?></th>
              <th><?= e($lse('migration_col_suggested_english')) ?></th>
              <th><?= e($lse('migration_col_key_quality')) ?></th>
              <th><?= e($lse('col_file')) ?></th>
              <th><?= e($lse('col_line')) ?></th>
              <th><?= e($lse('col_element_type')) ?></th>
              <th><?= e($lse('col_relevance')) ?></th>
              <th><?= e($lse('col_occurrences')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($readyCandidates as $mc): ?>
            <tr class="lse-mig-row lse-mig-ready_to_migrate">
              <td class="lse-cell-detected">
                <details class="lse-text-reveal">
                  <summary><code><?= e(mb_substr((string)$mc['text'], 0, 80)) ?></code></summary>
                  <div class="lse-migration-full-text">
                    <dl>
                      <div>
                        <dt><?= e($lse('migration_full_source_text')) ?></dt>
                        <dd><?= e((string)$mc['text']) ?></dd>
                      </div>
                      <div>
                        <dt><?= e($lse('migration_col_suggested_key')) ?></dt>
                        <dd><code><?= e((string)$mc['suggested_key']) ?></code></dd>
                      </div>
                      <div>
                        <dt><?= e($lse('migration_col_suggested_english')) ?></dt>
                        <dd><?= e((string)$mc['suggested_english']) ?></dd>
                      </div>
                      <div>
                        <dt><?= e($lse('col_file')) ?></dt>
                        <dd class="lse-cell-file"><?= e((string)$mc['file']) ?></dd>
                      </div>
                      <div>
                        <dt><?= e($lse('col_occurrences')) ?></dt>
                        <dd><?= (int)($mc['occurrence_count'] ?? 1) ?></dd>
                      </div>
                    </dl>
                  </div>
                </details>
              </td>
              <td><code><?= e($mc['suggested_key']) ?></code></td>
              <td><?= e(mb_substr($mc['suggested_english'], 0, 60)) ?></td>
              <?php $quality = (string)($mc['key_quality'] ?? 'needs_review'); ?>
              <td><span class="lse-badge lse-badge-sm lse-quality-<?= e($quality) ?>" title="<?= e((string)($mc['key_quality_reason'] ?? '')) ?>"><?= e($lse('migration_quality_' . $quality)) ?></span></td>
              <td class="lse-cell-file"><?= e($mc['file']) ?></td>
              <td><?= (int)($mc['line'] ?? 0) > 0 ? (int)$mc['line'] : '-' ?></td>
              <td><?= e($mc['element_type']) ?></td>
              <td><span class="lse-rel-badge is-<?= e($mc['relevance']) ?>"><?= e($lse('relevance_' . $mc['relevance'])) ?></span></td>
              <td><?= (int)$mc['occurrence_count'] ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <form class="lse-action-form" method="post" action="/apps/studio/tools/localization-scan-extraction/apply-inline-migration" style="margin-top:12px">
        <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="owner" value="<?= e($selectedOwner) ?>">
        <input type="hidden" name="scope" value="<?= e($selectedScope) ?>">
        <input type="hidden" name="locale" value="en">
        <button class="lse-btn" type="submit" onclick="return confirm('<?= e($lse('migration_apply_confirm')) ?>')"><?= e($lse('migration_apply_button')) ?> (<?= $readyCount ?>)</button>
        <span class="lse-readonly-note" style="margin-left:8px"><?= e($lse('migration_apply_note')) ?></span>
      </form>
      <?php else: ?>
      <div class="lse-summary-empty-state">
        <?= e($lse('no_corrections_findings_available')) ?>
        <?php if ($totalInline > 0): ?>
          <?= (int)$totalInline ?> <?= e($lse('inline_findings_available')) ?>
        <?php endif; ?>
        <?php if ($reviewCount > 0): ?>
          <span class="lse-badge is-warn" style="margin-left:8px"><?= (int)$reviewCount ?> <?= e($lse('migration_review_label')) ?></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      </details>

      <?php if ($reviewCandidates !== []): ?>
      <details class="lse-review-candidate-fold lse-section-fold">
        <summary class="lse-review-candidate-summary lse-section-summary">
          <span class="lse-review-candidate-title lse-section-title"><?= e($lse('review_candidate_table_title')) ?></span>
          <span class="lse-badge is-warn"><?= (int)count($reviewCandidates) ?> <?= e($lse('migration_review_label')) ?></span>
        </summary>
      <div class="lse-table-wrap">
        <table class="lse-table lse-migration-table lse-review-table">
          <thead>
            <tr>
              <th><?= e($lse('migration_col_text')) ?></th>
              <th><?= e($lse('review_col_reason')) ?></th>
              <th><?= e($lse('review_col_priority')) ?></th>
              <th><?= e($lse('col_semantic_category')) ?></th>
              <th><?= e($lse('col_relevance')) ?></th>
              <th><?= e($lse('col_occurrences')) ?></th>
              <th><?= e($lse('review_col_direction')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($reviewCandidates, 0, 150) as $mc): ?>
            <?php
              $reason = (string)($mc['review_reason'] ?? 'other');
              $priority = (string)($mc['review_priority'] ?? 'manual_review');
              $relevance = (string)($mc['relevance'] ?? 'low');
            ?>
            <tr class="lse-mig-row lse-mig-needs_review">
              <td class="lse-cell-detected"><code><?= e(mb_substr((string)$mc['text'], 0, 90)) ?></code></td>
              <td><span class="lse-badge lse-badge-sm is-info"><?= e($lse('review_reason_' . $reason)) ?></span></td>
              <td><span class="lse-badge lse-badge-sm lse-priority-<?= e($priority) ?>"><?= e($lse('review_priority_' . $priority)) ?></span></td>
              <td><?= e((string)($mc['semantic_category'] ?? '-')) ?></td>
              <td><span class="lse-rel-badge is-<?= e($relevance) ?>"><?= e($lse('relevance_' . $relevance)) ?></span></td>
              <td><?= (int)($mc['occurrence_count'] ?? 1) ?></td>
              <td><?= e((string)($mc['suggested_direction'] ?? '')) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (count($reviewCandidates) > 150): ?>
      <p class="lse-readonly-note"><?= e($lse('owner_findings_limited')) ?></p>
      <?php endif; ?>
      </details>
      <?php endif; ?>

      <div class="lse-migration-note">
        <p><?= e($lse('migration_plan_note')) ?></p>
      </div>
    </details>
  </div>
  <?php endif; ?>

  <div class="lse-panel" id="lse-governance">
    <details class="lse-collapsible">
      <summary class="lse-collapsible-summary">
        <h3><?= e($lse('governance_title')) ?></h3>
        <p class="lse-panel-desc"><?= e($lse('governance_desc')) ?></p>
      </summary>
      <div class="lse-gov-grid">
        <div class="lse-gov-card">
          <div class="lse-gov-card-title"><?= e($lse('governance_snapshots')) ?> <span class="lse-badge is-safe"><?= e($lse('status_ready')) ?></span></div>
          <div class="lse-gov-card-desc"><?= e($lse('governance_snapshots_desc')) ?></div>
        </div>
        <div class="lse-gov-card">
          <div class="lse-gov-card-title"><?= e($lse('governance_validation')) ?> <span class="lse-badge is-safe"><?= e($lse('status_ready')) ?></span></div>
          <div class="lse-gov-card-desc"><?= e($lse('governance_validation_desc')) ?></div>
        </div>
        <div class="lse-gov-card">
          <div class="lse-gov-card-title"><?= e($lse('governance_rollback')) ?> <span class="lse-badge is-safe"><?= e($lse('status_ready')) ?></span></div>
          <div class="lse-gov-card-desc"><?= e($lse('governance_rollback_desc')) ?></div>
        </div>
        <div class="lse-gov-card">
          <div class="lse-gov-card-title"><?= e($lse('governance_history')) ?> <span class="lse-badge is-safe"><?= e($lse('status_ready')) ?></span></div>
          <div class="lse-gov-card-desc"><?= e($lse('governance_history_desc')) ?></div>
        </div>
        <div class="lse-gov-card">
          <div class="lse-gov-card-title"><?= e($lse('governance_verification')) ?> <span class="lse-badge is-safe"><?= e($lse('status_ready')) ?></span></div>
          <div class="lse-gov-card-desc"><?= e($lse('governance_verification_desc')) ?></div>
        </div>
      </div>
    </details>
  </div>

  <div class="lse-safety-bar" aria-label="<?= e($lse('safety_bar_title')) ?>">
    <span class="lse-safety-bar-item">
      <span class="lse-safety-bar-label"><?= e($lse('scanner_status')) ?>:</span>
      <span class="lse-badge lse-badge-sm <?= $ownerDiscoveryWired ? 'is-safe' : 'is-warn' ?>"><?= $ownerDiscoveryWired ? e($lse('status_ready')) : e($lse('backend_not_wired')) ?></span>
    </span>
    <span class="lse-safety-bar-item">
      <span class="lse-safety-bar-label"><?= e($lse('correction_ready_badge')) ?>:</span>
      <span class="lse-badge lse-badge-sm is-warn"><?= e($lse('status_ready')) ?></span>
    </span>
    <span class="lse-safety-bar-item">
      <span class="lse-safety-bar-label"><?= e($lse('validation_protected_badge')) ?>:</span>
      <span class="lse-badge lse-badge-sm is-safe"><?= e($lse('status_protected')) ?></span>
    </span>
    <span class="lse-safety-bar-item">
      <span class="lse-safety-bar-label"><?= e($lse('rollback_protected_badge')) ?>:</span>
      <span class="lse-badge lse-badge-sm is-info"><?= e($lse('status_protected')) ?></span>
    </span>
    <span class="lse-safety-bar-item lse-safety-bar-note"><?= e($lse('writes_enabled_note')) ?></span>
  </div>


</section>

<style>
.lse-safety-bar {
  position: sticky;
  bottom: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 16px;
  padding: 6px 16px;
  margin-top: 24px;
  background: var(--surface-raised, #f8f9fa);
  border-top: 1px solid var(--border-subtle, #dee2e6);
  font-size: 0.8125rem;
  z-index: 10;
}
.lse-safety-bar-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.lse-safety-bar-label {
  color: var(--text-muted, #6c757d);
  font-weight: 500;
}
.lse-safety-bar-note {
  margin-left: auto;
  color: var(--text-muted, #6c757d);
  font-style: italic;
  font-size: 0.75rem;
}
.lse-badge-sm {
  font-size: 0.75rem;
  padding: 1px 6px;
  border-radius: 3px;
}
.lse-report {
  margin-top: 16px;
  padding: 12px 16px;
  border: 1px solid var(--border-subtle, #dee2e6);
  border-radius: 6px;
  background: var(--surface-raised, #f8f9fa);
}
.lse-report-title {
  margin: 0 0 8px;
  font-size: 0.9375rem;
}
.lse-report-body {
  font-size: 0.8125rem;
}
.lse-report-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 16px;
  margin-bottom: 8px;
}
.lse-report-meta-item {
  font-size: 0.8125rem;
}
.lse-report-error {
  color: var(--text-danger, #dc3545);
}
.lse-report-details {
  margin-top: 8px;
}
.lse-report-details summary {
  cursor: pointer;
  font-weight: 500;
  font-size: 0.8125rem;
}
.lse-report-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 6px;
  font-size: 0.8125rem;
}
.lse-report-table th,
.lse-report-table td {
  text-align: left;
  padding: 4px 8px;
  border-bottom: 1px solid var(--border-subtle, #dee2e6);
}
.lse-report-table th {
  font-weight: 600;
  background: var(--surface-raised-alt, #f0f0f0);
}
.lse-report-diags {
  margin-top: 6px;
  font-size: 0.8125rem;
}
.lse-report-diags p {
  margin: 2px 0;
}
.lse-report-snapshots {
  margin-top: 8px;
  font-size: 0.8125rem;
}
.lse-report-snapshots ul {
  margin: 4px 0;
  padding-left: 20px;
}
.lse-report-snapshots li {
  margin: 2px 0;
}
.lse-gov-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
  margin-top: 12px;
}
.lse-gov-card {
  padding: 12px;
  border: 1px solid var(--border-subtle, #dee2e6);
  border-radius: 6px;
  background: var(--surface-raised, #f8f9fa);
}
.lse-gov-card-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  font-size: 0.875rem;
  margin-bottom: 6px;
}
.lse-gov-card-desc {
  font-size: 0.75rem;
  color: var(--text-muted, #6c757d);
  line-height: 1.4;
}
.lse-panel {
  margin-top: 20px;
}
.lse-panel-desc {
  font-size: 0.8125rem;
  color: var(--text-muted, #6c757d);
  margin: 4px 0 0;
}
.lse-history {
  margin-top: 16px;
}
.lse-history-list {
  list-style: none;
  padding: 0;
  margin: 8px 0 0;
}
.lse-history-item {
  display: flex;
  gap: 10px;
  padding: 8px 0;
  font-size: 0.8125rem;
  border-bottom: 1px solid var(--border-subtle, #eee);
  align-items: flex-start;
}
.lse-history-icon {
  flex-shrink: 0;
  width: 20px;
  height: 20px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  font-size: 0.6875rem;
  margin-top: 1px;
}
.lse-history-icon.is-success {
  background: #d4edda;
  color: #155724;
}
.lse-history-icon.is-failed {
  background: #f8d7da;
  color: #721c24;
}
.lse-history-body {
  flex: 1;
  min-width: 0;
}
.lse-history-title {
  font-weight: 600;
}
.lse-history-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 12px;
  margin-top: 2px;
  font-size: 0.75rem;
  color: var(--text-muted, #6c757d);
}
.lse-collapsible-summary {
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
}
.lse-collapsible-summary h3 {
  margin: 0;
}
.lse-summary-empty-state {
  margin-bottom: 12px;
  padding: 12px;
  background: var(--surface-raised, #fffbe6);
  border: 1px solid var(--border-warn, #e6d8a8);
  border-radius: 6px;
  font-size: 0.875rem;
  color: var(--text-warn, #856404);
}
</style>

<script id="lse-review-plan-data" type="text/plain"><?= e($missingKeyReviewTsv) ?></script>
<script id="lse-findings-data" type="application/json"><?= json_encode($scanFindings) ?></script>
<script id="lse-detail-copy" type="application/json"><?= json_encode([
    'human_facing_candidate' => $lse('next_step_human_facing'),
    'already_localized_usage' => $lse('next_step_already_localized'),
    'missing_owner_key' => $lse('next_step_missing_owner'),
    'external_shared_key_usage' => $lse('next_step_shared_external'),
    'possibly_unused_key' => $lse('next_step_possibly_unused'),
    'internal_string' => $lse('next_step_internal'),
    'ambiguous_string' => $lse('next_step_ambiguous'),
    'ignored_string' => $lse('next_step_internal'),
    'default' => $lse('next_step_default'),
    'badge_actionable' => $lse('detail_badge_actionable'),
    'badge_not_actionable' => $lse('detail_extractable_no'),
]) ?></script>
<script id="lse-async-copy" type="application/json"><?= json_encode([
    'csrf_token' => $csrfToken ?? '',
    'scan_scope' => $selectedScope ?? 'views',
    'scan_locale' => $selectedLocale ?? 'en',
    'async_results_title' => $lse('async_results_title'),
    'async_results_desc' => $lse('async_results_desc'),
    'scan_complete' => $lse('scan_complete'),
    'scan_progress_starting' => $lse('scan_progress_starting'),
    'scan_progress_running' => $lse('scan_progress_running'),
    'scan_progress_complete' => $lse('scan_progress_complete'),
    'scan_progress_failed' => $lse('scan_progress_failed'),
    'full_scan_results_title' => $lse('full_scan_results_title'),
    'full_owners_label' => $lse('full_owners_label'),
    'full_findings_label' => $lse('full_findings_label'),
    'owner_result_title' => $lse('owner_result_title'),
    'owner_findings_limited' => $lse('owner_findings_limited'),
    'correction_plan_title' => $lse('correction_plan_title'),
    'inline_text_candidates_label' => $lse('inline_text_candidates_label'),
    'correction_files_affected' => $lse('correction_files_affected'),
    'candidate_corrections' => $lse('candidate_corrections'),
    'safe_auto_fixes' => $lse('safe_auto_fixes'),
    'needs_review' => $lse('needs_review'),
    'rejected_unsafe' => $lse('rejected_unsafe'),
    'pending_keys_short' => $lse('pending_keys_short'),
    'correction_status_clean' => $lse('correction_status_clean'),
    'correction_status_action_needed' => $lse('correction_status_action_needed'),
    'correction_status_review_needed' => $lse('correction_status_review_needed'),
    'open_correction_plan' => $lse('open_correction_plan'),
    'apply_safe_corrections' => $lse('apply_safe_corrections'),
    'applying_safe_corrections' => $lse('applying_safe_corrections'),
    'applied_safe_corrections' => $lse('applied_safe_corrections'),
    'apply_safe_confirm' => $lse('apply_safe_confirm'),
    'no_safe_corrections' => $lse('no_safe_corrections'),
    'safe_corrections_note' => $lse('safe_corrections_note'),
    'change_preview_title' => $lse('change_preview_title'),
    'correction_report_title' => $lse('correction_report_title'),
    'validation_passed' => $lse('validation_passed'),
    'validation_needs_review' => $lse('validation_needs_review'),
    'failures_label' => $lse('failures_label'),
    'pending_change_label' => $lse('pending_change_label'),
    'rollback_status_label' => $lse('rollback_status_label'),
    'owner_label' => $lse('owner_label'),
    'status_label' => $lse('status_label'),
    'status_success' => $lse('status_success'),
    'status_failed' => $lse('status_failed'),
    'human_facing' => $lse('human_facing'),
    'already_localized' => $lse('already_localized'),
    'missing_keys' => $lse('missing_keys'),
    'shared_keys' => $lse('shared_keys'),
    'unused_keys' => $lse('unused_keys'),
    'ambiguous_strings' => $lse('ambiguous_strings'),
    'internal_strings' => $lse('internal_strings'),
    'pending_keys_label' => $lse('pending_keys_label'),
    'review_plan_key' => $lse('review_plan_key'),
    'review_plan_file' => $lse('review_plan_file'),
    'review_plan_confidence' => $lse('review_plan_confidence'),
    'review_plan_no_suggestion' => $lse('review_plan_no_suggestion'),
    'not_ready_reason' => $lse('not_ready_reason'),
    'not_ready_empty_value' => $lse('not_ready_empty_value'),
    'not_ready_path_url' => $lse('not_ready_path_url'),
    'not_ready_expression_fragment' => $lse('not_ready_expression_fragment'),
    'not_ready_html_tag' => $lse('not_ready_html_tag'),
    'not_ready_config_route_class_id' => $lse('not_ready_config_route_class_id'),
    'not_ready_unsafe_key' => $lse('not_ready_unsafe_key'),
    'not_ready_ambiguous_key' => $lse('not_ready_ambiguous_key'),
    'not_ready_duplicate_label' => $lse('not_ready_duplicate_label'),
    'not_ready_low_confidence' => $lse('not_ready_low_confidence'),
    'not_ready_no_suggestion' => $lse('not_ready_no_suggestion'),
    'not_ready_needs_review' => $lse('not_ready_needs_review'),
    'value_label' => $lse('value_label'),
    'evidence_type_label' => $lse('evidence_type_label'),
    'evidence_source_label' => $lse('evidence_source_label'),
    'reason_label' => $lse('reason_label'),
    'confidence_auto_safe' => $lse('confidence_auto_safe'),
    'confidence_high' => $lse('confidence_high'),
    'confidence_medium' => $lse('confidence_medium'),
    'ready_confidence_breakdown' => $lse('ready_confidence_breakdown'),
    'keys_added_label' => $lse('keys_added_label'),
    'col_type' => $lse('col_type'),
    'col_action' => $lse('col_action'),
    'col_file' => $lse('col_file'),
    'col_line' => $lse('col_line'),
    'col_detected' => $lse('col_detected'),
    'col_status' => $lse('col_status'),
    'findings_empty' => $lse('findings_empty'),
    'inline_findings_label' => $lse('inline_findings_label'),
    'relevance_distribution' => $lse('relevance_distribution'),
    'relevance_critical' => $lse('relevance_critical'),
    'relevance_high' => $lse('relevance_high'),
    'relevance_medium' => $lse('relevance_medium'),
    'relevance_low' => $lse('relevance_low'),
    'inline_breakdown_title' => $lse('inline_breakdown_title'),
    'inline_breakdown_desc' => $lse('inline_breakdown_desc'),
    'technical_terms_label' => $lse('technical_terms_label'),
    'false_positives_label' => $lse('false_positives_label'),
    'col_semantic_category' => $lse('col_semantic_category'),
    'col_relevance' => $lse('col_relevance'),
    'col_occurrences' => $lse('col_occurrences'),
    'col_element_type' => $lse('col_element_type'),
    'type_inline_text' => $lse('type_inline_text'),
    'type_missing_key' => $lse('type_missing_key'),
    'corrections_empty_none' => $lse('corrections_empty_none'),
    'inline_findings_available' => $lse('inline_findings_available'),
    'migration_plan_title' => $lse('migration_plan_title'),
    'migration_plan_desc' => $lse('migration_plan_desc'),
    'migration_ready_label' => $lse('migration_ready_label'),
    'migration_review_label' => $lse('migration_review_label'),
    'migration_rejected_label' => $lse('migration_rejected_label'),
    'migration_total_label' => $lse('migration_total_label'),
    'migration_col_text' => $lse('migration_col_text'),
    'migration_col_suggested_key' => $lse('migration_col_suggested_key'),
    'migration_col_suggested_english' => $lse('migration_col_suggested_english'),
    'migration_col_key_quality' => $lse('migration_col_key_quality'),
    'migration_preview_title' => $lse('migration_preview_title'),
    'migration_preview_desc' => $lse('migration_preview_desc'),
    'migration_apply_button' => $lse('migration_apply_button'),
    'migration_apply_note' => $lse('migration_apply_note'),
    'migration_apply_confirm' => $lse('migration_apply_confirm'),
    'migration_none_ready' => $lse('migration_none_ready'),
    'migration_none_ready_review' => $lse('migration_none_ready_review'),
    'migration_none_ready_rejected' => $lse('migration_none_ready_rejected'),
    'migration_none_ready_review_rejected' => $lse('migration_none_ready_review_rejected'),
    'migration_key_quality_summary' => $lse('migration_key_quality_summary'),
    'migration_quality_excellent' => $lse('migration_quality_excellent'),
    'migration_quality_good' => $lse('migration_quality_good'),
    'migration_quality_acceptable' => $lse('migration_quality_acceptable'),
    'migration_quality_needs_review' => $lse('migration_quality_needs_review'),
    'ops_dashboard_title' => $lse('ops_dashboard_title'),
    'ops_dashboard_badge' => $lse('ops_dashboard_badge'),
    'ops_ready_missing_total' => $lse('ops_ready_missing_total'),
    'ops_ready_inline_total' => $lse('ops_ready_inline_total'),
    'ops_missing_ready_col' => $lse('ops_missing_ready_col'),
    'ops_inline_ready_col' => $lse('ops_inline_ready_col'),
    'ops_status_ready_to_fix' => $lse('ops_status_ready_to_fix'),
    'ops_status_mixed' => $lse('ops_status_mixed'),
    'ops_action_correct' => $lse('ops_action_correct'),
    'ops_action_migrate' => $lse('ops_action_migrate'),
    'ops_action_review' => $lse('ops_action_review'),
    'review_intelligence_title' => $lse('review_intelligence_title'),
    'review_candidate_table_title' => $lse('review_candidate_table_title'),
    'review_col_reason' => $lse('review_col_reason'),
    'review_col_priority' => $lse('review_col_priority'),
    'review_col_direction' => $lse('review_col_direction'),
    'review_reason_domain_vocabulary' => $lse('review_reason_domain_vocabulary'),
    'review_reason_ambiguous_context' => $lse('review_reason_ambiguous_context'),
    'review_reason_long_description' => $lse('review_reason_long_description'),
    'review_reason_duplicate_candidate' => $lse('review_reason_duplicate_candidate'),
    'review_reason_technical_term' => $lse('review_reason_technical_term'),
    'review_reason_naming_uncertain' => $lse('review_reason_naming_uncertain'),
    'review_reason_reusable_ui_term' => $lse('review_reason_reusable_ui_term'),
    'review_reason_mixed_usage' => $lse('review_reason_mixed_usage'),
    'review_reason_other' => $lse('review_reason_other'),
    'review_priority_likely_migratable' => $lse('review_priority_likely_migratable'),
    'review_priority_manual_review' => $lse('review_priority_manual_review'),
    'review_priority_blocked' => $lse('review_priority_blocked'),
    'campaign_builder_title' => $lse('campaign_builder_title'),
    'campaign_readonly_badge' => $lse('campaign_readonly_badge'),
    'campaign_builder_desc' => $lse('campaign_builder_desc'),
    'campaign_default_name' => $lse('campaign_default_name'),
    'campaign_default_description' => $lse('campaign_default_description'),
    'campaign_name_label' => $lse('campaign_name_label'),
    'campaign_description_label' => $lse('campaign_description_label'),
    'campaign_owners_included' => $lse('campaign_owners_included'),
    'campaign_ready_missing' => $lse('campaign_ready_missing'),
    'campaign_ready_inline' => $lse('campaign_ready_inline'),
    'campaign_estimated_impact_title' => $lse('campaign_estimated_impact_title'),
    'campaign_estimated_missing_reductions' => $lse('campaign_estimated_missing_reductions'),
    'campaign_estimated_inline_reductions' => $lse('campaign_estimated_inline_reductions'),
    'campaign_estimated_keys_created' => $lse('campaign_estimated_keys_created'),
    'campaign_estimated_files_modified' => $lse('campaign_estimated_files_modified'),
    'campaign_execution_queue_title' => $lse('campaign_execution_queue_title'),
    'campaign_progress_title' => $lse('campaign_progress_title'),
    'campaign_status_label' => $lse('campaign_status_label'),
    'campaign_status_planned' => $lse('campaign_status_planned'),
    'campaign_status_running' => $lse('campaign_status_running'),
    'campaign_status_complete' => $lse('campaign_status_complete'),
    'campaign_owners_completed' => $lse('campaign_owners_completed'),
    'campaign_inline_debt_reduced' => $lse('campaign_inline_debt_reduced'),
    'campaign_missing_keys_corrected' => $lse('campaign_missing_keys_corrected'),
    'campaign_no_owners_selected' => $lse('campaign_no_owners_selected'),
    'campaign_queue_ready' => $lse('campaign_queue_ready'),
    'campaign_select_all' => $lse('campaign_select_all'),
    'campaign_select_none' => $lse('campaign_select_none'),
    'migration_full_source_text' => $lse('migration_full_source_text'),
    'campaign_filter_all' => $lse('campaign_filter_all'),
    'campaign_filter_ready_fixes' => $lse('campaign_filter_ready_fixes'),
    'campaign_filter_ready_migrations' => $lse('campaign_filter_ready_migrations'),
    'campaign_filter_needs_review' => $lse('campaign_filter_needs_review'),
    'campaign_filter_rejected' => $lse('campaign_filter_rejected'),
    'campaign_owner_search_label' => $lse('campaign_owner_search_label'),
    'campaign_owner_search_placeholder' => $lse('campaign_owner_search_placeholder'),
    'owner_workbench_title' => $lse('owner_workbench_title'),
    'owner_workbench_desc' => $lse('owner_workbench_desc'),
    'owner_workbench_owner_summary' => $lse('owner_workbench_owner_summary'),
    'owner_workbench_ready_missing' => $lse('owner_workbench_ready_missing'),
    'owner_workbench_ready_inline' => $lse('owner_workbench_ready_inline'),
    'owner_workbench_files_affected' => $lse('owner_workbench_files_affected'),
    'owner_workbench_rollback_link' => $lse('owner_workbench_rollback_link'),
    'owner_workbench_history_link' => $lse('owner_workbench_history_link'),
]) ?></script>
<script><?php require APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/assets/lse-tool.js'; ?></script>
