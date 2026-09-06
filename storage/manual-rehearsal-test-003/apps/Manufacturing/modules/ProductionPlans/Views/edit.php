<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php
$gov = is_array($governance ?? null) ? $governance : [];
$approvalEvents = is_array($approval_events ?? null) ? $approval_events : [];
$isLocked = !empty($gov['locked']);
$canEditPlan = !empty($can_edit_plan);
$canEditAddedBy = !empty($can_edit_added_by);
$readOnlyAll = $isLocked || (!$canEditPlan && !$canEditAddedBy);
$statusOptions = ['Draft', 'Planned', 'In Progress', 'Completed', 'On Hold', 'Cancelled'];
?>
<div class="card"><div class="row u-style-2d7d2729a4"><h2 class="u-style-1169661891"><?= e(t('module.production_plans.title')) ?> #<?= (int)$row['id'] ?></h2><div class="row u-style-f0abc1db33"><a class="btn" href="/production-plans/print-pdf?id=<?= (int)$row['id'] ?>" target="_blank" rel="noopener"><?= e(t('common.print_pdf')) ?></a><a class="btn" href="/production-plans/download-pdf?id=<?= (int)$row['id'] ?>"><?= e(t('common.download_pdf')) ?></a><a class="btn" href="/production-plans"><?= e(t('back_to_list')) ?></a></div></div></div>
<?php if (!($pdf_ready ?? false)): ?><div class="card u-style-64e7e2c551"><?= e(t('common.pdf_not_available')) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card u-style-64e7e2c551"><?= e((string)$error) ?></div><?php endif; ?>
<div class="card">
	<div class="row u-style-91ebbdc206">
		<div class="ui-block"><strong>Approval:</strong> <?= e((string)($row['approval_status'] ?? 'Draft')) ?> | <strong>Locked:</strong> <?= $isLocked ? 'Yes' : 'No' ?></div>
		<div class="muted"> <?= e($tt('production_plans.added_by_action')) ?> <?= e((string)($row['added_by'] ?? '-')) ?> <?= e($tt('production_plans.approved_by_action')) ?> <?= e((string)($row['approved_by'] ?? '-')) ?> at <?= e((string)($row['approved_at'] ?? '-')) ?></div>
	</div>
	<?php if ($isLocked): ?><div class="card u-style-f47b09d788"> <?= e($tt('production_plans.edit_description')) ?> </div><?php endif; ?>
	<?php if (!$canEditPlan && !$canEditAddedBy): ?><div class="card u-style-d60e50b0d3">You can view this production plan, but you do not have edit permissions.</div><?php endif; ?>
	<?php if (!$canEditPlan && $canEditAddedBy): ?><div class="card u-style-d60e50b0d3">You can edit only the Added by field.</div><?php endif; ?>
	<div class="row u-style-8bb0cda49c">
		<?php if (!empty($gov['can_submit'])): ?><form class="u-style-ed6103acbe" method="post" action="/production-plans/approval-action"><input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="submit"><input class="input" name="note" placeholder= "<?= e($tt('production_plans.submit_note_action')) ?>"><button class="btn" type="submit"> <?= e($tt('production_plans.submit_for_approval_action')) ?> </button></form><?php endif; ?>
		<?php if (!empty($gov['can_approve'])): ?><form class="u-style-ed6103acbe" method="post" action="/production-plans/approval-action"><input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="approve"><input class="input" name="note" placeholder="Approval note"><button class="btn ok" type="submit"> <?= e($tt('common.approve_action')) ?> </button></form><?php endif; ?>
		<?php if (!empty($gov['can_reject'])): ?><form class="u-style-ed6103acbe" method="post" action="/production-plans/approval-action"><input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="reject"><input class="input" name="reason" placeholder= "<?= e($tt('production_plans.reject_action')) ?>" required><input class="input" name="note" placeholder="Optional note"><button class="btn" type="submit"> <?= e($tt('common.reject_action')) ?> </button></form><?php endif; ?>
		<?php if (!empty($gov['can_reopen'])): ?><form class="u-style-ed6103acbe" method="post" action="/production-plans/approval-action"><input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="reopen"><input class="input" name="reason" placeholder= "<?= e($tt('production_plans.reopen_reason_action')) ?>" required><input class="input" name="note" placeholder="Optional note"><button class="btn" type="submit"> <?= e($tt('production_plans.reopen_action')) ?> </button></form><?php endif; ?>
		<?php if (!empty($gov['can_override'])): ?><form class="u-style-ed6103acbe" method="post" action="/production-plans/approval-action"><input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="unlock_override"><input class="input" name="reason" placeholder="Override reason" required><button class="btn" type="submit">Unlock Override</button></form><?php endif; ?>
	</div>
	<?php if (!$canEditPlan && $canEditAddedBy): ?>
	<form method="post" action="/production-plans/edit" class="row u-style-761d3addb2"><input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
		<div class="u-style-6c0d09d5fa"><label class="muted u-style-98847c28df"> <?= e($tt('production_plans.added_by_action')) ?> </label><input class="input" name="added_by" value="<?= e((string)($row['added_by'] ?? '')) ?>" <?= $isLocked ? 'readonly' : '' ?>></div>
		<div class="row u-style-0466783d98"><button class="btn ok" type="submit" <?= $isLocked ? 'disabled' : '' ?>> <?= e($tt('production_plans.update_added_by_action')) ?> </button></div>
	</form>
	<?php endif; ?>
<form method="post" action="/production-plans/edit" class="row"><input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><fieldset <?= $readOnlyAll ? 'disabled' : '' ?> style="border:0;padding:0;margin:0;display:contents">
<div class="u-style-a17b2f9a1e"><label class="muted u-style-98847c28df">Plan Date *</label><input class="input" type="date" name="plan_date" required value="<?= e((string)$row['plan_date']) ?>"></div>
<div class="u-style-6c0d09d5fa"><label class="muted u-style-98847c28df"><?= e(t('machine')) ?> *</label><select class="u-style-81a7ab4e7d" name="machine_id" required><?php foreach ($machines as $m): ?><option value="<?= (int)$m['id'] ?>" <?= ((int)$row['machine_id'] === (int)$m['id']) ? 'selected' : '' ?>><?= e((string)$m['machine_no']) ?> - <?= e((string)$m['machine_name']) ?></option><?php endforeach; ?></select></div>
<div class="u-style-6c0d09d5fa"><label class="muted u-style-98847c28df"><?= e(t('part')) ?> *</label><select class="u-style-81a7ab4e7d" name="product_id" required><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= ((int)$row['product_id'] === (int)$p['id']) ? 'selected' : '' ?>><?= e((string)$p['parts_name']) ?> (<?= e((string)$p['parts_number']) ?>)</option><?php endforeach; ?></select></div>
<div class="u-style-aae0403196"><label class="muted u-style-98847c28df"><?= e(t('planned_qty')) ?> *</label><input class="input" type="number" step="0.01" name="planned_qty" required value="<?= e((string)$row['planned_qty']) ?>"></div>
<div class="u-style-f09611ef0f"><label class="muted u-style-98847c28df">Sequence</label><input class="input" type="number" name="sequence_no" value="<?= e((string)$row['sequence_no']) ?>"></div>
<div class="u-style-ead6a4fcdb"><label class="muted u-style-98847c28df">Runtime</label><input class="input" type="number" step="0.01" name="runtime" value="<?= e((string)($row['runtime'] ?? '')) ?>"></div>
<div class="u-style-a17b2f9a1e"><label class="muted u-style-98847c28df">Status</label><select class="u-style-81a7ab4e7d" name="status"><?php foreach ($statusOptions as $opt): ?><option value="<?= e($opt) ?>" <?= ((string)$row['status'] === $opt) ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select></div>
<div class="u-style-4ad81db508"><label class="muted u-style-98847c28df">Plan Type</label><input class="input" name="plan_type" value="<?= e((string)$row['plan_type']) ?>"></div>
<div class="u-style-a17b2f9a1e"><label class="muted u-style-98847c28df">Ref Doctype</label><input class="input" name="reference_doctype" value="<?= e((string)($row['reference_doctype'] ?? '')) ?>"></div>
<div class="u-style-a17b2f9a1e"><label class="muted u-style-98847c28df">Ref Name</label><input class="input" name="reference_name" value="<?= e((string)($row['reference_name'] ?? '')) ?>"></div>
<div class="u-style-aae0403196"><label class="muted u-style-98847c28df">Coverage %</label><input class="input" type="number" step="0.01" name="coverage_pct" value="<?= e((string)$row['coverage_pct']) ?>"></div>
<div class="u-style-aae0403196"><label class="muted u-style-98847c28df">Shortage Qty</label><input class="input" type="number" step="0.01" name="shortage_qty" value="<?= e((string)$row['shortage_qty']) ?>"></div>
<div class="u-style-aae0403196"><label class="muted u-style-98847c28df">Auto Created</label><select name="auto_created"><option value="0" <?= ((int)$row['auto_created'] === 0) ? 'selected' : '' ?>>No</option><option value="1" <?= ((int)$row['auto_created'] === 1) ? 'selected' : '' ?>>Yes</option></select></div>
<div class="u-style-9405df25bc"><label class="muted u-style-98847c28df"> <?= e($tt('production_plans.added_by_action')) ?> </label><input class="input" name="added_by" value="<?= e((string)($row['added_by'] ?? '')) ?>" <?= $canEditAddedBy ? '' : 'readonly' ?>></div>
<div class="u-style-4ca11fcb42"><label class="muted u-style-98847c28df"><?= e(t('notes')) ?></label><textarea name="notes"><?= e((string)($row['notes'] ?? '')) ?></textarea></div>
<div class="row u-style-0466783d98"><button class="btn ok" type="submit" <?= $readOnlyAll ? 'disabled' : '' ?>><?= e(t('update_production_plan')) ?></button><a class="btn" href="/production-plans"><?= e(t('cancel')) ?></a></div>
</fieldset></form></div>
<?php if (!empty($approvalEvents)): ?><div class="card"><h3 class="u-style-d462248a40">Governance History</h3><div class="table-wrap"><table><thead><tr><th>When</th><th>Action</th><th>Approval</th><th>Lock</th><th>Reason</th><th>Note</th><th>Actor</th></tr></thead><tbody><?php foreach ($approvalEvents as $event): ?><tr><td><?= e((string)($event['acted_at'] ?? '')) ?></td><td><?= e((string)($event['action_name'] ?? '')) ?></td><td><?= e((string)($event['previous_approval_status'] ?? '-')) ?> -> <?= e((string)($event['new_approval_status'] ?? '-')) ?></td><td><?= ((int)($event['previous_locked_state'] ?? 0) === 1 ? 'Locked' : 'Unlocked') ?> -> <?= ((int)($event['new_locked_state'] ?? 0) === 1 ? 'Locked' : 'Unlocked') ?></td><td><?= e((string)($event['reason_text'] ?? '')) ?></td><td><?= e((string)($event['note_text'] ?? '')) ?></td><td><?= e((string)($event['actor_email'] ?? 'System')) ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
