<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$pipeline = isset($pipeline) && is_array($pipeline) ? $pipeline : [];
$columns = is_array($pipeline['columns'] ?? null) ? $pipeline['columns'] : [];
$totals = is_array($pipeline['totals'] ?? null) ? $pipeline['totals'] : ['items' => 0, 'overdue' => 0, 'blocked' => 0, 'at_risk' => 0];
$date = (string)($date ?? date('Y-m-d'));
$today = (string)($today ?? date('Y-m-d'));
$isToday = ($date === $today);

$flatRows = [];
foreach ($columns as $column) {
  $items = is_array($column['items'] ?? null) ? $column['items'] : [];
  $colKey = (string)($column['key'] ?? '');
  foreach ($items as $item) {
    $item['stage_key'] = $colKey;
    $flatRows[] = $item;
  }
}
?>

<div class="card">
  <div class="mfg-page-header">
    <div class="ui-block">
      <h2 class="mfg-page-title"><?= e((string)t('mfg.stage.title')) ?></h2>
      <div class="muted">
        <?= e((string)t('mfg.stage.subtitle')) ?>
        <?php if ($isToday): ?>
          · <?= e((string)t('mfg.stage.today_prefix')) ?> <?= e(date('l, d M Y', strtotime($date))) ?>
        <?php else: ?>
          · <?= e(date('d M Y', strtotime($date))) ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="mfg-page-header-links">
      <a class="btn" href="/apps/manufacturing/production-queue?date=<?= urlencode($date) ?>"><?= e((string)t('mfg.stage.link.production_queue')) ?></a>
      <a class="btn" href="/apps/manufacturing/qc-queue"><?= e((string)t('mfg.stage.link.qc_queue')) ?></a>
      <a class="btn" href="/apps/manufacturing/dispatch-ops"><?= e((string)t('mfg.stage.link.dispatch_ops')) ?></a>
    </div>
  </div>
</div>

<?php if (!empty($flash ?? '')): ?><div class="card dsp-flash-ok"><?= e((string)$flash) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card dsp-flash-err"><?= e((string)$error) ?></div><?php endif; ?>

<div class="card">
  <form method="get" action="/apps/manufacturing/stage-board" class="mfg-filter-row">
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('mfg.stage.filter.date')) ?></label>
      <input class="input" type="date" name="date" value="<?= e($date) ?>">
    </div>
    <div class="row">
      <button class="btn" type="submit"><?= e((string)t('mfg.stage.filter.view')) ?></button>
      <a class="btn" href="/apps/manufacturing/stage-board"><?= e((string)t('mfg.stage.filter.today')) ?></a>
    </div>
  </form>
</div>

<div class="summary-row">
  <div class="summary-box"><div class="summary-label"><?= e((string)t('mfg.stage.kpi.pipeline_items')) ?></div><div class="summary-value"><?= (int)($totals['items'] ?? 0) ?></div></div>
  <div class="summary-box"><div class="summary-label"><?= e((string)t('mfg.stage.kpi.overdue_sla')) ?></div><div class="summary-value stage-kpi-overdue"><?= (int)($totals['overdue'] ?? 0) ?></div></div>
  <div class="summary-box"><div class="summary-label"><?= e((string)t('mfg.stage.kpi.at_risk_sla')) ?></div><div class="summary-value stage-kpi-at_risk"><?= (int)($totals['at_risk'] ?? 0) ?></div></div>
  <div class="summary-box"><div class="summary-label"><?= e((string)t('mfg.stage.kpi.blocked')) ?></div><div class="summary-value stage-kpi-overdue"><?= (int)($totals['blocked'] ?? 0) ?></div></div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e((string)t('common.stage')) ?></th>
          <th><?= e((string)t('common.part')) ?></th>
          <th><?= e((string)t('mfg.stage.item.current_stage')) ?></th>
          <th><?= e((string)t('mfg.stage.item.age_days')) ?></th>
          <th><?= e((string)t('mfg.stage.kpi.at_risk_sla')) ?></th>
          <th><?= e((string)t('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php if ($flatRows === []): ?>
        <tr><td colspan="6" class="muted"><?= e((string)t('mfg.stage.empty_date')) ?> <?= e($date) ?></td></tr>
      <?php else: ?>
        <?php foreach ($flatRows as $item): ?>
          <?php
            $stageKey = (string)($item['stage_key'] ?? '');
            $stageLabel = (string)t('mfg.stage.col.' . $stageKey);
            $priority = ucfirst((string)($item['priority'] ?? 'normal'));
            $blocked = !empty($item['blocked']);
            $slaLabel = (string)($item['sla_label'] ?? '');
            $openUrl = (string)($item['open_url'] ?? '/apps/manufacturing/stage-board');
          ?>
          <tr>
            <td><?= e($stageLabel !== '' ? $stageLabel : $stageKey) ?></td>
            <td>
              <?= e((string)($item['part_name'] ?? '')) ?>
              <span class="muted">(<?= e((string)($item['part_code'] ?? '')) ?>)</span>
            </td>
            <td>
              <?= e($priority) ?>
              <?php if ($blocked): ?>
                <div class="stage-item-blocked"><?= e((string)t('mfg.stage.item.blocked_prefix')) ?> <?= e((string)($item['block_reason'] ?? (string)t('mfg.stage.item.default_block'))) ?></div>
              <?php endif; ?>
            </td>
            <td><?= (int)($item['age_days'] ?? 0) ?> <?= e((string)t('mfg.stage.item.day')) ?></td>
            <td><?= e($slaLabel !== '' ? $slaLabel : (string)t('mfg.cov.status.no_data')) ?></td>
            <td><a class="btn" href="<?= e($openUrl) ?>"><?= e((string)t('mfg.stage.action.open')) ?></a></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>