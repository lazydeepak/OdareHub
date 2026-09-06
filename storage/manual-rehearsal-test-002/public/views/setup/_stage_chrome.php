<?php
$setupStatus = is_array($setupStatus ?? null) ? $setupStatus : [];
$overallStatus = is_array($setupStatus['overall_status'] ?? null) ? $setupStatus['overall_status'] : [];
$currentStage = is_array($setupStatus['current_stage'] ?? null) ? $setupStatus['current_stage'] : [];
$stepStates = array_values(is_array($setupStatus['step_states'] ?? null) ? $setupStatus['step_states'] : []);
$currentAlert = is_array($setupStatus['current_alert'] ?? null) ? $setupStatus['current_alert'] : [];
$recommendedAction = trim((string)($setupStatus['recommended_action'] ?? ''));
$quickActions = array_values(is_array($setupStatus['quick_actions'] ?? null) ? $setupStatus['quick_actions'] : []);
$meta = array_values(is_array($setupStatus['meta'] ?? null) ? $setupStatus['meta'] : []);
$tr = static function (string $key, string $fallback, array $params = []): string {
    if (function_exists('t')) {
        $translated = (string)t($key, $params);
        if ($translated !== '' && $translated !== $key) {
            return $translated;
        }
    }

    if ($params === []) {
        return $fallback;
    }

    $replace = [];
    foreach ($params as $paramKey => $paramValue) {
        $replace['{' . $paramKey . '}'] = (string)$paramValue;
    }

    return strtr($fallback, $replace);
};

if ($stepStates === []) {
    $fallbackSteps = [
        'welcome' => $tr('setup.step_short.welcome', 'Welcome'),
        'readiness' => $tr('setup.step_short.readiness', 'Readiness'),
        'database' => $tr('setup.step_short.database', 'Database'),
        'core_install' => $tr('setup.step_short.core_install', 'Provisioning'),
        'admin' => $tr('setup.step_short.admin', 'Admin'),
        'setup_2fa' => $tr('setup.step_short.setup_2fa', '2FA'),
        'verify' => $tr('setup.step_short.verify', 'Verify'),
    ];
    $marker = 1;
    foreach ($fallbackSteps as $stepKey => $stepLabel) {
        $stepStates[] = [
            'key' => $stepKey,
            'label' => $stepLabel,
            'marker' => (string)$marker,
            'state' => $marker === 1 ? 'current' : 'pending',
            'is_current' => $marker === 1,
            'href' => '',
        ];
        $marker++;
    }
}

$toneClass = static function (string $tone): string {
    return match ($tone) {
        'success' => 'success',
        'warning' => 'warning',
        'danger' => 'danger',
        'info' => 'info',
        default => 'neutral',
    };
};
?>

<section class="card setup-status-bar" role="region" aria-label="<?= e($tr('setup.status.aria', 'Setup status')) ?>">
  <div class="setup-status-grid">
    <div class="setup-status-zone">
      <div class="setup-status-kicker"><?= e($tr('setup.status.section', 'Setup Status')) ?></div>
      <div class="setup-status-head">
        <span class="setup-status-badge" data-tone="<?= e($toneClass((string)($overallStatus['tone'] ?? 'neutral'))) ?>">
          <?= e((string)($overallStatus['label'] ?? $tr('setup.overall.incomplete', 'Setup Incomplete'))) ?>
        </span>
        <div class="setup-status-current"><?= e($tr('setup.status.current', 'Current: {stage}', ['stage' => (string)($currentStage['label'] ?? $tr('setup.step.welcome', 'Welcome'))])) ?></div>
      </div>

      <?php if ($meta !== []): ?>
        <div class="setup-status-meta">
          <?php foreach ($meta as $metaItem): ?>
            <span class="setup-status-meta-pill"><?= e((string)$metaItem) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="setup-status-zone">
      <div class="setup-status-kicker"><?= e($tr('setup.status.progress', 'Progress')) ?></div>
      <div class="setup-status-steps">
        <?php foreach ($stepStates as $step): ?>
          <?php $href = trim((string)($step['href'] ?? '')); ?>
          <?php if ($href !== ''): ?>
            <a
              class="setup-status-step"
              data-state="<?= e((string)($step['state'] ?? 'pending')) ?>"
              href="<?= e($href) ?>"
              <?= !empty($step['is_current']) ? 'aria-current="step"' : '' ?>
            >
              <span class="setup-status-step-num"><?= e((string)($step['marker'] ?? '')) ?></span>
              <span class="setup-status-step-label"><?= e((string)($step['label'] ?? 'Step')) ?></span>
            </a>
          <?php else: ?>
            <span
              class="setup-status-step"
              data-state="<?= e((string)($step['state'] ?? 'pending')) ?>"
              <?= !empty($step['is_current']) ? 'aria-current="step"' : '' ?>
            >
              <span class="setup-status-step-num"><?= e((string)($step['marker'] ?? '')) ?></span>
              <span class="setup-status-step-label"><?= e((string)($step['label'] ?? 'Step')) ?></span>
            </span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="setup-status-zone">
      <div class="setup-status-kicker"><?= e($tr('setup.status.next_action', 'Next Action')) ?></div>
      <div class="setup-status-alert">
        <div class="setup-status-alert-title"><?= e((string)($currentAlert['title'] ?? $tr('setup.status.continue', 'Continue setup'))) ?></div>
        <?php if (trim((string)($currentAlert['summary'] ?? '')) !== ''): ?>
          <div class="setup-status-alert-copy"><?= e((string)($currentAlert['summary'] ?? '')) ?></div>
        <?php endif; ?>
        <?php if ($recommendedAction !== ''): ?>
          <div class="setup-status-alert-copy"><strong><?= e($tr('setup.status.next_prefix', 'Next:')) ?></strong> <?= e($recommendedAction) ?></div>
        <?php endif; ?>
      </div>

      <?php if ($quickActions !== []): ?>
        <div class="setup-status-actions">
          <?php foreach ($quickActions as $action): ?>
            <?php
            $actionTone = (string)($action['tone'] ?? 'default');
            $buttonClass = $actionTone === 'ok' ? 'btn ok' : 'btn';
            ?>
            <?php if ((string)($action['type'] ?? 'link') === 'post'): ?>
              <form method="post" action="<?= e((string)($action['action'] ?? '/setup')) ?>">
                <?php foreach ((array)($action['fields'] ?? []) as $fieldKey => $fieldValue): ?>
                  <input type="hidden" name="<?= e((string)$fieldKey) ?>" value="<?= e((string)$fieldValue) ?>">
                <?php endforeach; ?>
                <button class="<?= e($buttonClass) ?>" type="submit" data-processing-text="<?= e($tr('setup.status.processing', 'Processing...')) ?>"><?= e((string)($action['label'] ?? $tr('setup.status.continue_button', 'Continue'))) ?></button>
              </form>
            <?php else: ?>
              <a class="<?= e($buttonClass) ?>" href="<?= e((string)($action['href'] ?? '/setup')) ?>"><?= e((string)($action['label'] ?? $tr('common.open', 'Open'))) ?></a>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
