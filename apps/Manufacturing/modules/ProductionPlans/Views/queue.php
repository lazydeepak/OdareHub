<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<?php
$today    = (string)($today ?? date('Y-m-d'));
$date     = (string)($date ?? $today);
$rows     = $rows ?? [];
$machines = $machines ?? [];
$totalPlanned  = (float)($totalPlanned ?? 0);
$totalProduced = (float)($totalProduced ?? 0);
$machine_id    = (int)($machine_id ?? 0);

$isToday   = ($date === $today);
$isPast    = ($date < $today);
$isFuture  = ($date > $today);

// Current hour of day for time-bar (0-24)
$nowHour = (float)date('G') + (float)date('i') / 60;

// Overall progress %
$overallPct = $totalPlanned > 0 ? min(100, round($totalProduced / $totalPlanned * 100, 1)) : 0;

$statusLabelMap = [
  'Done' => t('module.production_queue.status.done'),
  'Overdue' => t('module.production_queue.status.overdue'),
  'In Progress' => t('module.production_queue.status.in_progress'),
  'Upcoming' => t('module.production_queue.status.upcoming'),
];

function queueFillClass(float $pct): string {
    if ($pct >= 100) return 'fill-full';
    if ($pct >= 70)  return 'fill-high';
    if ($pct >= 35)  return 'fill-mid';
    return 'fill-low';
}

function queueCardClass(string $status, float $pct, bool $isToday, bool $isPast): string {
    if ($pct >= 100) return 'complete';
    if ($isPast && $pct < 100) return 'overdue';
    if ($isToday) return 'on-track';
    return '';
}
?>

<!-- Header -->
<div class="card u-style-d4645e97d7">
  <div class="ui-block">
    <h2 class="u-style-1169661891"><?= e(t('module.production_queue.title')) ?></h2>
    <div class="muted u-style-9b125dac7d">
      <?php if ($isToday): ?><?= e(t('common.today')) ?> &mdash; <?= e(date('l, d M Y', strtotime($date))) ?>
      <?php elseif ($isPast): ?><span class="u-style-8ce6e50b5f"><?= e(t('module.production_queue.past')) ?> &mdash; <?= e(date('d M Y', strtotime($date))) ?></span>
      <?php else: ?><span class="u-style-7b1b003e7f"><?= e(t('module.production_queue.upcoming')) ?> &mdash; <?= e(date('d M Y', strtotime($date))) ?></span>
      <?php endif; ?>
    </div>
  </div>
  <div class="u-style-58703f152a">
    <a class="btn" href="/apps/manufacturing/production-plans/queue?date=<?= urlencode((string)date('Y-m-d', strtotime($date . ' -1 day'))) ?><?= $machine_id > 0 ? '&machine_id='.$machine_id : '' ?>">&larr;</a>
    <a class="btn" href="/apps/manufacturing/production-plans/queue"><?= $isToday ? e(t('common.today')) : e(t('module.production_queue.go_to_today')) ?></a>
    <a class="btn" href="/apps/manufacturing/production-plans/queue?date=<?= urlencode((string)date('Y-m-d', strtotime($date . ' +1 day'))) ?><?= $machine_id > 0 ? '&machine_id='.$machine_id : '' ?>">&rarr;</a>
    <a class="btn ok" href="/apps/manufacturing/production-plans/add"><?= e(t('module.production_queue.add_plan')) ?></a>
    <a class="btn" href="/apps/manufacturing/production-entries/add"><?= e(t('module.production_queue.add_entry')) ?></a>
    <a class="btn" href="/apps/manufacturing/production-plans"><?= e(t('module.production_queue.list_view')) ?></a>
  </div>
</div>

<!-- Filter -->
<div class="card">
  <form class="u-style-c1f82e51f6" method="get" action="/apps/manufacturing/production-plans/queue">
    <div class="ui-block"><label class="muted u-style-81fb9ffd75"><?= e(t('common.date')) ?></label>
      <input class="input" type="date" name="date" value="<?= e($date) ?>"></div>
    <div class="ui-block"><label class="muted u-style-81fb9ffd75"><?= e(t('common.machine')) ?></label>
      <select name="machine_id">
        <option value="0"><?= e(t('module.production_queue.all_machines')) ?></option>
        <?php foreach ($machines as $m): ?>
          <option value="<?= (int)$m['id'] ?>" <?= ($machine_id === (int)$m['id']) ? 'selected' : '' ?>>
            <?= e((string)$m['machine_no']) ?> &mdash; <?= e((string)$m['machine_name']) ?>
          </option>
        <?php endforeach; ?>
      </select></div>
    <div class="u-style-49cd09213e">
      <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
      <a class="btn" href="/apps/manufacturing/production-plans/queue"><?= e(t('common.reset')) ?></a>
    </div>
  </form>
</div>

<!-- Stat summary -->
<div class="stat-row">
  <div class="stat-box">
    <div class="stat-box-label"><?= e(t('module.production_queue.plans')) ?></div>
    <div class="stat-box-value"><?= count($rows) ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-box-label"><?= e(t('module.production_queue.total_planned_qty')) ?></div>
    <div class="stat-box-value"><?= number_format($totalPlanned, 0) ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-box-label"><?= e(t('module.production_queue.produced_good')) ?></div>
    <div class="stat-box-value" style="color: var(--text)"><?= number_format($totalProduced, 0) ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-box-label"><?= e(t('module.production_queue.overall_progress')) ?></div>
    <div class="stat-box-value" style="color: var(--text)"><?= $overallPct ?>%</div>
    <div class="u-style-fe7b4979fe"><div class="progress-bar-wrap"><div class="progress-bar-fill <?= queueFillClass($overallPct) ?>" style="width:<?= $overallPct ?>%"></div></div></div>
  </div>
  <?php if ($isToday): ?>
  <div class="stat-box">
    <div class="stat-box-label"><?= e(t('module.production_queue.time_of_day')) ?></div>
    <div class="stat-box-value"><?= date('H:i') ?></div>
    <div class="u-style-fe7b4979fe">
      <div class="progress-bar-wrap">
        <?php $dayPct = min(100, round($nowHour / 24 * 100, 1)); ?>
        <div class="progress-bar-fill" style="width:<?= $dayPct ?>%;background: var(--style-subtle-bg)"></div>
    </div>
    <div class="u-style-a4abb89f4d"><?= sprintf(e(t('module.production_queue.remaining_hours')), round(24 - $nowHour, 1)) ?></div>
  </div>
  <?php endif; ?>
</div>

<?php if (empty($rows)): ?>
<div class="card u-style-4f60c780bb"><?= e(t('module.production_queue.no_plans_for_date')) ?><?= $machine_id > 0 ? ' / ' . e(t('common.machine')) : '' ?>.</div>
<?php else: ?>

<!-- Cards grid -->
<div class="queue-grid">
<?php foreach ($rows as $r):
  $planned  = (float)$r['planned_qty'];
  $produced = (float)$r['good_qty'];
  $rejected = (float)$r['rejected_qty'];
  $pct      = $planned > 0 ? min(100, round($produced / $planned * 100, 1)) : 0;
  $remaining = max(0, $planned - $produced);

  // Runtime / cycle
  $runtime = $r['runtime'] !== null ? (float)$r['runtime'] : null;
  $productCycle = ($r['cycle_time'] ?? null) !== null && (string)$r['cycle_time'] !== '' ? (float)$r['cycle_time'] : null;
  $cyclePerUnit = $productCycle !== null
      ? round($productCycle, 2)
      : (($runtime !== null && $planned > 0) ? round($runtime * 60 / $planned, 2) : null); // minutes per unit

  // Time progress (runtime as planned shift hours, date = plan_date)
  $timeElapsedHrs = null;
  $timePct        = null;
  if ($isToday && $runtime !== null && $runtime > 0) {
    $timeElapsedHrs = min($runtime, $nowHour); // simplistic: elapsed since midnight
    $timePct = min(100, round($timeElapsedHrs / $runtime * 100, 1));
    $nowPct  = min(100, round($nowHour / $runtime * 100, 1));
  }

  $status = (string)$r['status'];

  // Card state
  if ($pct >= 100) {
    $cardClass  = 'complete';
    $badgeClass = 'badge-done';
    $badgeLabel = (string)t('module.production_queue.status.done');
  } elseif ($isPast && $pct < 100) {
    $cardClass  = 'overdue';
    $badgeClass = 'badge-overdue';
    $badgeLabel = (string)t('module.production_queue.status.overdue');
  } elseif ($pct > 0) {
    $cardClass  = 'on-track';
    $badgeClass = 'badge-progress';
    $badgeLabel = (string)t('module.production_queue.status.in_progress');
  } elseif ($isFuture) {
    $cardClass  = '';
    $badgeClass = 'badge-upcoming';
    $badgeLabel = (string)t('module.production_queue.status.upcoming');
  } else {
    $cardClass  = '';
    $badgeClass = 'badge-planned';
    $badgeLabel = (string)($statusLabelMap[$status] ?? $status);
  }
?>
<div class="queue-card <?= $cardClass ?>">
  <!-- Card header -->
  <div class="queue-card-header">
    <div class="ui-block">
      <div class="queue-machine">
        <?= e((string)$r['machine_no']) ?> &mdash; <?= e((string)$r['machine_name']) ?>
        <?php if (!empty($r['section'])): ?>
          <span class="seq-badge"><?= e((string)$r['section']) ?></span>
        <?php endif; ?>
        <span class="seq-badge">#<?= (int)$r['sequence_no'] ?></span>
      </div>
      <div class="queue-product"><?= e((string)$r['parts_name']) ?> <span class="u-style-892571dcf1"><?= e((string)$r['parts_number']) ?></span></div>
      <?php if (!empty($r['model'])): ?><div class="queue-model"><?= e((string)$r['model']) ?></div><?php endif; ?>
    </div>
    <span class="queue-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
  </div>

  <!-- Progress bar -->
  <div class="ui-block">
    <div class="u-style-edeed852ef">
      <span class="u-style-57f9e31d78"><?= e(t('module.production_queue.production_progress')) ?></span>
      <span style="font-weight:700;color: var(--text)"><?= $pct ?>%</span>
    </div>
    <div class="progress-bar-wrap">
      <div class="progress-bar-fill <?= queueFillClass($pct) ?>" style="width:<?= $pct ?>%"></div>
    </div>
  </div>

  <!-- Numbers -->
  <div class="queue-nums">
    <div class="queue-num">
      <div class="queue-num-label"><?= e(t('module.production_queue.planned')) ?></div>
      <div class="queue-num-value"><?= number_format($planned, 0) ?></div>
    </div>
    <div class="queue-num">
      <div class="queue-num-label"><?= e(t('module.production_queue.produced')) ?></div>
      <div class="queue-num-value u-style-ce8274176b"><?= number_format($produced, 0) ?></div>
    </div>
    <?php if ($rejected > 0): ?>
    <div class="queue-num">
      <div class="queue-num-label"><?= e(t('module.production_queue.rejected')) ?></div>
      <div class="queue-num-value u-style-8ce6e50b5f"><?= number_format($rejected, 0) ?></div>
    </div>
    <?php endif; ?>
    <div class="queue-num">
      <div class="queue-num-label"><?= e(t('module.production_queue.remaining')) ?></div>
      <div class="queue-num-value" style="color: var(--text)"><?= number_format($remaining, 0) ?></div>
    </div>
    <?php if ($cyclePerUnit !== null): ?>
    <div class="queue-num">
      <div class="queue-num-label"><?= e(t('module.production_queue.cycle_min_unit')) ?></div>
      <div class="queue-num-value"><?= $cyclePerUnit ?></div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Time bar (only for today's plans with runtime set) -->
  <?php if ($runtime !== null && $runtime > 0): ?>
  <div class="ui-block">
    <div class="u-style-0899906b77">
      <span><?= e(t('module.production_queue.planned_time')) ?></span>
      <span><?= sprintf(e(t('module.production_queue.runtime_hours')), $runtime) ?></span>
    </div>
    <?php if ($isToday && $timePct !== null): ?>
    <div class="u-style-d461c96de5">
      <div class="progress-bar-wrap u-style-cc3d9d14f2">
        <div class="progress-bar-fill" style="width:<?= $timePct ?>%;background: var(--style-subtle-bg)"></div>
      <?php if ($nowPct <= 100): ?>
      <div class="ui-block" style="position:absolute;top:-2px;left:<?= min(96, $nowPct) ?>%;width:2px;height:10px;background: var(--style-subtle-bg);border-radius:1px"></div>
      <?php endif; ?>
    </div>
    <div class="u-style-3d4a6623cc">
      <span><?= e(t('module.production_queue.start_of_day')) ?></span>
      <span class="u-style-daa9286eba">▲ <?= date('H:i') ?></span>
      <span><?= sprintf('%02d:%02d', (int)$runtime, (int)(($runtime - floor($runtime)) * 60)) ?></span>
    </div>
    <?php else: ?>
    <div class="u-style-aa8858f6a8">
      <?= e($r['plan_date']) ?> &mdash; <?= sprintf(e(t('module.production_queue.runtime_hours_planned')), $runtime) ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Plan date context -->
  <div class="u-style-472fd3529f">
    <span><?= e(t('module.production_queue.plan_label')) ?>: <?= e((string)$r['plan_date']) ?></span>
    <?php if (!$isToday): ?>
      <?php
        $diffDays = (int)((strtotime($date) - strtotime($today)) / 86400);
        $diffLabel = $diffDays > 0 ? sprintf((string)t('module.production_queue.days_from_today'), $diffDays) : sprintf((string)t('module.production_queue.days_ago'), abs($diffDays));
      ?>
      <span style="color: var(--text)"><?= e($diffLabel) ?></span>
    <?php endif; ?>
    <?php if (!empty($r['plan_type']) && $r['plan_type'] !== 'Manual'): ?>
      <span class="seq-badge"><?= e((string)$r['plan_type']) ?></span>
    <?php endif; ?>
    <?php if ((int)$r['entry_count'] > 0): ?>
      <span class="seq-badge"><?= sprintf(e((int)$r['entry_count'] === 1 ? t('module.production_queue.entry_singular') : t('module.production_queue.entry_plural')), (int)$r['entry_count']) ?></span>
    <?php endif; ?>
  </div>

  <!-- Actions -->
  <div class="queue-actions">
    <a class="btn" href="/apps/manufacturing/production-entries/add?product_id=<?= (int)$r['product_id'] ?>" style="font-size:.8em;padding:4px 10px"><?= e(t('module.production_queue.add_entry')) ?></a>
    <a class="btn" href="/apps/manufacturing/production-plans/edit?id=<?= (int)$r['id'] ?>" style="font-size:.8em;padding:4px 10px"><?= e(t('module.production_queue.edit_plan')) ?></a>
    <a class="btn" href="/apps/manufacturing/production-entries?machine_id=<?= (int)$r['machine_id'] ?>&production_date=<?= urlencode($r['plan_date']) ?>" style="font-size:.8em;padding:4px 10px"><?= e(t('module.production_queue.entries')) ?></a>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
