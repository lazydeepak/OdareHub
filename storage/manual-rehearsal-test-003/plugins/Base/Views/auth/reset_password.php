<?php
declare(strict_types=1);
$envName  = function_exists('app_env') ? app_env() : 'production';
$envLabel = match ($envName) {
  'production' => '',
  'staging' => 'STAGING',
  'development', 'dev', 'local', 'test' => 'DEV',
  default => strtoupper($envName),
};
$envProd  = $envName === 'production';
$policy = is_array($policy ?? null) ? $policy : [];
$minLength = (int)($policy['min_length'] ?? 10);
$requireLowercase = !empty($policy['require_lowercase']);
$requireUppercase = !empty($policy['require_uppercase']);
$requireNumber = !empty($policy['require_number']);
?>
<?php require APP_ROOT . '/public/views/layouts/auth_header.php'; ?>
<section class="auth-card auth-login-card">
  <div class="auth-header">
    <div class="auth-header-top">
      <div class="auth-brand-block">
        <div class="auth-product-name"><?= e(t('app.name')) ?></div>
        <div class="auth-subtitle"><?= e(t('auth.choose_new_password')) ?></div>
      </div>
      <?php if ($envLabel !== ''): ?>
        <span class="auth-badge <?= $envProd ? 'auth-badge-prod' : 'auth-badge-dev' ?>">
          <?= e($envLabel) ?>
        </span>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($message ?? '')): ?>
    <div class="auth-note" role="status"><?= e((string)$message) ?></div>
  <?php endif; ?>

  <?php if (!empty($error ?? '')): ?>
    <div class="auth-error" role="alert"><?= e((string)$error) ?></div>
  <?php endif; ?>

  <?php if (!empty($tokenValid)): ?>
    <form method="post" action="/reset-password" class="auth-form" id="resetPasswordForm" novalidate>
      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
      <input type="hidden" name="token" value="<?= e((string)($token ?? '')) ?>">

      <label class="auth-field">
        <span class="auth-label"><?= e(t('auth.password')) ?></span>
        <input
          class="auth-input"
          type="password"
          name="password"
          id="resetPasswordField"
          autocomplete="new-password"
          required
          minlength="<?= $minLength ?>"
          placeholder="••••••••"
        >
      </label>

      <div class="auth-note u-style-5435712713">
        <strong id="passwordStrengthLabel"><?= e(t('auth.password_strength')) ?>:</strong>
        <span id="passwordStrengthValue">-</span>
      </div>

      <ul class="auth-note u-style-8133255871" id="passwordRuleList">
        <li data-rule="min_length"><?= e(t('auth.password_rule_length', ['count' => $minLength])) ?></li>
        <?php if ($requireLowercase): ?><li data-rule="lowercase"><?= e(t('auth.password_rule_lowercase')) ?></li><?php endif; ?>
        <?php if ($requireUppercase): ?><li data-rule="uppercase"><?= e(t('auth.password_rule_uppercase')) ?></li><?php endif; ?>
        <?php if ($requireNumber): ?><li data-rule="number"><?= e(t('auth.password_rule_number')) ?></li><?php endif; ?>
      </ul>

      <label class="auth-field">
        <span class="auth-label"><?= e(t('auth.confirm_password')) ?></span>
        <input
          class="auth-input"
          type="password"
          name="password_confirm"
          autocomplete="new-password"
          required
          minlength="<?= $minLength ?>"
          placeholder="••••••••"
        >
      </label>

      <div class="auth-actions">
        <button class="auth-submit" type="submit">
          <span class="auth-submit-label"><?= e(t('auth.reset_password_submit')) ?></span>
          <span class="auth-submit-loading" aria-hidden="true" hidden><?= e(t('auth.resetting')) ?></span>
        </button>
        <a href="/login" class="auth-forgot"><?= e(t('auth.back_to_login')) ?></a>
      </div>
    </form>

    <script>
    (function () {
      var field = document.getElementById('resetPasswordField');
      var label = document.getElementById('passwordStrengthValue');
      var list = document.getElementById('passwordRuleList');
      if (!field || !label || !list) {
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

      function score(check) {
        var points = 0;
        if (check.min_length) points += 1;
        if (field.value.length >= Math.max(12, <?= $minLength ?> + 2)) points += 1;
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
          if (result[key]) {
            node.style.color = '#86efac';
          } else {
            node.style.color = '';
          }
        });
        label.textContent = field.value === '' ? '-' : labelFor(score(result));
      }

      field.addEventListener('input', paint);
      paint();
    })();
    </script>
  <?php else: ?>
    <div class="auth-note"><?= e(t('auth.reset_link_invalid')) ?></div>
    <div class="auth-actions">
      <a href="/forgot-password" class="auth-submit u-style-4d657dbfe1"><?= e(t('auth.request_new_link')) ?></a>
    </div>
  <?php endif; ?>
</section>
<?php require APP_ROOT . '/public/views/layouts/auth_footer.php'; ?>
