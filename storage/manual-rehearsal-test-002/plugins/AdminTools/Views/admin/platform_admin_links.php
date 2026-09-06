<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php $platformLinkGroups = array_values(array_filter((array)($linkGroups ?? []), 'is_array')); ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"><?= e(t('admin.platform_links.title')) ?></h2>
      <div class="muted"><?= e(t('admin.platform_links.description')) ?></div>
    </div>
    <div class="module-header-actions"><a class="btn" href="/admin"><?= e(t('admin.platform_links.unified_home')) ?></a></div>
  </div>
  <div class="admin-upgrade-workbench-notice" role="status">
    <strong><?= e(t('admin.platform_links.status_title')) ?></strong>
    <span class="muted"><?= e(t('admin.platform_links.status_description')) ?></span>
  </div>
</div>

<?php foreach ($platformLinkGroups as $group): ?>
  <div class="card">
    <div class="module-header-info u-style-da12f2858b">
      <h3 class="u-style-1169661891"><?= e(t((string)($group['title_key'] ?? ''))) ?></h3>
      <div class="muted"><?= e(t((string)($group['description_key'] ?? ''))) ?></div>
    </div>
    <div class="dashboard-grid">
      <?php foreach (array_values(array_filter((array)($group['links'] ?? []), 'is_array')) as $link): ?>
        <a class="dashboard-link-card" href="<?= e((string)($link['url'] ?? '')) ?>">
          <div class="dashboard-link-top"><strong><?= e(t((string)($link['label_key'] ?? ''))) ?></strong></div>
          <div class="muted"><?= e(t('admin.platform_links.open_retained')) ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
