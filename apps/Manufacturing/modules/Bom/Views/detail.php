<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$bb = static function (string $key): string {
    return t($key);
};
$payload = $payload ?? [];
$bom = is_array($payload['bom'] ?? null) ? $payload['bom'] : [];
$lines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
$resolved = is_array($payload['resolved'] ?? null) ? $payload['resolved'] : [];
$status = strtolower((string)($bom['status'] ?? 'draft'));
$canEdit = $status === 'draft';
$items = $items ?? [];
?>
<div class="card">
  <div class="row u-style-5930bcd33d">
    <div class="ui-block">
      <h2><?= e((string)($resolved['finished_label'] ?? '')) ?></h2>
      <div class="muted">
        <?= e((string)($bom['version'] ?? '')) ?> r<?= (int)($bom['revision'] ?? 1) ?>
        &middot; <?= e(t('bom.status.' . $status)) ?>
        &middot; <?= e((string)($bom['updated_at'] ?? '')) ?>
      </div>
    </div>
    <div class="row u-style-33fcd4c359">
      <a class="btn" href="/apps/manufacturing/bom"><?= e($bb('common.back')) ?></a>
      <?php if ($canEdit): ?>
      <form method="post" action="/apps/manufacturing/bom/status">
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <input type="hidden" name="id" value="<?= (int)($bom['id'] ?? 0) ?>">
        <input type="hidden" name="action" value="release">
        <button class="btn ok" type="submit"><?= e($bb('bom.action.release')) ?></button>
      </form>
      <form method="post" action="/apps/manufacturing/bom/status">
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <input type="hidden" name="id" value="<?= (int)($bom['id'] ?? 0) ?>">
        <input type="hidden" name="action" value="archive">
        <button class="btn" type="submit"><?= e($bb('bom.action.archive')) ?></button>
      </form>
      <?php elseif ($status === 'released'): ?>
      <form method="post" action="/apps/manufacturing/bom/status">
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <input type="hidden" name="id" value="<?= (int)($bom['id'] ?? 0) ?>">
        <input type="hidden" name="action" value="supersede">
        <button class="btn" type="submit"><?= e($bb('bom.action.supersede')) ?></button>
      </form>
      <form method="post" action="/apps/manufacturing/bom/status">
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <input type="hidden" name="id" value="<?= (int)($bom['id'] ?? 0) ?>">
        <input type="hidden" name="action" value="archive">
        <button class="btn" type="submit"><?= e($bb('bom.action.archive')) ?></button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (!empty($flash ?? '')): ?><div class="card u-style-a25b37e313"><?= e($bb((string)$flash)) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card u-style-64e7e2c551"><?= e($bb((string)$error)) ?></div><?php endif; ?>

<?php if ($canEdit): ?>
<div class="card">
  <form method="post" action="/apps/manufacturing/bom/update">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)($bom['id'] ?? 0) ?>">
    <label class="muted"><?= e($bb('bom.form.notes')) ?></label>
    <textarea class="input" name="notes" rows="3"><?= e((string)($bom['notes'] ?? '')) ?></textarea>
    <button class="btn" type="submit"><?= e($bb('bom.action.save_notes')) ?></button>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h3><?= e($bb('bom.detail.lines_title')) ?></h3>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e($bb('bom.line.component')) ?></th>
          <th><?= e($bb('bom.line.quantity')) ?></th>
          <th><?= e($bb('bom.line.unit')) ?></th>
          <th><?= e($bb('bom.line.sequence')) ?></th>
          <?php if ($canEdit): ?><th></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lines as $l): ?>
          <tr>
            <td>
              <?= e((string)($l['component_name'] ?? '') ?: $bb('bom.unresolved_item')) ?>
              <span class="muted">#<?= (int)$l['component_item_ref'] ?></span>
            </td>
            <td><?= e((string)$l['quantity']) ?></td>
            <td><?= e((string)$l['unit']) ?></td>
            <td><?= (int)$l['sequence'] ?></td>
            <?php if ($canEdit): ?>
            <td>
              <form method="post" action="/apps/manufacturing/bom/lines/delete" onsubmit="return confirm('<?= e($bb('bom.delete_confirm')) ?>');">
                <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int)($bom['id'] ?? 0) ?>">
                <input type="hidden" name="line_id" value="<?= (int)$l['id'] ?>">
                <button class="btn danger" type="submit"><?= e($bb('common.delete')) ?></button>
              </form>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (empty($lines)): ?><p class="muted"><?= e($bb('bom.detail.no_lines')) ?></p><?php endif; ?>
  </div>

  <?php if ($canEdit): ?>
  <form method="post" action="/apps/manufacturing/bom/lines/add" class="row u-style-a4c06ee61b">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)($bom['id'] ?? 0) ?>">
    <div>
      <label class="muted"><?= e($bb('bom.line.component')) ?></label>
      <select name="component_item_ref" required>
        <?php foreach ($items as $i): ?>
          <option value="<?= (int)$i['item_ref'] ?>">
            <?= e((string)($i['parts_name'] ?? '')) ?> (#<?= (int)$i['item_ref'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="muted"><?= e($bb('bom.line.quantity')) ?></label>
      <input class="input" type="number" step="any" name="quantity" value="1" min="0.0001" required>
    </div>
    <div>
      <label class="muted"><?= e($bb('bom.line.unit')) ?></label>
      <input class="input" type="text" name="unit" value="each" maxlength="50">
    </div>
    <div>
      <label class="muted"><?= e($bb('bom.line.sequence')) ?></label>
      <input class="input" type="number" name="sequence" value="0" min="0">
    </div>
    <button class="btn ok" type="submit"><?= e($bb('bom.action.add_line')) ?></button>
  </form>
  <?php endif; ?>
</div>