<?php
declare(strict_types=1);

$account = is_array($account ?? null) ? $account : [];
$policy = is_array($policy ?? null) ? $policy : [];
$flash = trim((string)($flash ?? ''));
$error = trim((string)($error ?? ''));
$accountBaseUrl = trim((string)($account_base_url ?? '/account'));
if ($accountBaseUrl === '') {
  $accountBaseUrl = '/account';
}

$minLength = (int)($policy['min_length'] ?? 10);
$requireLowercase = !empty($policy['require_lowercase']);
$requireUppercase = !empty($policy['require_uppercase']);
$requireNumber = !empty($policy['require_number']);
$specialBonus = !empty($policy['special_bonus']);

$toneClass = static function (string $key): string {
    return match (strtolower(trim($key))) {
        'ready', 'standard', 'twofa_enabled', 'active' => 'account-pill-ok',
        'invite_pending', 'setup_pending', 'password_setup_pending', 'pending' => 'account-pill-warn',
        'review_needed', 'security_attention', 'suspended', 'disabled' => 'account-pill-danger',
        default => 'account-pill-info',
    };
};
?>

<div class="account-shell">
  <section class="card">
    <div class="account-kicker"><?= e((string)__('common.account')) ?></div>
    <div class="section-head">
      <div class="ui-block">
        <h2 class="u-style-1169661891"><?= e((string)__('account.title')) ?></h2>
        <div class="muted u-style-fe7b4979fe"><?= e((string)__('account.subtitle')) ?></div>
      </div>
    </div>
  </section>

  <?php if ($flash !== ''): ?>
    <section class="card ops-section-card ops-accent-green"><div class="ui-block"><?= e($flash) ?></div></section>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <section class="card ops-section-card ops-accent-red"><div class="ui-block"><?= e($error) ?></div></section>
  <?php endif; ?>

  <section class="account-grid">
    <section class="card account-panel">
      <div class="account-kicker"><?= e((string)__('account.section.profile')) ?></div>
      <h3 class="account-title"><?= e((string)__('account.section.profile_title')) ?></h3>
      <div class="muted"><?= e((string)__('account.section.profile_note')) ?></div>
      <form method="post" action="<?= e($accountBaseUrl) ?>/profile" class="account-form">
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <div class="row">
          <label><?= e((string)__('account.field.display_name')) ?>
            <input class="input" type="text" name="display_name" value="<?= e((string)($account['display_name'] ?? '')) ?>" required>
          </label>
          <label><?= e((string)__('auth.email')) ?>
            <input class="input" type="email" value="<?= e((string)($account['email'] ?? '')) ?>" readonly>
          </label>
        </div>
        <div class="row">
          <label><?= e((string)__('account.field.username')) ?>
            <input class="input" type="text" name="username" value="<?= e((string)($account['username'] ?? '')) ?>">
          </label>
          <label><?= e((string)__('account.field.department')) ?>
            <input class="input" type="text" name="department" value="<?= e((string)($account['department'] ?? '')) ?>">
          </label>
        </div>
        <div class="muted"><?= e((string)__('account.email_change_note')) ?></div>
        <div class="row">
          <button class="btn" type="submit"><?= e((string)__('account.action.save_profile')) ?></button>
        </div>
      </form>
    </section>

    <section class="card account-panel">
      <div class="account-kicker"><?= e((string)__('account.section.password')) ?></div>
      <h3 class="account-title"><?= e((string)__('account.section.password_title')) ?></h3>
      <div class="muted"><?= e((string)__('account.section.password_note')) ?></div>
      <form method="post" action="<?= e($accountBaseUrl) ?>/password" class="account-form" id="myAccountPasswordForm" novalidate>
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <label><?= e((string)__('account.field.current_password')) ?>
          <input class="input" type="password" name="current_password" autocomplete="current-password" required>
        </label>
        <label><?= e((string)__('account.field.new_password')) ?>
          <input class="input" type="password" id="myAccountPasswordField" name="password" autocomplete="new-password" required minlength="<?= $minLength ?>">
        </label>
        <div class="account-strength">
          <div class="account-strength-meter"><div class="account-strength-fill" id="myAccountPasswordStrengthFill"></div></div>
          <div class="ui-block"><strong><?= e((string)__('auth.password_strength')) ?>:</strong> <span id="myAccountPasswordStrengthLabel">-</span></div>
        </div>
        <ul class="account-note-list" id="myAccountPasswordRules">
          <li data-rule="min_length"><?= e(t('auth.password_rule_length', ['count' => $minLength])) ?></li>
          <?php if ($requireLowercase): ?><li data-rule="lowercase"><?= e((string)__('auth.password_rule_lowercase')) ?></li><?php endif; ?>
          <?php if ($requireUppercase): ?><li data-rule="uppercase"><?= e((string)__('auth.password_rule_uppercase')) ?></li><?php endif; ?>
          <?php if ($requireNumber): ?><li data-rule="number"><?= e((string)__('auth.password_rule_number')) ?></li><?php endif; ?>
          <?php if ($specialBonus): ?><li data-rule="special"><?= e((string)__('account.password_special_bonus')) ?></li><?php endif; ?>
        </ul>
        <label><?= e((string)__('auth.confirm_password')) ?>
          <input class="input" type="password" name="password_confirm" autocomplete="new-password" required minlength="<?= $minLength ?>">
        </label>
        <div class="muted"><?= e((string)__('account.password_sessions_note')) ?></div>
        <div class="row">
          <button class="btn" type="submit"><?= e((string)__('account.action.change_password')) ?></button>
        </div>
      </form>
    </section>
  </section>

  <section class="account-grid">
    <section class="card account-panel">
      <div class="account-kicker"><?= e((string)__('account.section.security')) ?></div>
      <h3 class="account-title"><?= e((string)__('account.section.security_title')) ?></h3>
      <div class="account-status-stack">
        <div class="account-status-item">
          <div class="account-meta-label"><?= e((string)__('account.security.password_status')) ?></div>
          <div class="account-meta-value"><?= e((string)($account['password_status_label'] ?? '-')) ?></div>
          <div class="muted"><?= e((string)($account['password_status_note'] ?? '')) ?></div>
        </div>
        <div class="account-status-item">
          <div class="account-meta-label"><?= e((string)__('account.security.twofa_status')) ?></div>
          <div class="account-meta-value">
            <span class="account-pill <?= e($toneClass(!empty($account['twofa_enabled']) ? 'twofa_enabled' : 'setup_pending')) ?>"><?= e((string)($account['twofa_status_label'] ?? 'Not enabled')) ?></span>
          </div>
          <div class="muted"><?= e((string)($account['twofa_status_note'] ?? '')) ?></div>
          <div class="row u-style-d2c171b18b">
            <a class="btn" href="<?= e((string)($account['twofa_action_url'] ?? ($accountBaseUrl . '/2fa/setup'))) ?>"><?= e((string)($account['twofa_action_label'] ?? __('account.action.enable_2fa'))) ?></a>
          </div>
          <div class="muted u-style-fe7b4979fe"><?= e((string)($account['twofa_action_note'] ?? '')) ?></div>
        </div>
        <div class="account-status-item">
          <div class="account-meta-label"><?= e((string)__('account.security.passkey_status')) ?></div>
          <div class="account-meta-value">
            <span class="account-pill <?= e($toneClass((int)($account['passkey_count'] ?? 0) > 0 ? 'standard' : 'pending')) ?>"><?= e((string)($account['passkey_status_label'] ?? 'No passkeys registered')) ?></span>
          </div>
          <div class="muted"><?= e((string)($account['passkey_status_note'] ?? '')) ?></div>
          <div class="row u-style-d2c171b18b">
            <a class="btn" href="/account/passkey/manage"><?= e((string)($account['passkey_action_label'] ?? __('account.action.manage_passkeys'))) ?></a>
          </div>
        </div>
        <div class="account-status-item">
          <div class="account-meta-label"><?= e((string)__('account.status.account')) ?></div>
          <div class="account-meta-value"><span class="account-pill <?= e($toneClass(strtolower((string)($account['account_status'] ?? 'active')))) ?>"><?= e((string)($account['account_status_label'] ?? '-')) ?></span></div>
        </div>
        <div class="account-status-item">
          <div class="account-meta-label"><?= e((string)__('account.status.verification')) ?></div>
          <div class="account-meta-value"><span class="account-pill <?= e($toneClass((string)($account['verification_status_key'] ?? 'ready'))) ?>"><?= e((string)($account['verification_status_label'] ?? '-')) ?></span></div>
          <div class="muted"><?= e((string)($account['verification_status_note'] ?? '')) ?></div>
        </div>
        <div class="account-status-item">
          <div class="account-meta-label"><?= e((string)__('account.status.security')) ?></div>
          <div class="account-meta-value"><span class="account-pill <?= e($toneClass((string)($account['security_status_key'] ?? 'standard'))) ?>"><?= e((string)($account['security_status_label'] ?? '-')) ?></span></div>
          <div class="muted"><?= e((string)($account['security_status_note'] ?? '')) ?></div>
        </div>
      </div>
    </section>
  </section>

  <section class="account-grid">
    <section class="card account-panel">
      <div class="account-kicker"><?= e((string)__('account.section.session')) ?></div>
      <h3 class="account-title"><?= e((string)__('account.section.session_title')) ?></h3>
      <div class="muted"><?= e((string)__('account.section.session_note')) ?></div>
      <div class="account-meta-grid">
        <div class="account-meta-item">
          <div class="account-meta-label"><?= e((string)__('account.session.current_session')) ?></div>
          <div class="account-meta-value"><span class="account-pill account-pill-ok"><?= e((string)__('account.session.active')) ?></span></div>
          <div class="muted u-style-fe7b4979fe"><?= e((string)__('account.session.privacy_note')) ?></div>
        </div>
        <div class="account-meta-item">
          <div class="account-meta-label"><?= e((string)__('account.session.ip_address')) ?></div>
          <div class="account-meta-value"><?= e(trim((string)(($account['session_summary'] ?? [])['ip'] ?? '')) !== '' ? (string)(($account['session_summary'] ?? [])['ip'] ?? '') : '-') ?></div>
        </div>
        <div class="account-meta-item">
          <div class="account-meta-label"><?= e((string)__('account.session.user_agent')) ?></div>
          <div class="account-meta-value"><?= e(trim((string)(($account['session_summary'] ?? [])['device_summary'] ?? '')) !== '' ? (string)(($account['session_summary'] ?? [])['device_summary'] ?? '') : '-') ?></div>
        </div>
        <div class="account-meta-item">
          <div class="account-meta-label"><?= e((string)__('account.session.recent_2fa')) ?></div>
          <div class="account-meta-value"><?= e((string)(($account['session_summary'] ?? [])['recent_2fa_label'] ?? '-')) ?></div>
        </div>
      </div>
      <div class="muted u-style-56f4356299"><?= e((string)__('account.session.reference')) ?>: <?= e(trim((string)(($account['session_summary'] ?? [])['session_reference'] ?? '')) !== '' ? (string)(($account['session_summary'] ?? [])['session_reference'] ?? '') : '-') ?></div>
    </section>
  </section>
</div>

<script>
(function () {
  var field = document.getElementById('myAccountPasswordField');
  var rules = document.getElementById('myAccountPasswordRules');
  var label = document.getElementById('myAccountPasswordStrengthLabel');
  var fill = document.getElementById('myAccountPasswordStrengthFill');
  if (!field || !rules || !label || !fill) {
    return;
  }

  function checks(value) {
    return {
      min_length: value.length >= <?= $minLength ?>,
      lowercase: /[a-z]/.test(value),
      uppercase: /[A-Z]/.test(value),
      number: /[0-9]/.test(value),
      special: /[^A-Za-z0-9]/.test(value)
    };
  }

  function score(check, value) {
    var points = 0;
    if (check.min_length) points += 1;
    if (value.length >= Math.max(12, <?= $minLength ?> + 2)) points += 1;
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

  function colorFor(points) {
    if (points <= 2) return '#ef4444';
    if (points <= 4) return '#f59e0b';
    if (points === 5) return '#22c55e';
    return '#14b8a6';
  }

  function paint() {
    var value = field.value || '';
    var result = checks(value);
    Array.prototype.forEach.call(rules.querySelectorAll('[data-rule]'), function (node) {
      var key = node.getAttribute('data-rule');
      if (result[key]) {
        node.style.color = '#86efac';
      } else {
        node.style.color = '';
      }
    });

    if (value === '') {
      label.textContent = '-';
      fill.style.width = '0';
      fill.style.background = '#475569';
      return;
    }

    var points = score(result, value);
    label.textContent = labelFor(points);
    fill.style.width = Math.min(100, Math.max(16, (points / 6) * 100)) + '%';
    fill.style.background = colorFor(points);
  }

  field.addEventListener('input', paint);
  paint();
})();
</script>
