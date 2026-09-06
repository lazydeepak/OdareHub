<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$auditSummary = is_array($audit_summary ?? null) ? $audit_summary : [];
$approvalLabel = static function (string $status): string {
	return match (strtolower(trim($status))) {
		'pending approval' => t('ops.approval_inbox.status_pending_approval'),
		'approved' => t('ops.approval_inbox.status_approved'),
		'rejected' => t('ops.approval_inbox.status_rejected'),
		'reopened' => t('ops.approval_inbox.status_reopened'),
		'draft' => t('ops.approval_inbox.status_draft'),
		default => $status,
	};
};
?>
<div class="card"><div class="row u-style-2d7d2729a4"><div class="ui-block"><h2 class="u-style-1169661891"><?= e(t('module.qc_entries.title')) ?></h2><div class="muted"><?= e(t('module.qc_entries.subtitle')) ?></div></div><div class="row u-style-f0abc1db33"><a class="btn" href="/qc-entries/export"><?= e(t('common.export')) ?></a><a class="btn ok" href="/qc-entries/add"><?= e(t('module.qc_entries.add')) ?></a></div></div></div>
<?php if (!empty($flash ?? '')): ?><div class="card u-style-a25b37e313"><?= e((string)$flash) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card u-style-64e7e2c551"><?= e((string)$error) ?></div><?php endif; ?>
<div class="card"><form method="get" action="/qc-entries" class="row u-style-9bc05614aa"><div class="u-style-eb62184e30"><label class="muted u-style-98847c28df"><?= e(t('module.qc_entries.qc_type')) ?></label><input class="input" name="qc_type" value="<?= e((string)$qc_type) ?>" placeholder="<?= e(t('module.qc_entries.qc_type_placeholder')) ?>"></div><div class="u-style-eb62184e30"><label class="muted u-style-98847c28df"><?= e(t('common.status')) ?></label><input class="input" name="status" value="<?= e((string)$status) ?>" placeholder="<?= e(t('module.qc_entries.status_placeholder')) ?>"></div><div class="row"><button class="btn" type="submit"><?= e(t('common.filter')) ?></button><a class="btn" href="/qc-entries"><?= e(t('common.reset')) ?></a></div></form></div>
<div class="card"><div class="table-wrap"><table><thead><tr><th><?= e(t('common.id')) ?></th><th><?= e(t('common.part')) ?></th><th><?= e(t('module.qc_entries.qc_type')) ?></th><th><?= e(t('module.qc_entries.checked_qty')) ?></th><th><?= e(t('module.qc_entries.pass_qty')) ?></th><th><?= e(t('module.qc_entries.fail_qty')) ?></th><th><?= e(t('module.qc_entries.stock_impact')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('ops.approval_inbox.approval')) ?></th><th><?= e(t('ops.approval_inbox.lock')) ?></th><th>Last Activity</th><th><?= e(t('common.actions')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<?php $failQty = (float)($r['fail_qty'] ?? 0); ?>
<?php $summary = $auditSummary[(int)$r['id']] ?? []; ?><tr><td><?= (int)$r['id'] ?></td><td><?= e((string)$r['parts_name']) ?> <span class="muted">(<?= e((string)$r['parts_number']) ?>)</span></td><td><?= e((string)$r['qc_type']) ?></td><td><?= e((string)$r['checked_qty']) ?></td><td><?= e((string)$r['pass_qty']) ?></td><td><?= e((string)$r['fail_qty']) ?></td><td><?= $failQty > 0 ? '-' . e(number_format($failQty, 2, '.', '')) : '<span class="muted">0.00</span>' ?></td><td><?= e((string)$r['status']) ?></td><td><?= e($approvalLabel((string)($r['approval_status'] ?? 'draft'))) ?></td><td><?= trim((string)($r['locked_at'] ?? '')) !== '' ? e(t('ops.approval_inbox.locked_badge')) : e(t('common.open')) ?></td><td><?php if (!empty($summary)): ?><div class="ui-block"><?= e((string)($summary['action'] ?? '')) ?></div><div class="muted u-style-3995822e95"><?= e((string)($summary['actor'] ?? t('common.system'))) ?> @ <?= e((string)($summary['at'] ?? '')) ?></div><?php else: ?><span class="muted">-</span><?php endif; ?></td><td><div class="row"><a class="btn" href="/apps/manufacturing/products/360?id=<?= (int)$r['product_id'] ?>"><?= e(t('ops.supervisor_cockpit.order_360')) ?></a><a class="btn" href="/qc-entries/edit?id=<?= (int)$r['id'] ?>"><?= e(t('common.edit')) ?></a><a class="btn" href="/ledger?product_id=<?= (int)$r['product_id'] ?>&source_module=QCEntries&source_id=<?= (int)$r['id'] ?>"><?= e(t('nav.stock_ledger')) ?></a><form method="post" action="/qc-entries/delete" onsubmit="return confirm('<?= e(t('module.qc_entries.delete_confirm')) ?>');" style="margin:0"><input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn danger" type="submit"><?= e(t('common.delete')) ?></button></form></div></td></tr><?php endforeach; ?>
<?php if (empty($rows)): ?><tr><td colspan="12" class="muted"><?= e(t('module.qc_entries.none')) ?></td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
