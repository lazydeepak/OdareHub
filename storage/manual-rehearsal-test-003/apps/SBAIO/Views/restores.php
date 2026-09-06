<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$suite = is_array($suite ?? null) ? $suite : [];
$modules = is_array($modules ?? null) ? $modules : [];
$history = is_array($history ?? null) ? $history : [];
$csrf = (string)($csrf ?? '');
$previewPayload = is_array($preview ?? null) ? $preview : [];
$previewData = is_array($previewPayload['preview'] ?? null) ? $previewPayload['preview'] : [];
$scopes = array_values(array_map('strval', (array)($previewData['available_restore_scopes'] ?? [])));
$selectedScope = in_array('full', $scopes, true) ? 'full' : ($scopes[0] ?? 'package_only');
$targetLabel = strtoupper((string)($previewData['target_type'] ?? 'suite')) . ': ' . (string)($previewData['target_key'] ?? 'sbaio');
?>

<div class="card">
  <div class="u-style-8a84800a49">
    <div class="ui-block">
      <h2 class="u-style-ad7f18b19e"><?= e(t('sbaio.restores.title')) ?></h2>
      <div class="muted"><?= e(t('sbaio.restores.description')) ?></div>
    </div>
    <div class="u-style-3de8f987ba">
      <a class="btn" href="/apps/sbaio"><?= e(t('sbaio.common.back_to_sbaio')) ?></a>
      <a class="btn" href="/apps/sbaio/exports"><?= e(t('sbaio.dashboard.exports')) ?></a>
      <a class="btn" href="/apps/sbaio/imports"><?= e(t('sbaio.dashboard.legacy_imports')) ?></a>
    </div>
  </div>
</div>

<?php if (($flash_ok ?? '') !== ''): ?>
  <div class="card u-style-e640db1c6b"><?= e((string)$flash_ok) ?></div>
<?php endif; ?>
<?php if (($flash_err ?? '') !== ''): ?>
  <div class="card u-style-c2fa10bd43"><?= e((string)$flash_err) ?></div>
<?php endif; ?>

<div class="card">
  <h3 class="u-style-df671843ff"><?= e(t('sbaio.restores.targets')) ?></h3>
  <div class="muted u-style-761d3addb2"><?= e(t('sbaio.restores.targets_desc')) ?></div>
  <div class="u-style-fe682a1428">
    <div class="card u-style-1169661891">
      <strong><?= e(t('sbaio.restores.whole_suite')) ?></strong>
      <div class="muted u-style-fe7b4979fe"><?= e(t('sbaio.restores.whole_suite_desc', ['suite' => (string)($suite['label'] ?? 'SBAIO')])) ?></div>
    </div>
    <div class="card u-style-1169661891">
      <strong><?= e(t('sbaio.restores.module_restore')) ?></strong>
      <div class="muted u-style-fe7b4979fe"><?= e(t('sbaio.restores.module_restore_desc')) ?></div>
    </div>
    <div class="card u-style-1169661891">
      <strong><?= e(t('sbaio.restores.data_import_back')) ?></strong>
      <div class="muted u-style-fe7b4979fe"><?= e(t('sbaio.restores.data_import_back_desc')) ?></div>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-df671843ff"><?= e(t('sbaio.restores.preview_restore')) ?></h3>
  <div class="muted u-style-761d3addb2"><?= e(t('sbaio.restores.preview_restore_desc')) ?></div>
  <form class="u-style-97ded659e4" method="post" action="/apps/sbaio/restores/preview" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <label>
      <div class="muted u-style-4e420aff3f"><?= e(t('sbaio.restores.backup_file')) ?></div>
      <input class="input" type="file" name="backup_file" accept=".zip,.json" required>
    </label>
    <button class="btn ok" type="submit"><?= e(t('sbaio.restores.preview_action')) ?></button>
  </form>
</div>

<?php if ($previewData !== []): ?>
  <div class="card">
    <div class="u-style-8a84800a49">
      <div class="ui-block">
        <h3 class="u-style-df671843ff"><?= e(t('sbaio.restores.verification')) ?></h3>
        <div class="muted"><?= e(t('sbaio.restores.verification_desc')) ?></div>
      </div>
      <span class="pill"><?= e($targetLabel) ?></span>
    </div>

    <div class="u-style-560b8d4988">
      <div class="card u-style-1169661891">
        <div class="muted">Backup Type</div>
        <strong><?= e((string)($previewData['backup_type'] ?? 'unknown')) ?></strong>
      </div>
      <div class="card u-style-1169661891">
        <div class="muted">Schema Compatibility</div>
        <strong><?= e((string)($previewData['schema_compatibility'] ?? 'unknown')) ?></strong>
      </div>
      <div class="card u-style-1169661891">
        <div class="muted">Warnings / Conflicts</div>
        <strong><?= (int)($previewData['conflict_summary']['warning_count'] ?? 0) ?> / <?= (int)($previewData['conflict_summary']['conflict_count'] ?? 0) ?></strong>
      </div>
      <div class="card u-style-1169661891">
        <div class="muted">Duplicate Risk Rows</div>
        <strong><?= (int)($previewData['duplicate_risk_rows'] ?? 0) ?></strong>
      </div>
    </div>

    <div class="u-style-45d892dee5">
      <div class="card u-style-1169661891">
        <h4 class="u-style-df671843ff">Restore Identity</h4>
        <div class="muted">Source file: <?= e((string)($previewData['source_backup'] ?? '')) ?></div>
        <div class="muted">Live status: <?= e((string)($previewData['live_state']['status'] ?? 'not_installed')) ?></div>
        <div class="muted">Live version: <?= e((string)($previewData['live_state']['version'] ?? 'n/a')) ?></div>
        <div class="muted">Package payload: <?= !empty($previewData['has_package']) ? 'yes' : 'no' ?></div>
        <div class="muted">Data payload: <?= !empty($previewData['has_data']) ? 'yes' : 'no' ?></div>
      </div>
      <div class="card u-style-1169661891">
        <h4 class="u-style-df671843ff">Conflict Summary</h4>
        <?php if (!empty($previewData['conflicts'])): ?>
          <?php foreach ((array)$previewData['conflicts'] as $conflict): ?>
            <div class="muted u-style-4e420aff3f">• <?= e((string)$conflict) ?></div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="muted">No blocking conflicts detected in the preview.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="u-style-45d892dee5">
      <div class="card u-style-1169661891">
        <h4 class="u-style-df671843ff">Package / Version Info</h4>
        <?php foreach ((array)($previewData['package_info'] ?? []) as $pkg): ?>
          <div class="u-style-fdf33f2304">
            <strong><?= e((string)($pkg['name'] ?? 'package')) ?></strong>
            <div class="muted">Version: <?= e((string)($pkg['version'] ?? 'n/a')) ?></div>
            <div class="muted">Type: <?= e((string)($pkg['type'] ?? 'package')) ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (empty($previewData['package_info'])): ?>
          <div class="muted">No package archive found in this backup.</div>
        <?php endif; ?>
      </div>
      <div class="card u-style-1169661891">
        <h4 class="u-style-df671843ff">Schema / Dependencies</h4>
        <div class="muted">Missing tables: <?= e(implode(', ', array_map('strval', (array)($previewData['schema']['missing_tables'] ?? [])))) ?: 'none' ?></div>
        <div class="muted u-style-fe7b4979fe">Missing dependencies: <?= e(implode(', ', array_map('strval', (array)($previewData['missing_dependencies'] ?? [])))) ?: 'none' ?></div>
        <div class="muted u-style-fe7b4979fe">Version mismatches: <?= (int)($previewData['conflict_summary']['version_conflict_count'] ?? 0) ?></div>
      </div>
      <div class="card u-style-1169661891">
        <h4 class="u-style-df671843ff">Overwrite / Merge Risk</h4>
        <?php if (!empty($previewData['overwrite_risk'])): ?>
          <?php foreach ((array)$previewData['overwrite_risk'] as $risk): ?>
            <div class="muted u-style-4e420aff3f">• <?= e((string)$risk) ?></div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="muted">No live rows detected in the restore tables.</div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($scopes !== []): ?>
      <form class="u-style-56f4356299" method="post" action="/apps/sbaio/restores/commit">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="preview_token" value="<?= e((string)($previewPayload['preview_token'] ?? '')) ?>">
        <div class="u-style-97d35f2be1">
          <label>
            <div class="muted u-style-4e420aff3f">Restore scope</div>
            <select name="restore_scope">
              <?php foreach ($scopes as $scope): ?>
                <option value="<?= e($scope) ?>" <?= $scope === $selectedScope ? 'selected' : '' ?>><?= e($scope) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            <div class="muted u-style-4e420aff3f">Conflict handling</div>
            <select name="conflict_strategy">
              <option value="overwrite">Overwrite live rows</option>
              <option value="merge">Merge where safe</option>
            </select>
          </label>
          <label class="u-style-01ef7fc99e">
            <input type="checkbox" name="install_missing_dependencies" value="1">
            <span>Install missing dependency first when required</span>
          </label>
        </div>
        <div class="u-style-ab94f75edc">
          <button class="btn ok" type="submit"><?= e(t('sbaio.restores.apply')) ?></button>
        </div>
      </form>
    <?php else: ?>
      <div class="card u-style-56f4356299">
        <strong><?= e(t('sbaio.restores.review_only')) ?></strong>
        <div class="muted u-style-fe7b4979fe"><?= e(t('sbaio.restores.review_only_desc')) ?></div>
      </div>
    <?php endif; ?>

    <form class="u-style-8a77e5a311" method="post" action="/apps/sbaio/restores/cancel">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="preview_token" value="<?= e((string)($previewPayload['preview_token'] ?? '')) ?>">
      <button class="btn" type="submit"><?= e(t('sbaio.restores.cancel_review')) ?></button>
    </form>
  </div>
<?php endif; ?>

<div class="card">
  <h3 class="u-style-df671843ff"><?= e(t('sbaio.restores.module_coverage')) ?></h3>
  <div class="muted u-style-761d3addb2"><?= e(t('sbaio.restores.module_coverage_desc')) ?></div>
  <div class="u-style-fe682a1428">
    <?php foreach ($modules as $module): ?>
      <div class="card u-style-1169661891">
        <strong><?= e((string)($module['display_name'] ?? '')) ?></strong>
        <div class="muted u-style-fe7b4979fe">Status: <?= e((string)($module['status'] ?? 'missing')) ?></div>
        <div class="muted u-style-fe7b4979fe">Tables: <?= e(implode(', ', array_map('strval', (array)($module['required_tables'] ?? [])))) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <h3 class="u-style-df671843ff"><?= e(t('sbaio.restores.history')) ?></h3>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>When</th>
          <th>Target</th>
          <th>Type</th>
          <th>Status</th>
          <th>Rollback</th>
          <th>Warnings / Errors</th>
          <th>User</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($history as $row): ?>
          <tr>
            <td><?= e((string)($row['created_at'] ?? '')) ?></td>
            <td><?= e((string)($row['target_type'] ?? '')) ?>: <?= e((string)($row['target_key'] ?? '')) ?></td>
            <td><?= e((string)($row['restore_type'] ?? '')) ?></td>
            <td><?= e((string)($row['status'] ?? '')) ?></td>
            <td><?= !empty($row['rollback_attempted']) ? e(t('sbaio.restores.rollback_attempted')) : e(t('sbaio.restores.rollback_no')) ?></td>
            <td><?= e(trim((string)($row['warning_text'] ?? '') . ' ' . (string)($row['error_text'] ?? ''))) ?: e(t('sbaio.common.none')) ?></td>
            <td><?= e((string)($row['created_by'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($history === []): ?>
          <tr>
            <td colspan="7" class="muted"><?= e(t('sbaio.restores.no_activity')) ?></td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
