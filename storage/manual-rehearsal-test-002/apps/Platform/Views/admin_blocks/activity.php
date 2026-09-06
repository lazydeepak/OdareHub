<?php
$activityFeed = is_array($activityFeed ?? null) ? $activityFeed : [];
$blockOrderStyle = is_callable($blockOrderStyle ?? null) ? $blockOrderStyle : static fn(string $blockKey): string => '';
?>
<section class="card me-dashboard-activity-card me-dashboard-activity-bottom<?= $activityFeed === [] ? ' is-empty' : '' ?>"<?= $blockOrderStyle('monitoring_widgets') ?>>
  <div class="section-head">
    <h4><?= e(t('admin.dashboard.activity.title')) ?></h4>
    <a class="btn" href="/ops/audit-log"><?= e(t('admin.dashboard.activity.action_view_all')) ?></a>
  </div>
  <?php if ($activityFeed === []): ?>
    <div class="muted"><?= e(t('admin.dashboard.activity.empty')) ?></div>
  <?php else: ?>
    <div class="me-activity-list">
      <?php foreach ($activityFeed as $activity): ?>
        <?php
          $activityUrl = trim((string)($activity['url'] ?? ''));
          $activityTagName = $activityUrl !== '' ? 'a' : 'div';
          $statusTone = strtolower(trim((string)($activity['status_tone'] ?? 'info')));
          $statusClass = match ($statusTone) {
              'success' => 'success',
              'warning' => 'warning',
              'danger' => 'danger',
              'neutral' => 'neutral',
              default => 'info',
          };
        ?>
        <<?= $activityTagName ?> class="me-activity-item<?= $activityUrl !== '' ? ' is-clickable' : '' ?>"<?= $activityUrl !== '' ? ' href="' . e($activityUrl) . '"' : '' ?>>
          <div class="row-sb">
            <div class="me-activity-copy">
              <strong class="me-activity-title"><?= e((string)($activity['title'] ?? t('admin.dashboard.activity.fallback_item'))) ?></strong>
              <div class="me-activity-detail muted"><?= e((string)($activity['module'] ?? t('admin.dashboard.fallback_operations'))) ?><?php if (trim((string)($activity['subtitle'] ?? '')) !== ''): ?> · <?= e((string)$activity['subtitle']) ?><?php endif; ?></div>
            </div>
            <span class="status-chip <?= e($statusClass) ?>"><?= e((string)($activity['status'] ?? t('admin.dashboard.activity.fallback_status'))) ?></span>
          </div>
        </<?= $activityTagName ?>>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
