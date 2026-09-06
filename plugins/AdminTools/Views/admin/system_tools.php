<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$isPlatformAdmin = (bool)($isPlatformAdmin ?? false);
$developerToolsVisible = (bool)($developerToolsVisible ?? false);
$toolSections = [
    [
        'title_key' => 'admin.system_tools.section.platform_ops_title',
        'desc_key' => 'admin.system_tools.section.platform_ops_desc',
        'cards' => [
            [
                'href' => '/admin/system-tools/platform-mode',
                'title_key' => 'admin.platform_mode.title',
                'desc_key' => 'admin.platform_mode.description',
            ],
            [
                'href' => '/admin/setup',
                'title_key' => 'admin.system_tools.card.setup',
                'desc_key' => 'admin.system_tools.card.setup_desc',
            ],
            [
                'href' => '/admin/setup/environment',
                'title_key' => 'admin.system_tools.card.environment',
                'desc_key' => 'admin.system_tools.card.environment_desc',
            ],
            [
                'href' => '/admin/base',
                'title_key' => 'admin.system_tools.card.schema_utilities',
                'desc_key' => 'admin.system_tools.card.schema_utilities_desc',
            ],
            [
                'href' => '/admin/system-tools/email-settings',
                'title_key' => 'admin.system_tools.card.identity_security',
                'desc_key' => 'admin.system_tools.card.identity_security_desc',
            ],
        ],
    ],
    [
        'title_key' => 'admin.system_tools.section.health_title',
        'desc_key' => 'admin.system_tools.section.health_desc',
        'cards' => [
            [
                'href' => '/admin/setup/health',
                'title_key' => 'admin.system_tools.card.platform_health',
                'desc_key' => 'admin.system_tools.card.platform_health_desc',
            ],
            [
                'href' => '/admin/system-tools/module-health',
                'title_key' => 'admin.system_tools.card.module_health',
                'desc_key' => 'admin.system_tools.card.module_health_desc',
            ],
            [
                'href' => '/admin/architecture-health',
                'title_key' => 'admin.system_tools.card.architecture_health',
                'desc_key' => 'admin.system_tools.card.architecture_health_desc',
                'visibility' => 'developer_tools',
            ],
        ],
    ],
    [
        'title_key' => 'admin.system_tools.section.observability_title',
        'desc_key' => 'admin.system_tools.section.observability_desc',
        'cards' => [
            [
                'href' => '/admin/system-tools/runtime-report',
                'title_key' => 'admin.system_tools.card.runtime_report',
                'desc_key' => 'admin.system_tools.card.runtime_report_desc',
            ],
            [
                'href' => '/admin/system-tools/export-audit',
                'title_key' => 'admin.system_tools.card.export_audit',
                'desc_key' => 'admin.system_tools.card.export_audit_desc',
            ],
            [
                'href' => '/admin/system-tools/data-control/audit-trail',
                'title_key' => 'admin.system_tools.card.data_audit',
                'desc_key' => 'admin.system_tools.card.data_audit_desc',
            ],
        ],
    ],
    [
        'title_key' => 'admin.system_tools.section.apps_lifecycle_title',
        'desc_key' => 'admin.system_tools.section.apps_lifecycle_desc',
        'cards' => [
            ['href' => '/admin/apps', 'title_key' => 'admin.system_tools.card.apps_manager', 'desc_key' => 'admin.system_tools.card.apps_manager_desc'],
            ['href' => '/admin/app-manager', 'title_key' => 'admin.system_tools.card.app_inventory', 'desc_key' => 'admin.system_tools.card.app_inventory_desc'],
            ['href' => '/admin/apps/deps', 'title_key' => 'admin.system_tools.card.dependencies', 'desc_key' => 'admin.system_tools.card.dependencies_desc'],
            ['href' => '/admin/system-tools/app-management', 'title_key' => 'admin.system_tools.card.app_management', 'desc_key' => 'admin.system_tools.card.app_management_desc'],
            ['href' => '/admin/system-tools/resilience', 'title_key' => 'admin.system_tools.card.resilience_map', 'desc_key' => 'admin.system_tools.card.resilience_map_desc'],
        ],
    ],
    [
        'title_key' => 'admin.system_tools.section.runtime_title',
        'desc_key' => 'admin.system_tools.section.runtime_desc',
        'cards' => [
            [
                'href' => '/admin/system-tools/entity-runtime',
                'title_key' => 'admin.system_tools.card.entity_runtime',
                'desc_key' => 'admin.system_tools.card.entity_runtime_desc',
            ],
            [
                'href' => '/admin/system-tools/my-work-runtime',
                'title_key' => 'admin.system_tools.card.my_work_runtime',
                'desc_key' => 'admin.system_tools.card.my_work_runtime_desc',
            ],
            [
                'href' => '/admin/system-tools/stage-inspector',
                'title_key' => 'admin.system_tools.card.stage_inspector',
                'desc_key' => 'admin.system_tools.card.stage_inspector_desc',
            ],
        ],
    ],
];
?>
<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"><?= t('admin.system_tools.title') ?></h2>
      <div class="muted"><?= t('admin.system_tools.subtitle') ?></div>
    </div>
    <?php if ($developerToolsVisible): ?><div class="module-header-actions"><a class="btn" href="/admin/system-tools/upgrade-catalog"><?= e(t('admin.upgrade_catalog.title')) ?></a></div><?php endif; ?>
  </div>
</div>

<?php foreach ($toolSections as $section): ?>
<div class="card">
  <div class="module-header-info u-style-da12f2858b">
    <h3 class="u-style-1169661891"><?= t((string)$section['title_key']) ?></h3>
    <div class="muted"><?= t((string)$section['desc_key']) ?></div>
  </div>
  <div class="dashboard-grid">
    <?php foreach ((array)$section['cards'] as $card): ?>
    <?php if (($card['visibility'] ?? '') === 'developer_tools' && !$developerToolsVisible) { continue; } ?>
    <a class="dashboard-link-card" href="<?= e((string)$card['href']) ?>">
      <div class="dashboard-link-top"><strong><?= t((string)$card['title_key']) ?></strong></div>
      <div class="muted"><?= t((string)$card['desc_key']) ?></div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<div class="card">
  <div class="module-header-info u-style-da12f2858b">
    <h3 class="u-style-1169661891"><?= t('admin.system_tools.section.future_title') ?></h3>
    <div class="muted"><?= t('admin.system_tools.section.future_desc') ?></div>
  </div>
  <div class="dashboard-grid">
    <div class="dashboard-link-card is-disabled">
      <div class="dashboard-link-top"><strong><?= t('admin.system_tools.card.datastudio_future') ?></strong></div>
      <div class="muted"><?= t('admin.system_tools.card.datastudio_future_desc') ?></div>
    </div>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
