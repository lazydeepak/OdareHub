<?php
/**
 * Shared diagnostics panel partial.
 *
 * Renders a summary card (pass/info/warning/error counts) followed by
 * an expandable severity-colored diagnostics table.
 *
 * Expected variables (set by caller before require):
 *   $panelTitle      — section heading string (already localized)
 *   $panelItems      — array of ['severity' => 'pass'|'info'|'warning'|'error', 'code' => '…', 'message' => '…']
 *   $panelClass      — optional CSS class suffix or '' (default: '')
 *   $panelId         — optional anchor id (default: '')
 *   $panelCollapsed  — bool, whether the details table is collapsed (default: true)
 *   $panelExtra      — optional extra HTML rendered below summary counts (default: '')
 */
$panelItems = isset($panelItems) && is_array($panelItems) ? $panelItems : [];
$panelClass = isset($panelClass) && is_string($panelClass) ? $panelClass : '';
$panelId = isset($panelId) && is_string($panelId) ? $panelId : '';
$panelCollapsed = isset($panelCollapsed) ? (bool)$panelCollapsed : true;
$panelExtra = isset($panelExtra) && is_string($panelExtra) ? $panelExtra : '';

$passCount = 0;
$infoCount = 0;
$warnCount = 0;
$errorCount = 0;
foreach ($panelItems as $item) {
    $sev = strtolower((string)($item['severity'] ?? 'pass'));
    if ($sev === 'error' || $sev === 'fail' || $sev === 'failed') { $errorCount++; }
    elseif ($sev === 'warning' || $sev === 'warn') { $warnCount++; }
    elseif ($sev === 'info') { $infoCount++; }
    else { $passCount++; }
}
$totalDiag = count($panelItems);
?>
<div class="ld-diag-panel <?= e($panelClass) ?>"<?= $panelId !== '' ? ' id="' . e($panelId) . '"' : '' ?>>
  <div class="ld-diag-summary">
    <div class="ld-diag-summary-counts">
      <span class="ld-diag-count ld-diag-count-pass" title="PASS"><?= $passCount ?></span>
      <span class="ld-diag-count ld-diag-count-info" title="INFO"><?= $infoCount ?></span>
      <span class="ld-diag-count ld-diag-count-warn" title="WARNING"><?= $warnCount ?></span>
      <span class="ld-diag-count ld-diag-count-error" title="ERROR"><?= $errorCount ?></span>
    </div>
    <div class="ld-diag-summary-labels">
      <span class="ld-diag-label ld-diag-label-pass">pass</span>
      <span class="ld-diag-label ld-diag-label-info">info</span>
      <span class="ld-diag-label ld-diag-label-warn">warning</span>
      <span class="ld-diag-label ld-diag-label-error">error</span>
    </div>
    <?php if ($panelExtra !== ''): ?>
      <div class="ld-diag-extra"><?= $panelExtra ?></div>
    <?php endif; ?>
  </div>
  <?php if ($totalDiag > 0): ?>
    <details class="ld-diag-details"<?= $panelCollapsed ? '' : ' open' ?>>
      <summary class="ld-diag-details-summary"><?= e(sprintf('Show details (%d diagnostic(s))', $totalDiag)) ?></summary>
      <div class="ld-diag-table-wrap">
        <table class="ld-diag-table">
          <thead>
            <tr>
              <th class="ld-diag-th ld-diag-th-sev">Severity</th>
              <th class="ld-diag-th ld-diag-th-code">Code</th>
              <th class="ld-diag-th ld-diag-th-msg">Message</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($panelItems as $item): ?>
              <?php
              $sev = strtolower((string)($item['severity'] ?? 'pass'));
              if (in_array($sev, ['error', 'fail', 'failed'], true)) { $sevClass = 'error'; }
              elseif (in_array($sev, ['warning', 'warn'], true)) { $sevClass = 'warning'; }
              elseif ($sev === 'info') { $sevClass = 'info'; }
              else { $sevClass = 'pass'; }
              $code = (string)($item['code'] ?? '');
              $msg = (string)($item['message'] ?? '');
              ?>
              <tr class="ld-diag-row ld-diag-row-<?= e($sevClass) ?>">
                <td class="ld-diag-cell ld-diag-cell-sev ld-diag-cell-<?= e($sevClass) ?>"><?= e($sevClass) ?></td>
                <td class="ld-diag-cell ld-diag-cell-code"><code><?= e($code) ?></code></td>
                <td class="ld-diag-cell ld-diag-cell-msg"><?= e($msg) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>
  <?php endif; ?>
</div>
