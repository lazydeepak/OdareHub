<?php
$renderFlash = static function () use ($labelDesignerFlash): void {
    if (empty($labelDesignerFlash)) return;
    ?><div class="gs-studio-tool-card-purpose" style="margin-bottom:8px">
      <strong><?= e((string)($labelDesignerFlash['type'] ?? 'info')) ?>:</strong>
      <?php $flashDetails = isset($labelDesignerFlash['details']) && is_array($labelDesignerFlash['details']) ? $labelDesignerFlash['details'] : []; ?>
      <?php if (!empty($flashDetails)): ?>
        <ul style="margin:4px 0">
          <?php foreach ($flashDetails as $detail): ?>
            <li><?= e((string)$detail) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div><?php
};
$renderDiagTable = static function (string $prefix) use ($ld, $labelEditDiagnostics): void {
    if (empty($labelEditDiagnostics['checks'])) return;
    $titleKey = $prefix . '_diagnostics_title';
    $codeKey = $prefix . '_diagnostics_code_label';
    $checkKey = $prefix . '_diagnostics_check_label';
    $resultKey = $prefix . '_diagnostics_result_label';
    ?><div class="ld-inspector-section-title ld-diag-title"><?= e($ld($titleKey)) ?></div>
    <table class="ld-inspector-table ld-diag-table">
      <thead>
        <tr>
          <th><?= e($ld($codeKey)) ?></th>
          <th><?= e($ld($checkKey)) ?></th>
          <th><?= e($ld($resultKey)) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($labelEditDiagnostics['checks'] as $check): ?>
        <tr>
          <td><code><?= e((string)($check['code'] ?? '')) ?></code></td>
          <td><?= e((string)($check['label'] ?? '')) ?></td>
          <td><?php $sev = (string)($check['severity'] ?? ''); ?><span class="ld-diag-<?= e(strtolower($sev)) ?>"><?= e($sev) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table><?php
};
