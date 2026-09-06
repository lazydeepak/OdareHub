<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php $auditSummary = is_array($audit_summary ?? null) ? $audit_summary : []; ?>

<div class="card">
  <div class="row u-style-5930bcd33d">
    <div class="ui-block">
      <h2 class="u-style-1169661891">Assembly Plans</h2>
      <div class="muted">Demand-backed assembly plans with approved quantity, execution progress, and downstream readiness context.</div>
    </div>
    <div class="row u-style-33fcd4c359">
      <a class="btn ok" href="/apps/manufacturing/demands?demand_type=assembly"> <?= e($tt('assembly_plans.open_link')) ?> </a>
      <a class="btn" href="/pre-orders">Pre Orders</a>
      <a class="btn" href="/qc-plans">QC Plans</a>
      <a class="btn" href="/apps/manufacturing/assembly-queue">Assembly Queue</a>
      <a class="btn" href="/apps/manufacturing/stage-board"> <?= e($tt('assembly_plans.stage_board_message')) ?> </a>
      <a class="btn" href="/apps/manufacturing/dispatch-ops">Dispatch Workbench</a>
    </div>
  </div>
</div>

<?php if (!empty($flash ?? '')): ?><div class="card u-style-a25b37e313"><?= e((string)$flash) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card u-style-64e7e2c551"><?= e((string)$error) ?></div><?php endif; ?>

<div class="card">
  <form method="get" action="/apps/manufacturing/assembly-plans" class="row u-style-a4c06ee61b">
    <div class="u-style-94253f99ba">
      <label class="muted u-style-98847c28df">Date</label>
      <input class="input" type="date" name="date" value="<?= e((string)($date ?? '')) ?>">
    </div>
    <div class="u-style-94253f99ba">
      <label class="muted u-style-98847c28df">Status</label>
      <select name="status">
        <option value="">All</option>
        <?php foreach (['calculated', 'adjusted', 'approved'] as $s): ?>
          <option value="<?= e($s) ?>" <?= ((string)($status ?? '') === $s) ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="u-style-571a77ba57">
      <label class="muted u-style-98847c28df">Part</label>
      <select name="product_id">
        <option value="0">All parts</option>
        <?php foreach (($products ?? []) as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= ((int)($product_id ?? 0) === (int)$p['id']) ? 'selected' : '' ?>>
            <?= e((string)$p['parts_name']) ?> (<?= e((string)$p['parts_number']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <label class="u-style-ad7c3007e7">
      <input type="checkbox" name="only_open" value="1" <?= !empty($only_open) ? 'checked' : '' ?>>
      <span class="muted">Only open</span>
    </label>
    <div class="row u-style-33fcd4c359">
      <button class="btn" type="submit"> <?= e($tt('common.filter_action')) ?> </button>
      <a class="btn" href="/apps/manufacturing/assembly-plans"> <?= e($tt('common.reset_action')) ?> </a>
    </div>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Plan Date</th>
          <th>Part</th>
          <th>Source Demand</th>
          <th>System Assembly Demand</th>
          <th>Adjusted Qty</th>
          <th> <?= e($tt('assembly_plans.approved_qty_column')) ?> </th>
          <th> <?= e($tt('assembly_plans.completed_qty_column')) ?> </th>
          <th>Remaining Qty</th>
          <th>Status</th>
          <th>Next Stage</th>
          <th>Dispatch Readiness</th>
          <th>Last Activity</th>
          <th>Notes</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <?php $summary = $auditSummary[(int)$r['id']] ?? []; ?>
        <tr>
          <td><?= e((string)$r['demand_date']) ?></td>
          <td>
            <?= e((string)$r['parts_name']) ?>
            <span class="muted">(<?= e((string)$r['parts_number']) ?>)</span>
            <?php if (!empty($r['assembly_released'])): ?>
              <div class="u-style-2ae37dc813">Released Downstream</div>
            <?php endif; ?>
          </td>
          <td><?= e((string)$r['source_demand']) ?></td>
          <td><?= e((string)$r['system_qty']) ?></td>
          <td><?= e((string)($r['adjusted_qty'] ?? '')) ?></td>
          <td><?= e((string)($r['approved_qty'] ?? '')) ?></td>
          <td><?= e((string)($r['completed_qty'] ?? '0')) ?></td>
          <td style="font-weight:700;color:<?= (float)($r['remaining_qty'] ?? 0) > 0 ? 'var(--color-warning-text)' : 'var(--color-success-text)' ?>">
            <?= e((string)($r['remaining_qty'] ?? '0')) ?>
          </td>
          <td>
            <span style="font-size:.78em;padding:2px 8px;border-radius:20px;
              <?= match((string)($r['status'] ?? 'calculated')) {
                'approved' => 'background: var(--color-success-bg);color: var(--color-success-text);border: 1px solid var(--color-success-border)',
                'adjusted' => 'background: var(--color-warning-bg);color: var(--color-warning-text);border: 1px solid var(--color-warning-border)',
                default => 'background: var(--style-subtle-bg);color: var(--text);border: 1px solid var(--style-border-soft)',
              } ?>">
              <?= e(ucfirst((string)($r['status'] ?? 'calculated'))) ?>
            </span>
          </td>
          <td>
            <?php if (!empty($r['next_stage'])): ?>
              <?= e(ucfirst((string)$r['next_stage'])) ?>
            <?php else: ?>
              <span class="u-style-ce8274176b"> <?= e($tt('assembly_plans.complete_message')) ?> </span>
            <?php endif; ?>
            <?php if (!empty($r['block_reason'])): ?>
              <div class="u-style-5e1290577f"><?= e((string)$r['block_reason']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <span style="font-size:.78em;color:<?= (string)($r['dispatch_stage_status'] ?? '') === 'eligible' ? '#a5c5ff' : 'var(--muted)' ?>">
              <?= e(ucfirst((string)($r['dispatch_stage_status'] ?? 'pending'))) ?>
            </span>
          </td>
          <td>
            <?php if (!empty($summary)): ?>
              <div class="ui-block"><?= e((string)($summary['action'] ?? '')) ?></div>
              <div class="muted u-style-3995822e95"><?= e((string)($summary['actor'] ?? 'System')) ?> @ <?= e((string)($summary['at'] ?? '')) ?></div>
            <?php else: ?>
              <span class="muted">-</span>
            <?php endif; ?>
          </td>
          <td><?= e((string)($r['adjustment_note'] ?? '')) ?></td>
          <td><a class="btn" href="/apps/manufacturing/assembly-plans/detail?id=<?= (int)$r['id'] ?>"> <?= e($tt('common.open_action')) ?> </a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows ?? [])): ?>
        <tr><td colspan="14" class="muted"> <?= e($tt('assembly_plans.records_empty_state')) ?> </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
