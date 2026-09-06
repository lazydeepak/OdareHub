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
<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"><?= e(t('module.dispatch_entries.title')) ?></h2>
      <div class="muted"><?= e(t('module.dispatch_entries.subtitle')) ?></div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/dispatch-entries/export"><?= e(t('common.export')) ?></a>
      <a class="btn ok" href="/dispatch-entries/add"><?= e(t('module.dispatch_entries.add')) ?></a>
    </div>
  </div>
</div>
<?php if (!empty($flash ?? '')): ?><div class="card notice-ok"><?= e((string)$flash) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card notice-err"><?= e((string)$error) ?></div><?php endif; ?>
<div class="card">
  <form method="get" action="/dispatch-entries" class="control-row">
    <label class="control-field-compact">
      <span class="control-label"><?= e(t('common.date')) ?></span>
      <input class="input" type="date" name="dispatch_date" value="<?= e((string)$dispatch_date) ?>">
    </label>
    <label class="control-field-compact">
      <span class="control-label"><?= e(t('common.status')) ?></span>
      <input class="input" name="dispatch_status" value="<?= e((string)$dispatch_status) ?>" placeholder="<?= e(t('module.dispatch_entries.status_placeholder')) ?>">
    </label>
    <div class="control-actions">
      <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
      <a class="btn" href="/dispatch-entries"><?= e(t('common.reset')) ?></a>
    </div>
  </form>
</div>
<div class="card"><div class="table-wrap"><table><thead><tr><th><?= e(t('common.id')) ?></th><th><?= e(t('common.date')) ?></th><th><?= e(t('common.part')) ?></th><th><?= e(t('module.dispatch_entries.dispatchable_qty')) ?></th><th><?= e(t('module.dispatch_entries.destination')) ?></th><th><?= e(t('module.dispatch_entries.dispatch_type')) ?></th><th><?= e(t('module.dispatch_entries.dispatch_status')) ?></th><th><?= e(t('ops.approval_inbox.approval')) ?></th><th><?= e(t('ops.approval_inbox.lock')) ?></th><th>Last Activity</th><th><?= e(t('common.actions')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><?php $summary = $auditSummary[(int)$r['id']] ?? []; ?><tr><td><?= (int)$r['id'] ?></td><td><?= e((string)$r['dispatch_date']) ?></td><td><?= e((string)$r['parts_name']) ?> <span class="muted">(<?= e((string)$r['parts_number']) ?>)</span></td><td><?= e((string)$r['dispatchable_qty']) ?></td><td><?= e((string)($r['destination'] ?? '')) ?></td><td><?= e((string)$r['dispatch_type']) ?></td><td><?= e((string)$r['dispatch_status']) ?></td><td><?= e($approvalLabel((string)($r['approval_status'] ?? 'draft'))) ?></td><td><?= trim((string)($r['locked_at'] ?? '')) !== '' || strtolower(trim((string)($r['dispatch_status'] ?? ''))) === 'dispatched' ? e(t('ops.approval_inbox.locked_badge')) : e(t('common.open')) ?></td><td><?php if (!empty($summary)): ?><div class="ui-block"><?= e((string)($summary['action'] ?? '')) ?></div><div class="muted u-style-3995822e95"><?= e((string)($summary['actor'] ?? 'System')) ?> @ <?= e((string)($summary['at'] ?? '')) ?></div><?php else: ?><span class="muted">-</span><?php endif; ?></td><td><div class="control-actions"><a class="btn" href="/apps/manufacturing/products/360?id=<?= (int)$r['product_id'] ?>"><?= e(t('ops.supervisor_cockpit.order_360')) ?></a><a class="btn" href="/dispatch-entries/edit?id=<?= (int)$r['id'] ?>"><?= e(t('common.edit')) ?></a><a class="btn" href="/dispatch-entries/print-pdf?id=<?= (int)$r['id'] ?>" target="_blank" rel="noopener"><?= e(t('common.print_pdf')) ?></a><a class="btn" href="/ledger?product_id=<?= (int)$r['product_id'] ?>&source_module=DispatchEntries&source_id=<?= (int)$r['id'] ?>"><?= e(t('nav.stock_ledger')) ?></a><form method="post" action="/dispatch-entries/delete" onsubmit="return confirm('<?= e(t('module.dispatch_entries.delete_confirm')) ?>');"><input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn danger" type="submit"><?= e(t('common.delete')) ?></button></form></div></td></tr><?php endforeach; ?>
<?php if (empty($rows)): ?><tr><td colspan="11" class="muted"><?= e(t('module.dispatch_entries.none')) ?></td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
