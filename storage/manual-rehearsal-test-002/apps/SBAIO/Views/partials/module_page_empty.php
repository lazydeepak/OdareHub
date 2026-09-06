<?php
declare(strict_types=1);

$emptyTitle = trim((string)($emptyTitle ?? t('sbaio.common.empty_title')));
$emptyMessage = trim((string)($emptyMessage ?? ''));
?>
<section class="card">
  <h3 class="u-style-d462248a40"><?= e($emptyTitle) ?></h3>
  <div class="muted"><?= e($emptyMessage) ?></div>
</section>
