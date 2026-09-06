<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php $activityTimeline = is_array($activity_timeline ?? null) ? $activity_timeline : []; ?>
<div class="card"><div class="row u-style-2d7d2729a4"><h2 class="u-style-1169661891"><?= e(t('edit')) ?> Production Entry #<?= (int)$row['id'] ?></h2><a class="btn" href="/production-entries"><?= e(t('back_to_list')) ?></a></div></div>
<?php if (!empty($error ?? '')): ?><div class="card u-style-64e7e2c551"><?= e((string)$error) ?></div><?php endif; ?>
<?php $ownership_title = 'Operational Ownership'; require APP_ROOT . '/public/views/partials/ownership_summary.php'; ?>
<div class="card"><form method="post" action="/production-entries/edit" class="row"><input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
<div class="u-style-eb62184e30"><label class="muted u-style-98847c28df">Production Date *</label><input class="input" type="date" name="production_date" required value="<?= e((string)$row['production_date']) ?>"></div>
<div class="u-style-aae0403196"><label class="muted u-style-98847c28df">Shift</label><input class="input" name="shift" value="<?= e((string)$row['shift']) ?>"></div>
<div class="u-style-6c0d09d5fa"><label class="muted u-style-98847c28df"><?= e(t('machine')) ?> *</label><select class="u-style-81a7ab4e7d" name="machine_id" required><?php foreach ($machines as $m): ?><option value="<?= (int)$m['id'] ?>" <?= ((int)$row['machine_id'] === (int)$m['id']) ? 'selected' : '' ?>><?= e((string)$m['machine_no']) ?> - <?= e((string)$m['machine_name']) ?></option><?php endforeach; ?></select></div>
<div class="u-style-6c0d09d5fa"><label class="muted u-style-98847c28df"><?= e(t('part')) ?> *</label><select class="u-style-81a7ab4e7d" name="product_id" required><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= ((int)$row['product_id'] === (int)$p['id']) ? 'selected' : '' ?>><?= e((string)$p['parts_name']) ?> (<?= e((string)$p['parts_number']) ?>)</option><?php endforeach; ?></select></div>
<div class="u-style-aae0403196"><label class="muted u-style-98847c28df">Produced Qty *</label><input class="input" type="number" step="0.01" name="produced_qty" required value="<?= e((string)$row['produced_qty']) ?>"></div>
<div class="u-style-aae0403196"><label class="muted u-style-98847c28df">Rejected Qty *</label><input class="input" type="number" step="0.01" name="rejected_qty" required value="<?= e((string)$row['rejected_qty']) ?>"></div>
<div class="u-style-2f7ab6d1f3"><label class="muted u-style-98847c28df">Status</label><input class="input" name="status" value="<?= e((string)$row['status']) ?>"></div>
<div class="u-style-4ca11fcb42"><label class="muted u-style-98847c28df"><?= e(t('notes')) ?></label><textarea name="notes"><?= e((string)($row['notes'] ?? '')) ?></textarea></div>
<div class="row u-style-0466783d98"><button class="btn ok" type="submit"><?= e(t('update_production_entry')) ?></button><a class="btn" href="/production-entries"><?= e(t('cancel')) ?></a></div>
</form></div>
<?php if (!empty($activityTimeline)): ?><div class="card"><h3 class="u-style-d462248a40">Activity Timeline</h3><div class="table-wrap"><table><thead><tr><th>When</th><th>Action</th><th>Type</th><th> <?= e($tt('production_entries.state_column')) ?> </th><th>Reason / Note</th><th>Changes</th><th>Actor</th></tr></thead><tbody><?php foreach ($activityTimeline as $event): ?><tr><td><?= e((string)($event['created_at'] ?? '')) ?></td><td><?= e((string)($event['action_name'] ?? '')) ?></td><td><?= e((string)($event['event_type'] ?? '')) ?></td><td><?= e((string)($event['old_state'] ?? '-')) ?> -> <?= e((string)($event['new_state'] ?? '-')) ?></td><td><?= e(trim((string)($event['reason_text'] ?? '')) !== '' ? (string)$event['reason_text'] : (string)($event['note_text'] ?? '')) ?></td><td><?php $diff = is_array($event['diff'] ?? null) ? $event['diff'] : []; ?><?php if (!empty($diff)): ?><?php $items = []; foreach ($diff as $d) { $items[] = (string)($d['field'] ?? '') . ': ' . (string)($d['old'] ?? '-') . ' -> ' . (string)($d['new'] ?? '-'); } ?><?= e(implode(' | ', $items)) ?><?php else: ?>-<?php endif; ?></td><td><?= e((string)($event['actor_label'] ?? 'System')) ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
