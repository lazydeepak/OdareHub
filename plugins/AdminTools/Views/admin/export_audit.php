<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"><?= e(t('admin.export_audit.title')) ?></h2>
      <div class="muted"><?= e(t('admin.export_audit.subtitle')) ?></div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/admin/system-tools"><?= e(t('admin.system_tools.nav.back')) ?></a>
    </div>
  </div>
</div>

<?php
$exports = is_array($exports ?? null) ? $exports : [];
$summary = is_array($summary ?? null) ? $summary : [];
?>

<div class="card">
  <h3 class="u-style-d462248a40"><?= e(t('admin.export_audit.summary')) ?></h3>
  <div class="u-style-a344fcd590">
    <div class="u-style-1e32d34ea1">
      <div class="u-style-cf5c8cb19b"><?= (int)($summary['total'] ?? 0) ?></div>
      <div class="muted u-style-6cb285c61d"><?= e(t('admin.export_audit.total_exports')) ?></div>
    </div>
    <div class="u-style-79f23e1750">
      <div class="u-style-e585316bfb"><?= (int)($summary['success_count'] ?? 0) ?></div>
      <div class="muted u-style-6cb285c61d"><?= e(t('admin.export_audit.successful')) ?></div>
    </div>
    <div class="u-style-efd7a2968c">
      <div class="u-style-5464aef99d"><?= (int)($summary['warning_count'] ?? 0) ?></div>
      <div class="muted u-style-6cb285c61d"><?= e(t('admin.export_audit.warnings')) ?></div>
    </div>
    <div class="u-style-87b9eb3557">
      <div class="u-style-2bddb34f6d"><?= (int)($summary['error_count'] ?? 0) ?></div>
      <div class="muted u-style-6cb285c61d"><?= e(t('admin.export_audit.errors')) ?></div>
    </div>
    <div class="u-style-e2e9a32fb9">
      <div class="u-style-e736dde5fe"><?= (int)($summary['csv_count'] ?? 0) ?></div>
      <div class="muted u-style-6cb285c61d"><?= e(t('admin.export_audit.csv_exports')) ?></div>
    </div>
    <div class="u-style-d431b56203">
      <div class="u-style-a115f39450"><?= (int)($summary['pdf_count'] ?? 0) ?></div>
      <div class="muted u-style-6cb285c61d"><?= e(t('admin.export_audit.pdf_exports')) ?></div>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40"><?= e(t('admin.export_audit.history')) ?></h3>
  <form method="get" action="/admin/system-tools/export-audit" class="row u-style-0777bf4cab">
    <label class="u-style-eb62184e30">
      <div class="muted u-style-86c64b5cbf"><?= e(t('admin.export_audit.filter_type')) ?></div>
      <select class="u-style-0466783d98" name="type">
        <option value=""><?= e(t('admin.export_audit.all_types')) ?></option>
        <option value="csv" <?= ((string)($type ?? '') === 'csv') ? 'selected' : '' ?>>CSV</option>
        <option value="pdf" <?= ((string)($type ?? '') === 'pdf') ? 'selected' : '' ?>>PDF</option>
      </select>
    </label>
    <label class="u-style-eb62184e30">
      <div class="muted u-style-86c64b5cbf"><?= e(t('admin.export_audit.filter_status')) ?></div>
      <select class="u-style-0466783d98" name="status">
        <option value=""><?= e(t('admin.export_audit.all_statuses')) ?></option>
        <option value="completed" <?= ((string)($status ?? '') === 'completed') ? 'selected' : '' ?>>Completed</option>
        <option value="warning" <?= ((string)($status ?? '') === 'warning') ? 'selected' : '' ?>>Warning</option>
        <option value="error" <?= ((string)($status ?? '') === 'error') ? 'selected' : '' ?>>Error</option>
      </select>
    </label>
    <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
    <a class="btn" href="/admin/system-tools/export-audit"><?= e(t('common.reset')) ?></a>
  </form>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e(t('admin.export_audit.when')) ?></th>
          <th><?= e(t('admin.export_audit.app')) ?></th>
          <th><?= e(t('admin.export_audit.target')) ?></th>
          <th><?= e(t('admin.export_audit.export_type')) ?></th>
          <th><?= e(t('admin.export_audit.format')) ?></th>
          <th><?= e(t('admin.export_audit.status')) ?></th>
          <th><?= e(t('admin.export_audit.summary_info')) ?></th>
          <th><?= e(t('admin.export_audit.user')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($exports)): ?>
          <tr>
            <td colspan="8" class="muted u-style-02c99fd2eb"><?= e(t('admin.export_audit.no_exports')) ?></td>
          </tr>
        <?php else: ?>
          <?php foreach ($exports as $row): ?>
            <tr>
              <td class="u-style-6cb285c61d"><?= e((string)($row['created_at'] ?? '')) ?></td>
              <td><?= e((string)($row['suite_key'] ?? 'system')) ?></td>
              <td>
                <div class="ui-block"><?= e((string)($row['target_type'] ?? '')) ?></div>
                <div class="muted u-style-11a508128d"><?= e((string)($row['target_key'] ?? '')) ?></div>
              </td>
              <td><?= e((string)($row['export_type'] ?? '')) ?></td>
              <td>
                <?php
                $fmt = strtoupper((string)($row['export_type'] ?? 'unknown'));
                if ($fmt === 'CSV') {
                  echo '<span class="u-style-8cbdb9646a">CSV</span>';
                } elseif ($fmt === 'PDF') {
                  echo '<span class="u-style-d6bb6c7fdd">PDF</span>';
                } else {
                  echo e($fmt);
                }
                ?>
              </td>
              <td>
                <?php
                $st = strtolower((string)($row['status'] ?? 'unknown'));
                if ($st === 'completed') {
                  echo '<span class="u-style-ce361340e5">✓ Completed</span>';
                } elseif ($st === 'warning') {
                  echo '<span class="u-style-e3163d2a91">⚠ Warning</span>';
                } elseif ($st === 'error') {
                  echo '<span class="u-style-0d3e1d2cca">✗ Error</span>';
                } else {
                  echo e($st);
                }
                ?>
              </td>
              <td class="u-style-11a508128d">
                <?php $sum = json_decode((string)($row['summary_json'] ?? '{}'), true); ?>
                <?php if (!empty($sum) && is_array($sum)): ?>
                  <?php if (!empty($sum['row_count'])): ?>
                    <?= (int)($sum['row_count']) ?> rows
                  <?php endif; ?>
                  <?php if (!empty($sum['file_size'])): ?>
                    · <?= e((string)($sum['file_size'] ?? '')) ?>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="muted">-</span>
                <?php endif; ?>
              </td>
              <td class="u-style-11a508128d"><?= e((string)($row['created_by'] ?? '-')) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
