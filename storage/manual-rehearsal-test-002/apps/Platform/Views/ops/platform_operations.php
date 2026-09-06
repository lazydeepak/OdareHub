<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$studioAppEnabled = (bool)($studioAppEnabled ?? false);
$conditionalStudioEntryHref = '/apps/studio'; // conditional Studio entry when installed/enabled
$sections = [
    [
        'title_key' => 'ops.platform_operations.section.user_management_title',
        'desc_key' => 'ops.platform_operations.section.user_management_desc',
        'cards' => [
            [
                'href' => '/ops/access-control',
                'title_key' => 'ops.platform_operations.card.access_control',
                'desc_key' => 'ops.platform_operations.card.access_control_desc',
            ],
            [
                'href' => '/ops/user-control',
                'title_key' => 'ops.platform_operations.card.user_control',
                'desc_key' => 'ops.platform_operations.card.user_control_desc',
            ],
        ],
    ],
    [
        'title_key' => 'ops.platform_operations.section.display_title',
        'desc_key' => 'ops.platform_operations.section.display_desc',
        'cards' => [
            [
                'href' => '/ops/display-manager',
                'title_key' => 'ops.platform_operations.card.display_manager',
                'desc_key' => 'ops.platform_operations.card.display_manager_desc',
            ],
        ],
    ],
    [
        'title_key' => 'ops.platform_operations.section.experience_title',
        'desc_key' => 'ops.platform_operations.section.experience_desc',
        'cards' => [
            [
                'href' => '/ops/workspace-profiles',
                'title_key' => 'ops.platform_operations.card.workspace_profiles',
                'desc_key' => 'ops.platform_operations.card.workspace_profiles_desc',
            ],
            [
                'href' => '/ops/navigation-tree',
                'title_key' => 'ops.platform_operations.card.navigation_tree',
                'desc_key' => 'ops.platform_operations.card.navigation_tree_desc',
            ],
        ],
    ],
    [
        'title_key' => 'ops.platform_operations.section.workforce_title',
        'desc_key' => 'ops.platform_operations.section.workforce_desc',
        'cards' => [
            [
                'href' => '/ops/operator-tasks',
                'title_key' => 'ops.platform_operations.card.operator_tasks',
                'desc_key' => 'ops.platform_operations.card.operator_tasks_desc',
            ],
            [
                'href' => '/ops/notifications',
                'title_key' => 'ops.platform_operations.card.notifications',
                'desc_key' => 'ops.platform_operations.card.notifications_desc',
            ],
        ],
    ],
    [
        'title_key' => 'ops.platform_operations.section.audit_title',
        'desc_key' => 'ops.platform_operations.section.audit_desc',
        'cards' => [
            ['href' => '/ops/audit-log', 'title_key' => 'ops.platform_operations.card.audit_explorer', 'desc_key' => 'ops.platform_operations.card.audit_explorer_desc'],
        ],
    ],
];
if ($studioAppEnabled) {
    $sections[] = [
        'title_key' => 'ops.platform_operations.section.composition_title',
        'desc_key' => 'ops.platform_operations.section.composition_desc',
        'cards' => [
            [
                'href' => $conditionalStudioEntryHref,
                'title_key' => 'ops.platform_operations.card.gui_studio',
                'desc_key' => 'ops.platform_operations.card.gui_studio_desc',
            ],
        ],
    ];
}
?>
<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"><?= t('ops.platform_operations.title') ?></h2>
      <div class="muted"><?= t('ops.platform_operations.subtitle') ?></div>
    </div>
  </div>
</div>

<?php foreach ($sections as $section): ?>
<div class="card">
  <div class="module-header-info u-style-da12f2858b">
    <h3 class="u-style-1169661891"><?= t((string)$section['title_key']) ?></h3>
    <div class="muted"><?= t((string)$section['desc_key']) ?></div>
  </div>
  <div class="dashboard-grid">
    <?php foreach ((array)$section['cards'] as $card): ?>
    <a class="dashboard-link-card" href="<?= e((string)$card['href']) ?>">
      <div class="dashboard-link-top"><strong><?= t((string)$card['title_key']) ?></strong></div>
      <div class="muted"><?= t((string)$card['desc_key']) ?></div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
