<?php
declare(strict_types=1);
$module = is_array($generatedModule ?? null) ? $generatedModule : [];
$fields = is_array($generatedFields ?? null) ? $generatedFields : [];
$rows = is_array($generatedRows ?? null) ? $generatedRows : [];
$editRow = is_array($generatedEditRow ?? null) ? $generatedEditRow : [];
$search = (string)($generatedSearch ?? '');
$filters = is_array($generatedFilters ?? null) ? $generatedFilters : [];
$sort = is_array($generatedSort ?? null) ? $generatedSort : [];
$flash = is_array($generatedFlash ?? null) ? $generatedFlash : [];
$routePath = (string)($module['route_path'] ?? '/apps/manufacturing-studio/manufacturing-studio');
$locale = strtolower((string)($_SESSION['locale'] ?? 'en'));
$labels = [
    'en' => ['search' => 'Search', 'search_placeholder' => 'Search parts', 'all' => 'All', 'new_record' => 'New record', 'edit_record' => 'Edit record', 'save' => 'Save', 'reset' => 'Reset', 'actions' => 'Actions', 'edit' => 'Edit', 'no_rows' => 'No records yet.', 'saved' => 'Saved.', 'failed' => 'Save failed.', 'metadata' => 'Module metadata', 'app' => 'App', 'module' => 'Module', 'route' => 'Route'],
    'ja' => ['search' => '検索', 'search_placeholder' => '部品を検索', 'all' => 'すべて', 'new_record' => '新規レコード', 'edit_record' => 'レコード編集', 'save' => '保存', 'reset' => 'リセット', 'actions' => '操作', 'edit' => '編集', 'no_rows' => 'レコードはまだありません。', 'saved' => '保存しました。', 'failed' => '保存に失敗しました。', 'metadata' => 'モジュール情報', 'app' => 'アプリ', 'module' => 'モジュール', 'route' => 'ルート'],
    'ne' => ['search' => 'खोज', 'search_placeholder' => 'Parts खोज्नुहोस्', 'all' => 'सबै', 'new_record' => 'नयाँ रेकर्ड', 'edit_record' => 'रेकर्ड सम्पादन', 'save' => 'सेभ', 'reset' => 'रिसेट', 'actions' => 'कार्यहरू', 'edit' => 'सम्पादन', 'no_rows' => 'अहिलेसम्म रेकर्ड छैन।', 'saved' => 'सेभ भयो।', 'failed' => 'सेभ असफल भयो।', 'metadata' => 'Module metadata', 'app' => 'App', 'module' => 'Module', 'route' => 'Route'],
];
$tr = $labels[$locale] ?? $labels['en'];
?>
<section class="card">
  <h1><?= e((string)($pageTitle ?? 'Manufacturing Studio')) ?></h1>
  <p class="muted"><?= e('Manufacturing Studio') ?> / <?= e((string)($module['display_name'] ?? 'Manufacturing Studio')) ?></p>
  <?php if ($flash !== []): ?><div class="notice <?= e((string)($flash['status'] ?? '')) ?>"><?= e((string)($tr[(string)($flash['status'] ?? '')] ?? $flash['message'] ?? '')) ?></div><?php endif; ?>
  <?php if ($fields !== []): ?>
    <form method="get" action="<?= e($routePath) ?>" class="toolbar">
      <label><?= e((string)$tr['search']) ?><input class="input" type="search" name="q" value="<?= e($search) ?>" placeholder="<?= e((string)$tr['search_placeholder']) ?>"></label>
      <?php foreach ($fields as $field): if ((string)($field['type'] ?? '') !== 'select') { continue; } $key = (string)($field['key'] ?? ''); $filterKey = 'filter_' . $key; ?><label><?= e((string)($field['label'] ?? $key)) ?><select name="<?= e($filterKey) ?>"><option value=""><?= e((string)$tr['all']) ?></option><?php foreach ((array)($field['options'] ?? []) as $option): ?><option value="<?= e((string)$option) ?>" <?= (string)($filters[$key] ?? '') === (string)$option ? 'selected' : '' ?>><?= e((string)$option) ?></option><?php endforeach; ?></select></label><?php endforeach; ?>
      <button class="btn" type="submit"><?= e((string)$tr['search']) ?></button>
      <a class="button secondary" href="<?= e($routePath) ?>"><?= e((string)$tr['reset']) ?></a>
    </form>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><?php foreach ($fields as $field): $key = (string)($field['key'] ?? ''); $nextDir = ((string)($sort['field'] ?? '') === $key && (string)($sort['dir'] ?? 'asc') === 'asc') ? 'desc' : 'asc'; $sortUrl = $routePath . '?' . http_build_query(array_merge($_GET, ['sort' => $key, 'dir' => $nextDir])); ?><th><a href="<?= e($sortUrl) ?>"><?= e((string)($field['label'] ?? $key)) ?></a></th><?php endforeach; ?><th><?= e((string)$tr['actions']) ?></th></tr></thead>
        <tbody>
          <?php if ($rows === []): ?><tr><td colspan="<?= count($fields) + 1 ?>"><?= e((string)$tr['no_rows']) ?></td></tr><?php endif; ?>
          <?php foreach ($rows as $row): ?><tr><?php foreach ($fields as $field): $key = (string)($field['key'] ?? ''); ?><td><?= e((string)($row[$key] ?? '')) ?></td><?php endforeach; ?><td><a href="<?= e($routePath . '?edit=' . rawurlencode((string)($row['id'] ?? ''))) ?>"><?= e((string)$tr['edit']) ?></a></td></tr><?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <form method="post" action="<?= e($routePath) ?>" class="form-panel">
      <h2><?= e($editRow !== [] ? (string)$tr['edit_record'] : (string)$tr['new_record']) ?></h2>
      <input class="input" type="hidden" name="csrf" value="<?= e((string)($csrf ?? '')) ?>">
      <input class="input" type="hidden" name="row_id" value="<?= e((string)($editRow['id'] ?? '')) ?>">
      <?php foreach ($fields as $field): $key = (string)($field['key'] ?? ''); $type = (string)($field['type'] ?? 'string'); ?>
        <label><?= e((string)($field['label'] ?? $key)) ?>
          <?php if ($type === 'select'): ?><select name="<?= e($key) ?>"><?php foreach ((array)($field['options'] ?? []) as $option): ?><option value="<?= e((string)$option) ?>" <?= (string)($editRow[$key] ?? '') === (string)$option ? 'selected' : '' ?>><?= e((string)$option) ?></option><?php endforeach; ?></select>
          <?php else: ?><input class="input" type="<?= $type === 'integer' ? 'number' : 'text' ?>" name="<?= e($key) ?>" value="<?= e((string)($editRow[$key] ?? '')) ?>"><?php endif; ?>
        </label>
      <?php endforeach; ?>
      <button class="btn" type="submit"><?= e((string)$tr['save']) ?></button>
    </form>
  <?php else: ?>
    <h2><?= e((string)$tr['metadata']) ?></h2>
    <div class="table-wrap"><table class="table"><tbody><tr><td><?= e((string)$tr['app']) ?></td><td><?= e((string)($module['app_key'] ?? 'manufacturing_studio')) ?></td></tr><tr><td><?= e((string)$tr['module']) ?></td><td><?= e((string)($module['module_key'] ?? 'manufacturing_studio')) ?></td></tr><tr><td><?= e((string)$tr['route']) ?></td><td><code><?= e($routePath) ?></code></td></tr></tbody></table></div>
  <?php endif; ?>
</section>
