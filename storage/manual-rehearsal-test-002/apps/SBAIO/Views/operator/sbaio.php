<?php
// Operator Layer View: sbaio
// All composer scope variables available via include.
?>
<?php
    $sbaioTab = trim((string)(($data['current_query'] ?? [])['tab'] ?? 'overview'));
    if ($sbaioTab === '') {
        $sbaioTab = 'overview';
    }
    $sbaioUser = htmlspecialchars(rawurlencode((string)($data['username'] ?? '')));
    $base = '/u/' . $sbaioUser . '/sbaio';

    $tabMeta = [
        'overview' => [
            'label' => $this->tr('operator.sbaio.subnav.overview', 'Overview'),
            'target' => '/apps/sbaio',
        ],
        'attendance' => [
            'label' => $this->tr('nav.sbaio_attendance', 'Attendance'),
            'target' => '/apps/sbaio/attendance',
        ],
        'timecards' => [
            'label' => $this->tr('nav.sbaio_timecards', 'Timecards'),
            'target' => '/apps/sbaio/timecards',
        ],
        'payroll' => [
            'label' => $this->tr('nav.sbaio_payroll', 'Payroll'),
            'target' => '/apps/sbaio/payroll',
        ],
        'leave' => [
            'label' => $this->tr('nav.sbaio_leave', 'Leave'),
            'target' => '/apps/sbaio/leave',
        ],
        'staff' => [
            'label' => $this->tr('nav.sbaio_staff', 'Staff'),
            'target' => '/apps/sbaio/staff',
        ],
        'schedules' => [
            'label' => $this->tr('nav.sbaio_schedules', 'Schedules'),
            'target' => '/apps/sbaio/schedules',
        ],
        'tasks' => [
            'label' => $this->tr('nav.sbaio_tasks', 'Tasks'),
            'target' => '/apps/sbaio/tasks',
        ],
        'customers' => [
            'label' => $this->tr('nav.sbaio_customers', 'Customers'),
            'target' => '/apps/sbaio/customers',
        ],
    ];

    if (!isset($tabMeta[$sbaioTab])) {
        $sbaioTab = 'overview';
    }

    $selectedLabel = (string)($tabMeta[$sbaioTab]['label'] ?? $this->tr('operator.sbaio.subnav.overview', 'Overview'));
    $selectedTarget = (string)($tabMeta[$sbaioTab]['target'] ?? '/apps/sbaio');
?>
<section class="recent-focus" aria-label="<?php echo htmlspecialchars($this->tr('operator.sbaio.title', 'SBAIO Workspace')); ?>">
    <h2 class="dashboard-top-title"><?php echo htmlspecialchars($this->tr('operator.sbaio.title', 'SBAIO Workspace')); ?></h2>
    <p class="recent-focus-subtitle"><?php echo htmlspecialchars($this->tr('operator.sbaio.subtitle', 'Operator-facing SBAIO workspace is now available on /u.')); ?></p>

    <nav class="operator-subnav" aria-label="<?php echo htmlspecialchars($this->tr('operator.sbaio.subnav.label', 'SBAIO sections')); ?>">
        <?php foreach ($tabMeta as $tabSlug => $tab): ?>
            <a class="operator-subnav-item<?php echo $sbaioTab === $tabSlug ? ' operator-subnav-item--active' : ''; ?>" href="<?php echo htmlspecialchars($base . '?tab=' . rawurlencode((string)$tabSlug)); ?>"<?php echo $sbaioTab === $tabSlug ? ' aria-current="page"' : ''; ?>><?php echo htmlspecialchars((string)($tab['label'] ?? '')); ?></a>
        <?php endforeach; ?>
    </nav>

    <section class="surface-card" aria-label="<?php echo htmlspecialchars($this->tr('operator.sbaio.card.current_focus', 'Current SBAIO Focus')); ?>">
        <h3 class="work-entry-embed-title"><?php echo htmlspecialchars($this->tr('operator.sbaio.card.current_focus', 'Current SBAIO Focus')); ?></h3>
        <p class="u-m-0"><?php echo htmlspecialchars($this->tr('operator.sbaio.current_focus_value', 'Selected section: {tab}', ['tab' => $selectedLabel])); ?></p>
        <p class="u-muted-compact u-mt-6"><?php echo htmlspecialchars($this->tr('operator.sbaio.destination_hint', 'Canonical SBAIO surface for this section: {url}', ['url' => $selectedTarget])); ?></p>
        <p class="u-muted-compact u-mt-6"><?php echo htmlspecialchars($this->tr('operator.sbaio.note', 'This /u SBAIO view is app-owned and loaded through dynamic operator contribution contracts.')); ?></p>
    </section>
</section>
