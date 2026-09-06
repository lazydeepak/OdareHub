<?php
$ownershipSummary = is_array($ownership_summary ?? null) ? $ownership_summary : [];
$ownershipCurrent = is_array($ownershipSummary['current'] ?? null) ? $ownershipSummary['current'] : null;
$ownershipActive = is_array($ownershipSummary['active'] ?? null) ? $ownershipSummary['active'] : [];
$ownershipMetrics = is_array($ownershipSummary['metrics'] ?? null) ? $ownershipSummary['metrics'] : [];
$ownershipTitle = trim((string)($ownership_title ?? t('ops.ownership.summary_title')));

if (!function_exists('ownership_badge_tone')) {
    function ownership_badge_tone(string $state): string
    {
        $state = strtolower(trim($state));
        if ($state === 'breached' || $state === 'blocked' || $state === 'escalated') {
            return 'color:#ff9d9d;border-color:rgba(255,90,90,.35);background:rgba(255,90,90,.12);';
        }
        if ($state === 'overdue' || $state === 'at_risk' || $state === 'waiting_upstream' || $state === 'waiting_downstream') {
            return 'color:#ffd483;border-color:rgba(255,194,72,.35);background:rgba(255,194,72,.12);';
        }
        return 'color:#9fe8bf;border-color:rgba(90,210,130,.35);background:rgba(90,210,130,.12);';
    }
}
?>
<div class="card">
  <h3 style="margin-top:0"><?= e($ownershipTitle) ?></h3>
  <?php if (!empty($ownership_chain_note ?? '')): ?>
    <div class="muted" style="margin-bottom:10px;font-size:.84rem"><?= e((string)($ownership_chain_note ?? '')) ?></div>
  <?php endif; ?>
  <?php if ($ownershipCurrent === null): ?>
    <div class="muted"><?= e(t('ops.ownership.no_active_handoff')) ?></div>
  <?php else: ?>
    <div class="row" style="gap:10px;flex-wrap:wrap;margin-bottom:10px">
      <div class="card" style="min-width:170px"><div class="muted" style="font-size:.75em"><?= e(t('common.current_owner')) ?></div><div style="font-weight:700"><?= e((string)($ownershipCurrent['current_owner_label'] ?? '')) ?></div></div>
      <div class="card" style="min-width:170px"><div class="muted" style="font-size:.75em"><?= e(t('common.ownership_state')) ?></div><div style="font-weight:700"><?= e((string)($ownershipCurrent['ownership_state_label'] ?? '')) ?></div></div>
      <div class="card" style="min-width:170px"><div class="muted" style="font-size:.75em"><?= e(t('common.current_stage')) ?></div><div style="font-weight:700"><?= e((string)($ownershipCurrent['stage_label'] ?? '')) ?></div></div>
      <div class="card" style="min-width:170px"><div class="muted" style="font-size:.75em"><?= e(t('ops.ownership.waiting_stage_age')) ?></div><div style="font-weight:700"><?= e(number_format((float)($ownershipCurrent['age_hours'] ?? 0), 2, '.', ',')) ?>h</div></div>
      <div class="card" style="min-width:170px"><div class="muted" style="font-size:.75em"><?= e(t('common.sla')) ?></div><div><span style="display:inline-block;border:1px solid;border-radius:999px;padding:2px 8px;font-size:.75em;<?= ownership_badge_tone((string)($ownershipCurrent['sla_state'] ?? 'on_track')) ?>"><?= e((string)($ownershipCurrent['sla_label'] ?? t('ops.common.state.on_track'))) ?></span></div></div>
    </div>
    <div class="muted" style="margin-bottom:8px">
      <?= e(t('ops.ownership.last_handoff_action')) ?>: <strong><?= e((string)($ownershipCurrent['last_handoff_action'] ?? '-')) ?></strong>
      <?php if (!empty($ownershipCurrent['last_handoff_at'] ?? '')): ?> <?= e(t('ops.ownership.at')) ?> <?= e((string)$ownershipCurrent['last_handoff_at']) ?><?php endif; ?>
      <?php if (!empty($ownershipCurrent['escalation_indicator'] ?? '')): ?> | <?= e(t('ops.ownership.escalation')) ?>: <strong><?= e((string)$ownershipCurrent['escalation_indicator']) ?></strong><?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($ownershipActive)): ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><?= e(t('common.item')) ?></th>
            <th><?= e(t('common.stage')) ?></th>
            <th><?= e(t('common.owner')) ?></th>
            <th><?= e(t('common.state')) ?></th>
            <th><?= e(t('common.age')) ?></th>
            <th><?= e(t('common.sla')) ?></th>
            <th><?= e(t('common.next_owner')) ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($ownershipActive as $row): ?>
          <tr>
            <td><?= e((string)($row['item_ref'] ?? '')) ?></td>
            <td><?= e((string)($row['stage_label'] ?? '')) ?></td>
            <td><?= e((string)($row['current_owner_label'] ?? '')) ?></td>
            <td><?= e((string)($row['ownership_state_label'] ?? '')) ?></td>
            <td><?= e(number_format((float)($row['age_hours'] ?? 0), 2, '.', ',')) ?>h</td>
            <td><span style="display:inline-block;border:1px solid;border-radius:999px;padding:2px 8px;font-size:.75em;<?= ownership_badge_tone((string)($row['sla_state'] ?? 'on_track')) ?>"><?= e((string)($row['sla_label'] ?? t('ops.common.state.on_track'))) ?></span></td>
            <td><?= e((string)($row['next_owner_role'] ?? '-')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if (!empty($ownershipMetrics)): ?>
    <div class="muted" style="margin-top:8px"><?= e(t('ops.ownership.metrics_line', ['active' => (int)($ownershipMetrics['active'] ?? 0), 'waiting' => (int)($ownershipMetrics['waiting'] ?? 0), 'blocked' => (int)($ownershipMetrics['blocked'] ?? 0), 'overdue' => (int)($ownershipMetrics['overdue'] ?? 0), 'breached' => (int)($ownershipMetrics['breached'] ?? 0)])) ?></div>
  <?php endif; ?>
</div>
