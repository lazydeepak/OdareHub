<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="u-style-e2ff0fd1a7">
      <a class="u-style-e3220afdb1" href="/admin/system-tools/data-control">
        <?= e(t('system_tools.data_control.title')) ?>
      </a>
      <span class="u-style-4cb80da29d">›</span>
      <h2 class="u-style-1169661891"><?= e((string)($tableName ?? 'N/A')) ?></h2>
    </div>
    <div class="muted"><?= e(t('system_tools.data_control.row_browser_subtitle')) ?></div>
  </div>
</div>

<?php if (!empty($tableName)): ?>
<div class="card">
  <form class="u-style-9bf647d04d" method="GET" action="/admin/system-tools/data-control/browse">
    <input type="hidden" name="table" value="<?= e($tableName) ?>">
    <input type="hidden" name="limit" value="<?= (int)$pageSize ?>">
    <div class="u-style-4a7c913411">
      <label class="u-style-b64766877a"><?= e(t('system_tools.data_control.where_filter_label')) ?></label>
      <input class="input" type="text" name="where" value="<?= e($whereFilter) ?>" placeholder="<?= e(t('system_tools.data_control.where_filter_placeholder')) ?>" style="width: 100%; padding: 6px 8px; border: 1px solid var(--style-border-soft); border-radius: 4px; box-sizing: border-box;">
    </div>
    <div class="ui-block">
      <button class="u-style-8844e7cfd8" type="submit"><?= e(t('common.filter')) ?></button>
      <?php if (!empty($whereFilter)): ?>
        <a href="?table=<?= urlencode($tableName) ?>&limit=<?= (int)$pageSize ?>" style="padding: 7px 10px; color: var(--text); text-decoration: none; display: inline-block;"><?= e(t('common.clear')) ?></a>
      <?php endif; ?>
    </div>
  </form>
  <?php if (!empty($whereError)): ?>
    <div class="u-style-f15708d952"><?= e($whereError) ?></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($error)): ?>
  <div class="card u-style-a582d0365d">
    <div class="u-style-58b279bf09"><?= e(t('common.error')) ?></div>
    <div class="u-style-60377be37b"><?= e($error) ?></div>
  </div>
<?php endif; ?>

<?php if (!empty($tableName) && empty($error)): ?>
  <div class="card">
    <div class="u-style-294406cdda">
      <div class="ui-block">
        <strong><?= e(t('system_tools.data_control.row_count')) ?></strong>: <?= number_format($totalRows) ?>
        <?php if (!empty($whereFilter)): ?>
          <span class="u-style-82f1b44512"><?= e(t('system_tools.data_control.filtered')) ?></span>
        <?php endif; ?>
        <?php if ($totalPages > 1): ?>
          | <strong><?= e(t('system_tools.data_control.page')) ?></strong>: <?= $page ?> / <?= $totalPages ?>
        <?php endif; ?>
      </div>
      <div class="u-style-8d9785d68f">
        <?= e(t('system_tools.data_control.page_size')) ?>: <?= $pageSize ?> 
        <?php if ($totalRows > $pageSize): ?>
          (<a href="?table=<?= urlencode($tableName) ?>&page=1&limit=25"><?= e(t('system_tools.data_control.reset')) ?></a>)
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($columns) && !empty($rows)): ?>
      <div class="u-style-0082b38792">
        <table class="table">
          <thead>
            <tr>
              <?php foreach ($columns as $col): ?>
                <th>
                  <div class="u-style-20b55dc9b9">
                    <?= e((string)($col['column_name'] ?? '')) ?>
                  </div>
                  <div class="u-style-bbe8d082fc">
                    <?= e((string)($col['column_type'] ?? '')) ?>
                  </div>
                </th>
              <?php endforeach; ?>
              <?php if (!empty($canEdit)): ?>
                <th><?= e(t('common.actions')) ?></th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <?php foreach ($columns as $col): ?>
                  <td class="u-style-1e9db32416">
                    <?php
                      $cellValue = $row[(string)($col['column_name'] ?? '')] ?? null;
                      if ($cellValue === null) {
                        echo '<span class="u-style-ad725937d4">NULL</span>';
                      } else {
                        echo e((string)$cellValue);
                      }
                    ?>
                  </td>
                <?php endforeach; ?>
                <?php if (!empty($canEdit)): ?>
                  <td>
                    <?php
                      $pkVal = !empty($pkColumn) ? ($row[$pkColumn] ?? null) : null;
                      if ($pkVal !== null && !empty($pkColumn)) {
                        $whereStr = $pkColumn . '=' . (int)$pkVal;
                        $previewUrl = '/admin/system-tools/data-control/preview-mutation?table=' . urlencode($tableName) . '&op=delete&where=' . urlencode($whereStr);
                        echo '<a class="action-link u-style-80306035bc" href="' . $previewUrl . '">' . e(t('system_tools.data_control.action_preview_delete')) . '</a>';
                      } else {
                        echo '<span class="u-style-e0fe55d810">—</span>';
                      }
                    ?>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
        <?php $whereQS = !empty($whereFilter) ? '&where=' . urlencode($whereFilter) : ''; ?>
        <div class="u-style-3ce2a04f16">
          <?php if ($page > 1): ?>
            <a href="?table=<?= urlencode($tableName) ?>&page=1&limit=<?= $pageSize ?><?= $whereQS ?>" class="btn btn-sm">
              <?= e(t('system_tools.data_control.first_page')) ?>
            </a>
            <a href="?table=<?= urlencode($tableName) ?>&page=<?= $page - 1 ?>&limit=<?= $pageSize ?><?= $whereQS ?>" class="btn btn-sm">
              <?= e(t('common.previous')) ?>
            </a>
          <?php endif; ?>

          <div class="u-style-d2f87c7fad">
            <?php
              $startPage = max(1, $page - 2);
              $endPage = min($totalPages, $page + 2);
              if ($startPage > 1) {
                echo '<span class="u-style-d54ea954e0">...</span>';
              }
              for ($p = $startPage; $p <= $endPage; $p++) {
                $isCurrentPage = ($p === $page);
                $style = $isCurrentPage ? 'font-weight: bold; background: var(--style-subtle-bg); padding: 4px 8px; border-radius: 2px;' : 'padding: 4px 8px;';
                echo '<a href="?table=' . urlencode($tableName) . '&page=' . $p . '&limit=' . $pageSize . $whereQS . '" style="' . $style . '">';
                echo $p;
                echo '</a>';
              }
              if ($endPage < $totalPages) {
                echo '<span class="u-style-d54ea954e0">...</span>';
              }
            ?>
          </div>

          <?php if ($page < $totalPages): ?>
            <a href="?table=<?= urlencode($tableName) ?>&page=<?= $page + 1 ?>&limit=<?= $pageSize ?><?= $whereQS ?>" class="btn btn-sm">
              <?= e(t('common.next')) ?>
            </a>
            <a href="?table=<?= urlencode($tableName) ?>&page=<?= $totalPages ?>&limit=<?= $pageSize ?><?= $whereQS ?>" class="btn btn-sm">
              <?= e(t('system_tools.data_control.last_page')) ?>
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php elseif (empty($rows) && empty($error)): ?>
      <div class="muted"><?= e(t('system_tools.data_control.no_rows')) ?></div>
    <?php endif; ?>
  </div>
<?php elseif (empty($tableName)): ?>
  <div class="card">
    <div class="muted"><?= e(t('system_tools.data_control.select_table_to_browse')) ?></div>
  </div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
