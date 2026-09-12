<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$bb = static function (string $key): string {
    return t($key);
};
?>
<div class="card">
  <div class="ui-block">
    <h2><?= e($bb('bom.form.title')) ?></h2>
    <div class="muted"><?= e($bb('bom.form.subtitle')) ?></div>
  </div>
</div>

<?php if (!empty($flash ?? '')): ?><div class="card u-style-a25b37e313"><?= e($bb((string)$flash)) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card u-style-64e7e2c551"><?= e($bb((string)$error)) ?></div><?php endif; ?>

<div class="card">
  <form method="post" action="/apps/manufacturing/bom/add">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <div class="u-style-94253f99ba">
      <label class="muted"><?= e($bb('bom.form.finished_item')) ?></label>
      <select name="finished_item_ref" required>
        <option value=""><?= e($bb('bom.form.select_finished')) ?></option>
        <?php foreach (($items ?? []) as $i): ?>
          <option value="<?= (int)$i['item_ref'] ?>">
            <?= e((string)($i['parts_name'] ?? '')) ?> (<?= e((string)$i['parts_number']) ?>) #<?= (int)$i['item_ref'] ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="u-style-94253f99ba">
      <label class="muted"><?= e($bb('bom.form.version')) ?></label>
      <input class="input" type="text" name="version" value="1.0" maxlength="50" required>
    </div>
    <div class="u-style-94253f99ba">
      <label class="muted"><?= e($bb('bom.form.revision')) ?></label>
      <input class="input" type="number" name="revision" value="1" min="1" required>
    </div>
    <div class="u-style-94253f99ba">
      <label class="muted"><?= e($bb('bom.form.notes')) ?></label>
      <textarea class="input" name="notes" rows="3"></textarea>
    </div>
    <div class="row u-style-33fcd4c359">
      <button class="btn ok" type="submit"><?= e($bb('bom.form.submit')) ?></button>
      <a class="btn" href="/apps/manufacturing/bom"><?= e($bb('common.cancel')) ?></a>
    </div>
  </form>
</div>