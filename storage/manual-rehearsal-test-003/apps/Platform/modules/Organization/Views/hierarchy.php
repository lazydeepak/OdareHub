<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$companies          = is_array($companies ?? null) ? $companies : [];
$tree               = is_array($tree ?? null) ? $tree : [];
$flat               = is_array($flat ?? null) ? $flat : [];
$editing            = is_array($editing ?? null) ? $editing : [];
$selectedCompanyId  = (int)($selectedCompanyId ?? 0);
$canManage          = (bool)($canManage ?? false);

require __DIR__ . '/partials/nav.php';
require __DIR__ . '/partials/feedback.php';

/** @param array<mixed> $nodes */
function renderHierarchyTree(array $nodes, bool $canManage, int $companyId): void
{
    if ($nodes === []) {
        return;
    }
    echo '<ul class="hierarchy-tree">';
    foreach ($nodes as $node) {
        $nodeId   = (int)($node['id'] ?? 0);
        $code     = (string)($node['hierarchy_code'] ?? '');
        $name     = (string)($node['hierarchy_name'] ?? '');
        $type     = (string)($node['hierarchy_type'] ?? '');
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        echo '<li class="hierarchy-node">';
        echo '<div class="hierarchy-node-row">';
        echo '<span class="hierarchy-code">' . e($code) . '</span>';
        echo '<span class="hierarchy-name">' . e($name) . '</span>';
        echo '<span class="badge badge-neutral hierarchy-type">' . e($type) . '</span>';
        if ($canManage) {
            echo '<span class="hierarchy-actions">';
            echo '<a class="btn btn-xs" href="/ops/organization/hierarchy?company_id=' . $companyId . '&edit=' . $nodeId . '">' . e(t('common.edit')) . '</a> ';
            echo '<form class="u-style-cccfa4560d" method="post" action="/ops/organization/hierarchy/delete" onsubmit="return confirm(\'' . e(t('common.confirm_delete')) . '\')">';
            echo '<input type="hidden" name="csrf" value="' . e(\App\Core\Auth::csrfToken()) . '">';
            echo '<input type="hidden" name="hierarchy_id" value="' . $nodeId . '">';
            echo '<input type="hidden" name="company_id" value="' . $companyId . '">';
            echo '<button type="submit" class="btn btn-xs btn-danger">' . e(t('common.delete')) . '</button>';
            echo '</form>';
            echo '</span>';
        }
        echo '</div>';
        renderHierarchyTree($children, $canManage, $companyId);
        echo '</li>';
    }
    echo '</ul>';
}
?>

<?php if ($companies === []): ?>
  <div class="card notice-err"><?= e(t('organization.notice.create_company_before_branches')) ?></div>
<?php else: ?>

  <!-- Company selector -->
  <section class="card">
    <form class="u-style-97ded659e4" method="get" action="/ops/organization/hierarchy">
      <label class="u-style-26db0b2108">
        <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.company')) ?></div>
        <select name="company_id" onchange="this.form.submit()">
          <?php foreach ($companies as $companyRow): ?>
            <option value="<?= e((string)($companyRow['id'] ?? 0)) ?>"
              <?= (int)($companyRow['id'] ?? 0) === $selectedCompanyId ? 'selected' : '' ?>>
              <?= e((string)($companyRow['company_name'] ?? '')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
    </form>
  </section>

  <div class="u-style-52699a4f43">

    <!-- Tree -->
    <section class="card u-style-2cdbef81c7">
      <div class="module-header">
        <div class="module-header-info">
          <h3 class="u-style-1169661891"><?= e(t('organization.hierarchy.title')) ?></h3>
          <div class="muted"><?= e(t('organization.hierarchy.description')) ?></div>
        </div>
        <?php if ($canManage): ?>
          <a class="btn" href="/ops/organization/hierarchy?company_id=<?= e((string)$selectedCompanyId) ?>&add=1"><?= e(t('organization.hierarchy.add')) ?></a>
        <?php endif; ?>
      </div>
      <?php if ($tree === []): ?>
        <div class="muted u-style-56f4356299"><?= e(t('organization.hierarchy.empty')) ?></div>
      <?php else: ?>
        <div class="u-style-56f4356299">
          <?php renderHierarchyTree($tree, $canManage, $selectedCompanyId); ?>
        </div>
      <?php endif; ?>
    </section>

    <!-- Add / Edit form -->
    <?php if ($canManage && ($editing !== [] || isset($_GET['add']))): ?>
    <section class="card u-style-4156737b8c">
      <h3 class="u-style-7d0b01be6f">
        <?= $editing !== [] ? e(t('organization.hierarchy.edit')) : e(t('organization.hierarchy.add')) ?>
      </h3>
      <form method="post" action="/ops/organization/hierarchy/save">
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <input type="hidden" name="company_id" value="<?= e((string)$selectedCompanyId) ?>">
        <?php if ($editing !== []): ?>
          <input type="hidden" name="id" value="<?= e((string)($editing['id'] ?? 0)) ?>">
        <?php endif; ?>

        <label class="u-style-668a9d3d32">
          <div class="muted u-style-c81ce4b250"><?= e(t('organization.hierarchy.code')) ?> *</div>
          <input class="input" type="text" name="hierarchy_code" value="<?= e((string)($editing['hierarchy_code'] ?? '')) ?>" required>
        </label>

        <label class="u-style-668a9d3d32">
          <div class="muted u-style-c81ce4b250"><?= e(t('organization.hierarchy.name')) ?> *</div>
          <input class="input" type="text" name="hierarchy_name" value="<?= e((string)($editing['hierarchy_name'] ?? '')) ?>" required>
        </label>

        <label class="u-style-668a9d3d32">
          <div class="muted u-style-c81ce4b250"><?= e(t('organization.hierarchy.type')) ?></div>
          <select name="hierarchy_type">
            <?php foreach (['department', 'region', 'costcenter'] as $typeOption): ?>
              <option value="<?= e($typeOption) ?>"
                <?= ($editing['hierarchy_type'] ?? 'department') === $typeOption ? 'selected' : '' ?>>
                <?= e(t('organization.hierarchy.type.' . $typeOption)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="u-style-668a9d3d32">
          <div class="muted u-style-c81ce4b250"><?= e(t('organization.hierarchy.parent')) ?></div>
          <select name="parent_id">
            <option value=""><?= e(t('organization.hierarchy.root_label')) ?></option>
            <?php foreach ($flat as $flatNode): ?>
              <?php
              $flatId     = (int)($flatNode['id'] ?? 0);
              $flatLevel  = (int)($flatNode['level'] ?? 0);
              $flatName   = (string)($flatNode['hierarchy_name'] ?? '');
              $flatCode   = (string)($flatNode['hierarchy_code'] ?? '');
              $editingId  = (int)($editing['id'] ?? 0);
              if ($editingId > 0 && $flatId === $editingId) {
                  continue; // cannot be own parent
              }
              $indent  = str_repeat('&nbsp;&nbsp;&nbsp;', $flatLevel);
              $isSelected = (int)($editing['parent_id'] ?? 0) === $flatId;
              ?>
              <option value="<?= e((string)$flatId) ?>" <?= $isSelected ? 'selected' : '' ?>>
                <?= $indent ?><?= e($flatCode . ' — ' . $flatName) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="u-style-3b32668616">
          <div class="muted u-style-c81ce4b250"><?= e(t('organization.hierarchy.sort_order')) ?></div>
          <input class="input" type="number" name="sort_order" value="<?= e((string)($editing['sort_order'] ?? 0)) ?>" min="0">
        </label>

        <div class="u-style-a76d597a07">
          <button type="submit" class="btn btn-primary"><?= e(t('common.save')) ?></button>
          <a class="btn" href="/ops/organization/hierarchy?company_id=<?= e((string)$selectedCompanyId) ?>"><?= e(t('common.cancel')) ?></a>
        </div>
      </form>
    </section>
    <?php endif; ?>

  </div>

<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
