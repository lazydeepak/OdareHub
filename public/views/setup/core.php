<?php
$wizard = is_array($wizard ?? null) ? $wizard : [];
$data = is_array($wizard['data'] ?? null) ? $wizard['data'] : [];
$readiness = is_array($wizard['readiness'] ?? null) ? $wizard['readiness'] : [];
$dbTest = is_array($wizard['db_test'] ?? null) ? $wizard['db_test'] : [];
$installRun = is_array($wizard['install_run'] ?? null) ? $wizard['install_run'] : [];
$currentStep = (string)($wizard['current_step'] ?? 'welcome');
$installReady = !empty($wizard['install_ready']);
$installFailed = in_array((string)($installRun['status'] ?? ''), ['failed', 'partial'], true);
$installFailedStep = is_array($installRun['failed_step'] ?? null) ? $installRun['failed_step'] : [];
$suiteSetupResults = is_array($wizard['suite_setup_results'] ?? null) ? $wizard['suite_setup_results'] : [];
$installToken = trim((string)($wizard['install_submit_token'] ?? ''));
$error = trim((string)($wizard['error'] ?? ''));
$notice = trim((string)($wizard['notice'] ?? ''));
$csrf = (string)($csrf ?? '');
$platformProfiles = is_array($platformProfiles ?? null) ? $platformProfiles : [];
$appName = (string)t('app.name');
$setupTwoFaEmail = (string)($_SESSION['setup_email'] ?? '');
$setupTwoFaSecret = (string)($_SESSION['setup_secret'] ?? '');
$setupTwoFaUri = ($setupTwoFaEmail !== '' && $setupTwoFaSecret !== '')
    ? \App\Core\TOTP::provisioningUri(\App\Core\TOTP::defaultIssuer(), $setupTwoFaEmail, $setupTwoFaSecret)
    : '';
$passwordPolicy = (new \App\Services\PasswordPolicyService())->describePolicy();
$passwordMinLength = (int)($passwordPolicy['min_length'] ?? 10);
$setupTwoFaQrImageSrc = trim((string)($qrImageSrc ?? ''));
$setupTwoFaQrError = trim((string)($qrImageError ?? ''));
$selectedPlatformProfileKey = strtolower(trim((string)($data['platform_profile'] ?? 'core_only')));
$selectedPlatformProfile = is_array($platformProfiles[$selectedPlatformProfileKey] ?? null) ? $platformProfiles[$selectedPlatformProfileKey] : null;
$selectedPlatformSuites = is_array($selectedPlatformProfile) ? (array)($selectedPlatformProfile['suites'] ?? []) : [];
$selectedWorkspaceHome = trim((string)($data['workspace_home'] ?? '/'));
$profileSummaryMap = [];
foreach ($platformProfiles as $profileKey => $profileMeta) {
  $normalizedKey = strtolower(trim((string)$profileKey));
  if ($normalizedKey === '' || !is_array($profileMeta)) {
    continue;
  }
  $profileSummaryMap[$normalizedKey] = [
    'label' => (string)($profileMeta['label'] ?? $profileKey),
    'suites' => array_values(array_map('strval', array_keys((array)($profileMeta['suites'] ?? [])))),
  ];
}
$setupStatus = is_array($setupStatus ?? null) ? $setupStatus : [];
$coreStatus = is_array($coreStatus ?? null) ? $coreStatus : [];
$corePlatformRows = array_values((array)($coreStatus['core_platform_apps']['rows'] ?? []));
$corePlatformReady = !empty($coreStatus['core_platform_apps']['all_healthy']);

$readinessChecks = array_values((array)($readiness['checks'] ?? []));
$readinessFailed = array_values(array_filter($readinessChecks, static fn(array $check): bool => (string)($check['status'] ?? '') === 'failed'));
$readinessWarnings = array_values(array_filter($readinessChecks, static fn(array $check): bool => (string)($check['status'] ?? '') === 'warning'));
$readinessPassedCount = count(array_filter($readinessChecks, static fn(array $check): bool => (string)($check['status'] ?? '') === 'passed'));

$dbFieldsTouched = trim((string)($data['db_host'] ?? '')) !== ''
    || trim((string)($data['db_name'] ?? '')) !== ''
    || trim((string)($data['db_user'] ?? '')) !== '';
$dbVerified = !empty($dbTest['ok']);
$dbPasswordConfigured = (string)($data['db_pass_configured'] ?? '') === '1'
    || !empty($wizard['verified_db']['password_configured']);
$dbStatusLabel = $dbVerified
    ? (string)t('setup.database.verified_saved')
    : (string)($dbFieldsTouched ? t('setup.database.needs_test') : t('setup.database.not_tested'));
$dbStatusToneClass = $dbVerified ? 'setup-pill-success' : 'setup-pill-warning';
$checkStatusLabels = [
    'passed' => (string)t('setup.check_status.passed'),
    'completed' => (string)t('setup.check_status.completed'),
    'running' => (string)t('setup.check_status.running'),
    'failed' => (string)t('setup.check_status.failed'),
    'warning' => (string)t('setup.check_status.warning'),
];
$setupJsStrings = [
    'processing' => (string)t('setup.common.processing'),
    'dbVerified' => (string)t('setup.database.status_verified'),
    'dbChanged' => (string)t('setup.database.status_changed'),
    'dbNeedsTest' => (string)t('setup.database.status_needs_test'),
    'dbEnterThenTest' => (string)t('setup.database.status_enter_then_test'),
  'coreModulesOnly' => (string)t('setup.provision.core_modules_only'),
  'profileFallback' => (string)t('setup.provision.core_only'),
];

$statusToneClass = static function (string $status): string {
    return match ($status) {
        'passed', 'completed' => 'setup-pill-success',
        'running' => 'setup-pill-info',
        'failed' => 'setup-pill-danger',
        'warning' => 'setup-pill-warning',
        default => 'setup-pill-neutral',
    };
};

require APP_ROOT . '/public/views/setup/_stage_chrome.php';
?>

<div class="setup-shell">
  <?php if ($error !== ''): ?>
    <section class="card setup-banner setup-banner-danger">
      <strong><?= e(t('setup.banner.action_needed')) ?></strong>
      <div><?= e($error) ?></div>
    </section>
  <?php endif; ?>

  <?php if ($notice !== ''): ?>
    <section class="card setup-banner setup-banner-info">
      <strong><?= e(t('setup.banner.status_update')) ?></strong>
      <div><?= e($notice) ?></div>
    </section>
  <?php endif; ?>

  <?php if ($currentStep === 'welcome'): ?>
    <section class="card setup-panel">
      <div class="setup-panel-head">
        <h3><?= e(t('setup.welcome.title')) ?></h3>
        <p><?= e(t('setup.welcome.subtitle')) ?></p>
      </div>

      <div class="setup-grid-3">
        <div class="setup-item"><h4><?= e(t('setup.welcome.item.readiness_title')) ?></h4><p><?= e(t('setup.welcome.item.readiness_desc')) ?></p></div>
        <div class="setup-item"><h4><?= e(t('setup.welcome.item.database_title')) ?></h4><p><?= e(t('setup.welcome.item.database_desc')) ?></p></div>
        <div class="setup-item"><h4><?= e(t('setup.welcome.item.plan_title')) ?></h4><p><?= e(t('setup.welcome.item.plan_desc')) ?></p></div>
        <div class="setup-item"><h4><?= e(t('setup.welcome.item.bootstrap_title')) ?></h4><p><?= e(t('setup.welcome.item.bootstrap_desc')) ?></p></div>
        <div class="setup-item"><h4><?= e(t('setup.welcome.item.secure_title')) ?></h4><p><?= e(t('setup.welcome.item.secure_desc')) ?></p></div>
        <div class="setup-item"><h4><?= e(t('setup.welcome.item.land_title')) ?></h4><p><?= e(t('setup.welcome.item.land_desc')) ?></p></div>
      </div>

      <div class="setup-callout setup-callout-top">
        <strong><?= e(t('setup.welcome.scope_title')) ?></strong>
        <p><?= e(t('setup.welcome.scope_desc')) ?></p>
      </div>

      <div class="setup-actions">
        <div class="setup-note"><?= e(t('setup.welcome.note')) ?></div>
        <div class="setup-actions-main">
          <form method="post" action="/setup" class="setup-form-inline">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="wizard_action" value="start_setup">
            <button class="btn ok" type="submit" data-processing-text="<?= e(t('setup.common.processing')) ?>"><?= e(t('setup.action.start')) ?></button>
          </form>
        </div>
      </div>
    </section>
  <?php elseif ($currentStep === 'readiness'): ?>
    <section class="card setup-panel">
      <div class="setup-panel-head">
        <h3><?= e(t('setup.readiness.title')) ?></h3>
        <p><?= e(t('setup.readiness.subtitle')) ?></p>
      </div>

      <?php if ($readinessFailed !== []): ?>
        <div class="setup-callout setup-callout-danger setup-callout-spaced">
          <strong><?= e(t('setup.readiness.failed_title', ['count' => count($readinessFailed)])) ?></strong>
          <p><?= e(t('setup.readiness.failed_desc')) ?></p>
        </div>
      <?php elseif (!empty($readiness['can_continue'])): ?>
        <div class="setup-callout setup-callout-success setup-callout-spaced">
          <strong><?= e(t('setup.readiness.ready_title')) ?></strong>
          <p><?= e(t('setup.readiness.ready_desc', ['count' => $readinessPassedCount])) ?></p>
        </div>
      <?php endif; ?>

      <div class="setup-list">
        <?php foreach ($readinessChecks as $check): ?>
          <?php $checkStatus = (string)($check['status'] ?? 'warning'); ?>
          <div class="setup-item">
            <div class="setup-status-row">
              <div class="setup-status-copy">
                <strong><?= e((string)($check['label'] ?? t('common.check'))) ?></strong>
                <div class="muted"><?= e((string)($check['detail'] ?? '')) ?></div>
              </div>
              <span class="setup-pill <?= e($statusToneClass($checkStatus)) ?>"><?= e($checkStatusLabels[$checkStatus] ?? ucfirst($checkStatus)) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="setup-actions">
        <div class="setup-actions-main">
          <form method="post" action="/setup" class="setup-form-inline">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="wizard_action" value="back">
            <input type="hidden" name="back_step" value="welcome">
            <button class="btn" type="submit" data-processing-text="<?= e(t('setup.common.processing')) ?>"><?= e(t('setup.common.back')) ?></button>
          </form>
          <form method="post" action="/setup" class="setup-form-inline">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="wizard_action" value="run_readiness">
            <button class="btn" type="submit" data-processing-text="<?= e(t('setup.common.processing')) ?>"><?= e(t('setup.common.run_check_again')) ?></button>
          </form>
        </div>
        <div class="setup-actions-main">
          <?php if (!empty($readiness['can_continue'])): ?>
            <form method="post" action="/setup" class="setup-form-inline">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="wizard_action" value="continue_readiness">
              <button class="btn ok" type="submit" data-processing-text="<?= e(t('setup.common.processing')) ?>"><?= e(t('setup.readiness.continue_button')) ?></button>
            </form>
            <span class="setup-note"><?= e(t('setup.readiness.next_step_note')) ?></span>
          <?php else: ?>
            <span class="setup-note"><?= e(t('setup.readiness.required_blocked')) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </section>
  <?php elseif ($currentStep === 'database'): ?>
    <section class="card setup-panel">
      <div class="setup-panel-head">
        <h3><?= e(t('setup.database.title')) ?></h3>
        <p><?= e(t('setup.database.subtitle')) ?></p>
      </div>

      <div class="setup-summary setup-summary-spaced">
        <div class="setup-summary-card">
          <strong><?= e(t('setup.database.connection_status')) ?></strong>
          <span class="setup-pill <?= e($dbStatusToneClass) ?>"><?= e($dbStatusLabel) ?></span>
        </div>
        <div class="setup-summary-card">
          <strong><?= e(t('setup.database.what_happens')) ?></strong>
          <span><?= e(t('setup.database.what_happens_desc')) ?></span>
        </div>
      </div>

      <form method="post" action="/setup" class="setup-form-grid" id="databaseStepForm">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="wizard_action" value="test_database">

        <div class="setup-callout">
          <strong><?= e(t('setup.database.connection_details')) ?></strong>
          <p><?= e(t('setup.database.connection_details_desc', ['app' => $appName])) ?></p>
        </div>

        <div class="setup-grid setup-fields">
          <label><?= e(t('setup.database.host')) ?>
            <input type="text" name="db_host" value="<?= e((string)($data['db_host'] ?? 'localhost')) ?>" required>
          </label>
          <label><?= e(t('setup.database.port')) ?>
            <input type="number" name="db_port" value="<?= e((string)($data['db_port'] ?? '3306')) ?>" required>
          </label>
          <label><?= e(t('setup.database.name')) ?>
            <input type="text" name="db_name" value="<?= e((string)($data['db_name'] ?? '')) ?>" required>
          </label>
          <label><?= e(t('setup.database.user')) ?>
            <input type="text" name="db_user" value="<?= e((string)($data['db_user'] ?? '')) ?>" required>
          </label>
          <label><?= e(t('setup.database.password')) ?>
            <input type="password" name="db_pass" value="">
            <small><?= e(t('setup.database.password_hint')) ?></small>
            <small><?= e($dbPasswordConfigured ? t('setup.database.password_configured') : t('setup.database.password_required_state')) ?></small>
          </label>
          <label class="setup-secondary-field"><?= e(t('setup.database.encoding')) ?>
            <input type="text" name="db_charset" value="<?= e((string)($data['db_charset'] ?? 'utf8mb4')) ?>" required>
            <small><?= e(t('setup.database.encoding_hint')) ?></small>
          </label>
        </div>

        <label class="checkbox-label setup-checkbox-top">
          <input type="checkbox" name="db_create_if_missing" value="1"<?= (string)($data['db_create_if_missing'] ?? '1') === '1' ? ' checked' : '' ?>>
          <span><?= e(t('setup.database.create_if_missing')) ?></span>
        </label>

        <?php if ($dbVerified): ?>
          <div class="setup-callout setup-callout-success">
            <strong><?= e(t('setup.database.verified_title')) ?></strong>
            <p><?= e((string)($dbTest['message'] ?? t('setup.database.verified_default'))) ?></p>
          </div>
        <?php else: ?>
          <div class="setup-callout setup-callout-warning">
            <strong><?= e(t('setup.database.test_required_title')) ?></strong>
            <p><?= e(t('setup.database.test_required_desc')) ?></p>
          </div>
        <?php endif; ?>

      </form>

      <div class="setup-actions">
        <div class="setup-actions-main">
          <button class="btn" type="submit" form="databaseBackForm" data-processing-text="<?= e(t('setup.common.processing')) ?>"><?= e(t('setup.common.back')) ?></button>
          <button class="btn ok" type="submit" form="databaseStepForm" data-processing-text="<?= e(t('setup.common.processing')) ?>"><?= e(t('setup.common.test_connection')) ?></button>
        </div>
        <div class="setup-actions-main">
          <button
            class="btn ok"
            type="submit"
            form="databaseContinueForm"
            id="databaseContinueButton"
            data-processing-text="<?= e(t('setup.common.processing')) ?>"
            <?= $dbVerified ? '' : 'disabled' ?>
          ><?= e(t('setup.database.continue_button')) ?></button>
        </div>
      </div>

      <div class="setup-actions setup-actions-tight-top">
        <div class="setup-note" id="databaseContinueStatus">
          <?php if ($dbVerified): ?>
            <?= e(t('setup.database.status_verified')) ?>
          <?php elseif ($dbFieldsTouched): ?>
            <?= e(t('setup.database.status_needs_test')) ?>
          <?php else: ?>
            <?= e(t('setup.database.status_enter_then_test')) ?>
          <?php endif; ?>
        </div>
      </div>

      <form method="post" action="/setup" id="databaseContinueForm" class="setup-form-hidden">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="wizard_action" value="continue_database">
      </form>

      <form method="post" action="/setup" id="databaseBackForm" class="setup-form-hidden">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="wizard_action" value="back">
        <input type="hidden" name="previous_step" value="readiness">
      </form>
    </section>
  <?php elseif ($currentStep === 'core_install'): ?>
    <section class="card setup-panel">
      <div class="setup-panel-head">
        <h3><?= e(t('setup.provision.title')) ?></h3>
        <p><?= e(t('setup.provision.subtitle')) ?></p>
      </div>

      <form method="post" action="/setup" class="setup-form-grid">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="wizard_action" value="continue_core_install">
        <input type="hidden" name="back_step" value="database">

        <div class="setup-grid setup-fields">
          <label><?= e(t('setup.provision.instance_name')) ?>
            <input type="text" name="instance_name" value="<?= e((string)($data['instance_name'] ?? $appName)) ?>" required>
            <small><?= e(t('setup.provision.instance_name_hint')) ?></small>
          </label>
          <label><?= e(t('setup.provision.language')) ?>
            <select name="language" required>
              <?php foreach ($languageOptions as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= ($data['language'] ?? 'en') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <small><?= e(t('setup.provision.language_hint')) ?></small>
          </label>
          <label><?= e(t('setup.provision.number_format')) ?>
            <select name="number_format" required>
              <option value="1,234.56" <?= ($data['number_format'] ?? '1,234.56') === '1,234.56' ? 'selected' : '' ?>>1,234.56 (US / Japan)</option>
              <option value="1.234,56" <?= ($data['number_format'] ?? '1,234.56') === '1.234,56' ? 'selected' : '' ?>>1.234,56 (European)</option>
              <option value="1 234.56" <?= ($data['number_format'] ?? '1,234.56') === '1 234.56' ? 'selected' : '' ?>>1 234.56 (International)</option>
              <option value="12,34,567.89" <?= ($data['number_format'] ?? '1,234.56') === '12,34,567.89' ? 'selected' : '' ?>>12,34,567.89 (India / Nepal)</option>
            </select>
            <small><?= e(t('setup.provision.number_format_hint')) ?></small>
          </label>
          <label><?= e(t('setup.provision.currency')) ?>
            <select name="currency" required>
              <?php foreach ($currencyOptions as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= ($data['currency'] ?? 'JPY') === $code ? 'selected' : '' ?>><?= e($code) ?></option>
              <?php endforeach; ?>
            </select>
            <small><?= e(t('setup.provision.currency_hint')) ?></small>
          </label>
          <label><?= e(t('setup.provision.timezone')) ?>
            <input type="text" name="timezone" value="<?= e((string)($data['timezone'] ?? 'Asia/Tokyo')) ?>" required>
            <small><?= e(t('setup.provision.timezone_hint')) ?></small>
          </label>
          <label><?= e(t('setup.provision.theme')) ?>
            <select name="theme" required>
              <?php foreach ($themeOptions as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= ($data['theme'] ?? 'system-liquid-glass') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <small><?= e(t('setup.provision.theme_hint')) ?></small>
          </label>
          <label><?= e(t('setup.provision.profile')) ?>
            <select name="platform_profile" required>
              <?php foreach ($platformProfiles as $profileKey => $profile): ?>
                <option value="<?= e((string)$profileKey) ?>" <?= $selectedPlatformProfileKey === (string)$profileKey ? 'selected' : '' ?>><?= e((string)($profile['label'] ?? $profileKey)) ?></option>
              <?php endforeach; ?>
            </select>
            <small><?= e(t('setup.provision.profile_hint')) ?></small>
          </label>
          <label><?= e(t('setup.provision.workspace_home')) ?>
            <select name="workspace_home" required>
              <?php foreach ($homeRouteOptions as $route => $label): ?>
                <option value="<?= e($route) ?>" <?= ($data['workspace_home'] ?? '/') === $route ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <small><?= e(t('setup.provision.workspace_home_hint')) ?></small>
          </label>
        </div>

        <div class="setup-summary">
          <div class="setup-summary-card"><strong><?= e(t('setup.provision.selected_profile')) ?></strong><span id="setupSelectedProfileLabel"><?= e((string)($selectedPlatformProfile['label'] ?? t('setup.provision.core_only'))) ?></span></div>
          <div class="setup-summary-card"><strong><?= e(t('setup.provision.provisioned_suites')) ?></strong><span id="setupProvisionedSuitesLabel"><?= e($selectedPlatformSuites === [] ? t('setup.provision.core_modules_only') : implode(', ', array_keys($selectedPlatformSuites))) ?></span></div>
          <div class="setup-summary-card"><strong><?= e(t('setup.provision.first_landing')) ?></strong><span id="setupSelectedWorkspaceHomeLabel"><?= e($selectedWorkspaceHome !== '' ? $selectedWorkspaceHome : '/') ?></span></div>
        </div>

        <div class="setup-actions">
          <div class="setup-actions-main"><button class="btn" type="submit" form="platformBackForm" data-processing-text="<?= e(t('setup.common.processing')) ?>"><?= e(t('setup.common.back')) ?></button></div>
          <div class="setup-actions-main"><button class="btn ok" type="submit" data-processing-text="<?= e(t('setup.common.processing')) ?>"><?= e(t('setup.common.continue')) ?></button></div>
        </div>
      </form>

      <form method="post" action="/setup" id="platformBackForm" class="setup-form-hidden">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="wizard_action" value="back">
        <input type="hidden" name="previous_step" value="database">
      </form>
    </section>
  <?php elseif ($currentStep === 'admin'): ?>
    <section class="card setup-panel">
      <div class="setup-panel-head">
        <h3><?= e(t('setup.admin.title')) ?></h3>
        <p><?= e(t('setup.admin.subtitle')) ?></p>
      </div>

      <div class="setup-callout setup-callout-spaced">
        <strong><?= e(t('setup.admin.security_note_title')) ?></strong>
        <p><?= e(t('setup.admin.security_note_desc')) ?></p>
      </div>

      <div class="setup-callout setup-callout-spaced">
        <strong><?= e(t('setup.admin.provisioning_plan_title')) ?></strong>
        <p><?= e((string)($selectedPlatformProfile['label'] ?? 'Core only')) ?> will be provisioned during bootstrap, including required core modules and any selected suite install, activation, migration, and verification steps.</p>
      </div>

      <?php if ($installReady): ?>
        <div class="setup-callout setup-callout-success setup-callout-spaced">
          <strong><?= e(t('setup.admin.install_ready_title')) ?></strong>
          <p><?= e(t('setup.admin.install_ready_desc')) ?></p>
        </div>
      <?php elseif ($installFailed): ?>
        <div class="setup-callout setup-callout-danger setup-callout-spaced">
          <strong><?= e(t('setup.admin.install_failed_title', ['step' => (string)($installFailedStep['step_label'] ?? t('setup.admin.install_failed_fallback'))])) ?></strong>
          <p><?= e((string)($installFailedStep['error_text'] ?? ($installRun['error_text'] ?? t('setup.admin.install_failed_desc')))) ?></p>
        </div>
      <?php endif; ?>

      <form method="post" action="/setup" class="setup-form-grid">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="wizard_action" value="install_core_platform">
        <input type="hidden" name="back_step" value="core_install">
        <input type="hidden" name="install_submit_token" value="<?= e($installToken) ?>">

        <div class="setup-grid setup-fields">
          <label><?= e(t('setup.admin.email')) ?>
            <input type="email" name="admin_email" value="<?= e((string)($data['admin_email'] ?? '')) ?>" required>
            <small><?= e(t('setup.admin.email_hint')) ?></small>
          </label>
          <label><?= e(t('setup.admin.password')) ?>
            <input type="password" name="admin_password" id="setupAdminPassword" value="" minlength="<?= $passwordMinLength ?>" required>
            <small><?= e(t('setup.admin.password_hint')) ?></small>
          </label>
          <label><?= e(t('setup.admin.confirm_password')) ?>
            <input type="password" name="admin_password_confirm" value="" minlength="<?= $passwordMinLength ?>" required>
            <small><?= e(t('setup.admin.confirm_password_hint')) ?></small>
          </label>
        </div>
        <div class="setup-callout setup-callout-reset-top">
          <strong><?= e(t('auth.password_strength')) ?>: <span id="setupPasswordStrengthValue">-</span></strong>
          <ul id="setupPasswordRuleList" class="setup-password-rule-list">
            <li data-rule="min_length"><?= e(t('auth.password_rule_length', ['count' => $passwordMinLength])) ?></li>
            <?php if (!empty($passwordPolicy['require_lowercase'])): ?><li data-rule="lowercase"><?= e(t('auth.password_rule_lowercase')) ?></li><?php endif; ?>
            <?php if (!empty($passwordPolicy['require_uppercase'])): ?><li data-rule="uppercase"><?= e(t('auth.password_rule_uppercase')) ?></li><?php endif; ?>
            <?php if (!empty($passwordPolicy['require_number'])): ?><li data-rule="number"><?= e(t('auth.password_rule_number')) ?></li><?php endif; ?>
          </ul>
        </div>

        <div class="setup-actions">
          <div class="setup-actions-main"><button class="btn" type="submit" form="adminBackForm" data-processing-text="<?= e(t('setup.common.processing')) ?>"><?= e(t('setup.common.back')) ?></button></div>
          <div class="setup-actions-main"><button class="btn ok" type="submit" data-processing-text="<?= e(t('setup.admin.starting_installation')) ?>"><?= e(t('setup.admin.run_bootstrap')) ?></button></div>
        </div>
      </form>

      <script>
      (function () {
        var field = document.getElementById('setupAdminPassword');
        var label = document.getElementById('setupPasswordStrengthValue');
        var list = document.getElementById('setupPasswordRuleList');
        if (!field || !label || !list) {
          return;
        }

        function checks(value) {
          return {
            min_length: value.length >= <?= $passwordMinLength ?>,
            lowercase: /[a-z]/.test(value),
            uppercase: /[A-Z]/.test(value),
            number: /[0-9]/.test(value),
            special: /[^A-Za-z0-9]/.test(value)
          };
        }

        function score(check) {
          var points = 0;
          if (check.min_length) points += 1;
          if (field.value.length >= Math.max(12, <?= $passwordMinLength ?> + 2)) points += 1;
          if (check.lowercase) points += 1;
          if (check.uppercase) points += 1;
          if (check.number) points += 1;
          if (check.special) points += 1;
          return points;
        }

        function labelFor(points) {
          if (points <= 2) return <?= json_encode(t('auth.password_strength_weak')) ?>;
          if (points <= 4) return <?= json_encode(t('auth.password_strength_fair')) ?>;
          if (points === 5) return <?= json_encode(t('auth.password_strength_strong')) ?>;
          return <?= json_encode(t('auth.password_strength_very_strong')) ?>;
        }

        function paint() {
          var result = checks(field.value);
          Array.prototype.forEach.call(list.querySelectorAll('[data-rule]'), function (node) {
            var key = node.getAttribute('data-rule');
            node.classList.toggle('is-met', !!result[key]);
          });
          label.textContent = field.value === '' ? '-' : labelFor(score(result));
        }

        field.addEventListener('input', paint);
        paint();
      })();
      </script>

      <form method="post" action="/setup" id="adminBackForm" class="setup-form-hidden">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="wizard_action" value="back">
        <input type="hidden" name="previous_step" value="core_install">
      </form>
    </section>
  <?php elseif ($currentStep === 'setup_2fa'): ?>
    <section class="card setup-panel">
      <div class="setup-panel-head">
        <h3><?= e(t('setup.two_fa.title')) ?></h3>
        <p><?= e(t('setup.two_fa.subtitle')) ?></p>
      </div>

      <div class="setup-callout setup-callout-spaced">
        <strong><?= e(t('setup.two_fa.why_title')) ?></strong>
        <p><?= e(t('setup.two_fa.why_desc')) ?></p>
      </div>

      <?php if ($setupTwoFaEmail === '' || $setupTwoFaSecret === ''): ?>
        <div class="setup-callout setup-callout-spaced">
          <strong><?= e(t('setup.two_fa.missing_title')) ?></strong>
          <p><?= e(t('setup.two_fa.missing_desc')) ?></p>
        </div>
      <?php else: ?>
        <div class="setup-2fa-grid">
          <div class="setup-2fa-card setup-qr-card-centered">
            <h4 class="setup-inline-heading"><?= e(t('setup.two_fa.scan_title')) ?></h4>
            <?php if ($setupTwoFaQrImageSrc !== ''): ?>
              <img
                src="<?= e($setupTwoFaQrImageSrc) ?>"
                alt="TOTP setup QR code"
                class="setup-2fa-qr"
                loading="eager"
              >
              <p class="setup-inline-copy"><?= e(t('setup.two_fa.scan_desc')) ?></p>
            <?php else: ?>
              <p class="setup-2fa-qr-error setup-inline-error-visible"><?= e(t('setup.two_fa.qr_failed')) ?></p>
            <?php endif; ?>
            <?php if ($setupTwoFaQrError !== ''): ?>
              <div id="setupTwoFaQrError" class="setup-2fa-qr-error setup-inline-error-visible">
                <?= e($setupTwoFaQrError) ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="setup-stack-16">
            <div class="setup-2fa-card">
              <h4><?= e(t('setup.two_fa.manual_title')) ?></h4>
              <p><?= e(t('setup.two_fa.manual_desc')) ?></p>
              <div class="setup-2fa-key"><?= e($setupTwoFaSecret) ?></div>
              <details class="setup-details-top">
                <summary class="setup-note"><?= e(t('setup.two_fa.technical_uri')) ?></summary>
                <div class="setup-2fa-key setup-uri-compact"><?= e($setupTwoFaUri) ?></div>
              </details>
            </div>

            <div class="setup-2fa-card">
              <h4><?= e(t('setup.two_fa.verify_title')) ?></h4>
              <p><?= e(t('setup.two_fa.verify_desc')) ?></p>

              <?php if ($error !== ''): ?>
                <div class="setup-callout setup-callout-danger setup-details-top">
                  <strong><?= e(t('setup.two_fa.error_title')) ?></strong>
                  <p><?= e($error) ?></p>
                </div>
              <?php endif; ?>

              <form method="post" action="/setup/2fa" class="setup-fields setup-stack-16-compact">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <label><?= e(t('setup.two_fa.code')) ?>
                  <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
                  <small><?= e(t('setup.two_fa.code_hint', ['email' => $setupTwoFaEmail])) ?></small>
                </label>
                <div class="setup-actions setup-actions-reset-top">
                  <div class="setup-actions-main">
                    <button class="btn ok" type="submit" data-processing-text="<?= e(t('auth.verifying')) ?>"><?= e(t('setup.common.verify_and_continue')) ?></button>
                    <a class="btn" href="/setup/2fa"><?= e(t('setup.two_fa.open_standalone')) ?></a>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </section>
  <?php elseif ($currentStep === 'verify'): ?>
    <section class="card setup-panel">
      <div class="setup-panel-head">
        <h3><?= e(t('setup.verify.title')) ?></h3>
        <p><?= e(t('setup.verify.subtitle', ['app' => $appName])) ?></p>
      </div>

      <div class="setup-phase-progress setup-summary-spaced">
        <div class="setup-phase-step" data-state="done">
          <div class="setup-phase-num">Phase 1</div>
          <div class="setup-phase-label"><?= e(t('setup.verify.phase_one')) ?></div>
        </div>
        <div class="setup-phase-step" data-state="active">
          <div class="setup-phase-num">Phase 2</div>
          <div class="setup-phase-label"><?= e(t('setup.verify.phase_two')) ?></div>
        </div>
        <div class="setup-phase-step" data-state="future">
          <div class="setup-phase-num">Phase 3</div>
          <div class="setup-phase-label"><?= e(t('setup.verify.phase_three')) ?></div>
        </div>
      </div>

      <div class="setup-summary setup-summary-spaced">
        <div class="setup-summary-card"><strong><?= e(t('setup.verify.platform')) ?></strong><span><?= e((string)($data['instance_name'] ?? $appName)) ?></span></div>
        <div class="setup-summary-card"><strong><?= e(t('setup.verify.profile')) ?></strong><span><?= e((string)($selectedPlatformProfile['label'] ?? t('setup.provision.core_only'))) ?></span></div>
        <div class="setup-summary-card"><strong><?= e(t('setup.verify.timezone')) ?></strong><span><?= e((string)($data['timezone'] ?? 'Asia/Tokyo')) ?></span></div>
        <div class="setup-summary-card"><strong><?= e(t('setup.verify.administrator')) ?></strong><span><?= e((string)($data['admin_email'] ?? '')) ?></span></div>
      </div>

      <div class="setup-callout setup-callout-spaced">
        <strong><?= e(t('setup.verify.next_title')) ?></strong>
        <p><?= e(t('setup.verify.next_desc')) ?></p>
      </div>

      <div class="setup-item setup-verify-item">
        <h4><?= e(t('setup.verify.summary_title')) ?></h4>
        <p class="setup-verify-intro"><?= e(t('setup.verify.summary_intro')) ?></p>
        <div class="setup-list setup-verify-list-tight">
          <div class="muted">
            <?= e(t('setup.verify.core_ready')) ?>
            <?php if ($corePlatformRows !== []): ?>
              (<?= (int)($coreStatus['core_platform_apps']['healthy'] ?? 0) ?>/<?= (int)($coreStatus['core_platform_apps']['total'] ?? 0) ?> healthy)
            <?php endif; ?>
          </div>
          <?php if ($suiteSetupResults === []): ?>
            <div class="muted"><?= e(t('setup.verify.no_additional_suites')) ?></div>
          <?php else: ?>
            <?php foreach ($suiteSetupResults as $suiteKey => $suiteResult): ?>
              <div class="muted"><?= e(t('setup.verify.suite_ready', ['suite' => ucfirst((string)$suiteKey)])) ?></div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($corePlatformRows !== []): ?>
        <div class="setup-item setup-verify-item">
          <div class="setup-status-row setup-status-row-spaced">
            <div class="setup-status-copy">
              <strong>Core / Platform Apps</strong>
              <div class="muted">Pulled from the same Core / Platform Apps registry grouping used in Admin Tools.</div>
            </div>
            <span class="setup-pill <?= e($corePlatformReady ? 'setup-pill-success' : 'setup-pill-warning') ?>">
              <?= (int)($coreStatus['core_platform_apps']['healthy'] ?? 0) ?>/<?= (int)($coreStatus['core_platform_apps']['total'] ?? 0) ?> healthy
            </span>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Module</th>
                  <th>Installed</th>
                  <th>Schema</th>
                  <th>Active</th>
                  <th>Healthy</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($corePlatformRows as $row): ?>
                  <?php $rowHealthy = !empty($row['success_tick']); ?>
                  <tr>
                    <td>
                      <strong><?= e((string)($row['display_name'] ?? $row['name'] ?? '')) ?></strong>
                      <?php if (!empty($row['name'])): ?>
                        <div class="muted setup-muted-top"><?= e((string)$row['name']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?= !empty($row['installed']) ? 'Yes' : 'No' ?></td>
                    <td><?= !empty($row['schema_synced']) ? 'Synced' : 'Needs Sync' ?></td>
                    <td><?= !empty($row['active']) ? 'Active' : 'Inactive' ?></td>
                    <td>
                      <?php if ($rowHealthy): ?>
                        <span class="setup-pill setup-pill-success">&#10003; Healthy</span>
                      <?php else: ?>
                        <span class="setup-pill setup-pill-warning"><?= e((string)($row['healthy_label'] ?? 'Needs attention')) ?></span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($rowHealthy): ?>
                        <span class="setup-pill setup-pill-success">&#10003; Ready</span>
                      <?php else: ?>
                        <form method="post" action="/setup" class="setup-form-inline">
                          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                          <input type="hidden" name="wizard_action" value="retry_install">
                          <input type="hidden" name="install_submit_token" value="<?= e($installToken) ?>">
                          <button class="btn" type="submit" data-processing-text="<?= e(t('setup.common.processing')) ?>">Repair Core</button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <div class="setup-actions">
        <?php if ($corePlatformReady): ?>
          <div class="setup-finish-actions">
            <a class="btn ok" href="/"><?= e(t('setup.action.open_my_work')) ?></a>
            <?php if ($selectedWorkspaceHome !== '' && $selectedWorkspaceHome !== '/'): ?>
              <a class="btn" href="<?= e($selectedWorkspaceHome) ?>"><?= e(t('setup.verify.open_selected_workspace')) ?></a>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="setup-actions-main">
            <form method="post" action="/setup" class="setup-form-inline">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="wizard_action" value="retry_install">
              <input type="hidden" name="install_submit_token" value="<?= e($installToken) ?>">
              <button class="btn ok" type="submit" data-processing-text="<?= e(t('setup.common.processing')) ?>">Run Core Repair</button>
            </form>
          </div>
          <div class="setup-note">Finish verification after every required Core / Platform App is healthy.</div>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>
</div>

<script>
  const setupUiText = <?= json_encode($setupJsStrings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const setupProfileSummaryMap = <?= json_encode($profileSummaryMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  document.addEventListener('submit', function (event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const submitter = event.submitter instanceof HTMLButtonElement ? event.submitter : null;
    if (!submitter || submitter.dataset.processingApplied === '1') return;
    submitter.dataset.processingApplied = '1';
    submitter.dataset.originalText = submitter.textContent || '';
    submitter.textContent = submitter.dataset.processingText || setupUiText.processing;
    submitter.disabled = true;
  });

  (function () {
    const form = document.getElementById('databaseStepForm');
    const continueButton = document.getElementById('databaseContinueButton');
    const status = document.getElementById('databaseContinueStatus');
    if (!(form instanceof HTMLFormElement) || !(continueButton instanceof HTMLButtonElement) || !status) {
      return;
    }

    const trackedNames = ['db_host', 'db_port', 'db_name', 'db_user', 'db_pass', 'db_charset', 'db_create_if_missing'];
    const initialState = trackedNames.map((name) => {
      const field = form.elements.namedItem(name);
      if (field instanceof HTMLInputElement) {
        return field.type === 'checkbox' ? (field.checked ? '1' : '0') : field.value;
      }
      return '';
    }).join('|');
    const wasVerified = !continueButton.disabled;

    const currentState = () => trackedNames.map((name) => {
      const field = form.elements.namedItem(name);
      if (field instanceof HTMLInputElement) {
        return field.type === 'checkbox' ? (field.checked ? '1' : '0') : field.value;
      }
      return '';
    }).join('|');

    const hasMeaningfulInput = () => ['db_host', 'db_name', 'db_user'].some((name) => {
      const field = form.elements.namedItem(name);
      return field instanceof HTMLInputElement && field.value.trim() !== '';
    });

    const syncState = () => {
      const dirty = currentState() !== initialState;
      const enabled = wasVerified && !dirty;
      continueButton.disabled = !enabled;
      if (enabled) {
        status.textContent = setupUiText.dbVerified;
      } else if (wasVerified && dirty) {
        status.textContent = setupUiText.dbChanged;
      } else if (hasMeaningfulInput()) {
        status.textContent = setupUiText.dbNeedsTest;
      } else {
        status.textContent = setupUiText.dbEnterThenTest;
      }
    };

    trackedNames.forEach((name) => {
      const field = form.elements.namedItem(name);
      if (field instanceof HTMLInputElement) {
        field.addEventListener('input', syncState);
        field.addEventListener('change', syncState);
      }
    });

    syncState();
  })();

  (function () {
    const profileSelect = document.querySelector('select[name="platform_profile"]');
    const workspaceSelect = document.querySelector('select[name="workspace_home"]');
    const profileLabel = document.getElementById('setupSelectedProfileLabel');
    const suitesLabel = document.getElementById('setupProvisionedSuitesLabel');
    const workspaceLabel = document.getElementById('setupSelectedWorkspaceHomeLabel');
    if (!(profileSelect instanceof HTMLSelectElement) || !(workspaceSelect instanceof HTMLSelectElement)) {
      return;
    }

    const renderProfileSummary = () => {
      const profileKey = (profileSelect.value || '').toLowerCase().trim();
      const profileMeta = setupProfileSummaryMap[profileKey] || null;
      const profileText = profileMeta && profileMeta.label
        ? profileMeta.label
        : setupUiText.profileFallback;
      const suites = profileMeta && Array.isArray(profileMeta.suites) ? profileMeta.suites : [];
      const suitesText = suites.length > 0 ? suites.join(', ') : setupUiText.coreModulesOnly;
      const workspaceText = workspaceSelect.value && workspaceSelect.value.trim() !== ''
        ? workspaceSelect.value
        : '/';

      if (profileLabel) profileLabel.textContent = profileText;
      if (suitesLabel) suitesLabel.textContent = suitesText;
      if (workspaceLabel) workspaceLabel.textContent = workspaceText;
    };

    profileSelect.addEventListener('change', renderProfileSummary);
    workspaceSelect.addEventListener('change', renderProfileSummary);
    renderProfileSummary();
  })();
</script>
