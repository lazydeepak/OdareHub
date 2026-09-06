<?php
$preparedModules = is_array($preparedModules ?? null) ? $preparedModules : [];
$renderHostSection = is_callable($renderHostSection ?? null) ? $renderHostSection : static function (array $item): void {};
$blockOrderStyle = is_callable($blockOrderStyle ?? null) ? $blockOrderStyle : static fn(string $blockKey): string => '';
?>
<?php foreach ($preparedModules as $module): ?>
  <?php
    if (preg_match('/dispatch/i', (string)($module['key'] ?? '')) === 1) {
      continue;
    }
    $moduleItems = array_values((array)($module['items'] ?? []));
    if ($moduleItems === []) {
        continue;
    }
  ?>
  <section class="me-dashboard-module"<?= $blockOrderStyle('primary_work_widgets') ?>>
    <div class="me-dashboard-module-head">
      <h3><?= e((string)($module['label'] ?? t('admin.dashboard.fallback_operations'))) ?></h3>
    </div>
    <div class="me-dashboard-grid me-fit-grid me-dashboard-module-grid">
      <?php foreach ($moduleItems as $item): ?>
        <?php $renderHostSection($item); ?>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
