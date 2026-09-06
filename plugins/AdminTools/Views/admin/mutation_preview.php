<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="u-style-e2ff0fd1a7">
      <a class="u-style-e3220afdb1" href="/admin/system-tools/data-control">
        <?= e(t('system_tools.data_control.title')) ?>
      </a>
      <span class="u-style-4cb80da29d">›</span>
      <h2 class="u-style-1169661891"><?= e(t('system_tools.data_control.mutation_preview_title')) ?></h2>
    </div>
    <div class="muted"><?= e(t('system_tools.data_control.mutation_preview_subtitle')) ?></div>
  </div>
</div>

<?php if (!empty($error)): ?>
  <div class="card u-style-a582d0365d">
    <div class="u-style-58b279bf09"><?= e(t('common.error')) ?></div>
    <div class="u-style-60377be37b"><?= e($error) ?></div>
  </div>
<?php endif; ?>

<?php if (!empty($tableName) && empty($error)): ?>
  <div class="card">
    <h3 class="u-style-d462248a40"><?= e(t('system_tools.data_control.mutation_impact')) ?></h3>
    
    <div class="u-style-1a253803f2">
      <div class="u-style-3ebbd9b203">
        <div class="u-style-588b2c38c4"><?= e(t('system_tools.data_control.operation_type')) ?></div>
        <div class="u-style-6f0a43b159">
          <?php
            if ($operationType === 'delete') {
              echo '<span class="u-style-80306035bc">🗑 ' . e(t('system_tools.data_control.operation_delete')) . '</span>';
            } elseif ($operationType === 'update') {
              echo '<span class="u-style-c2cea5fa89">✎ ' . e(t('system_tools.data_control.operation_update')) . '</span>';
            }
          ?>
        </div>
      </div>

      <div class="u-style-3ebbd9b203">
        <div class="u-style-588b2c38c4"><?= e(t('system_tools.data_control.affected_rows_count')) ?></div>
        <div class="u-style-6f0a43b159"><?= number_format($affectedRows) ?></div>
      </div>
    </div>

    <?php if (!empty($whereClause)): ?>
      <div class="u-style-22571d5b84">
        <div class="u-style-e59086c1f4"><?= e(t('system_tools.data_control.filter_condition')) ?></div>
        <code class="u-style-8259f5a346"><?= e($whereClause) ?></code>
      </div>
    <?php else: ?>
      <div class="u-style-aa5ceb185f">
        <strong class="u-style-0e51d7258f">⚠ <?= e(t('system_tools.data_control.no_filter_warning')) ?></strong>
      </div>
    <?php endif; ?>
    <?php if ($operationType === 'update' && !empty($setClause ?? '')): ?>
      <div class="u-style-07cbb6468a">
        <div class="u-style-e59086c1f4"><?= e(t('system_tools.data_control.set_clause_label')) ?></div>
        <code class="u-style-8259f5a346"><?= e($setClause) ?></code>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!empty($beforeSample)): ?>
    <div class="card">
      <h3 class="u-style-d462248a40"><?= e(t('system_tools.data_control.affected_rows_preview')) ?></h3>
      <div class="u-style-f8c534c1dc">
        <?= e(t('system_tools.data_control.showing_first_n_rows', ['n' => min(5, count($beforeSample))])) ?>
      </div>

      <div class="u-style-0082b38792">
        <table class="table u-style-e87d7b0620">
          <thead>
            <tr>
              <?php foreach ($columns as $col): ?>
                <th>
                  <div class="u-style-2a81bb268b">
                    <?= e((string)($col['column_name'] ?? '')) ?>
                  </div>
                  <div class="u-style-99b918fcd9">
                    <?= e((string)($col['column_type'] ?? '')) ?>
                  </div>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($beforeSample as $row): ?>
              <tr>
                <?php foreach ($columns as $col): ?>
                  <td class="u-style-75ae96f024">
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
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="u-style-c310195ffe">
      <strong><?= e(t('system_tools.data_control.mutation_disclaimer')) ?></strong>
      <p class="u-style-b243bebe5e">
        <?= e(t('system_tools.data_control.no_data_modified_yet')) ?>
      </p>
    </div>
  </div>

  <?php if (!empty($canEdit) && $affectedRows > 0): ?>
    <?php if ($operationType === 'update' && empty($setClause ?? '')): ?>
      <div class="card u-style-e263eea21a">
        <h3 class="u-style-072edc3693"><?= e(t('system_tools.data_control.update_set_required_title')) ?></h3>
        <p class="u-style-b2f262ccb5"><?= e(t('system_tools.data_control.update_set_required_hint')) ?></p>
        <form class="u-style-9c9cee2093" method="GET" action="/admin/system-tools/data-control/preview-mutation">
          <input type="hidden" name="table" value="<?= e($tableName) ?>">
          <input type="hidden" name="op" value="update">
          <input type="hidden" name="where" value="<?= e($whereClause) ?>">
          <div class="u-style-72d6c38e5f">
            <label class="u-style-d1b9789ffc">
              <?= e(t('system_tools.data_control.set_clause_label')) ?>
            </label>
            <input class="input" type="text" name="set" placeholder="<?= e(t('system_tools.data_control.set_clause_placeholder')) ?>"
              style="width: 100%; padding: 8px; border: 1px solid var(--style-border-soft); border-radius: 4px; box-sizing: border-box; font-family: monospace;">
          </div>
          <button class="u-style-ecfb17622f" type="submit">
            <?= e(t('system_tools.data_control.action_preview_update')) ?>
          </button>
        </form>
      </div>
    <?php else: ?>
    <div class="card">
      <h3 class="u-style-d462248a40"><?= e(t('system_tools.data_control.submit_for_approval_title')) ?></h3>
      <p class="u-style-b2f262ccb5"><?= e(t('system_tools.data_control.submit_for_approval_hint')) ?></p>
      <form method="POST" action="/admin/system-tools/data-control/submit-approval">
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <input type="hidden" name="table" value="<?= e($tableName) ?>">
        <input type="hidden" name="op" value="<?= e($operationType) ?>">
        <input type="hidden" name="where" value="<?= e($whereClause) ?>">
        <input type="hidden" name="set_clause" value="<?= e($setClause ?? '') ?>">
        <input type="hidden" name="affected_rows" value="<?= (int)$affectedRows ?>">
        <button class="u-style-47fce3d8a4" type="submit">
          <?= e(t('system_tools.data_control.action_submit_approval')) ?>
        </button>
      </form>
    </div>
    <?php endif; ?>
  <?php endif; ?>
<?php elseif (empty($tableName)): ?>
  <div class="card">
    <div class="muted"><?= e(t('system_tools.data_control.select_table_to_preview')) ?></div>
  </div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
