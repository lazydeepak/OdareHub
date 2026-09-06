<?php
$ownershipBoard = is_array($ownership_board ?? null) ? $ownership_board : [];
$ownershipKpi = is_array($ownershipBoard['kpi'] ?? null) ? $ownershipBoard['kpi'] : [];
$ownershipWaiting = is_array($ownershipBoard['items_waiting_for_me'] ?? null) ? $ownershipBoard['items_waiting_for_me'] : [];
$ownershipBlocked = is_array($ownershipBoard['blocked_items'] ?? null) ? $ownershipBoard['blocked_items'] : [];
$ownershipOverdue = is_array($ownershipBoard['items_overdue'] ?? null) ? $ownershipBoard['items_overdue'] : [];
$ownershipBoardTitle = trim((string)($ownership_board_title ?? t('ops.ownership.workboard_title')));
$ownershipBoardEmptyHint = trim((string)($ownership_board_empty_hint ?? ''));
?>
<section class="card">
  <h3 style="margin-top:0"><?= e($ownershipBoardTitle) ?></h3>
  <div class="row" style="gap:10px;flex-wrap:wrap;margin-bottom:10px">
    <div class="card" style="min-width:150px"><div class="muted" style="font-size:.75em"><?= e(t('ops.ownership.waiting_for_me')) ?></div><div style="font-size:1.15rem;font-weight:800"><?= (int)($ownershipKpi['waiting_for_me'] ?? 0) ?></div></div>
    <div class="card" style="min-width:150px"><div class="muted" style="font-size:.75em"><?= e(t('ops.ownership.explicitly_owned')) ?></div><div style="font-size:1.15rem;font-weight:800"><?= (int)($ownershipKpi['owned_by_me'] ?? 0) ?></div></div>
    <div class="card" style="min-width:150px"><div class="muted" style="font-size:.75em"><?= e(t('common.overdue')) ?></div><div style="font-size:1.15rem;font-weight:800;color:#ffd483"><?= (int)($ownershipKpi['overdue'] ?? 0) ?></div></div>
    <div class="card" style="min-width:150px"><div class="muted" style="font-size:.75em"><?= e(t('common.blocked')) ?></div><div style="font-size:1.15rem;font-weight:800;color:#ff9d9d"><?= (int)($ownershipKpi['blocked'] ?? 0) ?></div></div>
  </div>

  <?php $ownershipRows = $ownershipWaiting !== [] ? $ownershipWaiting : ($ownershipOverdue !== [] ? $ownershipOverdue : $ownershipBlocked); ?>
  <?php if ($ownershipRows === []): ?>
    <div class="muted"><?= e(t('ops.ownership.no_active_lane_items')) ?></div>
    <?php if ($ownershipBoardEmptyHint !== ''): ?>
      <div class="muted" style="font-size:.82rem;margin-top:4px"><?= e($ownershipBoardEmptyHint) ?></div>
    <?php endif; ?>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><?= e(t('common.item')) ?></th>
            <th><?= e(t('common.stage')) ?></th>
            <th><?= e(t('common.state')) ?></th>
            <th><?= e(t('common.age')) ?></th>
            <th><?= e(t('common.sla')) ?></th>
            <th><?= e(t('common.actions')) ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($ownershipRows as $row): ?>
          <tr>
            <td>
              <?= e((string)($row['item_ref'] ?? '')) ?>
              <?php if (!empty($row['parts_name'])): ?>
                <span class="muted" style="display:block;font-size:.78em"><?= e((string)$row['parts_name']) ?><?php if (!empty($row['parts_number'])): ?> · <?= e((string)$row['parts_number']) ?><?php endif; ?></span>
              <?php endif; ?>
            </td>
            <td><?= e((string)($row['stage_label'] ?? '')) ?></td>
            <td><?= e((string)($row['ownership_state_label'] ?? '')) ?></td>
            <td><?= e(number_format((float)($row['age_hours'] ?? 0), 2, '.', ',')) ?>h</td>
            <td><?= e((string)($row['sla_label'] ?? t('ops.common.state.on_track'))) ?></td>
            <td><a class="btn" href="<?= e((string)($row['detail_url'] ?? '#')) ?>"><?= e(t('common.open')) ?></a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
