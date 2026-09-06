<?php
$govMode = (string)($result['mode'] ?? '');
$isPreflight = $govMode === 'preflight';
$isPublishGate = $govMode === 'publish_gate';
if ($result !== null && ($isPreflight || $isPublishGate)):
    $preflightChecks = is_array($result['preflight_checks'] ?? null) ? $result['preflight_checks'] : [];
    $preflightErrors = is_array($result['preflight_errors'] ?? null) ? $result['preflight_errors'] : [];
    $lintChecks      = is_array($result['lint_checks'] ?? null) ? $result['lint_checks'] : [];
    $lintErrors      = is_array($result['lint_errors'] ?? null) ? $result['lint_errors'] : [];
    $gateChecks      = is_array($result['gate_checks'] ?? null) ? $result['gate_checks'] : [];
    $gateErrors      = is_array($result['gate_errors'] ?? null) ? $result['gate_errors'] : [];
    $rollbackPlan    = is_array($result['rollback_plan'] ?? null) ? $result['rollback_plan'] : [];
    $publishDecision = is_array($result['publish_decision'] ?? null) ? $result['publish_decision'] : [];
    $auditWritten    = (bool)($result['audit_written'] ?? false);
    $focusedPlan     = is_array($result['focused_plan'] ?? null) ? $result['focused_plan'] : [];
    $todoSeed        = is_array($result['todo_seed'] ?? null) ? $result['todo_seed'] : [];
    $checkpoints     = is_array($result['checkpoints'] ?? null) ? $result['checkpoints'] : [];
    $govOk           = (bool)($result['ok'] ?? false);
?>

<section class="card" id="gs-governance-panel">
  <h3><?= e($gs('governance_title')) ?></h3>
  <p><strong><?= e($gs('governance_policy')) ?>:</strong> <span class="badge badge--info"><?= e($gs('governance_mode')) ?></span></p>

  <?php if ($isPreflight || $preflightChecks !== []): ?>
  <h4><?= e($gs('preflight_title')) ?></h4>
  <?php if ($preflightChecks !== []): ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th><?= e($gs('check')) ?></th><th><?= e($gs('status')) ?></th><th><?= e($gs('detail')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($preflightChecks as $pc): ?>
            <tr>
              <td><?= e((string)($pc['name'] ?? '')) ?></td>
              <td><?= (bool)($pc['pass'] ?? false) ? e($gs('pass')) : e($gs('fail')) ?></td>
              <td><?= e((string)($pc['context'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?php if ($preflightErrors !== []): ?>
    <ul class="errors-list">
      <?php foreach ($preflightErrors as $pErr): ?><li><?= e((string)$pErr) ?></li><?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="note success"><?= e($gs('preflight_ok')) ?></p>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($isPreflight || $lintChecks !== []): ?>
  <h4><?= e($gs('lint_title')) ?></h4>
  <?php if ($lintChecks !== []): ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th><?= e($gs('check')) ?></th><th><?= e($gs('status')) ?></th><th><?= e($gs('detail')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($lintChecks as $lc): ?>
            <tr>
              <td><?= e((string)($lc['name'] ?? '')) ?></td>
              <td><?= (bool)($lc['pass'] ?? false) ? e($gs('pass')) : e($gs('fail')) ?></td>
              <td><?= e((string)($lc['context'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?php if ($lintErrors !== []): ?>
    <ul class="errors-list">
      <?php foreach ($lintErrors as $lErr): ?><li><?= e((string)$lErr) ?></li><?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="note success"><?= e($gs('lint_ok')) ?></p>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($isPublishGate): ?>
  <h4><?= e($gs('publish_gate_title')) ?></h4>
  <?php if ($gateChecks !== []): ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th><?= e($gs('check')) ?></th><th><?= e($gs('status')) ?></th><th><?= e($gs('detail')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($gateChecks as $gc): ?>
            <tr>
              <td><?= e((string)($gc['name'] ?? '')) ?></td>
              <td><?= (bool)($gc['pass'] ?? false) ? e($gs('pass')) : e($gs('fail')) ?></td>
              <td><?= e((string)($gc['context'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?php if ($gateErrors !== []): ?>
    <ul class="errors-list">
      <?php foreach ($gateErrors as $gErr): ?>
        <?php
          $gateErrorText = t((string)$gErr);
          if ($gateErrorText === (string)$gErr) {
              $gateErrorText = $gs((string)$gErr);
          }
        ?>
        <li><?= e($gateErrorText) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="note success"><?= e($gs('publish_gate_ok')) ?></p>
  <?php endif; ?>
  <?php if ($result['apply_mode'] ?? '' !== ''): ?>
    <p><strong><?= e($gsBatch1('apply_mode_gate')) ?>:</strong> <?= e((string)($result['apply_mode'] ?? '')) ?></p>
  <?php endif; ?>

  <?php if ($rollbackPlan !== []): ?>
  <h4><?= e($gsBatch1('rollback_plan_title')) ?></h4>
  <p><?= e($gsBatch1('rollback_plan_artifacts')) ?>: <?= e((string)($rollbackPlan['artifact_count'] ?? 0)) ?></p>
  <?php if (is_array($rollbackPlan['artifacts'] ?? null) && $rollbackPlan['artifacts'] !== []): ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th><?= e($gs('target_path')) ?></th><th><?= e($gsBatch1('rollback_action')) ?></th><th><?= e($gs('rollback_reversible')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($rollbackPlan['artifacts'] as $ra): ?>
            <tr>
              <td><code><?= e((string)($ra['target_path'] ?? '')) ?></code></td>
              <td><?= e((string)($ra['rollback_action'] ?? '')) ?></td>
              <td><?= (bool)($ra['reversible'] ?? false) ? e($gs('pass')) : e($gs('fail')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($publishDecision !== []): ?>
  <h4><?= e($gs('publish_decision_title')) ?></h4>
  <p><strong><?= e($gs('publish_decision_by')) ?>:</strong> <?= e((string)($publishDecision['decided_by'] ?? '')) ?></p>
  <p><strong><?= e($gs('publish_decision_at')) ?>:</strong> <?= e((string)($publishDecision['decided_at'] ?? '')) ?></p>
  <p><strong><?= e($gs('publish_decision_allowed')) ?>:</strong>
    <?= (bool)($publishDecision['publish_allowed'] ?? false) ? e($gs('pass')) : e($gs('fail')) ?></p>
  <?php endif; ?>

  <p><strong><?= e($gs('audit_written')) ?>:</strong>
    <?= $auditWritten ? e($gs('audit_ok')) : e($gs('audit_failed')) ?></p>
  <?php endif; ?>

  <?php if ($focusedPlan !== [] && isset($focusedPlan['steps'])): ?>
  <h4><?= e($gs('focused_plan_title')) ?></h4>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th><?= e($gs('focused_plan_step')) ?></th><th><?= e($gs('check')) ?></th><th><?= e($gs('focused_plan_gate')) ?></th></tr></thead>
      <tbody>
        <?php foreach ((array)$focusedPlan['steps'] as $fStep): ?>
          <tr>
            <td><?= e((string)($fStep['step'] ?? '')) ?></td>
            <td><?= e(t((string)($fStep['label_key'] ?? ''), (string)($fStep['label_key'] ?? ''))) ?></td>
            <td><code><?= e((string)($fStep['gate'] ?? '')) ?></code></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($checkpoints !== []): ?>
  <h4><?= e($gs('checkpoints_title')) ?></h4>
  <div class="table-wrap">
    <table class="table">
      <thead><tr>
        <th><?= e($gs('checkpoint_label')) ?></th>
        <th><?= e($gs('checkpoint_stage')) ?></th>
        <th><?= e($gs('checkpoint_gates')) ?></th>
      </tr></thead>
      <tbody>
        <?php foreach ($checkpoints as $cp): ?>
          <tr>
            <td><?= e((string)($cp['checkpoint'] ?? '')) ?></td>
            <td><?= e((string)($cp['lifecycle_stage'] ?? '')) ?></td>
            <td><?= e(implode(', ', is_array($cp['required_gates'] ?? null) ? array_map('strval', $cp['required_gates']) : [])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($todoSeed !== []): ?>
  <h4><?= e($gs('todo_title')) ?></h4>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th><?= e($gs('check')) ?></th><th><?= e($gs('status')) ?></th><th><?= e($gs('focused_plan_gate')) ?></th></tr></thead>
      <tbody>
        <?php foreach ($todoSeed as $todo): ?>
          <tr>
            <td><?= e(t((string)($todo['label_key'] ?? ''), (string)($todo['label_key'] ?? ''))) ?></td>
            <td><?= (bool)($todo['completed'] ?? false) ? e($gs('pass')) : e($gs('fail')) ?></td>
            <td><code><?= e((string)($todo['gate'] ?? '')) ?></code></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>
