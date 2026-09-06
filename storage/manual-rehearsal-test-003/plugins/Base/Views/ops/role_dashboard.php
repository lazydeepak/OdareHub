<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php
$dashboard         = is_array($dashboard ?? null) ? $dashboard : [];
$cards             = is_array($dashboard['cards'] ?? null) ? $dashboard['cards'] : [];
$sections          = is_array($dashboard['sections'] ?? null) ? $dashboard['sections'] : [];
$quickLinks        = is_array($dashboard['quick_links'] ?? null) ? $dashboard['quick_links'] : [];
$quickLinksTitle   = trim((string)($dashboard['quick_links_title'] ?? ''));
$placeholders      = is_array($dashboard['placeholders'] ?? null) ? $dashboard['placeholders'] : [];
$dashboardType     = (string)($dashboardType ?? '');
$currentOperationalProfile = (string)($currentOperationalProfile ?? '');
$currentAccountType = (string)($currentAccountType ?? '');
$notificationCount = (int)($notificationCount ?? 0);
$platformMode      = (string)($platformMode ?? '');
$platformModeAvailable = is_array($platformModeAvailable ?? null) ? $platformModeAvailable : [];

// Per-role identity: accent color + primary action buttons
$roleMap = [
  'my_work' => [
    'label'   => t('nav.my_work'),
    'accent'  => 'my-work',
    'actions' => [
      ['label' => t('ops.role_dashboard.my_workboard'), 'url' => '/'],
      ['label' => t('nav.approval_inbox'), 'url' => '/ops/approval-inbox'],
    ],
  ],
    'production_leader' => [
      'label'   => 'Production',
        'accent'  => 'production',
        'actions' => [
        ['label' => t('nav.production_queue'), 'url' => '/manufacturing/production-queue'],
        ['label' => t('nav.production_entries'), 'url' => '/production-entries'],
            ['label' => t('nav.production_plans'), 'url' => '/apps/manufacturing/production-plans'],
            ['label' => t('nav.stage_board'),      'url' => '/manufacturing/stage-board'],
        ],
    ],
    'assembly_leader' => [
      'label'   => 'Assembly',
        'accent'  => 'assembly',
        'actions' => [
            ['label' => t('nav.assembly_queue'), 'url' => '/manufacturing/assembly-queue'],
            ['label' => t('nav.assembly_plans'), 'url' => '/manufacturing/assembly-plans'],
            ['label' => t('nav.stage_board'),    'url' => '/manufacturing/stage-board'],
        ],
    ],
    'qc_leader' => [
      'label'   => 'QC',
        'accent'  => 'qc',
        'actions' => [
            ['label' => t('nav.qc_entries'), 'url' => '/qc-entries'],
            ['label' => t('nav.qc_plans'),   'url' => '/qc-plans'],
            ['label' => t('nav.stage_board'),'url' => '/manufacturing/stage-board'],
        ],
    ],
    'dispatch_leader' => [
      'label'   => 'Dispatch',
        'accent'  => 'dispatch',
        'actions' => [
        ['label' => t('ops.role_dashboard.dispatch_preparation_queue'), 'url' => '/manufacturing/dispatch-ops'],
        ['label' => t('ops.role_dashboard.dispatch_preparation_form'),  'url' => '/manufacturing/dispatch-ops/preparation'],
            ['label' => t('nav.dispatch_entries'), 'url' => '/dispatch-entries'],
        ['label' => t('ops.dispatch_leader.open_dispatch_entry'), 'url' => '/dispatch-entries/add'],
        ],
    ],
];

$identity = $roleMap[$dashboardType] ?? ['label' => t('ops.role_dashboard.dashboard'), 'accent' => 'default', 'actions' => []];

$routeExists = static function (string $url): bool {
  $url = trim($url);
  if ($url === '') {
    return false;
  }
  if (!str_starts_with($url, '/')) {
    return true;
  }
  if (!class_exists('\App\\Core\\RouteRuntimeAuthority')) {
    return true;
  }
  return \App\Core\RouteRuntimeAuthority::hasLoadedRoute($url, 'GET');
};

$identity['actions'] = array_values(array_filter((array)($identity['actions'] ?? []), static function (array $action) use ($routeExists): bool {
  return $routeExists((string)($action['url'] ?? ''));
}));

$quickLinks = array_values(array_filter($quickLinks, static function (array $link) use ($routeExists): bool {
  return $routeExists((string)($link['url'] ?? ''));
}));

$toneClass = static function (string $tone): string {
    return match ($tone) {
        'danger' => 'role-dashboard-value--danger',
        'warn'   => 'role-dashboard-value--warning',
        'ok'     => 'role-dashboard-value--success',
        default  => 'role-dashboard-value--accent',
    };
};
$identityAccent = in_array((string)($identity['accent'] ?? ''), ['platform', 'app-admin', 'my-work', 'production', 'assembly', 'qc', 'dispatch'], true)
    ? (string)$identity['accent']
    : 'default';
$platformModeClass = in_array($platformMode, ['production', 'development', 'demo'], true) ? $platformMode : 'default';
?>

<section class="card role-dashboard-hero role-dashboard-accent--<?= e($identityAccent) ?>">
  <div class="section-head u-style-4e420aff3f">
    <div class="u-style-bbc056015a">
      <span class="role-dashboard-role-badge"><?= e($identity['label']) ?></span>
      <h2 class="u-style-1169661891"><?= e((string)($dashboard['title'] ?? t('ops.role_dashboard.role_dashboard'))) ?></h2>
      <?php if ($platformMode !== ''): ?>
        <span class="role-dashboard-mode-badge role-dashboard-mode-badge--<?= e($platformModeClass) ?>"><?= e('Mode: ' . \App\Services\PlatformModeService::modeLabel($platformMode)) ?></span>
      <?php endif; ?>
      <?php if ($notificationCount > 0): ?>
        <a class="u-style-974d8c29a1" href="/ops/notifications"><?= e(t($notificationCount === 1 ? 'ops.role_dashboard.alert_count_one' : 'ops.role_dashboard.alert_count_other', ['count' => $notificationCount])) ?></a>
      <?php endif; ?>
    </div>
    <div class="muted u-style-876fec37a0">
      <?= e(t('ops.role_dashboard.operational_role')) ?>: <strong><?= e($currentOperationalProfile) ?></strong>
      &middot; <?= e(t('ops.role_dashboard.authority_role')) ?>: <?= e($currentAccountType) ?>
      &middot; <?= e(t('ops.role_dashboard.dashboard_experience')) ?>: <?= e($dashboardType) ?>
    </div>
  </div>
  <?php if (!empty($identity['actions'])): ?>
  <div class="row u-style-3d0d9d26f7">
    <?php foreach ($identity['actions'] as $action): ?>
      <a class="btn" href="<?= e((string)($action['url'] ?? '/')) ?>"><?= e((string)($action['label'] ?? '')) ?></a>
    <?php endforeach; ?>
    <a class="btn u-style-6d00061700" href="/ops/notifications"><?= e(t('ops.role_dashboard.notifications')) ?><?= $notificationCount > 0 ? ' (' . $notificationCount . ')' : '' ?></a>
  </div>
  <?php endif; ?>
  <?php if ((string)($dashboard['subtitle'] ?? '') !== ''): ?>
  <div class="muted u-style-ea76e76842"><?= e((string)$dashboard['subtitle']) ?></div>
  <?php endif; ?>

</section>

<?php if (!empty($cards)): ?>
<section class="card">
  <div class="coverage-kpi-grid">
    <?php foreach ($cards as $card): ?>
      <?php $tone = (string)($card['tone'] ?? 'info'); ?>
      <div class="coverage-kpi">
        <div class="tile-card-shell">
        <div class="ui-block">
          <div class="muted u-style-3995822e95"><?= e((string)($card['label'] ?? t('ops.role_dashboard.metric'))) ?></div>
          <div class="coverage-kpi-value <?= e($toneClass($tone)) ?>"><?= e((string)($card['value'] ?? '0')) ?></div>
        </div>
        <?php
          $miniAction = is_array($card['mini_action'] ?? null) ? (array)$card['mini_action'] : [];
          if ($miniAction === []) {
              $syncAction = is_array($card['sync_action'] ?? null) ? (array)$card['sync_action'] : [];
              if (!empty($syncAction['url']) && !empty($syncAction['action'])) {
                  $miniAction = [
                      'method' => 'post',
                      'url' => (string)$syncAction['url'],
                      'label' => (string)($syncAction['label'] ?? t('ops.role_dashboard.run')),
                      'confirm' => (string)($syncAction['confirm'] ?? t('ops.role_dashboard.sync_confirm')),
                      'fields' => [
                          'action' => (string)$syncAction['action'],
                          'name' => (string)($syncAction['name'] ?? '*'),
                      ],
                  ];
              }
          }
          $miniMethod = strtolower((string)($miniAction['method'] ?? 'get'));
          $miniFields = is_array($miniAction['fields'] ?? null) ? (array)$miniAction['fields'] : [];
          $miniConfirm = trim((string)($miniAction['confirm'] ?? ''));
        ?>
        <?php if (!empty($miniAction['url'])): ?>
          <div class="tile-card-footer tile-card-footer-end">
            <?php if ($miniMethod === 'post'): ?>
              <form method="post" action="<?= e((string)$miniAction['url']) ?>" class="dashboard-action-form">
                <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                <?php foreach ($miniFields as $k => $v): ?>
                  <input type="hidden" name="<?= e((string)$k) ?>" value="<?= e((string)$v) ?>">
                <?php endforeach; ?>
                <button class="btn tile-secondary-action tile-secondary-action--compact" type="submit"<?= $miniConfirm !== '' ? ' onclick="return confirm(' . htmlspecialchars(json_encode($miniConfirm), ENT_QUOTES, 'UTF-8') . ');"' : '' ?>><?= e((string)($miniAction['label'] ?? 'Run')) ?></button>
              </form>
            <?php else: ?>
              <a class="btn tile-secondary-action tile-secondary-action--compact" href="<?= e((string)$miniAction['url']) ?>"><?= e((string)($miniAction['label'] ?? t('common.open'))) ?></a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php foreach ($sections as $section): ?>
<section class="card">
  <div class="section-head">
    <h2><?= e((string)($section['title'] ?? t('ops.role_dashboard.section'))) ?></h2>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th><?= e(t('common.item')) ?></th><th><?= e(t('common.value')) ?></th></tr></thead>
      <tbody>
        <?php foreach ((array)($section['items'] ?? []) as $item): ?>
        <tr>
          <td><?= e((string)($item['label'] ?? '')) ?></td>
          <td><?= e((string)($item['value'] ?? '')) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endforeach; ?>

<?php if (!empty($quickLinks)): ?>
<section class="card">
  <div class="section-head"><h2><?= e($quickLinksTitle !== '' ? $quickLinksTitle : t('ops.role_dashboard.quick_links')) ?></h2></div>
  <div class="app-quicklink-row">
    <?php foreach ($quickLinks as $link): ?>
      <a class="app-quicklink-card" href="<?= e((string)($link['url'] ?? '/')) ?>">
        <span class="app-quicklink-label"><?= e((string)($link['label'] ?? t('common.open'))) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($placeholders)): ?>
<section class="card">
  <div class="section-head"><h2><?= e(t('ops.role_dashboard.future_placeholders')) ?></h2></div>
  <ul class="u-style-592d196c45">
    <?php foreach ($placeholders as $note): ?>
      <li class="muted u-style-c1fafc235b"><?= e((string)$note) ?></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
