<?php
$rows          = isset($rows) && is_array($rows) ? $rows : [];
$roles         = isset($roles) && is_array($roles) ? $roles : [];
$surfaces      = isset($surfaces) && is_array($surfaces) ? $surfaces : [];
$summaryCounts = isset($summaryCounts) && is_array($summaryCounts) ? $summaryCounts : ['all' => 0, 'draft' => 0, 'published' => 0, 'archived' => 0];
$totalRows     = isset($totalRows) ? (int)$totalRows : count($rows);
$filteredRows  = isset($filteredRows) ? (int)$filteredRows : count($rows);
$page          = isset($page) ? max(1, (int)$page) : 1;
$perPage       = isset($perPage) ? max(5, min(50, (int)$perPage)) : 20;
$totalPages    = isset($totalPages) ? max(1, (int)$totalPages) : 1;
$statusFilter  = trim((string)($statusFilter ?? 'all'));
$roleFilter    = trim((string)($roleFilter ?? 'all'));
$surfaceFilter = trim((string)($surfaceFilter ?? 'all'));
$searchQ       = trim((string)($searchQ ?? ''));
$csrf          = (string)($csrf ?? '');
$flashKey      = trim((string)($flash ?? ''));
$errorKey      = trim((string)($error ?? ''));
$flashText     = $flashKey !== '' ? t($flashKey) : '';
$errorText     = $errorKey !== '' ? t($errorKey) : '';

$currentQuery  = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
$redirectTo    = '/ops/design-studio' . ($currentQuery !== '' ? ('?' . $currentQuery) : '');

$buildFilterUrl = static function (array $query): string {
    $queryString = http_build_query(array_filter($query, static fn($v) => $v !== null && $v !== '' && $v !== 'all'));
    return '/ops/design-studio' . ($queryString !== '' ? ('?' . $queryString) : '');
};

// Build role label map
$roleLabelMap = [];
foreach ($roles as $r) {
    $roleLabelMap[(string)($r['role_key'] ?? '')] = t((string)($r['label_key'] ?? ''));
}

// Build surface label map
$surfaceLabelMap = [];
foreach ($surfaces as $s) {
    $surfaceLabelMap[(string)($s['surface_key'] ?? '')] = t((string)($s['label_key'] ?? ''));
}

// Active filter chips
$activeFilterChips = [];
if ($statusFilter !== 'all') {
    $q = ['target_role' => $roleFilter !== 'all' ? $roleFilter : null, 'target_surface' => $surfaceFilter !== 'all' ? $surfaceFilter : null, 'q' => $searchQ !== '' ? $searchQ : null];
    $activeFilterChips[] = ['label' => t('common.status'), 'value' => t('ops.design_studio.status.' . $statusFilter), 'url' => $buildFilterUrl($q)];
}
if ($roleFilter !== 'all') {
    $q = ['status' => $statusFilter !== 'all' ? $statusFilter : null, 'target_surface' => $surfaceFilter !== 'all' ? $surfaceFilter : null, 'q' => $searchQ !== '' ? $searchQ : null];
    $activeFilterChips[] = ['label' => t('ops.design_studio.filter.role'), 'value' => $roleLabelMap[$roleFilter] ?? $roleFilter, 'url' => $buildFilterUrl($q)];
}
if ($surfaceFilter !== 'all') {
    $q = ['status' => $statusFilter !== 'all' ? $statusFilter : null, 'target_role' => $roleFilter !== 'all' ? $roleFilter : null, 'q' => $searchQ !== '' ? $searchQ : null];
    $activeFilterChips[] = ['label' => t('ops.design_studio.filter.surface'), 'value' => $surfaceLabelMap[$surfaceFilter] ?? $surfaceFilter, 'url' => $buildFilterUrl($q)];
}
if ($searchQ !== '') {
    $q = ['status' => $statusFilter !== 'all' ? $statusFilter : null, 'target_role' => $roleFilter !== 'all' ? $roleFilter : null, 'target_surface' => $surfaceFilter !== 'all' ? $surfaceFilter : null];
    $activeFilterChips[] = ['label' => t('ops.design_studio.filter.search'), 'value' => $searchQ, 'url' => $buildFilterUrl($q)];
}
?>
<div class="page-header">
  <div class="page-header__inner">
    <div class="page-header__meta">
      <a href="/ops/platform-operations" class="page-header__back"><?= htmlspecialchars(t('ops.design_studio.nav.back_to_platform_ops')) ?></a>
    </div>
    <h1 class="page-header__title"><?= htmlspecialchars(t('ops.design_studio.page_title')) ?></h1>
    <p class="page-header__subtitle"><?= htmlspecialchars(t('ops.design_studio.page_subtitle')) ?></p>
  </div>
</div>

<div class="ops-section">

<?php if ($flashText !== ''): ?>
  <div class="alert alert--success" role="status"><?= htmlspecialchars($flashText) ?></div>
<?php endif; ?>
<?php if ($errorText !== ''): ?>
  <div class="alert alert--error" role="alert"><?= htmlspecialchars($errorText) ?></div>
<?php endif; ?>

  <!-- KPI strip -->
  <div class="kpi-strip kpi-strip--4 u-style-bece355826">
    <a href="/ops/design-studio" class="kpi-card<?= $statusFilter === 'all' ? ' kpi-card--active' : '' ?>">
      <span class="kpi-card__label"><?= htmlspecialchars(t('ops.design_studio.kpi.total')) ?></span>
      <span class="kpi-card__value"><?= (int)($summaryCounts['all'] ?? 0) ?></span>
    </a>
    <a href="<?= htmlspecialchars($buildFilterUrl(['status' => 'draft', 'target_role' => $roleFilter !== 'all' ? $roleFilter : null, 'target_surface' => $surfaceFilter !== 'all' ? $surfaceFilter : null, 'q' => $searchQ !== '' ? $searchQ : null])) ?>" class="kpi-card<?= $statusFilter === 'draft' ? ' kpi-card--active' : '' ?>">
      <span class="kpi-card__label"><?= htmlspecialchars(t('ops.design_studio.kpi.draft')) ?></span>
      <span class="kpi-card__value"><?= (int)($summaryCounts['draft'] ?? 0) ?></span>
    </a>
    <a href="<?= htmlspecialchars($buildFilterUrl(['status' => 'published', 'target_role' => $roleFilter !== 'all' ? $roleFilter : null, 'target_surface' => $surfaceFilter !== 'all' ? $surfaceFilter : null, 'q' => $searchQ !== '' ? $searchQ : null])) ?>" class="kpi-card<?= $statusFilter === 'published' ? ' kpi-card--active' : '' ?>">
      <span class="kpi-card__label"><?= htmlspecialchars(t('ops.design_studio.kpi.published')) ?></span>
      <span class="kpi-card__value"><?= (int)($summaryCounts['published'] ?? 0) ?></span>
    </a>
    <a href="<?= htmlspecialchars($buildFilterUrl(['status' => 'archived', 'target_role' => $roleFilter !== 'all' ? $roleFilter : null, 'target_surface' => $surfaceFilter !== 'all' ? $surfaceFilter : null, 'q' => $searchQ !== '' ? $searchQ : null])) ?>" class="kpi-card<?= $statusFilter === 'archived' ? ' kpi-card--active' : '' ?>">
      <span class="kpi-card__label"><?= htmlspecialchars(t('ops.design_studio.kpi.archived')) ?></span>
      <span class="kpi-card__value"><?= (int)($summaryCounts['archived'] ?? 0) ?></span>
    </a>
  </div>

  <!-- Filter bar -->
  <form method="GET" action="/ops/design-studio" class="filter-bar" id="dsFilterForm">
    <div class="filter-bar__fields">
      <select name="status" class="filter-bar__select" onchange="document.getElementById('dsFilterForm').submit()">
        <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>><?= htmlspecialchars(t('ops.design_studio.status.all')) ?></option>
        <option value="draft"<?= $statusFilter === 'draft' ? ' selected' : '' ?>><?= htmlspecialchars(t('ops.design_studio.status.draft')) ?></option>
        <option value="published"<?= $statusFilter === 'published' ? ' selected' : '' ?>><?= htmlspecialchars(t('ops.design_studio.status.published')) ?></option>
        <option value="archived"<?= $statusFilter === 'archived' ? ' selected' : '' ?>><?= htmlspecialchars(t('ops.design_studio.status.archived')) ?></option>
      </select>
      <select name="target_role" class="filter-bar__select" onchange="document.getElementById('dsFilterForm').submit()">
        <option value="all"<?= $roleFilter === 'all' ? ' selected' : '' ?>><?= htmlspecialchars(t('ops.design_studio.filter.all_roles')) ?></option>
        <?php foreach ($roles as $r): ?>
          <option value="<?= htmlspecialchars((string)($r['role_key'] ?? '')) ?>"<?= $roleFilter === ($r['role_key'] ?? '') ? ' selected' : '' ?>><?= htmlspecialchars(t((string)($r['label_key'] ?? ''))) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="target_surface" class="filter-bar__select" onchange="document.getElementById('dsFilterForm').submit()">
        <option value="all"<?= $surfaceFilter === 'all' ? ' selected' : '' ?>><?= htmlspecialchars(t('ops.design_studio.filter.all_surfaces')) ?></option>
        <?php foreach ($surfaces as $s): ?>
          <option value="<?= htmlspecialchars((string)($s['surface_key'] ?? '')) ?>"<?= $surfaceFilter === ($s['surface_key'] ?? '') ? ' selected' : '' ?>><?= htmlspecialchars(t((string)($s['label_key'] ?? ''))) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="q" class="filter-bar__input" placeholder="<?= htmlspecialchars(t('ops.design_studio.filter.search_placeholder')) ?>" value="<?= htmlspecialchars($searchQ) ?>">
      <button type="submit" class="filter-bar__btn"><?= htmlspecialchars(t('ops.design_studio.filter.apply')) ?></button>
      <a href="/ops/design-studio" class="filter-bar__reset"><?= htmlspecialchars(t('ops.design_studio.filter.reset')) ?></a>
    </div>
  </form>

  <!-- Active filter chips -->
  <?php if ($activeFilterChips !== []): ?>
  <div class="filter-chips" aria-label="<?= htmlspecialchars(t('common.filter')) ?>">
    <?php foreach ($activeFilterChips as $chip): ?>
      <a href="<?= htmlspecialchars($chip['url']) ?>" class="filter-chip">
        <span class="filter-chip__label"><?= htmlspecialchars($chip['label']) ?>:</span>
        <span class="filter-chip__value"><?= htmlspecialchars($chip['value']) ?></span>
        <span class="filter-chip__remove" aria-hidden="true">&times;</span>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Compositions table -->
  <div class="data-table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th><?= htmlspecialchars(t('ops.design_studio.col.name')) ?></th>
          <th><?= htmlspecialchars(t('ops.design_studio.col.target_role')) ?></th>
          <th><?= htmlspecialchars(t('ops.design_studio.col.target_surface')) ?></th>
          <th><?= htmlspecialchars(t('ops.design_studio.col.status')) ?></th>
          <th><?= htmlspecialchars(t('ops.design_studio.col.updated')) ?></th>
          <th><?= htmlspecialchars(t('ops.design_studio.col.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows === []): ?>
          <tr>
            <td colspan="6" class="data-table__empty"><?= htmlspecialchars(t('ops.design_studio.empty')) ?></td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php
            $compositionId     = (int)($row['id'] ?? 0);
            $compositionName   = trim((string)($row['name'] ?? ''));
            $compositionDesc   = trim((string)($row['description'] ?? ''));
            $compositionRole   = trim((string)($row['target_role'] ?? ''));
            $compositionSurface = trim((string)($row['target_surface'] ?? ''));
            $compositionStatus = trim((string)($row['status'] ?? 'draft'));
            $compositionUpdated = trim((string)($row['updated_at'] ?? ''));
            $statusBadgeClass  = match($compositionStatus) {
                'published' => 'badge--green',
                'archived'  => 'badge--gray',
                default     => 'badge--yellow',
            };
            ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($compositionName) ?></strong>
                <?php if ($compositionDesc !== ''): ?>
                  <div class="data-table__meta"><?= htmlspecialchars(mb_strimwidth($compositionDesc, 0, 80, '…')) ?></div>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($roleLabelMap[$compositionRole] ?? $compositionRole) ?></td>
              <td><?= htmlspecialchars($surfaceLabelMap[$compositionSurface] ?? $compositionSurface) ?></td>
              <td><span class="badge <?= $statusBadgeClass ?>"><?= htmlspecialchars(t('ops.design_studio.status.' . $compositionStatus)) ?></span></td>
              <td><?= htmlspecialchars($compositionUpdated !== '' ? substr($compositionUpdated, 0, 10) : '—') ?></td>
              <td>
                <span class="data-table__actions">
                  <a href="/ops/design-studio/edit?id=<?= $compositionId ?>&amp;redirect_to=<?= urlencode($redirectTo) ?>" class="btn btn--xs"><?= htmlspecialchars(t('ops.design_studio.action.edit')) ?></a>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <nav class="pagination" aria-label="<?= htmlspecialchars(t('common.next')) ?>">
    <?php
    $baseParams = array_filter([
        'status'         => $statusFilter !== 'all' ? $statusFilter : null,
        'target_role'    => $roleFilter !== 'all' ? $roleFilter : null,
        'target_surface' => $surfaceFilter !== 'all' ? $surfaceFilter : null,
        'q'              => $searchQ !== '' ? $searchQ : null,
        'per_page'       => $perPage !== 20 ? $perPage : null,
    ], static fn($v) => $v !== null);
    ?>
    <?php if ($page > 1): ?>
      <a href="/ops/design-studio?<?= htmlspecialchars(http_build_query($baseParams + ['page' => $page - 1])) ?>" class="pagination__prev"><?= htmlspecialchars(t('common.previous')) ?></a>
    <?php endif; ?>
    <span class="pagination__info"><?= $page ?> / <?= $totalPages ?></span>
    <?php if ($page < $totalPages): ?>
      <a href="/ops/design-studio?<?= htmlspecialchars(http_build_query($baseParams + ['page' => $page + 1])) ?>" class="pagination__next"><?= htmlspecialchars(t('common.next')) ?></a>
    <?php endif; ?>
  </nav>
  <?php endif; ?>

  <!-- Create composition form -->
  <section class="form-card u-style-6e58ee35af">
    <h2 class="form-card__title"><?= htmlspecialchars(t('ops.design_studio.create.title')) ?></h2>
    <form method="POST" action="/ops/design-studio/save">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($redirectTo) ?>">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label" for="ds_name"><?= htmlspecialchars(t('ops.design_studio.create.name_label')) ?> *</label>
          <input type="text" id="ds_name" name="name" class="form-input" required maxlength="190" placeholder="<?= htmlspecialchars(t('ops.design_studio.create.name_placeholder')) ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="ds_description"><?= htmlspecialchars(t('ops.design_studio.create.description_label')) ?></label>
          <input type="text" id="ds_description" name="description" class="form-input" maxlength="500" placeholder="<?= htmlspecialchars(t('ops.design_studio.create.description_placeholder')) ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="ds_target_role"><?= htmlspecialchars(t('ops.design_studio.create.target_role_label')) ?> *</label>
          <select id="ds_target_role" name="target_role" class="form-select" required>
            <option value=""><?= htmlspecialchars(t('ops.design_studio.create.select_role')) ?></option>
            <?php foreach ($roles as $r): ?>
              <option value="<?= htmlspecialchars((string)($r['role_key'] ?? '')) ?>"><?= htmlspecialchars(t((string)($r['label_key'] ?? ''))) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="ds_target_surface"><?= htmlspecialchars(t('ops.design_studio.create.target_surface_label')) ?> *</label>
          <select id="ds_target_surface" name="target_surface" class="form-select" required>
            <option value=""><?= htmlspecialchars(t('ops.design_studio.create.select_surface')) ?></option>
            <?php foreach ($surfaces as $s): ?>
              <option value="<?= htmlspecialchars((string)($s['surface_key'] ?? '')) ?>"><?= htmlspecialchars(t((string)($s['label_key'] ?? ''))) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-actions u-style-0cf8f93b0e">
        <button type="submit" class="btn btn--primary"><?= htmlspecialchars(t('ops.design_studio.create.save_btn')) ?></button>
      </div>
    </form>
  </section>

</div>
