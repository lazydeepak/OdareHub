<?php
$tt = static function (string $key): string {
  return t($key);
};
$report = is_array($report ?? null) ? $report : null;
$reportData = is_array($reportData ?? null) ? $reportData : [];
$summary = is_array($reportData['summary'] ?? null) ? $reportData['summary'] : [];
$byType = is_array($reportData['byType'] ?? null) ? $reportData['byType'] : [];
$byLevel = is_array($reportData['byLevel'] ?? null) ? $reportData['byLevel'] : [];
$recent = is_array($reportData['recent'] ?? null) ? $reportData['recent'] : [];
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-m-0"> <?= e($tt('notices.notices_report_title')) ?> </h2>
      <div class="muted"> <?= e($tt('notices.report_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/apps/sbaio/notices">&larr; Notices</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row row-spread">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('notices.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/apps/sbaio/notices/export"> <?= e($tt('notices.open_link')) ?> </a></div>
    </div>
  <?php else: ?>
    <p class="muted u-m-0"> <?= e($tt('notices.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-mt-0"> <?= e($tt('notices.notice_snapshot_title')) ?> </h3>
  <div class="row row-stats">
    <div class="ui-block"><div class="muted">Total</div><div class="u-stat-num"><strong><?= (int)$summary['total'] ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('notices.active_message')) ?> </div><div class="u-stat-num"><strong><?= (int)$summary['active'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Requires Ack</div><div class="u-stat-num"><strong><?= (int)$summary['ack_required'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">New &le; 30d</div><div class="u-stat-num"><strong><?= (int)$summary['new_30d'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-mt-0">Distribution</h3>
  <div class="row row-distribute">
    <div class="flex-col-md">
      <h4 class="u-mt-0 u-mb-6">By Type</h4>
      <?php if (!$byType): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Type</th><th class="u-text-right">Count</th></tr></thead><tbody>
          <?php foreach ($byType as $r): ?>
            <tr><td><?= e((string)$r['notice_type']) ?></td><td class="u-text-right"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
    <div class="flex-col-md">
      <h4 class="u-mt-0 u-mb-6">By Level</h4>
      <?php if (!$byLevel): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Level</th><th class="u-text-right">Count</th></tr></thead><tbody>
          <?php foreach ($byLevel as $r): ?>
            <tr><td><?= e((string)$r['notice_level']) ?></td><td class="u-text-right"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-mt-0">Recent Notices</h3>
  <?php if (!$recent): ?><p class="muted u-m-0">No notices recorded.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>#</th><th> <?= e($tt('notices.title_column')) ?> </th><th>Type</th><th>Level</th><th> <?= e($tt('notices.active_message')) ?> </th><th>Ack</th><th> <?= e($tt('notices.created_column')) ?> </th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><?= e((string)$r['title']) ?></td>
            <td><?= e((string)$r['notice_type']) ?></td>
            <td><?= e((string)$r['notice_level']) ?></td>
            <td><?= ((int)$r['is_active'] === 1) ? 'Yes' : 'No' ?></td>
            <td><?= ((int)$r['requires_ack'] === 1) ? 'Yes' : '' ?></td>
            <td><?= e((string)$r['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
