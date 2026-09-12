<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$bb = static function (string $key): string {
    return t($key);
};
$statusLabel = static function (string $status): string {
    return t('bom.status.' . strtolower(trim($status)));
};
?>
<div class="card">
  <div class="row u-style-5930bcd33d">
    <div class="ui-block">
      <h2 class="u-style-1169661891"><?= e($bb('bom.title')) ?></h2>
      <div class="muted"><?= e($bb('bom.index.subtitle')) ?></div>
    </div>
    <div class="row u-style-33fcd4c359">
      <a class="btn ok" href="/apps/manufacturing/bom/add"><?= e($bb('bom.index.new_action')) ?></a>
      <a class="btn" href="/apps/manufacturing/products"><?= e($bb('bom.index.products_action')) ?></a>
    </div>
  </div>
</div>

<?php if (!empty($flash ?? '')): ?><div class="card u-style-a25b37e313"><?= e($bb((string)$flash)) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card u-style-64e7e2c551"><?= e($bb((string)$error)) ?></div><?php endif; ?>

<div class="card">
  <form method="get" action="/apps/manufacturing/bom" class="row u-style-a4c06ee61b">
    <div class="u-style-94253f99ba">
      <label class="muted u-style-98847c28df"><?= e($bb('bom.column.status')) ?></label>
      <select name="status">
        <option value=""><?= e($bb('common.all')) ?></option>
        <?php foreach (['draft', 'released', 'superseded', 'archived'] as $s): ?>
          <option value="<?= e($s) ?>" <?= ((string)($status ?? '') === $s) ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="u-style-571a77ba57">
      <label class="muted u-style-98847c28df"><?= e($bb('bom.column.finished_item')) ?></label>
      <select name="item_ref">
        <option value="0"><?= e($bb('bom.index.all_items')) ?></option>
        <?php foreach (($items ?? []) as $i): ?>
          <option value="<?= (int)$i['item_ref'] ?>" <?= ((int)($item_ref ?? 0) === (int)$i['item_ref']) ? 'selected' : '' ?>>
            <?= e((string)($i['parts_name'] ?? '')) ?> (#<?= (int)$i['item_ref'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <label class="u-style-ad7c3007e7">
      <input type="checkbox" name="only_active" value="1" <?= !empty($only_active ?? false) ? 'checked' : '' ?>>
      <span class="muted"><?= e($bb('bom.index.only_active')) ?></span>
    </label>
    <div class="row u-style-33fcd4c359">
      <button class="btn" type="submit"><?= e($bb('common.filter_action')) ?></button>
      <a class="btn" href="/apps/manufacturing/bom"><?= e($bb('common.reset_action')) ?></a>
    </div>
  </form>
</div>

<?php if (!empty($issues ?? [])): ?>
<div class="card">
  <h3 class="muted"><?= e($bb('bom.integrity.title')) ?></h3>
  <ul>
    <?php foreach ($issues as $issue): ?>
      <li><?= e($bb('bom.integrity.' . (string)$issue['code'])) ?>
        <?php if ((int)($issue['item_ref'] ?? 0) > 0): ?><span class="muted"><?= e('item#' . (int)$issue['item_ref']) ?></span><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e($bb('bom.column.version')) ?></th>
          <th><?= e($bb('bom.column.revision')) ?></th>
          <th><?= e($bb('bom.column.status')) ?></th>
          <th><?= e($bb('bom.column.finished_item')) ?></th>
          <th><?= e($bb('bom.column.lines')) ?></th>
          <th><?= e($bb('bom.column.updated')) ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($rows ?? []) as $r): ?>
          <tr>
            <td><?= e((string)$r['version']) ?></td>
            <td><?= (int)$r['revision'] ?></td>
            <td><span class="muted"><?= e($statusLabel((string)$r['status'])) ?></span></td>
            <td>
              <?= e((string)($r['parts_name'] ?? '') ?: $bb('bom.index.unresolved_item')) ?>
              <span class="muted">#<?= (int)$r['finished_item_ref'] ?></span>
            </td>
            <td><?= (int)$r['line_count'] ?></td>
            <td><?= e((string)$r['updated_at']) ?></td>
            <td><a class="btn" href="/apps/manufacturing/bom/detail?id=<?= (int)$r['id'] ?>"><?= e($bb('common.view')) ?></a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (empty($rows ?? [])): ?><p class="muted"><?= e($bb('bom.index.empty')) ?></p><?php endif; ?>
  </div>
</div>