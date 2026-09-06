<?php
$model = isset($localizationStudioModel) && is_array($localizationStudioModel) ? $localizationStudioModel : [];
$groups = isset($model['groups']) && is_array($model['groups']) ? $model['groups'] : [];
$summary = isset($model['summary']) && is_array($model['summary']) ? $model['summary'] : [];
$supportedLocales = isset($model['supported_locales']) && is_array($model['supported_locales']) ? $model['supported_locales'] : ['en', 'ja', 'ne'];
$isReadOnly = !empty($model['is_read_only']);

$ls = static function (string $key) use ($groups, $summary, $supportedLocales, $isReadOnly): string {
    $dict = [
        'workspace_title' => 'Localization Studio',
        'workspace_subtitle' => 'Read-only discovery and inspection of locale resources across all owners.',
        'workspace_readonly_badge' => 'Discovery & reporting (editor available)',
        'status_diagnostics_only' => 'Diagnostics only',
        'status_no_write' => 'No edits, no save, no apply',
        'summary_title' => 'Locale Coverage Summary',
        'summary_total_owners' => 'Total owners',
        'summary_total_files' => 'Total locale files',
        'summary_total_keys' => 'Total translation keys',
        'summary_owners_missing' => 'Owners with missing locale files',
        'summary_owners_mismatch' => 'Owners with key mismatch',
        'locale_en' => 'English',
        'locale_ja' => 'Japanese',
        'locale_ne' => 'Nepali',
        'coverage_title' => 'Locale File Coverage',
        'coverage_owner' => 'Owner',
        'coverage_language' => 'Language',
        'coverage_keys' => 'Keys',
        'coverage_path' => 'Path',
        'coverage_missing' => 'Missing',
        'coverage_present' => 'Present',
        'missing_locales_title' => 'Missing Locale Files',
        'missing_locales_none' => 'All locale files present for all owners.',
        'mismatch_title' => 'Key Mismatch Across Languages',
        'mismatch_none' => 'No key mismatches detected.',
        'mismatch_reference' => 'Reference',
        'mismatch_other' => 'Other',
        'mismatch_missing_in_other' => 'Keys missing in other',
        'mismatch_extra_in_other' => 'Extra keys in other',
        'owner_section' => 'Owner:',
        'back_to_studio' => 'Back to Studio Home',
        'open_legacy' => 'Open Legacy All-in-One',
        'no_data' => 'No locale data found.',
        'edit_disabled_notice' => 'This page reports locale coverage and missing translations. Editing is available through the linked editor and Create Locale File actions.',
        'missing' => 'missing',
        'keys_label' => 'keys',
        'mismatch_count' => 'mismatch(es)',
        'filter_owner_placeholder' => 'Filter by owner name...',
        'filter_missing_ja' => 'Missing JA',
        'filter_missing_ne' => 'Missing NE',
        'filter_key_mismatch' => 'Key mismatch',
        'filter_clear' => 'Clear',
        'copy_keys' => 'Copy keys',
        'copied' => 'Copied!',
        'locale_missing_file' => 'File missing',
        'file_path' => 'Path',
        'missing_keys_title' => 'Missing keys',
        'missing_keys_none' => 'No missing keys for this language.',
        'filter_no_results' => 'No owners match the active filters.',
        'coverage_dashboard_title' => 'Coverage Dashboard',
        'coverage_dashboard_desc' => 'Per-locale translation coverage across all owners.',
        'coverage_of_owners' => 'of',
        'coverage_present' => 'present',
        'coverage_total_keys' => 'total keys',
        'coverage_coverage' => 'Coverage',
        'coverage_locale_name' => 'Language',
        'coverage_files' => 'Files',
        'coverage_missing_keys' => 'Missing keys',
        'coverage_pct' => 'Coverage %',
        'lowest_title' => 'Lowest Coverage Owners',
        'lowest_desc' => 'Owners sorted by total missing translations. Click a row to filter the coverage table.',
        'lowest_owner' => 'Owner',
        'lowest_locales' => 'Locales',
        'lowest_keys' => 'Total keys',
        'lowest_missing' => 'Missing',
        'lowest_pct' => 'Coverage',
        'lowest_none' => 'All owners have complete coverage across all locales.',
        'locales_of' => 'of',
        'worklist_title' => 'Missing Translation Worklist',
        'worklist_desc' => 'All missing translations across owners and locales. Click a key to inspect, or use the filters to narrow.',
        'worklist_count_prefix' => 'Showing',
        'worklist_count_suffix' => 'missing translations',
        'worklist_owner' => 'Owner',
        'worklist_locale' => 'Locale',
        'worklist_key' => 'Missing Key',
        'worklist_values' => 'Available Values',
        'worklist_status' => 'Status',
        'worklist_filter_owner' => 'Filter by owner...',
        'worklist_filter_key' => 'Search by key...',
        'worklist_file_missing' => 'File missing',
        'worklist_no_results' => 'No missing translations match the active filters.',
        'worklist_sort_asc' => 'sorted asc',
        'worklist_sort_desc' => 'sorted desc',
        'worklist_report_title' => 'Worklist Summary',
        'worklist_report_total' => 'Total missing',
        'worklist_report_owners' => 'Affected owners',
        'worklist_report_locales' => 'Affected locales',
        'worklist_report_top' => 'Most incomplete',
        'worklist_report_entries' => 'entries',
        'worklist_export_tsv' => 'Copy as TSV',
        'worklist_exported' => 'Copied!',
        'worklist_group_label' => 'Owner:',
        'worklist_key_title' => 'Click to inspect translation values',
        'edit_card_title' => 'Edit Language Files',
        'edit_card_desc' => 'Add or edit translations in owner locale files.',
        'open_editor' => 'Open Editor',
        'create_locale_file' => '+ Create Locale File',
        'scan_card_title' => 'Localization Scan & Extraction Tool',
        'scan_card_desc' => 'Find inline UI text and localization key usage in owner source files before guided extraction.',
        'open_tool' => 'Open Tool',
        'file_present' => 'File present',
        'no_reference_values' => 'no reference values',
        'tsv_file_present' => 'File Present',
        'yes' => 'Yes',
        'no' => 'No',
        'owner_label' => 'Owner:',
        'no_value' => 'No value',
        'locale_file_not_found_for_owner' => 'Locale file not found for this owner.',
        'empty_string' => '(empty string)',
        'not_present_in_locale_file' => '(not present in locale file)',
        'key_detail_inspector' => 'Key Detail Inspector',
        'close' => 'Close',
        'open_in_editor' => 'Open in Editor',
    ];
    return (string)($dict[$key] ?? $key);
};

// Compute missing translation worklist
$worklist = [];
$worklistAffectedOwners = [];
$worklistAffectedLocales = [];

foreach ($groups as $ownerKey => $data) {
    foreach ($supportedLocales as $lc) {
        $fd = $data['files'][$lc] ?? null;
        $missingKeys = ($fd && isset($fd['missing_keys']) && is_array($fd['missing_keys'])) ? $fd['missing_keys'] : [];
        $fileMissing = !($fd && $fd['exists']);
        foreach ($missingKeys as $k) {
            $otherValues = [];
            foreach ($supportedLocales as $olc) {
                if ($olc === $lc) continue;
                $ofd = $data['files'][$olc] ?? null;
                if ($ofd && $ofd['exists'] && isset($ofd['values'][$k])) {
                    $otherValues[$olc] = $ofd['values'][$k];
                }
            }
            $worklist[] = [
                'owner' => $ownerKey,
                'locale' => $lc,
                'key' => $k,
                'file_path' => $fd ? $fd['path'] : null,
                'file_missing' => $fileMissing,
                'other_values' => $otherValues,
            ];
            $worklistAffectedOwners[$ownerKey] = true;
            $worklistAffectedLocales[$lc] = true;
        }
    }
}
usort($worklist, function ($a, $b) {
    $cmp = strcmp($a['owner'], $b['owner']);
    if ($cmp !== 0) return $cmp;
    $cmp = strcmp($a['locale'], $b['locale']);
    if ($cmp !== 0) return $cmp;
    return strcmp($a['key'], $b['key']);
});
$worklistCount = count($worklist);
$worklistAffectedOwnerCount = count($worklistAffectedOwners);
$worklistAffectedLocaleList = array_keys($worklistAffectedLocales);
sort($worklistAffectedLocaleList);

// Top 5 incomplete owners for report
$worklistOwnerScores = [];
foreach ($worklistAffectedOwners as $ownerKey => $_) {
    $worklistOwnerScores[$ownerKey] = 0;
}
foreach ($worklist as $wl) {
    $worklistOwnerScores[$wl['owner']]++;
}
arsort($worklistOwnerScores);
$worklistTopOwners = array_slice(array_keys($worklistOwnerScores), 0, 5);
$worklist = [];
foreach ($groups as $ownerKey => $data) {
    foreach ($supportedLocales as $lc) {
        $fd = $data['files'][$lc] ?? null;
        $missingKeys = ($fd && isset($fd['missing_keys']) && is_array($fd['missing_keys'])) ? $fd['missing_keys'] : [];
        $fileMissing = !($fd && $fd['exists']);
        foreach ($missingKeys as $k) {
            $otherValues = [];
            foreach ($supportedLocales as $olc) {
                if ($olc === $lc) continue;
                $ofd = $data['files'][$olc] ?? null;
                if ($ofd && $ofd['exists'] && isset($ofd['values'][$k])) {
                    $otherValues[$olc] = $ofd['values'][$k];
                }
            }
            $worklist[] = [
                'owner' => $ownerKey,
                'locale' => $lc,
                'key' => $k,
                'file_path' => $fd ? $fd['path'] : null,
                'file_missing' => $fileMissing,
                'other_values' => $otherValues,
            ];
        }
    }
}
usort($worklist, function ($a, $b) {
    $cmp = strcmp($a['owner'], $b['owner']);
    if ($cmp !== 0) return $cmp;
    $cmp = strcmp($a['locale'], $b['locale']);
    if ($cmp !== 0) return $cmp;
    return strcmp($a['key'], $b['key']);
});
$worklistCount = count($worklist);

// Compute per-locale coverage and owner ranking
$localeCoverage = [];
$ownerCoverageRanking = [];

foreach ($supportedLocales as $lc) {
    $fileCount = 0;
    $totalKeysUnion = 0;
    $totalMissingKeys = 0;
    $ownerStats = [];
    foreach ($groups as $ownerKey => $data) {
        $ownerTotal = (int)($data['key_count'] ?? 0);
        $fd = $data['files'][$lc] ?? null;
        if ($fd && $fd['exists']) {
            $fileCount++;
            $ownerMissing = (int)($fd['missing_key_count'] ?? 0);
        } else {
            $ownerMissing = $ownerTotal;
        }
        $totalKeysUnion += $ownerTotal;
        $totalMissingKeys += $ownerMissing;
        $ownerStats[$ownerKey] = [
            'present' => $ownerTotal - $ownerMissing,
            'missing' => $ownerMissing,
            'coverage_pct' => $ownerTotal > 0 ? round(($ownerTotal - $ownerMissing) / $ownerTotal * 100) : 0,
        ];
    }
    $localeCoverage[$lc] = [
        'file_count' => $fileCount,
        'owner_count' => count($groups),
        'total_keys' => $totalKeysUnion,
        'present_keys' => $totalKeysUnion - $totalMissingKeys,
        'missing_keys' => $totalMissingKeys,
        'coverage_pct' => $totalKeysUnion > 0 ? round(($totalKeysUnion - $totalMissingKeys) / $totalKeysUnion * 100) : 0,
        'owner_stats' => $ownerStats,
    ];
}

foreach ($groups as $ownerKey => $data) {
    $totalMissing = 0;
    $totalKeyLocalePairs = 0;
    $localePresent = 0;
    foreach ($supportedLocales as $lc) {
        $fd = $data['files'][$lc] ?? null;
        if ($fd && $fd['exists']) {
            $localePresent++;
            $totalMissing += (int)($fd['missing_key_count'] ?? 0);
            $totalKeyLocalePairs += (int)($data['key_count'] ?? 0);
        } else {
            $totalMissing += (int)($data['key_count'] ?? 0);
            $totalKeyLocalePairs += (int)($data['key_count'] ?? 0);
        }
    }
    $ownerCoverageRanking[] = [
        'owner' => $ownerKey,
        'total_missing' => $totalMissing,
        'locales_present' => $localePresent,
        'locales_total' => count($supportedLocales),
        'key_count' => (int)($data['key_count'] ?? 0),
        'coverage_pct' => $totalKeyLocalePairs > 0 ? round(($totalKeyLocalePairs - $totalMissing) / $totalKeyLocalePairs * 100) : 0,
    ];
}
usort($ownerCoverageRanking, function ($a, $b) {
    return $b['total_missing'] <=> $a['total_missing'];
});

$lowestCoverageCount = 0;
foreach ($ownerCoverageRanking as $r) {
    if ((int)$r['total_missing'] > 0) {
        $lowestCoverageCount++;
    }
}

$missingLocaleOwnerCount = 0;
$missingLocaleFileCount = 0;
$mismatchOwnerCount = 0;
$mismatchIssueCount = 0;
foreach ($groups as $data) {
    $missingLocales = isset($data['missing_locales']) && is_array($data['missing_locales']) ? $data['missing_locales'] : [];
    if ($missingLocales !== []) {
        $missingLocaleOwnerCount++;
        $missingLocaleFileCount += count($missingLocales);
    }
    $mismatchDetails = isset($data['mismatch_details']) && is_array($data['mismatch_details']) ? $data['mismatch_details'] : [];
    if (!empty($data['has_mismatch']) && $mismatchDetails !== []) {
        $mismatchOwnerCount++;
        $mismatchIssueCount += count($mismatchDetails);
    }
}
?>
<style>
<?php require APP_ROOT . '/apps/Studio/Tools/LocalizationStudio/assets/localization-studio.css'; ?>
</style>

<section class="gui-studio ls-tool">
  <div class="ls-hero">
    <div>
      <h2><?= e($ls('workspace_title')) ?></h2>
      <p class="muted"><?= e($ls('workspace_subtitle')) ?></p>
    </div>
    <span class="ls-badge is-readonly"><?= e($ls('workspace_readonly_badge')) ?></span>
  </div>

  <div class="ls-notice">
    <span><?= e($ls('edit_disabled_notice')) ?></span>
  </div>

  <div class="ls-card ls-edit-card">
    <div class="ls-edit-card-row">
      <div class="ls-edit-card-body">
        <h3><?= e($ls('edit_card_title')) ?></h3>
        <p><?= e($ls('edit_card_desc')) ?></p>
      </div>
      <a class="ls-edit-btn" href="/apps/studio/tools/localization-studio/edit"><?= e($ls('open_editor')) ?></a>
      <a class="ls-edit-btn" href="/apps/studio/tools/localization-studio/create-file" style="background:var(--tone-success-bg);"><?= e($ls('create_locale_file')) ?></a>
    </div>
  </div>

  <div class="ls-card ls-scan-card">
    <div class="ls-edit-card-row">
      <div class="ls-edit-card-body">
        <h3><?= e($ls('scan_card_title')) ?></h3>
        <p><?= e($ls('scan_card_desc')) ?></p>
      </div>
      <a class="ls-edit-btn" href="/apps/studio/tools/localization-scan-extraction" style="background:var(--tone-info-border,rgba(0,120,255,0.3));"><?= e($ls('open_tool')) ?></a>
    </div>
  </div>

  <?php if ($groups === []): ?>
    <div class="ls-card">
      <p><?= e($ls('no_data')) ?></p>
    </div>
  <?php else: ?>

  <div class="ls-card">
    <h3><?= e($ls('summary_title')) ?></h3>
    <div class="ls-summary-grid">
      <div class="ls-summary-item">
        <strong><?= e((string)$summary['total_owners']) ?></strong>
        <span><?= e($ls('summary_total_owners')) ?></span>
      </div>
      <div class="ls-summary-item">
        <strong><?= e((string)$summary['total_files']) ?></strong>
        <span><?= e($ls('summary_total_files')) ?></span>
      </div>
      <div class="ls-summary-item">
        <strong><?= e((string)$summary['total_keys']) ?></strong>
        <span><?= e($ls('summary_total_keys')) ?></span>
      </div>
      <div class="ls-summary-item">
        <strong><?= e((string)$summary['owners_with_missing_locales']) ?></strong>
        <span><?= e($ls('summary_owners_missing')) ?></span>
      </div>
      <div class="ls-summary-item">
        <strong><?= e((string)$summary['owners_with_key_mismatch']) ?></strong>
        <span><?= e($ls('summary_owners_mismatch')) ?></span>
      </div>
    </div>
  </div>

  <div class="ls-card">
    <h3><?= e($ls('coverage_dashboard_title')) ?></h3>
    <p class="muted"><?= e($ls('coverage_dashboard_desc')) ?></p>
    <div class="ls-locale-coverage-grid">
      <?php $localeNames = ['en' => $ls('locale_en'), 'ja' => $ls('locale_ja'), 'ne' => $ls('locale_ne')]; ?>
      <?php foreach ($supportedLocales as $lc):
        $ld = $localeCoverage[$lc] ?? null;
        if (!$ld) continue;
        $pct = $ld['coverage_pct'];
        $barClass = $pct >= 95 ? 'is-good' : ($pct >= 70 ? 'is-warn' : 'is-poor');
      ?>
      <div class="ls-locale-coverage-card">
        <h4><span class="ls-status-dot <?= $pct >= 95 ? 'ok' : ($ld['file_count'] > 0 ? 'missing' : 'missing') ?>"></span><?= e($localeNames[$lc] ?? strtoupper($lc)) ?></h4>
        <div class="ls-coverage-stat"><strong><?= e((string)$ld['file_count']) ?></strong> <span><?= e($ls('coverage_of_owners')) ?> <?= e((string)$ld['owner_count']) ?> <?= e($ls('coverage_files')) ?></span></div>
        <div class="ls-coverage-stat"><strong><?= e((string)$ld['present_keys']) ?></strong> <span><?= e($ls('coverage_present')) ?></span> <span class="ls-muted">/ <?= e((string)$ld['total_keys']) ?> <?= e($ls('coverage_total_keys')) ?></span></div>
        <?php if ($ld['missing_keys'] > 0): ?>
        <div class="ls-coverage-stat ls-missing-text"><strong><?= e((string)$ld['missing_keys']) ?></strong> <span><?= e($ls('coverage_missing_keys')) ?></span></div>
        <?php endif; ?>
        <meter class="ls-coverage-meter <?= e($barClass) ?>" min="0" max="100" value="<?= e((string)$pct) ?>"></meter>
        <div class="ls-coverage-pct"><strong><?= e((string)$pct) ?>%</strong> <span class="ls-muted"><?= e($ls('coverage_coverage')) ?></span></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <details class="ls-card ls-fold-card">
    <summary class="ls-fold-summary">
      <span class="ls-fold-title"><?= e($ls('lowest_title')) ?></span>
      <span class="ls-badge <?= $lowestCoverageCount > 0 ? 'is-warn' : 'is-ok' ?>"><?= e((string)$lowestCoverageCount) ?> <?= e($ls('lowest_owner')) ?></span>
    </summary>
    <div class="ls-fold-body">
    <p class="muted"><?= e($ls('lowest_desc')) ?></p>
    <?php
      $hasLow = false;
      foreach ($ownerCoverageRanking as $r):
        if ($r['total_missing'] > 0): $hasLow = true; break; endif;
      endforeach;
    ?>
    <?php if ($hasLow): ?>
    <div class="ls-table-wrap">
    <table class="ls-table">
      <thead>
        <tr>
          <th>#</th>
          <th><?= e($ls('lowest_owner')) ?></th>
          <th><?= e($ls('lowest_locales')) ?></th>
          <th><?= e($ls('lowest_keys')) ?></th>
          <th><?= e($ls('lowest_missing')) ?></th>
          <th><?= e($ls('lowest_pct')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php $rankIdx = 0; ?>
        <?php foreach ($ownerCoverageRanking as $r):
          if ($r['total_missing'] <= 0) continue;
          $rankIdx++;
          $barClass = $r['coverage_pct'] >= 95 ? 'is-good' : ($r['coverage_pct'] >= 70 ? 'is-warn' : 'is-poor');
        ?>
        <tr class="ls-rank-row" data-ls-rank-owner="<?= e($r['owner']) ?>">
          <td><span class="ls-rank-number"><?= e((string)$rankIdx) ?></span></td>
          <td><strong><?= e($r['owner']) ?></strong></td>
          <td><?= e((string)$r['locales_present']) ?>/<?= e((string)$r['locales_total']) ?></td>
          <td><?= e((string)$r['key_count']) ?></td>
          <td class="ls-missing-text"><?= e((string)$r['total_missing']) ?></td>
          <td>
            <meter class="ls-rank-meter <?= e($barClass) ?>" min="0" max="100" value="<?= e((string)$r['coverage_pct']) ?>"></meter>
            <?= e((string)$r['coverage_pct']) ?>%
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php else: ?>
    <p class="muted"><?= e($ls('lowest_none')) ?></p>
    <?php endif; ?>
    </div>
  </details>

  <details class="ls-card ls-fold-card">
    <summary class="ls-fold-summary">
      <span class="ls-fold-title"><?= e($ls('coverage_title')) ?></span>
      <span class="ls-badge is-readonly"><?= e((string)count($groups)) ?> <?= e($ls('summary_total_owners')) ?></span>
      <span class="ls-badge is-ok"><?= e((string)$summary['total_files']) ?> <?= e($ls('summary_total_files')) ?></span>
    </summary>
    <div class="ls-fold-body">

    <div class="ls-filter-bar">
      <input type="text" class="ls-filter-input" id="ls-owner-filter"
        placeholder="<?= e($ls('filter_owner_placeholder')) ?>">
      <button type="button" class="ls-filter-chip" data-ls-filter="missing-ja">
        <?= e($ls('filter_missing_ja')) ?>
      </button>
      <button type="button" class="ls-filter-chip" data-ls-filter="missing-ne">
        <?= e($ls('filter_missing_ne')) ?>
      </button>
      <button type="button" class="ls-filter-chip" data-ls-filter="has-mismatch">
        <?= e($ls('filter_key_mismatch')) ?>
      </button>
      <button type="button" class="ls-filter-chip ls-filter-clear" data-ls-clear>
        <?= e($ls('filter_clear')) ?>
      </button>
    </div>

    <div class="ls-table-wrap">
    <table class="ls-table">
      <thead>
        <tr>
          <th class="ls-expand-th"></th>
          <th><?= e($ls('coverage_owner')) ?></th>
          <?php foreach ($supportedLocales as $lc): ?>
            <th><?= e($ls('locale_' . $lc)) ?></th>
          <?php endforeach; ?>
          <th><?= e($ls('coverage_keys')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php $ownerRowIndex = 0; ?>
        <?php foreach ($groups as $ownerKey => $data):
          $hasMissingJa = !($data['files']['ja']['exists'] ?? false)
              || (int)($data['files']['ja']['missing_key_count'] ?? 0) > 0;
          $hasMissingNe = !($data['files']['ne']['exists'] ?? false)
              || (int)($data['files']['ne']['missing_key_count'] ?? 0) > 0;
          $hasMismatch = !empty($data['has_mismatch']);
          $ownerSlug = 'owner-' . (string)$ownerRowIndex;
          $ownerRowIndex++;
        ?>
        <tr class="ls-row-expand"
          data-ls-owner="<?= e($ownerKey) ?>"
          data-ls-detail="<?= e($ownerSlug) ?>"
          data-ls-missing-ja="<?= $hasMissingJa ? '1' : '0' ?>"
          data-ls-missing-ne="<?= $hasMissingNe ? '1' : '0' ?>"
          data-ls-has-mismatch="<?= $hasMismatch ? '1' : '0' ?>"
          role="button" tabindex="0"
          aria-expanded="false">
          <td><span class="ls-toggle-icon">▶</span></td>
          <td><strong><?= e($ownerKey) ?></strong></td>
          <?php foreach ($supportedLocales as $lc):
            $fd = $data['files'][$lc] ?? ['exists' => false];
          ?>
            <td>
              <?php if ($fd['exists']): ?>
                <span class="ls-status-dot ok"></span><?= e($ls('coverage_present')) ?>
              <?php else: ?>
                <span class="ls-status-dot missing"></span><?= e($ls('coverage_missing')) ?>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
          <td><?= e((string)$data['key_count']) ?></td>
        </tr>
        <tr class="ls-detail-row" data-ls-detail="<?= e($ownerSlug) ?>">
          <td colspan="<?= e((string)(count($supportedLocales) + 3)) ?>" class="ls-detail-cell">
            <div class="ls-detail-inner">
              <div class="ls-detail-grid">
                <?php foreach ($supportedLocales as $lc):
                  $fd = $data['files'][$lc] ?? null;
                  $keyListId = $ownerSlug . '-' . $lc . '-keys';
                  $copyFeedbackId = $ownerSlug . '-' . $lc . '-copy-feedback';
                  $missingKeys = ($fd && isset($fd['missing_keys']) && is_array($fd['missing_keys'])) ? $fd['missing_keys'] : [];
                ?>
                <div class="ls-detail-locale">
                  <h5>
                    <span class="ls-status-dot <?= ($fd && $fd['exists']) ? 'ok' : 'missing' ?>"></span>
                    <?= e($ls('locale_' . $lc)) ?>
                  </h5>
                  <?php if ($fd && $fd['exists']): ?>
                    <p class="meta">
                      <?= e((string)$fd['key_count']) ?> <?= e($ls('keys_label')) ?>
                      — <?= e($ls('file_path')) ?>: <code><?= e($fd['path']) ?></code>
                    </p>
                    <div class="ls-key-container" id="<?= e($keyListId) ?>">
                      <?php foreach ($fd['keys'] as $k): ?>
                        <code class="ls-key-item" data-ls-owner="<?= e($ownerKey) ?>"><?= e($k) ?></code>
                      <?php endforeach; ?>
                    </div>
                    <div class="ls-missing-key-block">
                      <strong><?= e($ls('missing_keys_title')) ?> (<?= e((string)count($missingKeys)) ?>)</strong>
                      <?php if ($missingKeys !== []): ?>
                        <div class="ls-key-container is-missing">
                          <?php foreach ($missingKeys as $k): ?>
                            <code class="ls-key-item" data-ls-owner="<?= e($ownerKey) ?>"><?= e($k) ?></code>
                          <?php endforeach; ?>
                        </div>
                      <?php else: ?>
                        <p class="meta"><?= e($ls('missing_keys_none')) ?></p>
                      <?php endif; ?>
                    </div>
                    <div class="ls-key-actions">
                      <button type="button" class="btn btn-sm" data-ls-copy="<?= e($keyListId) ?>" data-ls-copy-feedback="<?= e($copyFeedbackId) ?>">
                        <?= e($ls('copy_keys')) ?>
                      </button>
                      <span class="ls-copy-feedback" id="<?= e($copyFeedbackId) ?>">
                        <?= e($ls('copied')) ?>
                      </span>
                    </div>
                  <?php else: ?>
                    <p class="meta ls-missing-text">
                      <?= e($ls('locale_missing_file')) ?>
                    </p>
                    <?php if ($missingKeys !== []): ?>
                      <div class="ls-missing-key-block">
                        <strong><?= e($ls('missing_keys_title')) ?> (<?= e((string)count($missingKeys)) ?>)</strong>
                        <div class="ls-key-container is-missing">
                          <?php foreach ($missingKeys as $k): ?>
                            <code><?= e($k) ?></code>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <div class="ls-filter-empty" id="ls-filter-empty"><?= e($ls('filter_no_results')) ?></div>
    </div>
  </details>

  <details class="ls-card ls-fold-card">
    <summary class="ls-fold-summary">
      <span class="ls-fold-title"><?= e($ls('missing_locales_title')) ?></span>
      <span class="ls-badge <?= $missingLocaleFileCount > 0 ? 'is-missing' : 'is-ok' ?>"><?= e((string)$missingLocaleFileCount) ?> <?= e($ls('coverage_missing')) ?></span>
      <span class="ls-badge is-readonly"><?= e((string)$missingLocaleOwnerCount) ?> <?= e($ls('summary_total_owners')) ?></span>
    </summary>
    <div class="ls-fold-body">
    <?php
      $hasMissing = false;
      foreach ($groups as $ownerKey => $data):
        if ($data['missing_locales'] !== []):
          $hasMissing = true;
    ?>
    <div class="ls-missing-owner">
      <strong><?= e($ownerKey) ?></strong>: <?= e($ls('missing')) ?>
      <?php foreach ($data['missing_locales'] as $ml): ?>
        <span class="ls-badge is-missing"><?= e(strtoupper($ml)) ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; endforeach; ?>
    <?php if (!$hasMissing): ?>
    <p class="muted"><?= e($ls('missing_locales_none')) ?></p>
    <?php endif; ?>
    </div>
  </details>

  <details class="ls-card ls-fold-card">
    <summary class="ls-fold-summary">
      <span class="ls-fold-title"><?= e($ls('mismatch_title')) ?></span>
      <span class="ls-badge <?= $mismatchIssueCount > 0 ? 'is-warn' : 'is-ok' ?>"><?= e((string)$mismatchIssueCount) ?> <?= e($ls('mismatch_count')) ?></span>
      <span class="ls-badge is-readonly"><?= e((string)$mismatchOwnerCount) ?> <?= e($ls('summary_total_owners')) ?></span>
    </summary>
    <div class="ls-fold-body">
    <?php
      $hasMismatch = false;
      foreach ($groups as $ownerKey => $data):
        if ($data['has_mismatch'] && $data['mismatch_details'] !== []):
          $hasMismatch = true;
          foreach ($data['mismatch_details'] as $md):
    ?>
    <div class="ls-mismatch-item">
      <strong><?= e($ownerKey) ?></strong> —
      <?= e($ls('mismatch_reference')) ?>: <code><?= e($md['reference']) ?></code>,
      <?= e($ls('mismatch_other')) ?>: <code><?= e($md['other']) ?></code>
      <?php if ($md['missing_in_other'] !== []): ?>
        <br><?= e($ls('mismatch_missing_in_other')) ?>:
        <?php foreach (array_slice($md['missing_in_other'], 0, 10) as $k): ?>
          <code><?= e($k) ?></code>
        <?php endforeach; ?>
        <?php if (count($md['missing_in_other']) > 10): ?>
          <em> (+<?= e((string)(count($md['missing_in_other']) - 10)) ?> more)</em>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($md['extra_in_other'] !== []): ?>
        <br><?= e($ls('mismatch_extra_in_other')) ?>:
        <?php foreach (array_slice($md['extra_in_other'], 0, 10) as $k): ?>
          <code><?= e($k) ?></code>
        <?php endforeach; ?>
        <?php if (count($md['extra_in_other']) > 10): ?>
          <em> (+<?= e((string)(count($md['extra_in_other']) - 10)) ?> more)</em>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endforeach; endif; endforeach; ?>
    <?php if (!$hasMismatch): ?>
    <p class="muted"><?= e($ls('mismatch_none')) ?></p>
    <?php endif; ?>
    </div>
  </details>

  <details class="ls-card ls-fold-card">
    <summary class="ls-fold-summary">
      <span class="ls-fold-title"><?= e($ls('worklist_title')) ?></span>
      <span class="ls-badge <?= $worklistCount > 0 ? 'is-missing' : 'is-ok' ?>"><?= e((string)$worklistCount) ?> <?= e($ls('worklist_report_total')) ?></span>
      <span class="ls-badge is-readonly"><?= e((string)$worklistAffectedOwnerCount) ?> <?= e($ls('worklist_report_owners')) ?></span>
    </summary>
    <div class="ls-fold-body">
    <p class="muted"><?= e($ls('worklist_desc')) ?></p>

    <?php if ($worklistCount > 0): ?>
    <div class="ls-wl-report-grid">
      <div class="ls-wl-report-item">
        <strong><?= e((string)$worklistCount) ?></strong>
        <span><?= e($ls('worklist_report_total')) ?></span>
      </div>
      <div class="ls-wl-report-item">
        <strong><?= e((string)$worklistAffectedOwnerCount) ?></strong>
        <span><?= e($ls('worklist_report_owners')) ?></span>
      </div>
      <div class="ls-wl-report-item">
        <strong><?= e(implode(', ', array_map('strtoupper', $worklistAffectedLocaleList))) ?></strong>
        <span><?= e($ls('worklist_report_locales')) ?></span>
      </div>
      <div class="ls-wl-report-item">
        <strong><?= e(strtoupper(substr((string)$worklistTopOwners[0] ?? '', 0, 24))) ?>&hellip;</strong>
        <span><?= e($ls('worklist_report_top')) ?>: <?= e((string)($worklistOwnerScores[$worklistTopOwners[0]] ?? 0)) ?> <?= e($ls('worklist_report_entries')) ?></span>
      </div>
    </div>
    <?php endif; ?>

    <div class="ls-wl-export-bar">
      <div class="ls-wl-count">
        <?= e($ls('worklist_count_prefix')) ?> <strong id="ls-wl-total"><?= e((string)$worklistCount) ?></strong> <?= e($ls('worklist_count_suffix')) ?>
      </div>
      <button type="button" class="btn btn-sm" id="ls-wl-export-tsv"><?= e($ls('worklist_export_tsv')) ?></button>
      <span class="ls-wl-export-feedback" id="ls-wl-export-feedback"><?= e($ls('worklist_exported')) ?></span>
    </div>

    <div class="ls-filter-bar">
      <input type="text" class="ls-filter-input" id="ls-wl-filter-owner" placeholder="<?= e($ls('worklist_filter_owner')) ?>">
      <input type="text" class="ls-filter-input-sm" id="ls-wl-filter-key" placeholder="<?= e($ls('worklist_filter_key')) ?>">
      <button type="button" class="ls-filter-chip is-active is-missing" data-ls-wl-locale="ja">JA</button>
      <button type="button" class="ls-filter-chip is-active is-missing" data-ls-wl-locale="ne">NE</button>
      <button type="button" class="ls-filter-chip ls-filter-clear" data-ls-wl-clear><?= e($ls('filter_clear')) ?></button>
    </div>
    <div class="ls-table-wrap">
    <table class="ls-table">
      <thead>
        <tr>
          <th class="ls-th-sort ls-th-owner" data-ls-wl-sort="owner"><?= e($ls('worklist_owner')) ?><span class="ls-sort-icon"></span></th>
          <th class="ls-th-sort ls-th-locale" data-ls-wl-sort="locale"><?= e($ls('worklist_locale')) ?><span class="ls-sort-icon"></span></th>
          <th class="ls-th-sort" data-ls-wl-sort="key"><?= e($ls('worklist_key')) ?><span class="ls-sort-icon"></span></th>
          <th class="ls-th-values"><?= e($ls('worklist_values')) ?></th>
          <th class="ls-th-status"><?= e($ls('worklist_status')) ?></th>
          <th class="ls-wl-edit-th"></th>
        </tr>
      </thead>
      <tbody id="ls-wl-body" class="ls-wl-body">
      </tbody>
    </table>
    </div>
    <div class="ls-wl-empty" id="ls-wl-empty"><?= e($ls('worklist_no_results')) ?></div>
    </div>
  </details>

  <script id="ls-wl-data" type="application/json"><?php
    $wlJson = [];
    foreach ($worklist as $wl) {
        $wlJson[] = $wl;
    }
    echo json_encode($wlJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  ?></script>

  <script id="ls-values-data" type="application/json"><?php
    $valuesJson = [];
    foreach ($groups as $ownerKey => $data) {
        $ownerValues = [];
        foreach ($supportedLocales as $lc) {
            $fd = $data['files'][$lc] ?? null;
            if ($fd && $fd['exists'] && !empty($fd['values'])) {
                $ownerValues[$lc] = $fd['values'];
            }
        }
        if ($ownerValues !== []) {
            $valuesJson[$ownerKey] = $ownerValues;
        }
    }
    echo json_encode($valuesJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  ?></script>

  <script id="ls-i18n-data" type="application/json"><?php
    echo json_encode([
        'file_missing' => $ls('worklist_file_missing'),
        'file_present' => $ls('file_present'),
        'no_reference_values' => $ls('no_reference_values'),
        'tsv_owner' => $ls('worklist_owner'),
        'tsv_locale' => $ls('worklist_locale'),
        'tsv_missing_key' => $ls('worklist_key'),
        'tsv_reference_values' => $ls('worklist_values'),
        'tsv_file_present' => $ls('tsv_file_present'),
        'yes' => $ls('yes'),
        'no' => $ls('no'),
        'owner_label' => $ls('owner_label'),
        'locale_en' => $ls('locale_en'),
        'locale_ja' => $ls('locale_ja'),
        'locale_ne' => $ls('locale_ne'),
        'no_value' => $ls('no_value'),
        'locale_file_not_found_for_owner' => $ls('locale_file_not_found_for_owner'),
        'empty_string' => $ls('empty_string'),
        'not_present_in_locale_file' => $ls('not_present_in_locale_file'),
        'open_in_editor' => $ls('open_in_editor'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  ?></script>

  <div id="ls-key-detail" class="ls-key-detail-overlay" role="dialog" aria-modal="true" aria-label="<?= e($ls('key_detail_inspector')) ?>">
    <div class="ls-key-detail-backdrop" data-ls-close-detail></div>
    <div class="ls-key-detail-panel">
      <div class="ls-key-detail-header">
        <span class="ls-key-detail-title" id="ls-detail-key-name"><?= e($ls('no_data')) ?></span>
        <span class="ls-key-detail-owner" id="ls-detail-owner-name"></span>
        <button type="button" class="ls-key-detail-close" data-ls-close-detail aria-label="<?= e($ls('close')) ?>">&times;</button>
      </div>
      <div class="ls-key-detail-body" id="ls-detail-body">
        <div class="ls-key-detail-empty"><?= e($ls('no_data')) ?></div>
      </div>
    </div>
  </div>

  <script>
  (function(){
    var rows=[];
    var detailRows={};

    function init(){
      document.querySelectorAll('.ls-row-expand').forEach(function(r){
        rows.push(r);
      });
      document.querySelectorAll('.ls-detail-row').forEach(function(r){
        var detailKey=r.getAttribute('data-ls-detail');
        if(detailKey) detailRows[detailKey]=r;
      });
      document.querySelectorAll('.ls-row-expand').forEach(function(row){
        row.addEventListener('click',function(){
          window.LSC.toggleRow(row);
        });
        row.addEventListener('keydown',function(event){
          if(event.key==='Enter' || event.key===' '){
            event.preventDefault();
            window.LSC.toggleRow(row);
          }
        });
      });
      document.querySelectorAll('[data-ls-filter]').forEach(function(chip){
        chip.addEventListener('click',function(){
          window.LSC.toggleFilter(chip);
        });
      });
      document.querySelectorAll('[data-ls-clear]').forEach(function(button){
        button.addEventListener('click',function(){
          window.LSC.clearFilters();
        });
      });
      var ownerFilter=document.getElementById('ls-owner-filter');
      if(ownerFilter){
        ownerFilter.addEventListener('input',function(){
          window.LSC.filterOwners();
        });
      }
      document.querySelectorAll('[data-ls-copy]').forEach(function(button){
        button.addEventListener('click',function(event){
          event.stopPropagation();
          window.LSC.copyKeys(button.getAttribute('data-ls-copy'), button.getAttribute('data-ls-copy-feedback'));
        });
      });
      document.addEventListener('click',function(event){
        var target=event.target;
        var keyItem=target.closest('.ls-key-item');
        if(keyItem){
          var keyName=keyItem.textContent.trim();
          var ownerKey=keyItem.getAttribute('data-ls-owner');
          event.stopPropagation();
          window.LSC.showKeyDetail(keyName, ownerKey);
          return;
        }
        var closeTrigger=target.closest('[data-ls-close-detail]');
        if(closeTrigger){
          event.stopPropagation();
          window.LSC.hideKeyDetail();
          return;
        }
      });
      document.addEventListener('keydown',function(event){
        if(event.key==='Escape'){
          window.LSC.hideKeyDetail();
        }
      });
      document.querySelectorAll('.ls-rank-row').forEach(function(row){
        row.addEventListener('click',function(){
          var owner=row.getAttribute('data-ls-rank-owner');
          if(owner){
            window.LSC.filterByOwner(owner);
          }
        });
      });
      // Worklist init
      window.LSC.worklistSortField='owner';
      window.LSC.worklistSortDir='asc';
      window.LSC.renderWorklist();
      document.getElementById('ls-wl-filter-owner').addEventListener('input',function(){
        window.LSC.renderWorklist();
      });
      document.getElementById('ls-wl-filter-key').addEventListener('input',function(){
        window.LSC.renderWorklist();
      });
      document.querySelectorAll('[data-ls-wl-locale]').forEach(function(chip){
        chip.addEventListener('click',function(){
          chip.classList.toggle('is-active');
          window.LSC.renderWorklist();
        });
      });
      document.querySelectorAll('[data-ls-wl-clear]').forEach(function(btn){
        btn.addEventListener('click',function(){
          document.getElementById('ls-wl-filter-owner').value='';
          document.getElementById('ls-wl-filter-key').value='';
          document.querySelectorAll('[data-ls-wl-locale].is-active').forEach(function(c){
            c.classList.remove('is-active');
          });
          window.LSC.renderWorklist();
        });
      });
      document.querySelectorAll('[data-ls-wl-sort]').forEach(function(th){
        th.addEventListener('click',function(){
          var field=th.getAttribute('data-ls-wl-sort');
          window.LSC.toggleWorklistSort(field);
        });
      });
      document.getElementById('ls-wl-export-tsv').addEventListener('click',function(){
        window.LSC.exportWorklistTSV();
      });

      // Initialize coverage filter state
      window.LSC.filterOwners();
    }

    window.LSC={
      toggleRow: function(el){
        var detailKey=el.getAttribute('data-ls-detail');
        if(!detailKey)return;
        var detail=detailRows[detailKey];
        if(!detail)return;
        var icon=el.querySelector('.ls-toggle-icon');
        var expanded=detail.classList.contains('is-visible');
        detail.classList.toggle('is-visible');
        el.setAttribute('aria-expanded',expanded?'false':'true');
        if(icon)icon.classList.toggle('is-expanded');
      },

      toggleFilter: function(chip){
        chip.classList.toggle('is-active');
        this.filterOwners();
      },

      clearFilters: function(){
        document.querySelectorAll('.ls-filter-chip.is-active').forEach(function(c){
          c.classList.remove('is-active');
        });
        var inp=document.getElementById('ls-owner-filter');
        if(inp)inp.value='';
        this.filterOwners();
      },

      filterOwners: function(){
        var text=(document.getElementById('ls-owner-filter')||{}).value||'';
        text=text.toLowerCase().trim();

        var activeFilters=[];
        document.querySelectorAll('.ls-filter-chip.is-active').forEach(function(c){
          var f=c.getAttribute('data-ls-filter');
          if(f)activeFilters.push(f);
        });

        var visibleCount=0;
        rows.forEach(function(row){
          var owner=row.getAttribute('data-ls-owner')||'';
          var show=true;

          if(text && owner.toLowerCase().indexOf(text)===-1)show=false;

          activeFilters.forEach(function(f){
            if(f==='missing-ja' && row.getAttribute('data-ls-missing-ja')!=='1')show=false;
            if(f==='missing-ne' && row.getAttribute('data-ls-missing-ne')!=='1')show=false;
            if(f==='has-mismatch' && row.getAttribute('data-ls-has-mismatch')!=='1')show=false;
          });

          row.classList.toggle('ls-hidden',!show);
          var detail=detailRows[row.getAttribute('data-ls-detail')||''];
          if(detail)detail.classList.toggle('ls-hidden',!show);
          if(show) visibleCount++;
        });

        var empty=document.getElementById('ls-filter-empty');
        if(empty) empty.classList.toggle('is-visible',visibleCount===0);
      },

      copyKeys: function(keyListId,feedbackId){
        var container=document.getElementById(keyListId);
        if(!container)return;
        var keys=[];
        container.querySelectorAll('code').forEach(function(c){
          keys.push(c.textContent);
        });
        var text=keys.join('\n');
        if(navigator.clipboard && navigator.clipboard.writeText){
          navigator.clipboard.writeText(text).then(function(){
            LSC.showCopyFeedback(feedbackId);
          });
        }else{
          var ta=document.createElement('textarea');
          ta.value=text;
          ta.style.position='fixed';
          ta.style.opacity='0';
          document.body.appendChild(ta);
          ta.select();
          document.execCommand('copy');
          document.body.removeChild(ta);
          LSC.showCopyFeedback(feedbackId);
        }
      },

      showCopyFeedback: function(feedbackId){
        var fb=document.getElementById(feedbackId);
        if(!fb)return;
        fb.classList.add('is-visible');
        setTimeout(function(){fb.classList.remove('is-visible');},1500);
      },

      i18nData: null,

      t: function(key){
        if(!this.i18nData){
          var el=document.getElementById('ls-i18n-data');
          if(el){
            try{
              this.i18nData=JSON.parse(el.textContent);
            }catch(e){
              this.i18nData={};
            }
          }else{
            this.i18nData={};
          }
        }
        return this.i18nData[key] || key;
      },

      valuesData: null,

      getValuesData: function(){
        if(this.valuesData) return this.valuesData;
        var el=document.getElementById('ls-values-data');
        if(!el) return {};
        try{
          this.valuesData=JSON.parse(el.textContent);
        }catch(e){
          this.valuesData={};
        }
        return this.valuesData;
      },

      filterByOwner: function(ownerName){
        // Deactivate all coverage filter chips without clearing input
        document.querySelectorAll('[data-ls-filter].is-active').forEach(function(c){
          c.classList.remove('is-active');
        });
        var inp=document.getElementById('ls-owner-filter');
        if(inp) inp.value=ownerName;
        this.filterOwners();
        var row=document.querySelector('.ls-row-expand[data-ls-owner="'+ownerName+'"]');
        if(row) row.scrollIntoView({behavior:'smooth',block:'center'});
      },

      worklistData: null,
      worklistSortField: 'owner',
      worklistSortDir: 'asc',

      getWorklistData: function(){
        if(this.worklistData) return this.worklistData;
        var el=document.getElementById('ls-wl-data');
        if(!el) return [];
        try{
          this.worklistData=JSON.parse(el.textContent);
        }catch(e){
          this.worklistData=[];
        }
        return this.worklistData;
      },

      toggleWorklistSort: function(field){
        if(this.worklistSortField===field){
          this.worklistSortDir=this.worklistSortDir==='asc'?'desc':'asc';
        }else{
          this.worklistSortField=field;
          this.worklistSortDir='asc';
        }
        document.querySelectorAll('[data-ls-wl-sort] .ls-sort-icon').forEach(function(icon){
          icon.textContent='';
        });
        var activeTh=document.querySelector('[data-ls-wl-sort="'+field+'"] .ls-sort-icon');
        if(activeTh) activeTh.textContent=this.worklistSortDir==='asc'?'▲':'▼';
        this.renderWorklist();
      },

      renderWorklist: function(){
        var data=this.getWorklistData();
        var ownerText=(document.getElementById('ls-wl-filter-owner')||{}).value||'';
        ownerText=ownerText.toLowerCase().trim();
        var keyText=(document.getElementById('ls-wl-filter-key')||{}).value||'';
        keyText=keyText.toLowerCase().trim();

        var activeLocales=[];
        document.querySelectorAll('[data-ls-wl-locale].is-active').forEach(function(c){
          var l=c.getAttribute('data-ls-wl-locale');
          if(l) activeLocales.push(l);
        });

        // Filter
        var filtered=[];
        for(var i=0;i<data.length;i++){
          var item=data[i];
          if(ownerText && item.owner.toLowerCase().indexOf(ownerText)===-1) continue;
          if(keyText && item.key.toLowerCase().indexOf(keyText)===-1) continue;
          if(activeLocales.length>0 && activeLocales.indexOf(item.locale)===-1) continue;
          filtered.push(item);
        }

        // Sort
        var field=this.worklistSortField;
        var dir=this.worklistSortDir;
        filtered.sort(function(a,b){
          var va=a[field]||'', vb=b[field]||'';
          var cmp=va<vb?-1:va>vb?1:0;
          return dir==='asc'?cmp:-cmp;
        });

        // Render
        var body=document.getElementById('ls-wl-body');
        var empty=document.getElementById('ls-wl-empty');
        var totalEl=document.getElementById('ls-wl-total');
        if(totalEl) totalEl.textContent=String(filtered.length);

        if(!body) return;

        if(filtered.length===0){
          body.innerHTML='';
          if(empty) empty.classList.add('is-visible');
          return;
        }
        if(empty) empty.classList.remove('is-visible');

        var html='';
        var groupOwner=null;
        for(var i=0;i<filtered.length;i++){
          var item=filtered[i];

          // Group header by owner
          if(item.owner!==groupOwner){
            groupOwner=item.owner;
            html+='<tr class="ls-wl-group-header">';
            html+='<td colspan="6">'+LSC.escapeHtml(groupOwner)+'</td>';
            html+='</tr>';
          }

          var otv='';
          var first=true;
          for(var lc in item.other_values){
            if(item.other_values.hasOwnProperty(lc)){
              if(!first) otv+=' · ';
              first=false;
              otv+=lc+': '+LSC.escapeHtml(String(item.other_values[lc]));
            }
          }
          var fileStatus='';
          if(item.file_missing){
            fileStatus='<span class="ls-badge is-missing">'+LSC.escapeHtml(LSC.t('file_missing'))+'</span>';
          }else if(item.file_path){
            fileStatus='<span class="ls-badge is-ok">'+LSC.escapeHtml(LSC.t('file_present'))+'</span>';
          }
          var localeLabel=item.locale.toUpperCase();
          var localeClass=item.locale;
          var keyTrunc=LSC.escapeHtml(item.key);
          var keyLong=keyTrunc.length>50;
          if(keyLong) keyTrunc=keyTrunc.substring(0,47)+'...';
          html+='<tr>';
          html+='<td class="ls-wl-owner" data-ls-wl-owner-click="'+LSC.escapeAttr(item.owner)+'">'+LSC.escapeHtml(item.owner)+'</td>';
          html+='<td><span class="ls-wl-locale-badge '+localeClass+'">'+localeLabel+'</span></td>';
          html+='<td class="ls-wl-key ls-wl-key-col" title="'+LSC.escapeAttr(item.key)+': '+LSC.escapeAttr(otv||LSC.t('no_reference_values'))+'" data-ls-wl-key-click="'+LSC.escapeAttr(item.key)+'" data-ls-wl-key-owner="'+LSC.escapeAttr(item.owner)+'">'+keyTrunc+'</td>';
          html+='<td class="ls-wl-values-col" title="'+LSC.escapeAttr(otv||LSC.t('no_reference_values'))+'">'+(otv||'<span class="ls-muted">—</span>')+'</td>';
          html+='<td>'+fileStatus+'</td>';
          html+='<td class="ls-wl-edit-cell"><a href="'+LSC.escapeAttr('/apps/studio/tools/localization-studio/edit?owner='+item.owner+'&locale='+item.locale+'&key='+encodeURIComponent(item.key))+'" class="ls-wl-edit-link">'+LSC.escapeHtml(LSC.t('open_in_editor'))+'</a></td>';
          html+='</tr>';
        }
        body.innerHTML=html;

        // Wire clicks
        body.querySelectorAll('[data-ls-wl-key-click]').forEach(function(el){
          el.addEventListener('click',function(event){
            var keyName=el.getAttribute('data-ls-wl-key-click');
            var ownerKey=el.getAttribute('data-ls-wl-key-owner');
            if(keyName) window.LSC.showKeyDetail(keyName, ownerKey);
          });
        });
        body.querySelectorAll('[data-ls-wl-owner-click]').forEach(function(el){
          el.addEventListener('click',function(event){
            var ownerName=el.getAttribute('data-ls-wl-owner-click');
            if(ownerName) window.LSC.filterByOwner(ownerName);
          });
        });
      },

      exportWorklistTSV: function(){
        var data=this.getWorklistData();

        // Use current filters
        var ownerText=(document.getElementById('ls-wl-filter-owner')||{}).value||'';
        ownerText=ownerText.toLowerCase().trim();
        var keyText=(document.getElementById('ls-wl-filter-key')||{}).value||'';
        keyText=keyText.toLowerCase().trim();
        var activeLocales=[];
        document.querySelectorAll('[data-ls-wl-locale].is-active').forEach(function(c){
          var l=c.getAttribute('data-ls-wl-locale');
          if(l) activeLocales.push(l);
        });

        var filtered=[];
        for(var i=0;i<data.length;i++){
          var item=data[i];
          if(ownerText && item.owner.toLowerCase().indexOf(ownerText)===-1) continue;
          if(keyText && item.key.toLowerCase().indexOf(keyText)===-1) continue;
          if(activeLocales.length>0 && activeLocales.indexOf(item.locale)===-1) continue;
          filtered.push(item);
        }

        // Sort by current sort
        var field=this.worklistSortField;
        var dir=this.worklistSortDir;
        filtered.sort(function(a,b){
          var va=a[field]||'', vb=b[field]||'';
          var cmp=va<vb?-1:va>vb?1:0;
          return dir==='asc'?cmp:-cmp;
        });

        // Build TSV
        var rows=[[
          this.t('tsv_owner'),
          this.t('tsv_locale'),
          this.t('tsv_missing_key'),
          this.t('tsv_reference_values'),
          this.t('tsv_file_present')
        ].join('\t')];
        for(var i=0;i<filtered.length;i++){
          var item=filtered[i];
          var vals=[];
          for(var lc in item.other_values){
            if(item.other_values.hasOwnProperty(lc)){
              vals.push(lc+': '+item.other_values[lc]);
            }
          }
          var ref=(item.file_missing?this.t('no'):this.t('yes'));
          rows.push(item.owner+'\t'+item.locale+'\t'+item.key+'\t'+vals.join('; ')+'\t'+ref);
        }
        var text=rows.join('\n');

        // Copy to clipboard
        if(navigator.clipboard && navigator.clipboard.writeText){
          navigator.clipboard.writeText(text).then(function(){
            LSC.showWorklistExportFeedback();
          });
        }else{
          var ta=document.createElement('textarea');
          ta.value=text;
          ta.style.position='fixed';
          ta.style.opacity='0';
          document.body.appendChild(ta);
          ta.select();
          document.execCommand('copy');
          document.body.removeChild(ta);
          LSC.showWorklistExportFeedback();
        }
      },

      showWorklistExportFeedback: function(){
        var fb=document.getElementById('ls-wl-export-feedback');
        if(!fb)return;
        fb.classList.add('is-visible');
        setTimeout(function(){fb.classList.remove('is-visible');},1500);
      },

      escapeAttr: function(str){
        return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
      },

      showKeyDetail: function(keyName, ownerKey){
        var data=this.getValuesData();
        var localeData=data[ownerKey]||{};

        document.getElementById('ls-detail-key-name').textContent=keyName;
        var ownerEl=document.getElementById('ls-detail-owner-name');
        ownerEl.textContent=ownerKey ? this.t('owner_label')+' '+ownerKey : '';

        var body=document.getElementById('ls-detail-body');
        if(!body)return;

        var html='';
        var locales=['en','ja','ne'];
        var localeNames={'en':this.t('locale_en'),'ja':this.t('locale_ja'),'ne':this.t('locale_ne')};

        for(var i=0;i<locales.length;i++){
          var lc=locales[i];
          var value=localeData[lc] ? localeData[lc][keyName] : undefined;
          var hasValue=value!==undefined && value!==null;
          var isMissing=!localeData[lc];
          var localeName=localeNames[lc]||lc;

          html+='<div class="ls-key-detail-locale-card">';
          html+='<h5>';
          if(hasValue){
            html+='<span class="ls-status-dot ok"></span>'+localeName;
          }else{
            html+='<span class="ls-status-dot missing"></span>'+localeName;
          }
          if(isMissing){
            html+=' <span class="ls-badge is-missing">'+LSC.escapeHtml(LSC.t('file_missing'))+'</span>';
          }else if(!hasValue){
            html+=' <span class="ls-badge is-warn">'+LSC.escapeHtml(LSC.t('no_value'))+'</span>';
          }
          html+='</h5>';

          if(isMissing){
            html+='<p class="meta ls-missing-text">'+LSC.escapeHtml(LSC.t('locale_file_not_found_for_owner'))+'</p>';
          }else if(hasValue){
            var displayValue=String(value);
            if(displayValue===''){
              html+='<div class="ls-key-value-box is-empty">'+LSC.escapeHtml(LSC.t('empty_string'))+'</div>';
            }else{
              html+='<div class="ls-key-value-box">'+LSC.escapeHtml(displayValue)+'</div>';
            }
          }else{
            html+='<div class="ls-key-value-box is-empty">'+LSC.escapeHtml(LSC.t('not_present_in_locale_file'))+'</div>';
          }
          html+='</div>';
        }

        body.innerHTML=html;

        var overlay=document.getElementById('ls-key-detail');
        if(overlay) overlay.classList.add('is-visible');
      },

      hideKeyDetail: function(){
        var overlay=document.getElementById('ls-key-detail');
        if(overlay) overlay.classList.remove('is-visible');
      },

      escapeHtml: function(str){
        var div=document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
      }
    };

    if(document.readyState==='loading'){
      document.addEventListener('DOMContentLoaded',init);
    }else{
      init();
    }
  })();
  </script>

  <?php endif; ?>

  <div class="ls-actions">
    <a class="btn" href="/apps/studio/legacy"><?= e($ls('open_legacy')) ?></a>
  </div>
</section>
