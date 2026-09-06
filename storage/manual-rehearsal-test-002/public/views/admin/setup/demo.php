<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$csrf = (string)($csrf ?? '');
$stages = [
  'products' => t('setup.demo.stage_products'),
  'machines' => t('setup.demo.stage_machines'),
  'materials' => t('setup.demo.stage_materials'),
  'orders' => t('setup.demo.stage_orders'),
  'plans' => t('setup.demo.stage_plans'),
  'production' => t('setup.demo.stage_production'),
  'qc' => t('setup.demo.stage_qc'),
  'dispatch' => t('setup.demo.stage_dispatch'),
];
$today = date('Y-m-d');
?>

<div class="card">
  <h2 style="margin:0 0 8px"><?= e(t('setup.demo.title')) ?></h2>
  <div class="muted"><?= e(t('setup.demo.subtitle')) ?></div>
</div>

<?php $setupNavCurrent = 'demo'; require __DIR__ . '/_nav.php'; ?>
<?php require __DIR__ . '/_flash.php'; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:start">
  <form method="post" action="/admin/setup/demo/feed" class="card" style="margin:0">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <h3 style="margin:0 0 8px"><?= e(t('common.demo_data_plus')) ?></h3>
    <div class="muted" style="margin-bottom:10px"><?= e(t('setup.demo.feed_description')) ?></div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(160px,1fr));gap:8px;margin-bottom:10px">
      <?php foreach ($stages as $key => $label): ?>
      <label style="display:flex;gap:8px;align-items:center">
        <input type="checkbox" name="stages[]" value="<?= e($key) ?>" checked>
        <span><?= e($label) ?></span>
      </label>
      <?php endforeach; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
      <label>
        <div class="muted" style="margin-bottom:4px"><?= e(t('setup.demo.start_date')) ?></div>
        <input type="date" name="start_date" value="<?= e($today) ?>">
      </label>
      <label>
        <div class="muted" style="margin-bottom:4px"><?= e(t('setup.demo.day_span')) ?></div>
        <input type="number" name="day_span" value="5" min="1" max="30">
      </label>
    </div>

    <button class="btn ok" type="submit" onclick="return confirm('<?= e(t('messages.load_demo_data_confirm')) ?>');"><?= e(t('common.demo_data_plus')) ?></button>
  </form>

  <form method="post" action="/admin/setup/demo/reset" class="card" style="margin:0">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <h3 style="margin:0 0 8px"><?= e(t('common.reset_demo')) ?></h3>
    <div class="muted" style="margin-bottom:10px"><?= e(t('setup.demo.reset_description')) ?></div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(160px,1fr));gap:8px;margin-bottom:12px">
      <?php foreach ($stages as $key => $label): ?>
      <label style="display:flex;gap:8px;align-items:center">
        <input type="checkbox" name="stages[]" value="<?= e($key) ?>" checked>
        <span><?= e($label) ?></span>
      </label>
      <?php endforeach; ?>
    </div>

    <button class="btn" type="submit" onclick="return confirm('<?= e(t('messages.reset_demo_confirm')) ?>');"><?= e(t('common.reset_demo')) ?></button>
  </form>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
