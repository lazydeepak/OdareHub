<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$health = is_array($health ?? null) ? $health : [];
$summary = is_array($health['summary'] ?? null) ? $health['summary'] : [];
$core = is_array($health['core'] ?? null) ? $health['core'] : [];
$suiteCards = is_array($health['suites'] ?? null) ? $health['suites'] : [];
$modules = is_array($health['modules'] ?? null) ? $health['modules'] : [];
$moduleSummary = is_array($modules['summary'] ?? null) ? $modules['summary'] : [];
$moduleGroups = is_array($modules['by_suite'] ?? null) ? $modules['by_suite'] : [];
$healthTone = static function (string $status): string {
    return match ($status) {
        'healthy' => 'setup-health-status--healthy',
        'warning' => 'setup-health-status--warning',
        'error' => 'setup-health-status--error',
        'disabled' => 'setup-health-status--disabled',
        default => 'setup-health-status--unknown',
    };
};
$healthStatusLabel = static fn(string $status): string => t('admin.setup_health.status.' . strtolower(trim($status)));
?>
<?php $setupNavCurrent = 'health'; require __DIR__ . '/_nav.php'; ?>
<?php require __DIR__ . '/_flash.php'; ?>

<div class="card">
  <h2 class="setup-health-title"><?= e(t('admin.setup_health.title')) ?></h2>
  <div class="muted"><?= e(t('admin.setup_health.description')) ?></div>
</div>

<div class="setup-health-grid setup-health-grid--summary">
  <div class="card setup-health-card"><strong><?= e(t('admin.setup_health.healthy_targets')) ?></strong><div class="muted"><?= e((string)($summary['healthy_targets'] ?? 0)) ?></div></div>
  <div class="card setup-health-card"><strong><?= e(t('admin.setup_health.warnings')) ?></strong><div class="muted"><?= e((string)($summary['warning_targets'] ?? 0)) ?></div></div>
  <div class="card setup-health-card"><strong><?= e(t('admin.setup_health.errors')) ?></strong><div class="muted"><?= e((string)($summary['error_targets'] ?? 0)) ?></div></div>
  <div class="card setup-health-card"><strong><?= e(t('admin.setup_health.disabled_modules')) ?></strong><div class="muted"><?= e((string)($summary['disabled_modules'] ?? 0)) ?></div></div>
  <div class="card setup-health-card"><strong><?= e(t('admin.setup_health.module_warnings_errors')) ?></strong><div class="muted"><?= e((string)($summary['module_warnings'] ?? 0)) ?> / <?= e((string)($summary['module_errors'] ?? 0)) ?></div></div>
</div>

<?php if ($core !== []): ?>
<div class="card">
  <div class="setup-health-head">
    <div>
      <h3 class="setup-health-title"><?= e((string)($core['label'] ?? 'Core Susankhya OS')) ?></h3>
      <div class="muted"><?= e((string)($core['runtime_health'] ?? '')) ?></div>
    </div>
    <?php $coreStatus = (string)($core['health_status'] ?? 'healthy'); ?><span class="pill setup-health-status <?= e($healthTone($coreStatus)) ?>"><?= e($healthStatusLabel($coreStatus)) ?></span>
  </div>
  <div class="setup-health-grid setup-health-grid--metrics">
    <div class="card setup-health-card"><strong><?= e(t('admin.setup_health.enabled_disabled')) ?></strong><div class="muted"><?= e((string)($core['enabled_label'] ?? t('admin.setup_health.status.disabled'))) ?></div></div>
    <div class="card setup-health-card"><strong><?= e(t('admin.setup_health.missing_tables')) ?></strong><div class="muted"><?= e((string)($core['missing_tables'] ?? 0)) ?></div></div>
    <div class="card setup-health-card"><strong><?= e(t('admin.setup_health.failed_hooks')) ?></strong><div class="muted"><?= e((string)($core['failed_hooks'] ?? 0)) ?></div></div>
    <div class="card setup-health-card"><strong><?= e(t('admin.setup_health.schema_gaps')) ?></strong><div class="muted"><?= e((string)($core['schema_gaps'] ?? 0)) ?></div></div>
    <div class="card setup-health-card"><strong><?= e(t('admin.setup_health.warnings_errors')) ?></strong><div class="muted"><?= e((string)($core['warnings'] ?? 0)) ?> / <?= e((string)($core['errors'] ?? 0)) ?></div></div>
  </div>
  <div class="muted"><?= e(t('admin.setup_health.database')) ?>: <?= e((string)($core['status_detail']['database'] ?? '')) ?> · <?= e(t('admin.setup_health.platform_modules')) ?>: <?= e((string)($core['status_detail']['platform_modules'] ?? '')) ?> · <?= e(t('admin.setup_health.admin_bootstrap')) ?>: <?= e((string)($core['status_detail']['admin_bootstrap'] ?? '')) ?></div>
  <div class="setup-health-detail"><?= e(t('admin.setup_health.next')) ?>: <?= e((string)($core['next_action'] ?? '')) ?></div>
  <div class="setup-health-action"><a class="btn ok" href="/admin/setup/core"><?= e(t('admin.setup_health.open_core')) ?></a></div>
</div>
<?php endif; ?>

<div class="setup-health-grid setup-health-grid--suites">
  <?php foreach ($suiteCards as $suite): ?>
    <div class="card setup-health-card">
      <div class="setup-health-head">
        <div>
          <h3 class="setup-health-title"><?= e((string)($suite['label'] ?? t('admin.setup_health.suite'))) ?></h3>
          <div class="muted"><?= e((string)($suite['runtime_health'] ?? '')) ?></div>
        </div>
        <?php $suiteStatus = (string)($suite['health_status'] ?? 'healthy'); ?><span class="pill setup-health-status <?= e($healthTone($suiteStatus)) ?>"><?= e($healthStatusLabel($suiteStatus)) ?></span>
      </div>
      <div class="setup-health-grid setup-health-grid--metrics">
        <div class="card setup-health-card"><strong><?= e(t('admin.setup_health.enabled_disabled')) ?></strong><div class="muted"><?= e((string)($suite['enabled_label'] ?? t('admin.setup_health.status.disabled'))) ?></div></div>
        <div class="card setup-health-card"><strong><?= e(t('admin.setup_health.missing_tables')) ?></strong><div class="muted"><?= e((string)($suite['missing_tables'] ?? 0)) ?></div></div>
        <div class="card setup-health-card"><strong><?= e(t('admin.setup_health.failed_hooks')) ?></strong><div class="muted"><?= e((string)($suite['failed_hooks'] ?? 0)) ?></div></div>
        <div class="card setup-health-card"><strong><?= e(t('admin.setup_health.schema_gaps')) ?></strong><div class="muted"><?= e((string)($suite['schema_gaps'] ?? 0)) ?></div></div>
        <div class="card setup-health-card"><strong><?= e(t('admin.setup_health.warnings_errors')) ?></strong><div class="muted"><?= e((string)($suite['warnings'] ?? 0)) ?> / <?= e((string)($suite['errors'] ?? 0)) ?></div></div>
      </div>
      <div class="muted">
        <?= e(t('admin.setup_health.registry')) ?>: <?= e((string)($suite['status_detail']['registry'] ?? '')) ?>
        · <?= e(t('admin.setup_health.entry_file')) ?>: <?= e((string)($suite['status_detail']['entry_file'] ?? '')) ?>
        · <?= e(t('admin.setup_health.hooks')) ?>: <?= e((string)($suite['status_detail']['runtime_hooks'] ?? '0')) ?>
        · <?= e(t('admin.setup_health.routes')) ?>: <?= e((string)($suite['status_detail']['routes_declared'] ?? '0')) ?>
      </div>
      <?php if (!empty($suite['module_summary'])): ?>
        <div class="muted setup-health-detail"><?= e(t('admin.setup_health.modules_active')) ?>: <?= e((string)($suite['module_summary']['active'] ?? 0)) ?>/<?= e((string)($suite['module_summary']['total'] ?? 0)) ?> · <?= e(t('admin.setup_health.status.disabled')) ?>: <?= e((string)($suite['module_summary']['disabled'] ?? 0)) ?></div>
      <?php endif; ?>
      <div class="setup-health-detail"><?= e(t('admin.setup_health.next')) ?>: <?= e((string)($suite['next_action'] ?? '')) ?></div>
      <div class="setup-health-action"><a class="btn ok" href="/admin/setup/suites/detail?suite_key=<?= urlencode((string)($suite['key'] ?? '')) ?>"><?= e(t('admin.setup_health.open_suite')) ?></a></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="setup-health-head">
    <div>
      <h3 class="setup-health-title"><?= e(t('admin.setup_health.child_modules')) ?></h3>
      <div class="muted"><?= e(t('admin.setup_health.child_modules_desc')) ?></div>
    </div>
    <div class="muted"><?= e(t('admin.setup_health.status.active')) ?>: <?= e((string)($moduleSummary['active'] ?? 0)) ?> / <?= e((string)($moduleSummary['total'] ?? 0)) ?> · <?= e(t('admin.setup_health.status.disabled')) ?>: <?= e((string)($moduleSummary['disabled'] ?? 0)) ?> · <?= e(t('admin.setup_health.warnings_errors')) ?>: <?= e((string)($moduleSummary['warnings'] ?? 0)) ?> / <?= e((string)($moduleSummary['errors'] ?? 0)) ?></div>
  </div>

  <?php foreach ($moduleGroups as $suiteKey => $rows): ?>
    <div class="setup-health-group">
      <h4 class="setup-health-title"><?= e(ucfirst((string)$suiteKey)) ?></h4>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th><?= e(t('admin.setup_health.module')) ?></th>
              <th><?= e(t('admin.setup_health.enabled')) ?></th>
              <th><?= e(t('admin.setup_health.health')) ?></th>
              <th><?= e(t('admin.setup_health.missing_tables')) ?></th>
              <th><?= e(t('admin.setup_health.failed_hooks')) ?></th>
              <th><?= e(t('admin.setup_health.schema_gaps')) ?></th>
              <th><?= e(t('admin.setup_health.warnings_errors')) ?></th>
              <th><?= e(t('admin.setup_health.runtime_health')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $module): ?>
              <tr>
                <td><?= e((string)($module['display_name'] ?? '')) ?></td>
                <td><?= e((string)($module['enabled_label'] ?? t('admin.setup_health.status.disabled'))) ?></td>
                <?php $moduleStatus = (string)($module['health_status'] ?? 'healthy'); ?><td><span class="pill setup-health-status <?= e($healthTone($moduleStatus)) ?>"><?= e($healthStatusLabel($moduleStatus)) ?></span></td>
                <td><?= e((string)($module['missing_tables'] ?? 0)) ?></td>
                <td><?= e((string)($module['failed_hooks'] ?? 0)) ?></td>
                <td><?= e((string)($module['schema_gaps'] ?? 0)) ?></td>
                <td><?= e((string)($module['warnings'] ?? 0)) ?> / <?= e((string)($module['errors'] ?? 0)) ?></td>
                <td><?= e((string)($module['runtime_health'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="setup-health-action">
    <a class="btn" href="/admin/setup/modules"><?= e(t('admin.setup_health.open_modules')) ?></a>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
