<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$inbox          = is_array($inbox ?? null) ? $inbox : [];
$filters        = is_array($inbox['filters'] ?? null) ? $inbox['filters'] : [];
$moduleOptions  = is_array($inbox['module_options'] ?? null) ? $inbox['module_options'] : [];
$summary        = is_array($inbox['summary'] ?? null) ? $inbox['summary'] : [];
$pendingUrgent  = is_array($inbox['pending_urgent'] ?? null) ? $inbox['pending_urgent'] : [];
$pendingRecent  = is_array($inbox['pending_recent'] ?? null) ? $inbox['pending_recent'] : [];
$reworkItems    = is_array($inbox['rework_items'] ?? null) ? $inbox['rework_items'] : [];
$lockedItems    = is_array($inbox['locked_items'] ?? null) ? $inbox['locked_items'] : [];
$recentActivity = is_array($inbox['recent_activity'] ?? null) ? $inbox['recent_activity'] : [];
$hostRegions = is_array($inbox['host_regions'] ?? null) ? $inbox['host_regions'] : [];
$hostHeaderActions = is_array($hostRegions['header_actions'] ?? null) ? $hostRegions['header_actions'] : [];

$moduleFilter = (string)($filters['module'] ?? 'all');
$q            = (string)($filters['q'] ?? '');
$selfUrl      = (string)($_SERVER['REQUEST_URI'] ?? '/ops/approval-inbox');
if ($selfUrl === '' || $selfUrl[0] !== '/') {
    $selfUrl = '/ops/approval-inbox';
}

$moduleLabel = static function(string $module, string $fallback = ''): string {
    return match ($module) {
        'production_plan' => t('nav.production_plans'),
        'qc_entry'        => t('nav.qc_entries'),
        'dispatch_entry'  => t('nav.dispatch_entries'),
        default           => $fallback !== '' ? $fallback : $module,
    };
};

$approvalLabel = static function(string $status): string {
    $n = strtolower(trim($status));
    return match ($n) {
        'pending approval' => t('ops.approval_inbox.status_pending_approval'),
        'approved'         => t('ops.approval_inbox.status_approved'),
        'rejected'         => t('ops.approval_inbox.status_rejected'),
        'reopened'         => t('ops.approval_inbox.status_reopened'),
        'draft'            => t('ops.approval_inbox.status_draft'),
        default            => $status,
    };
};

$actionLabel = static function(string $action): string {
    $n = strtolower(trim(str_replace(['-', '_'], ' ', $action)));
    return match ($n) {
        'open'            => t('ops.approval_inbox.action_open'),
        'submit'          => t('ops.approval_inbox.action_submit'),
        'approve'         => t('ops.approval_inbox.action_approve'),
        'reject'          => t('ops.approval_inbox.action_reject'),
        'reopen'          => t('ops.approval_inbox.action_reopen'),
        'unlock override' => t('ops.approval_inbox.action_unlock_override'),
        'finalize'        => t('ops.approval_inbox.action_finalize'),
        default           => $action,
    };
};

// Human-readable age label from age_hours float
$ageLabel = static function(float $hours): string {
    if ($hours < 1.0) {
        return t('ops.approval_inbox.age_just_now');
    }
    if ($hours < 24.0) {
        return (int)round($hours) . ' ' . t('ops.approval_inbox.age_hours');
    }
    $days = (int)floor($hours / 24.0);
    return $days . ' ' . t('ops.approval_inbox.age_days');
};
?>

<?php
// ── helper: render a single decision item ──────────────────────────
$renderDecisionItem = static function(
    array    $item,
    string   $badgeClass,
    string   $badgeLabel,
    bool     $showActions,
    callable $actionLabel,
    callable $ageLabel,
    string   $selfUrl
): void {
    $ref          = e((string)($item['ref'] ?? ''));
    $modLabel     = e(match ((string)($item['module'] ?? '')) {
        'production_plan' => t('nav.production_plans'),
        'qc_entry'        => t('nav.qc_entries'),
        'dispatch_entry'  => t('nav.dispatch_entries'),
        default           => (string)($item['module_label'] ?? ''),
    });
    $partName     = (string)($item['part_name'] ?? '-');
    $partNum      = (string)($item['part_number'] ?? '');
    $ageHours     = (float)($item['age_hours'] ?? 0.0);
    $ageStr       = e($ageLabel($ageHours));
    $submittedBy  = trim((string)($item['submitted_by'] ?? ''));
    $wfState      = trim((string)($item['workflow_state'] ?? ''));
    $reopenReason = trim((string)($item['reopen_reason'] ?? ''));
    $approvalNote = trim((string)($item['approval_note'] ?? ''));
    $isUrgent     = ($item['urgency_tier'] ?? 'normal') === 'urgent';
    $prevRejected = !empty($item['previously_rejected']);
    $editUrl      = e((string)($item['edit_url'] ?? '#'));
    $actions      = (array)($item['actions'] ?? []);
    ?>
    <li class="ai-item">
      <div class="ai-item-top">
        <span class="ai-ref"><?= $ref ?></span>
        <span class="ai-badge ai-badge-module"><?= $modLabel ?></span>
        <span class="ai-badge <?= e($badgeClass) ?>"><?= e($badgeLabel) ?></span>
        <?php if ($isUrgent): ?>
          <span class="ai-badge ai-badge-urgent"><?= e(t('ops.approval_inbox.urgency_overdue')) ?></span>
        <?php endif; ?>
        <?php if ($prevRejected && !$isUrgent): ?>
          <span class="ai-badge ai-badge-rework"><?= e(t('ops.approval_inbox.previously_rejected')) ?></span>
        <?php endif; ?>
      </div>
      <div class="ai-item-body">
        <div class="ai-part">
          <?= e($partName) ?>
          <?php if ($partNum !== ''): ?><span class="ai-part-sub"> (<?= e($partNum) ?>)</span><?php endif; ?>
        </div>
        <div class="ai-meta">
          <?php if ($wfState !== ''): ?><span><?= e(t('ops.approval_inbox.workflow_at')) ?> <?= e($wfState) ?></span><?php endif; ?>
          <?php if ($submittedBy !== ''): ?><span><?= e(t('ops.approval_inbox.submitted_by')) ?> <?= e($submittedBy) ?></span><?php endif; ?>
          <span><?= $ageStr ?></span>
        </div>
        <?php if ($reopenReason !== ''): ?>
          <div class="ai-context-note ai-ctx-rejection">
            <strong><?= e(t('ops.approval_inbox.reopened_reason_label')) ?></strong><?= e($reopenReason) ?>
          </div>
        <?php elseif ($approvalNote !== ''): ?>
          <div class="ai-context-note">
            <strong><?= e(t('ops.approval_inbox.note_from_last_decision')) ?></strong><?= e($approvalNote) ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="ai-actions">
        <?php foreach ($actions as $action):
            $method = (string)($action['method'] ?? 'link');
            $key    = (string)($action['key'] ?? '');
            $hint   = trim((string)($action['action_hint'] ?? ''));
            if (!$showActions && $method === 'post' && $key !== 'open') { continue; }
            if ($method === 'link'): ?>
              <a class="btn" href="<?= e((string)($action['endpoint'] ?? '#')) ?>"><?= e($actionLabel((string)($action['label'] ?? t('common.open')))) ?></a>
            <?php else: ?>
              <div class="ai-action-group">
                <form method="post" action="<?= e((string)($action['endpoint'] ?? '#')) ?>" class="ai-inline-form">
                  <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                  <?php foreach ((array)($action['post'] ?? []) as $pk => $pv): ?>
                    <input type="hidden" name="<?= e((string)$pk) ?>" value="<?= e((string)$pv) ?>">
                  <?php endforeach; ?>
                  <input type="hidden" name="redirect" value="<?= e($selfUrl) ?>">
                  <?php if (!empty($action['requires_reason'])): ?>
                    <input class="input" type="text" name="reason" required placeholder="<?= e(t('common.remarks')) ?>" title="<?= e($hint) ?>">
                  <?php elseif (!empty($action['supports_note'])): ?>
                    <input class="input" type="text" name="note" placeholder="<?= e(t('ops.approval_inbox.note_optional')) ?>" title="<?= e($hint) ?>">
                  <?php endif; ?>
                  <button class="btn" type="submit"><?= e($actionLabel((string)($action['label'] ?? t('common.apply')))) ?></button>
                </form>
                <?php if ($hint !== ''): ?>
                  <div class="ai-action-hint"><?= e($hint) ?></div>
                <?php endif; ?>
              </div>
            <?php endif;
        endforeach; ?>
      </div>
    </li>
    <?php
};
?>

<!-- ── HEADER ──────────────────────────────────────────────────────── -->
<section class="card">
  <div class="ai-hero">
    <div class="ui-block">
      <h2 class="ai-title"><?= e(t('ops.approval_inbox.title_v2')) ?></h2>
      <div class="ai-sub"><?= e(t('ops.approval_inbox.subtitle_v2')) ?></div>
    </div>
    <div class="ai-nav">
      <a class="btn" href="/"><?= e(t('nav.my_work')) ?></a>
      <a class="btn" href="/apps/manufacturing/handoffs"><?= e(t('nav.cross_role_handoff')) ?></a>
      <?php foreach ($hostHeaderActions as $action): ?>
        <a class="btn" href="<?= e((string)($action['url'] ?? '/')) ?>"><?= e((string)($action['label'] ?? '')) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="ai-kpis">
    <div class="ai-kpi">
      <div class="ai-kpi-label"><?= e(t('ops.approval_inbox.kpi_decision_needed')) ?></div>
      <div class="ai-kpi-value ai-kpi-value-urgent"><?= (int)($summary['urgent'] ?? 0) ?></div>
    </div>
    <div class="ai-kpi">
      <div class="ai-kpi-label"><?= e(t('ops.approval_inbox.kpi_pending')) ?></div>
      <div class="ai-kpi-value ai-kpi-value-pending"><?= (int)($summary['pending'] ?? 0) ?></div>
    </div>
    <div class="ai-kpi">
      <div class="ai-kpi-label"><?= e(t('ops.approval_inbox.kpi_rework')) ?></div>
      <div class="ai-kpi-value ai-kpi-value-rework"><?= (int)($summary['rework'] ?? 0) ?></div>
    </div>
    <div class="ai-kpi">
      <div class="ai-kpi-label"><?= e(t('ops.approval_inbox.kpi_locked')) ?></div>
      <div class="ai-kpi-value ai-kpi-value-locked"><?= (int)($summary['locked'] ?? 0) ?></div>
    </div>
    <div class="ai-kpi">
      <div class="ai-kpi-label"><?= e(t('ops.approval_inbox.recent_activity')) ?></div>
      <div class="ai-kpi-value"><?= (int)($summary['recent_activity'] ?? 0) ?></div>
    </div>
  </div>
</section>

<!-- ── FILTER ──────────────────────────────────────────────────────── -->
<section class="card">
  <form method="get" action="/ops/approval-inbox" class="ai-filter">
    <div class="ui-block">
      <label class="muted u-label-sm"><?= e(t('common.module')) ?></label>
      <select name="module">
        <?php foreach ($moduleOptions as $key => $label): ?>
          <option value="<?= e((string)$key) ?>" <?= $moduleFilter === (string)$key ? 'selected' : '' ?>>
            <?= e(match ((string)$key) {
                'production_plan' => t('nav.production_plans'),
                'qc_entry'        => t('nav.qc_entries'),
                'dispatch_entry'  => t('nav.dispatch_entries'),
                default           => t('ops.approval_inbox.all_modules'),
            }) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="ui-block">
      <label class="muted u-label-sm"><?= e(t('common.search')) ?></label>
      <input class="input" type="text" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('ops.approval_inbox.search_placeholder')) ?>">
    </div>
    <div class="ai-inline-actions">
      <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
      <a class="btn" href="/ops/approval-inbox"><?= e(t('common.reset')) ?></a>
    </div>
  </form>
</section>

<!-- ── SECTION 1: DECISION NEEDED ─────────────────────────────────── -->
<section class="card ai-section">
  <div class="ai-section-header">
    <h3 class="ai-section-title"><?= e(t('ops.approval_inbox.section_decision_needed')) ?></h3>
    <span class="ai-section-count"><?= count($pendingUrgent) ?></span>
  </div>
  <div class="ai-section-desc"><?= e(t('ops.approval_inbox.section_decision_needed_desc')) ?></div>
  <div class="u-mt-10">
    <?php if (empty($pendingUrgent)): ?>
      <div class="ai-empty"><?= e(t('ops.approval_inbox.no_decision_needed')) ?></div>
    <?php else: ?>
      <ul class="ai-list">
        <?php foreach ($pendingUrgent as $item):
            $renderDecisionItem($item, 'ai-badge-urgent', t('ops.approval_inbox.urgency_overdue'), true, $actionLabel, $ageLabel, $selfUrl);
        endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>

<!-- ── SECTION 2: READY TO REVIEW ─────────────────────────────────── -->
<section class="card ai-section">
  <div class="ai-section-header">
    <h3 class="ai-section-title"><?= e(t('ops.approval_inbox.section_ready_to_review')) ?></h3>
    <span class="ai-section-count"><?= count($pendingRecent) ?></span>
  </div>
  <div class="ai-section-desc"><?= e(t('ops.approval_inbox.section_ready_desc')) ?></div>
  <div class="u-mt-10">
    <?php if (empty($pendingRecent)): ?>
      <div class="ai-empty"><?= e(t('ops.approval_inbox.no_ready_review')) ?></div>
    <?php else: ?>
      <ul class="ai-list">
        <?php foreach ($pendingRecent as $item):
            $renderDecisionItem($item, 'ai-badge-pending', t('ops.approval_inbox.pending_badge'), true, $actionLabel, $ageLabel, $selfUrl);
        endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>

<!-- ── SECTION 3: REWORK IN PROGRESS ──────────────────────────────── -->
<section class="card ai-section">
  <div class="ai-section-header">
    <h3 class="ai-section-title"><?= e(t('ops.approval_inbox.section_rework')) ?></h3>
    <span class="ai-section-count"><?= count($reworkItems) ?></span>
  </div>
  <div class="ai-section-desc"><?= e(t('ops.approval_inbox.section_rework_desc')) ?></div>
  <div class="u-mt-10">
    <?php if (empty($reworkItems)): ?>
      <div class="ai-empty"><?= e(t('ops.approval_inbox.no_rework')) ?></div>
    <?php else: ?>
      <ul class="ai-list">
        <?php foreach ($reworkItems as $item):
            $renderDecisionItem($item, 'ai-badge-rework', t('ops.approval_inbox.rework_badge'), true, $actionLabel, $ageLabel, $selfUrl);
        endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>

<!-- ── SECTION 4: RECENTLY DECIDED (locked – collapsed) ───────────── -->
<section class="card ai-section">
  <details class="ai-details" <?= empty($lockedItems) ? 'open' : '' ?>>
    <summary>
      <?= e(t('ops.approval_inbox.section_recently_decided')) ?>
      <span class="ai-section-count u-ml-6"><?= count($lockedItems) ?></span>
      <span class="ai-summary-tail">— <?= e(t('ops.approval_inbox.section_recently_decided_desc')) ?></span>
    </summary>
    <div class="ai-details-body">
      <?php if (empty($lockedItems)): ?>
        <div class="ai-empty"><?= e(t('ops.approval_inbox.no_locked')) ?></div>
      <?php else: ?>
        <ul class="ai-list">
          <?php foreach (array_slice($lockedItems, 0, 30) as $item): ?>
            <li class="ai-item ai-locked-item">
              <div class="ai-item-top">
                <span class="ai-ref"><?= e((string)($item['ref'] ?? '')) ?></span>
                <span class="ai-badge ai-badge-module"><?= e(match ((string)($item['module'] ?? '')) {
                    'production_plan' => t('nav.production_plans'),
                    'qc_entry'        => t('nav.qc_entries'),
                    'dispatch_entry'  => t('nav.dispatch_entries'),
                    default           => (string)($item['module_label'] ?? ''),
                }) ?></span>
                <span class="ai-badge ai-badge-locked"><?= e(t('ops.approval_inbox.locked_badge')) ?></span>
              </div>
              <div class="ai-item-body">
                <div class="ai-part"><?= e((string)($item['part_name'] ?? '-')) ?></div>
                <div class="ai-meta">
                  <?php if (trim((string)($item['workflow_state'] ?? '')) !== ''): ?>
                    <span><?= e((string)($item['workflow_state'] ?? '')) ?></span>
                  <?php endif; ?>
                  <span><?= e($ageLabel((float)($item['age_hours'] ?? 0.0))) ?></span>
                </div>
              </div>
              <div class="ai-actions u-mt-6">
                <a class="btn" href="<?= e((string)($item['edit_url'] ?? '#')) ?>"><?= e(t('ops.approval_inbox.action_open')) ?></a>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </details>
</section>

<!-- ── DECISION TRACEABILITY ──────────────────────────────────────── -->
<section class="card">
  <h2 class="ai-h2-compact"><?= e(t('ops.approval_inbox.recent_activity_section')) ?></h2>
  <div class="u-muted-compact"><?= e(t('ops.approval_inbox.recent_activity_desc')) ?></div>
  <?php if (empty($recentActivity)): ?>
    <div class="ai-empty"><?= e(t('ops.approval_inbox.no_recent')) ?></div>
  <?php else: ?>
    <div class="ai-table-wrap">
      <table class="ai-table">
        <thead>
          <tr>
            <th><?= e(t('ops.approval_inbox.when')) ?></th>
            <th><?= e(t('ops.approval_inbox.record')) ?></th>
            <th><?= e(t('ops.approval_inbox.action')) ?></th>
            <th><?= e(t('ops.approval_inbox.approval_change')) ?></th>
            <th><?= e(t('ops.approval_inbox.actor')) ?></th>
            <th><?= e(t('ops.approval_inbox.reason_note')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentActivity as $row): ?>
            <tr>
              <td class="u-nowrap"><?= e((string)($row['acted_at'] ?? '-')) ?></td>
              <td>
                <a href="<?= e((string)($row['open_url'] ?? '#')) ?>">
                  <?= e(match ((string)($row['module'] ?? '')) {
                      'production_plan' => t('nav.production_plans'),
                      'qc_entry'        => t('nav.qc_entries'),
                      'dispatch_entry'  => t('nav.dispatch_entries'),
                      default           => (string)($row['module_label'] ?? '-'),
                  }) ?> #<?= (int)($row['record_id'] ?? 0) ?>
                </a>
              </td>
              <td><?= e($actionLabel((string)($row['action_name'] ?? '-'))) ?></td>
              <td>
                <?php
                $fromA = (string)($row['from_approval'] ?? '');
                $toA   = (string)($row['to_approval'] ?? '');
                if ($fromA !== $toA): ?>
                  <span class="muted"><?= e($fromA !== '' ? $fromA : '—') ?></span>
                  &rarr; <strong><?= e($toA !== '' ? $toA : '—') ?></strong>
                <?php else: ?>
                  <?= e($toA !== '' ? $toA : '—') ?>
                <?php endif; ?>
                <?php if (!empty($row['to_locked']) && empty($row['from_locked'])): ?>
                  <span class="ai-badge ai-badge-locked ai-badge-gap-left"><?= e(t('ops.approval_inbox.locked_badge')) ?></span>
                <?php endif; ?>
              </td>
              <td class="u-text-xs"><?= e((string)($row['actor_email'] ?? t('common.system'))) ?></td>
              <td class="u-text-xs">
                <?php $rt = trim((string)($row['reason_text'] ?? '')); $nt = trim((string)($row['note_text'] ?? '')); ?>
                <?= $rt !== '' ? e($rt) : '' ?>
                <?php if ($nt !== ''): ?>
                  <?= $rt !== '' ? '<br>' : '' ?><span class="muted"><?= e(t('ops.approval_inbox.note_prefix')) ?>: <?= e($nt) ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
