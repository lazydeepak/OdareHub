<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$today = (string)($today ?? date('Y-m-d'));
$date = (string)($date ?? $today);
$rows = isset($rows) && is_array($rows) ? $rows : [];
$machines = isset($machines) && is_array($machines) ? $machines : [];
$machine_id = (int)($machine_id ?? 0);

$totalPlanned = (float)($totalPlanned ?? 0);
$totalProduced = (float)($totalProduced ?? 0);
$totalRemainingDemand = (float)($totalRemainingDemand ?? 0);
$blockedCount = (int)($blockedCount ?? 0);
$overallPct = $totalPlanned > 0 ? min(100, round($totalProduced / $totalPlanned * 100, 1)) : 0;

$statusBadge = static function (float $pct, bool $isPast): string {
  if ($pct >= 100) {
    return (string)t('mfg.prodq.badge.done');
  }
  if ($isPast && $pct < 100) {
    return (string)t('mfg.prodq.badge.overdue');
  }
  if ($pct > 0) {
    return (string)t('mfg.prodq.badge.in_progress');
  }
  return (string)t('mfg.prodq.badge.upcoming');
};

$isPast = ($date < $today);
?>

<div class="card">
  <div class="mfg-page-header">
    <div class="ui-block">
      <h2 class="mfg-page-title"><?= e((string)t('mfg.prodq.title')) ?></h2>
      <div class="muted"><?= e(date('d M Y', strtotime($date))) ?></div>
    </div>
    <div class="mfg-page-header-links">
      <a class="btn" href="/apps/manufacturing/production-queue?date=<?= urlencode((string)date('Y-m-d', strtotime($date . ' -1 day'))) ?><?= $machine_id > 0 ? '&machine_id=' . $machine_id : '' ?>">&larr;</a>
      <a class="btn" href="/apps/manufacturing/production-queue"><?= e((string)t('mfg.prodq.link.go_to_today')) ?></a>
      <a class="btn" href="/apps/manufacturing/production-queue?date=<?= urlencode((string)date('Y-m-d', strtotime($date . ' +1 day'))) ?><?= $machine_id > 0 ? '&machine_id=' . $machine_id : '' ?>">&rarr;</a>
      <a class="btn ok" href="/apps/manufacturing/production-plans/add"><?= e((string)t('mfg.prodq.link.add_plan')) ?></a>
      <a class="btn" href="/apps/manufacturing/production-entries/add"><?= e((string)t('mfg.prodq.link.add_entry')) ?></a>
      <a class="btn" href="/apps/manufacturing/stage-board?date=<?= urlencode($date) ?>"><?= e((string)t('mfg.prodq.link.stage_board')) ?></a>
    </div>
  </div>
</div>

<div class="card">
  <form method="get" action="/apps/manufacturing/production-queue" class="mfg-filter-row">
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('mfg.prodq.filter.date')) ?></label>
      <input class="input" type="date" name="date" value="<?= e($date) ?>">
    </div>
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('mfg.prodq.filter.machine')) ?></label>
      <select name="machine_id">
        <option value="0"><?= e((string)t('mfg.prodq.filter.all_machines')) ?></option>
        <?php foreach ($machines as $m): ?>
          <option value="<?= (int)$m['id'] ?>" <?= $machine_id === (int)$m['id'] ? 'selected' : '' ?>>
            <?= e((string)$m['machine_no']) ?> - <?= e((string)$m['machine_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="row">
      <button class="btn" type="submit"><?= e((string)t('mfg.prodq.filter.apply')) ?></button>
      <a class="btn" href="/apps/manufacturing/production-queue"><?= e((string)t('mfg.prodq.filter.reset')) ?></a>
    </div>
  </form>
</div>

<div class="stat-row">
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('mfg.prodq.stat.plans')) ?></div><div class="stat-box-value"><?= count($rows) ?></div></div>
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('mfg.prodq.stat.total_planned')) ?></div><div class="stat-box-value"><?= number_format($totalPlanned, 0) ?></div></div>
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('mfg.prodq.stat.produced')) ?></div><div class="stat-box-value"><?= number_format($totalProduced, 0) ?></div></div>
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('mfg.prodq.stat.remaining_demand')) ?></div><div class="stat-box-value <?= $totalRemainingDemand > 0 ? 'u-tone-warning' : 'u-tone-success' ?>"><?= number_format($totalRemainingDemand, 0) ?></div></div>
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('mfg.prodq.stat.blocked_rows')) ?></div><div class="stat-box-value <?= $blockedCount > 0 ? 'u-tone-danger' : 'u-tone-success' ?>"><?= $blockedCount ?></div></div>
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('mfg.prodq.stat.overall_progress')) ?></div><div class="stat-box-value"><?= $overallPct ?>%</div></div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e((string)t('mfg.prodq.filter.machine')) ?></th>
          <th><?= e((string)t('common.part')) ?></th>
          <th><?= e((string)t('mfg.prodq.card.planned')) ?></th>
          <th><?= e((string)t('mfg.prodq.card.produced')) ?></th>
          <th><?= e((string)t('mfg.prodq.card.remaining')) ?></th>
          <th><?= e((string)t('mfg.prodq.card.progress')) ?></th>
          <th><?= e((string)t('mfg.prodq.card.demand_status')) ?></th>
          <th><?= e((string)t('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <?php
          $planned = (float)($r['planned_qty'] ?? 0);
          $produced = (float)($r['good_qty'] ?? 0);
          $remaining = max(0, $planned - $produced);
          $pct = $planned > 0 ? min(100, round($produced / $planned * 100, 1)) : 0;
          $demand = is_array($r['demand_execution'] ?? null) ? $r['demand_execution'] : [];
          $demandStatus = strtolower(trim((string)($demand['status'] ?? 'calculated')));
          $demandLabel = match ($demandStatus) {
            'approved' => (string)t('mfg.dem.option.approved'),
            'adjusted' => (string)t('mfg.dem.option.adjusted'),
            default => (string)t('mfg.dem.option.calculated'),
          };
        ?>
        <tr>
          <td><?= e((string)($r['machine_no'] ?? '')) ?> - <?= e((string)($r['machine_name'] ?? '')) ?></td>
          <td><?= e((string)($r['parts_name'] ?? '')) ?> <span class="muted">(<?= e((string)($r['parts_number'] ?? '')) ?>)</span></td>
          <td><?= number_format($planned, 0) ?></td>
          <td><?= number_format($produced, 0) ?></td>
          <td><?= number_format($remaining, 0) ?></td>
          <td><span class="pill"><?= e($statusBadge($pct, $isPast)) ?> · <?= $pct ?>%</span></td>
          <td><?= e($demandLabel) ?></td>
          <td>
            <a class="btn" href="/apps/manufacturing/production-plans/edit?id=<?= (int)($r['id'] ?? 0) ?>"><?= e((string)t('mfg.prodq.card.action_edit')) ?></a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($rows === []): ?>
        <tr><td colspan="8" class="muted"><?= e((string)($machine_id > 0 ? t('mfg.prodq.empty_with_filter') : t('mfg.prodq.empty'))) ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>