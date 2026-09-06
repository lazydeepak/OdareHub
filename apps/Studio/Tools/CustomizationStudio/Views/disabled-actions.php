<?php
$disabledPanels = [
    'save_draft',
    'apply',
];
?>
<?php foreach ($disabledPanels as $panel): ?>
  <section class="cs-vc__status-card">
    <h3><?= e($vc($panel)) ?></h3>
    <button type="button" disabled><?= e($vc($panel)) ?></button>
  </section>
<?php endforeach; ?>
