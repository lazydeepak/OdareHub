<?php
$currentTab = (string)($currentTab ?? 'overview');
$pageHeading = (string)($pageHeading ?? t('organization.title'));
$pageDescription = (string)($pageDescription ?? t('organization.description.overview'));
$canManage = (bool)($canManage ?? false);
$tabs = [
    'overview'  => ['label' => t('organization.nav.overview'),   'url' => '/ops/organization'],
    'company'   => ['label' => t('organization.nav.company'),    'url' => '/ops/organization/company'],
    'branches'  => ['label' => t('organization.nav.branches'),   'url' => '/ops/organization/branches'],
    'fiscal'    => ['label' => t('organization.nav.fiscal'),     'url' => '/ops/organization/fiscal'],
    'branding'  => ['label' => t('organization.nav.branding'),   'url' => '/ops/organization/branding'],
    'hierarchy' => ['label' => t('organization.hierarchy.title'), 'url' => '/ops/organization/hierarchy'],
    'audit'     => ['label' => t('organization.audit.title'),     'url' => '/ops/organization/audit'],
];
?>
<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2><?= e($pageHeading) ?></h2>
      <div class="muted"><?= e($pageDescription) ?></div>
    </div>
    <div class="module-header-actions">
      <?php if ($canManage): ?>
        <span class="btn u-style-2a9295c304"><?= e(t('organization.manage_enabled')) ?></span>
      <?php else: ?>
        <span class="btn u-style-3847e1c36d"><?= e(t('organization.read_only')) ?></span>
      <?php endif; ?>
    </div>
  </div>
  <div class="u-style-ab94f75edc">
    <?php foreach ($tabs as $tabKey => $tab): ?>
      <a class="btn<?= $currentTab === $tabKey ? ' ok' : '' ?>" href="<?= e((string)$tab['url']) ?>"><?= e((string)$tab['label']) ?></a>
    <?php endforeach; ?>
  </div>
</div>
