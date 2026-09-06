<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<?php
  $summary = (array)($report['summary'] ?? []);
  $modules = (array)($report['modules'] ?? []);
  $bandLabels = [
    'mature' => t('admin.module_health.band.mature'),
    'usable_with_tracked_gaps' => t('admin.module_health.band.usable'),
    'partial_module' => t('admin.module_health.band.partial'),
    'baby_module_or_shell' => t('admin.module_health.band.baby_shell'),
    'undeclared_or_non_functional' => t('admin.module_health.band.non_functional'),
    'invalid_manifest' => t('admin.module_health.band.invalid'),
  ];
?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"><?= t('admin.module_health.title') ?></h2>
      <div class="muted"><?= t('admin.module_health.subtitle') ?></div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/admin/system-tools"><?= t('admin.system_tools.nav.back') ?></a>
      <a class="btn" href="/admin/system-tools/module-health?export=json" target="_blank"><?= t('admin.module_health.export_json') ?></a>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-291b7bbb01"><?= t('admin.module_health.summary') ?></h3>
  <div class="u-style-1b2a0757c1">
    <div class="u-style-baa5fba05d">
      <div class="u-style-4dbf65c507"><?= (int)($summary['total'] ?? 0) ?></div>
      <div class="muted"><?= t('admin.module_health.summary.total') ?></div>
    </div>
    <div class="u-style-165d40d2ba">
      <div class="u-style-4d7722c286"><?= (int)($summary['mature'] ?? 0) ?></div>
      <div class="muted"><?= t('admin.module_health.summary.mature') ?></div>
    </div>
    <div class="u-style-9932e9ea34">
      <div class="u-style-b5c5980a89"><?= (int)($summary['usable_with_tracked_gaps'] ?? 0) ?></div>
      <div class="muted"><?= t('admin.module_health.summary.usable') ?></div>
    </div>
    <div class="u-style-7cec62e299">
      <div class="u-style-e63448eda5"><?= (int)($summary['partial_module'] ?? 0) ?></div>
      <div class="muted"><?= t('admin.module_health.summary.partial') ?></div>
    </div>
    <div class="u-style-881cf3dc49">
      <div class="u-style-f460308f88"><?= (int)($summary['baby_module_or_shell'] ?? 0) ?></div>
      <div class="muted"><?= t('admin.module_health.summary.baby_shell') ?></div>
    </div>
    <div class="u-style-baa5fba05d">
      <div class="u-style-6f448306a0"><?= htmlspecialchars((string)($report['generated_at'] ?? '')) ?></div>
      <div class="muted"><?= t('admin.module_health.summary.generated') ?></div>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-291b7bbb01"><?= t('admin.module_health.modules') ?></h3>
  <table class="data-table u-style-cad980f4b7">
    <thead>
      <tr>
        <th><?= t('admin.module_health.col.module') ?></th>
        <th><?= t('admin.module_health.col.app') ?></th>
        <th><?= t('admin.module_health.col.type') ?></th>
        <th><?= t('admin.module_health.col.target') ?></th>
        <th><?= t('admin.module_health.col.score') ?></th>
        <th><?= t('admin.module_health.col.band') ?></th>
        <th><?= t('admin.module_health.col.missing') ?></th>
        <th><?= t('admin.module_health.col.partial') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($modules as $module): ?>
        <?php
          $band = (string)($module['band'] ?? '');
          $missing = (array)($module['missing'] ?? []);
          $partial = (array)($module['partial'] ?? []);
        ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars((string)($module['module'] ?? '')) ?></strong>
            <div class="muted u-style-e48b05836a"><?= htmlspecialchars((string)($module['path'] ?? '')) ?></div>
          </td>
          <td><?= htmlspecialchars((string)($module['suite'] ?? '')) ?></td>
          <td><code><?= htmlspecialchars((string)($module['module_type'] ?? '')) ?></code></td>
          <td><?= htmlspecialchars((string)($module['target_maturity_level'] ?? '')) ?></td>
          <td class="u-style-134820be08"><?= (int)($module['score_percent'] ?? 0) ?>%</td>
          <td><?= htmlspecialchars($bandLabels[$band] ?? $band) ?></td>
          <td><?= htmlspecialchars($missing ? implode(', ', $missing) : '-') ?></td>
          <td><?= htmlspecialchars($partial ? implode(', ', $partial) : '-') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
