<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionExecutionDryRunWorkspaceService;

require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionDryRunWorkspaceService.php';

$executionDryRunRequested = (string)($_GET['deletion_execution_dry_run'] ?? '') === '1';
$executionDryRunChangeSet = isset($changeSetWorkspace['change_set']) && is_array($changeSetWorkspace['change_set'])
    ? $changeSetWorkspace['change_set']
    : null;
$executionDryRunReadiness = isset($executionReadinessWorkspace['assessment']) && is_array($executionReadinessWorkspace['assessment'])
    ? $executionReadinessWorkspace['assessment']
    : null;
$executionDryRunWorkspace = OwnerStructureDeletionExecutionDryRunWorkspaceService::build(
    $impactOwners,
    $impactSelectedOwnerKey,
    $executionDryRunRequested,
    $executionDryRunChangeSet,
    $executionDryRunReadiness
);

$executionDryRunText = static function (string $key): string {
    $lang = function_exists('current_lang') ? (string)current_lang() : 'en';
    $dictionary = [
        'en' => [
            'title' => 'Deletion execution dry run',
            'description' => 'Simulate the exact approved operation sequence, snapshot destination, and verification contract.',
            'run' => 'Run deletion dry run',
            'refresh' => 'Refresh dry run',
            'hide' => 'Hide dry run',
            'idle' => 'Verify execution readiness before running the deletion simulation.',
            'boundary' => 'Simulation only. No file is written, archived, changed, or deleted, and no verification command is executed.',
            'state' => 'Dry-run state',
            'complete' => 'Simulation complete',
            'eligible' => 'Execution eligible at simulation',
            'fingerprint' => 'Dry-run fingerprint',
            'operations' => 'Operations',
            'preflight' => 'Preflight checks',
            'verification' => 'Verification commands',
            'snapshot' => 'Snapshot simulation',
            'source' => 'Bound source evidence',
            'change_set' => 'Change set',
            'plan' => 'Plan',
            'readiness' => 'Readiness',
            'approval' => 'Approval record',
            'check' => 'Check',
            'path' => 'Path',
            'expected' => 'Expected',
            'actual' => 'Actual',
            'passed' => 'Passed',
            'sequence' => 'Sequence',
            'phase' => 'Phase',
            'operation' => 'Operation',
            'simulation' => 'Simulation',
            'dependencies' => 'Dependencies',
            'destination' => 'Destination',
            'exists' => 'Destination exists',
            'command' => 'Command',
            'evidence' => 'Expected evidence',
            'blockers' => 'Blocking reasons',
            'diagnostics' => 'Diagnostics',
            'none' => 'None',
            'status_error' => 'The deletion dry run could not be prepared safely.',
        ],
        'ja' => [
            'title' => '削除実行ドライラン',
            'description' => '承認済みの操作順序、スナップショット先、検証契約を正確にシミュレーションします。',
            'run' => '削除ドライランを実行',
            'refresh' => 'ドライランを更新',
            'hide' => 'ドライランを隠す',
            'idle' => '削除シミュレーションの前に実行準備状態を検証してください。',
            'boundary' => 'シミュレーション専用です。ファイルの書込、変更、アーカイブ、削除、検証コマンド実行は行いません。',
            'state' => 'ドライラン状態',
            'complete' => 'シミュレーション完了',
            'eligible' => 'シミュレーション時の実行適格',
            'fingerprint' => 'ドライランフィンガープリント',
            'operations' => '操作',
            'preflight' => '事前チェック',
            'verification' => '検証コマンド',
            'snapshot' => 'スナップショットシミュレーション',
            'source' => '紐付けられた証拠',
            'change_set' => '変更セット',
            'plan' => '計画',
            'readiness' => '準備状態',
            'approval' => '承認記録',
            'check' => 'チェック',
            'path' => 'パス',
            'expected' => '期待値',
            'actual' => '実際',
            'passed' => '合格',
            'sequence' => '順序',
            'phase' => 'フェーズ',
            'operation' => '操作',
            'simulation' => 'シミュレーション',
            'dependencies' => '依存関係',
            'destination' => '保存先',
            'exists' => '保存先の存在',
            'command' => 'コマンド',
            'evidence' => '期待証拠',
            'blockers' => '阻害理由',
            'diagnostics' => '診断',
            'none' => 'なし',
            'status_error' => '削除ドライランを安全に準備できませんでした。',
        ],
        'ne' => [
            'title' => 'Deletion execution dry run',
            'description' => 'Approved operation sequence, snapshot destination र verification contract simulate गर्नुहोस्।',
            'run' => 'Deletion dry run चलाउनुहोस्',
            'refresh' => 'Dry run पुनः चलाउनुहोस्',
            'hide' => 'Dry run लुकाउनुहोस्',
            'idle' => 'Deletion simulation अघि execution readiness verify गर्नुहोस्।',
            'boundary' => 'Simulation मात्र। कुनै file write, change, archive, delete वा verification command execute हुँदैन।',
            'state' => 'Dry-run state',
            'complete' => 'Simulation complete',
            'eligible' => 'Execution eligible at simulation',
            'fingerprint' => 'Dry-run fingerprint',
            'operations' => 'Operations',
            'preflight' => 'Preflight checks',
            'verification' => 'Verification commands',
            'snapshot' => 'Snapshot simulation',
            'source' => 'Bound source evidence',
            'change_set' => 'Change set',
            'plan' => 'Plan',
            'readiness' => 'Readiness',
            'approval' => 'Approval record',
            'check' => 'Check',
            'path' => 'Path',
            'expected' => 'Expected',
            'actual' => 'Actual',
            'passed' => 'Passed',
            'sequence' => 'Sequence',
            'phase' => 'Phase',
            'operation' => 'Operation',
            'simulation' => 'Simulation',
            'dependencies' => 'Dependencies',
            'destination' => 'Destination',
            'exists' => 'Destination exists',
            'command' => 'Command',
            'evidence' => 'Expected evidence',
            'blockers' => 'Blocking reasons',
            'diagnostics' => 'Diagnostics',
            'none' => 'None',
            'status_error' => 'Deletion dry run सुरक्षित रूपमा तयार भएन।',
        ],
    ];
    $set = isset($dictionary[$lang]) ? $dictionary[$lang] : $dictionary['en'];
    return (string)($set[$key] ?? $dictionary['en'][$key] ?? $key);
};

$executionDryRunStatus = (string)($executionDryRunWorkspace['status'] ?? 'idle');
$executionDryRunSource = isset($executionDryRunWorkspace['source']) && is_array($executionDryRunWorkspace['source']) ? $executionDryRunWorkspace['source'] : [];
$executionDryRunSummary = isset($executionDryRunWorkspace['summary']) && is_array($executionDryRunWorkspace['summary']) ? $executionDryRunWorkspace['summary'] : [];
$executionDryRunPreflight = isset($executionDryRunWorkspace['preflight_checks']) && is_array($executionDryRunWorkspace['preflight_checks']) ? $executionDryRunWorkspace['preflight_checks'] : [];
$executionDryRunSnapshot = isset($executionDryRunWorkspace['snapshot_plan']) && is_array($executionDryRunWorkspace['snapshot_plan']) ? $executionDryRunWorkspace['snapshot_plan'] : [];
$executionDryRunOperations = isset($executionDryRunWorkspace['simulated_operations']) && is_array($executionDryRunWorkspace['simulated_operations']) ? $executionDryRunWorkspace['simulated_operations'] : [];
$executionDryRunVerification = isset($executionDryRunWorkspace['verification_commands']) && is_array($executionDryRunWorkspace['verification_commands']) ? $executionDryRunWorkspace['verification_commands'] : [];
$executionDryRunBlockers = isset($executionDryRunWorkspace['blocking_reasons']) && is_array($executionDryRunWorkspace['blocking_reasons']) ? $executionDryRunWorkspace['blocking_reasons'] : [];
$executionDryRunDiagnostics = isset($executionDryRunWorkspace['diagnostics']) && is_array($executionDryRunWorkspace['diagnostics']) ? $executionDryRunWorkspace['diagnostics'] : [];
$executionDryRunBaseUrl = '/apps/studio/tools/owner-structure-scan?owner=' . rawurlencode($impactSelectedOwnerKey) . '&scan=1&deletion_impact=1&deletion_plan=1&deletion_change_set=1&deletion_approval=1&deletion_execution_readiness=1';
?>
<section class="oss-contract" aria-label="<?= e($executionDryRunText('title')) ?>">
  <header class="oss-section-head">
    <div><h3><?= e($executionDryRunText('title')) ?></h3><span><?= e($executionDryRunText('description')) ?></span></div>
    <span><?= e($executionDryRunText('boundary')) ?></span>
  </header>

  <form method="get" action="/apps/studio/tools/owner-structure-scan" class="oss-owner-form">
    <input type="hidden" name="owner" value="<?= e($impactSelectedOwnerKey) ?>">
    <input type="hidden" name="scan" value="1">
    <input type="hidden" name="deletion_impact" value="1">
    <input type="hidden" name="deletion_plan" value="1">
    <input type="hidden" name="deletion_change_set" value="1">
    <input type="hidden" name="deletion_approval" value="1">
    <input type="hidden" name="deletion_execution_readiness" value="1">
    <button type="submit" name="deletion_execution_dry_run" value="1"><?= e($executionDryRunText($executionDryRunRequested ? 'refresh' : 'run')) ?></button>
    <?php if ($executionDryRunRequested): ?><a href="<?= e($executionDryRunBaseUrl) ?>"><?= e($executionDryRunText('hide')) ?></a><?php endif; ?>
  </form>

  <?php if ($executionDryRunStatus === 'idle'): ?>
    <p class="oss-planner-note"><?= e($executionDryRunText('idle')) ?></p>
  <?php elseif ($executionDryRunStatus === 'error'): ?>
    <p class="oss-owner-error"><strong><?= e($executionDryRunText('status_error')) ?></strong></p>
  <?php else: ?>
    <div class="oss-contract-summary">
      <div class="oss-summary-card"><dt><?= e($executionDryRunText('state')) ?></dt><dd><?= e((string)$executionDryRunWorkspace['dry_run_state']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($executionDryRunText('complete')) ?></dt><dd><?= e((string)$executionDryRunWorkspace['simulation_complete']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($executionDryRunText('eligible')) ?></dt><dd><?= e((string)$executionDryRunWorkspace['execution_eligible_at_simulation']) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($executionDryRunText('operations')) ?></dt><dd><?= e((string)($executionDryRunSummary['operation_count'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($executionDryRunText('preflight')) ?></dt><dd><?= e((string)($executionDryRunSummary['preflight_check_count'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($executionDryRunText('verification')) ?></dt><dd><?= e((string)($executionDryRunSummary['verification_command_count'] ?? 0)) ?></dd></div>
    </div>

    <dl class="oss-reference-facts">
      <div><dt><?= e($executionDryRunText('fingerprint')) ?></dt><dd><code><?= e((string)$executionDryRunWorkspace['dry_run_fingerprint']) ?></code></dd></div>
    </dl>

    <details class="oss-contract-findings" open>
      <summary><?= e($executionDryRunText('source')) ?></summary>
      <dl class="oss-reference-facts">
        <div><dt><?= e($executionDryRunText('change_set')) ?></dt><dd><code><?= e((string)($executionDryRunSource['change_set_fingerprint'] ?? '')) ?></code></dd></div>
        <div><dt><?= e($executionDryRunText('plan')) ?></dt><dd><code><?= e((string)($executionDryRunSource['plan_fingerprint'] ?? '')) ?></code></dd></div>
        <div><dt><?= e($executionDryRunText('readiness')) ?></dt><dd><code><?= e((string)($executionDryRunSource['readiness_fingerprint'] ?? '')) ?></code></dd></div>
        <div><dt><?= e($executionDryRunText('approval')) ?></dt><dd><code><?= e((string)($executionDryRunSource['approval_record_id'] ?? '')) ?></code></dd></div>
      </dl>
    </details>

    <details class="oss-contract-findings" open>
      <summary><?= e($executionDryRunText('preflight')) ?> (<?= e((string)count($executionDryRunPreflight)) ?>)</summary>
      <?php if ($executionDryRunPreflight === []): ?><p class="oss-empty-group"><?= e($executionDryRunText('none')) ?></p><?php else: ?>
        <div class="oss-table-wrap"><table class="oss-finding-table"><thead><tr><th><?= e($executionDryRunText('check')) ?></th><th><?= e($executionDryRunText('path')) ?></th><th><?= e($executionDryRunText('expected')) ?></th><th><?= e($executionDryRunText('actual')) ?></th><th><?= e($executionDryRunText('passed')) ?></th></tr></thead><tbody>
        <?php foreach ($executionDryRunPreflight as $check): if (!is_array($check)) { continue; } ?><tr><td><code><?= e((string)($check['check_type'] ?? '')) ?></code></td><td><code><?= e((string)($check['path'] ?? '')) ?></code></td><td><?= e((string)($check['expected'] ?? '')) ?></td><td><?= e((string)($check['actual'] ?? '')) ?></td><td><?= e((string)($check['passed'] ?? '')) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </details>

    <details class="oss-contract-findings" open>
      <summary><?= e($executionDryRunText('snapshot')) ?></summary>
      <dl class="oss-reference-facts">
        <div><dt><?= e($executionDryRunText('path')) ?></dt><dd><code><?= e((string)($executionDryRunSnapshot['source_path'] ?? '')) ?></code></dd></div>
        <div><dt><?= e($executionDryRunText('destination')) ?></dt><dd><code><?= e((string)($executionDryRunSnapshot['destination_path'] ?? '')) ?></code></dd></div>
        <div><dt><?= e($executionDryRunText('exists')) ?></dt><dd><?= e((string)($executionDryRunSnapshot['destination_exists'] ?? 'unknown')) ?></dd></div>
      </dl>
    </details>

    <details class="oss-contract-findings" open>
      <summary><?= e($executionDryRunText('operations')) ?> (<?= e((string)count($executionDryRunOperations)) ?>)</summary>
      <?php if ($executionDryRunOperations === []): ?><p class="oss-empty-group"><?= e($executionDryRunText('none')) ?></p><?php else: ?>
        <div class="oss-table-wrap"><table class="oss-plan-table"><thead><tr><th><?= e($executionDryRunText('sequence')) ?></th><th><?= e($executionDryRunText('phase')) ?></th><th><?= e($executionDryRunText('operation')) ?></th><th><?= e($executionDryRunText('path')) ?></th><th><?= e($executionDryRunText('simulation')) ?></th><th><?= e($executionDryRunText('dependencies')) ?></th></tr></thead><tbody>
        <?php foreach ($executionDryRunOperations as $operation): if (!is_array($operation)) { continue; } ?><tr><td><?= e((string)($operation['sequence'] ?? '')) ?></td><td><?= e((string)($operation['phase'] ?? '')) ?></td><td><code><?= e((string)($operation['operation_type'] ?? '')) ?></code></td><td><code><?= e((string)($operation['path'] ?? '')) ?></code></td><td><?= e((string)($operation['simulation_action'] ?? '')) ?></td><td><?= e(implode(', ', array_map('strval', is_array($operation['depends_on'] ?? null) ? $operation['depends_on'] : []))) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </details>

    <details class="oss-contract-findings">
      <summary><?= e($executionDryRunText('verification')) ?> (<?= e((string)count($executionDryRunVerification)) ?>)</summary>
      <?php if ($executionDryRunVerification === []): ?><p class="oss-empty-group"><?= e($executionDryRunText('none')) ?></p><?php else: ?>
        <div class="oss-table-wrap"><table class="oss-finding-table"><thead><tr><th><?= e($executionDryRunText('check')) ?></th><th><?= e($executionDryRunText('command')) ?></th><th><?= e($executionDryRunText('evidence')) ?></th></tr></thead><tbody>
        <?php foreach ($executionDryRunVerification as $command): if (!is_array($command)) { continue; } ?><tr><td><code><?= e((string)($command['check_type'] ?? '')) ?></code></td><td><code><?= e((string)($command['command'] ?? '')) ?></code></td><td><?= e((string)($command['expected_evidence'] ?? '')) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </details>

    <details class="oss-contract-findings"<?= $executionDryRunBlockers !== [] ? ' open' : '' ?>><summary><?= e($executionDryRunText('blockers')) ?> (<?= e((string)count($executionDryRunBlockers)) ?>)</summary><?php if ($executionDryRunBlockers === []): ?><p class="oss-empty-group"><?= e($executionDryRunText('none')) ?></p><?php else: ?><ul class="oss-domain-list"><?php foreach ($executionDryRunBlockers as $reason): ?><li><code><?= e((string)$reason) ?></code></li><?php endforeach; ?></ul><?php endif; ?></details>
  <?php endif; ?>

  <?php if ($executionDryRunDiagnostics !== []): ?><details class="oss-contract-findings"><summary><?= e($executionDryRunText('diagnostics')) ?> (<?= e((string)count($executionDryRunDiagnostics)) ?>)</summary><ul class="oss-domain-list"><?php foreach ($executionDryRunDiagnostics as $diagnostic): if (!is_array($diagnostic)) { continue; } ?><li><code><?= e((string)($diagnostic['code'] ?? '')) ?></code> <?= e((string)($diagnostic['message'] ?? '')) ?></li><?php endforeach; ?></ul></details><?php endif; ?>
</section>
