<?php
$manufacturingKpi = is_array($manufacturingKpi ?? null) ? $manufacturingKpi : [];
$kpiCards = is_array($kpiCards ?? null) ? $kpiCards : [];
$timelineRows = is_array($timelineRows ?? null) ? $timelineRows : [];
$experienceMode = (string)($experienceMode ?? '');
$dashboardType = (string)($dashboardType ?? '');
$authorityRole = (string)($authorityRole ?? '');
$blockOrderStyle = is_callable($blockOrderStyle ?? null) ? $blockOrderStyle : static fn(string $blockKey): string => '';
?>
<section class="card me-dashboard-hero"<?= $blockOrderStyle('operational_summary') ?>>
  <div class="me-dashboard-hero-head">
    <div class="ui-block">
      <h2 class="me-dashboard-title"><?= e(t('admin.dashboard.title')) ?></h2>
      <p class="muted me-dashboard-subtitle"><?= e(t('admin.dashboard.subtitle')) ?></p>
    </div>
    <div class="me-dashboard-badges">
      <?php foreach (array_unique(array_filter([strtoupper($experienceMode), strtoupper($dashboardType), strtoupper($authorityRole)])) as $_badge): ?>
        <span class="mapping-label"><?= e($_badge) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="me-dashboard-kpi-grid">
    <?php foreach ($kpiCards as $kpiKey => $kpiMeta): ?>
      <?php
        $kpiValue = (array)($manufacturingKpi[$kpiKey] ?? []);
        $total = (int)($kpiValue['total'] ?? 0);
        $active = (int)($kpiValue['active'] ?? 0);
        $critical = (int)($kpiValue['critical'] ?? 0);
        $completed = (int)($kpiValue['completed'] ?? 0);
        $completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0.0;
        $statusClass = $critical > 0 ? 'danger' : ($active > 0 ? 'warning' : 'success');
      ?>
      <article class="me-dashboard-kpi-card me-kpi-status-<?= e($statusClass) ?>">
        <div class="me-kpi-header">
          <div class="me-kpi-label muted"><?= e((string)($kpiMeta['label'] ?? ucfirst((string)$kpiKey))) ?></div>
          <div class="me-kpi-indicator me-status-<?= e($statusClass) ?>"></div>
        </div>
        <div class="me-kpi-main">
          <strong class="me-kpi-value"><?= e(number_format((float)$total, 0, '.', ',')) ?></strong>
        </div>
        <div class="me-dashboard-kpi-meta">
          <span class="pill pill-active"><?= e(t('admin.dashboard.kpi.active')) ?> <?= e((string)$active) ?></span>
          <span class="pill pill-critical"><?= e(t('admin.dashboard.kpi.critical')) ?> <?= e((string)$critical) ?></span>
          <span class="pill pill-done"><?= e(t('admin.dashboard.kpi.done')) ?> <?= e(number_format($completionRate, 1)) ?>%</span>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="card me-dashboard-flow-card me-dashboard-flow-full"<?= $blockOrderStyle('operational_summary') ?>>
  <div class="section-head">
    <h4><?= e(t('admin.dashboard.flow.title')) ?></h4>
    <span class="mapping-label"><?= e(t('admin.dashboard.badge.live')) ?></span>
  </div>
  <div class="me-flow-grid">
    <?php foreach ($kpiCards as $kpiKey => $kpiMeta): ?>
      <?php
        $kpiValue = (array)($manufacturingKpi[$kpiKey] ?? []);
        $total = (int)($kpiValue['total'] ?? 0);
        $active = (int)($kpiValue['active'] ?? 0);
        $critical = (int)($kpiValue['critical'] ?? 0);
      ?>
      <div class="me-flow-item">
        <div class="muted"><?= e((string)($kpiMeta['label'] ?? ucfirst((string)$kpiKey))) ?></div>
        <strong><?= e((string)$total) ?></strong>
        <div class="me-flow-meta"><?= e(t('admin.dashboard.flow.abbrev_active')) ?> <?= e((string)$active) ?> | <?= e(t('admin.dashboard.flow.abbrev_critical')) ?> <?= e((string)$critical) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="card me-dashboard-timeline-card me-dashboard-timeline-full"<?= $blockOrderStyle('operational_summary') ?>>
  <div class="section-head">
    <h4><?= e(t('admin.dashboard.timeline.title')) ?></h4>
    <span class="mapping-label"><?= e(t('admin.dashboard.badge.today')) ?></span>
  </div>
  <?php if ($timelineRows === []): ?>
    <div class="muted"><?= e(t('admin.dashboard.timeline.empty')) ?></div>
  <?php else: ?>
    <div class="me-timeline-list">
      <?php foreach ($timelineRows as $timeline): ?>
        <?php
          $timelineUrl = trim((string)($timeline['url'] ?? ''));
          $timelineStatusTone = strtolower(trim((string)($timeline['status_tone'] ?? 'info')));
          $timelineStatusClass = match ($timelineStatusTone) {
              'success' => 'success',
              'warning' => 'warning',
              'danger' => 'danger',
              'neutral' => 'neutral',
              default => 'info',
          };
          $timelineProgress = max(0.0, min(100.0, (float)($timeline['progress'] ?? 0.0)));
        ?>
        <div class="me-timeline-item">
          <div class="me-timeline-stage"><?= e((string)($timeline['stage'] ?? t('admin.dashboard.fallback_operations'))) ?></div>
          <div class="me-timeline-main">
            <div class="row-sb">
              <div class="me-timeline-copy">
                <?php if ($timelineUrl !== ''): ?>
                  <a class="me-timeline-title" href="<?= e($timelineUrl) ?>"><?= e((string)($timeline['title'] ?? t('admin.dashboard.timeline.fallback_item'))) ?></a>
                <?php else: ?>
                  <strong class="me-timeline-title"><?= e((string)($timeline['title'] ?? t('admin.dashboard.timeline.fallback_item'))) ?></strong>
                <?php endif; ?>
                <div class="me-timeline-sequence muted">
                  <span class="me-timeline-segment"><strong><?= e(t('admin.dashboard.timeline.past')) ?></strong> <?= e((string)($timeline['past_plan'] ?? t('admin.dashboard.timeline.no_previous'))) ?></span>
                  <span class="me-timeline-segment"><strong><?= e(t('admin.dashboard.timeline.current')) ?></strong> <?= e((string)($timeline['current_plan'] ?? t('admin.dashboard.timeline.no_current'))) ?></span>
                  <span class="me-timeline-segment"><strong><?= e(t('admin.dashboard.timeline.next')) ?></strong> <?= e((string)($timeline['next_plan'] ?? t('admin.dashboard.timeline.no_next'))) ?></span>
                </div>
              </div>
              <span class="status-chip <?= e($timelineStatusClass) ?>"><?= e((string)($timeline['status'] ?? t('admin.dashboard.timeline.fallback_status'))) ?></span>
            </div>
            <div class="me-timeline-progress-wrap">
              <div class="me-timeline-progress"><span style="width:<?= e(number_format($timelineProgress, 1, '.', '')) ?>%"></span></div>
              <div class="muted me-timeline-progress-label"><?= e(number_format($timelineProgress, 1, '.', ',')) ?>%</div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
