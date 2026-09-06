<?php
$adminLauncherGroups = array_values(array_filter((array)($admin_launcher_groups ?? []), 'is_array'));
if ($adminLauncherGroups === []) { return; }
?>
<section class="card admin-launcher" aria-labelledby="admin-launcher-title">
  <div class="admin-launcher-head">
    <div><h2 id="admin-launcher-title"><?= e(t('admin.launcher.title')) ?></h2><p class="muted"><?= e(t('admin.launcher.description')) ?></p></div>
    <span class="mapping-label"><?= e(t('admin.launcher.role_based')) ?></span>
  </div>
  <div class="admin-launcher-grid">
    <?php foreach ($adminLauncherGroups as $group): $items = array_values(array_filter((array)($group['items'] ?? []), 'is_array')); $groupAnchor = preg_replace('/[^a-z0-9_-]+/', '-', strtolower((string)($group['key'] ?? 'group'))) ?: 'group'; ?>
      <article class="admin-launcher-group" id="admin-launcher-group-<?= e($groupAnchor) ?>">
        <div class="admin-launcher-group-head"><h3><?= e(t((string)($group['label_key'] ?? ''))) ?></h3><span class="admin-launcher-count"><?= e((string)count($items)) ?></span></div>
        <p class="muted"><?= e(t((string)($group['description_key'] ?? ''))) ?></p>
        <nav class="admin-launcher-links" aria-label="<?= e(t((string)($group['label_key'] ?? ''))) ?>">
          <?php foreach ($items as $item): ?><a href="<?= e((string)($item['url'] ?? '')) ?>"><span><?= e((string)($item['label'] ?? '')) ?></span><span aria-hidden="true">→</span></a><?php endforeach; ?>
        </nav>
      </article>
    <?php endforeach; ?>
  </div>
</section>
