<?php
$setupNavCurrent = trim((string)($setupNavCurrent ?? 'overview'));
$setupNavItems = [
    'overview' => ['label' => t('setup.overview.nav.overview'), 'url' => '/admin/setup'],
    'onboarding' => ['label' => t('setup.overview.nav.onboarding'), 'url' => '/admin/setup/onboarding'],
    'core' => ['label' => t('setup.overview.nav.core'), 'url' => '/admin/setup/core'],
    'suites' => ['label' => t('setup.overview.nav.suites'), 'url' => '/admin/setup/suites'],
    'modules' => ['label' => t('setup.overview.nav.modules'), 'url' => '/admin/setup/modules'],
    'upgrades' => ['label' => t('setup.overview.nav.upgrades'), 'url' => '/admin/setup/upgrades'],
    'demo' => ['label' => t('setup.overview.nav.demo'), 'url' => '/admin/setup/demo'],
    'health' => ['label' => t('setup.overview.nav.health'), 'url' => '/admin/setup/health'],
    'environment' => ['label' => t('setup.overview.nav.environment'), 'url' => '/admin/setup/environment'],
    'config' => ['label' => t('setup.overview.nav.config'), 'url' => '/admin/setup/config'],
    'release' => ['label' => t('setup.overview.nav.release'), 'url' => '/admin/setup/release'],
    'dependencies' => ['label' => t('setup.overview.nav.dependencies'), 'url' => '/admin/setup/dependencies'],
    'scaffolds' => ['label' => t('setup.overview.nav.scaffolds'), 'url' => '/admin/setup/scaffolds'],
    'audit' => ['label' => t('setup.overview.nav.audit'), 'url' => '/admin/setup/audit'],
    'complete' => ['label' => t('setup.overview.nav.complete'), 'url' => '/admin/setup/complete'],
];
?>
<div class="card">
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <?php foreach ($setupNavItems as $key => $item): ?>
      <a class="btn<?= $setupNavCurrent === $key ? ' ok' : '' ?>" href="<?= e((string)$item['url']) ?>"><?= e((string)$item['label']) ?></a>
    <?php endforeach; ?>
  </div>
</div>
