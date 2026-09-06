<?php
declare(strict_types=1);

$moduleFilters = is_array($moduleFilters ?? null) ? $moduleFilters : [];
?>
<section class="card u-style-2b583d7389">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891"><?= e(t('sbaio.common.filters')) ?></h3>
      <div class="muted u-style-fe7b4979fe"><?= e(t('sbaio.common.filters_desc')) ?></div>
    </div>
  </div>
  <?php if ($moduleFilters === []): ?>
    <div class="muted"><?= e(t('sbaio.common.no_filters')) ?></div>
  <?php else: ?>
    <div class="row u-style-4725bb7117">
      <?php foreach ($moduleFilters as $filter): ?>
        <span class="pill">
          <?= e((string)($filter['label'] ?? t('sbaio.common.filter_label'))) ?>
          <?php if (!empty($filter['value'])): ?>
            : <?= e((string)$filter['value']) ?>
          <?php endif; ?>
        </span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
