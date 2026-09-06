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
$record = is_array($record ?? null) ? $record : [];
$policy = is_array($policy ?? null) ? $policy : [];
$tokenValid = !empty($tokenValid);
$token = (string)($token ?? '');
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
        <div class="auth-subtitle"><?= e((string)__('account.setup.subtitle')) ?></div>
      </div>
      <?php if ($envLabel !== ''): ?>
        <span class="auth-badge <?= $envProd ? 'auth-badge-prod' : 'auth-badge-dev' ?>"><?= e($envLabel) ?></span>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($message ?? '')): ?>
    <div class="auth-note" role="status"><?= e((string)$message) ?></div>
  <?php endif; ?>
  <?php if (!empty($error ?? '')): ?>
    <div class="auth-error" role="alert"><?= e((string)$error) ?></div>
  <?php endif; ?>

  <?php if ($tokenValid): ?>
    <form method="post" action="/account/setup" class="auth-form" id="accountSetupForm" novalidate>
      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
      <input type="hidden" name="token" value="<?= e($token) ?>">

      <label class="auth-field">
        <span class="auth-label"><?= e((string)__('account.field.display_name')) ?></span>
        <input class="auth-input" type="text" name="display_name" value="<?= e((string)($record['display_name'] ?? '')) ?>" required maxlength="190">
      </label>

      <label class="auth-field">
        <span class="auth-label"><?= e((string)__('auth.email')) ?></span>
        <input class="auth-input" type="email" value="<?= e((string)($record['email'] ?? '')) ?>" readonly>
      </label>

      <label class="auth-field">
        <span class="auth-label"><?= e((string)__('account.field.new_password')) ?></span>
        <input class="auth-input" type="password" name="password" id="setupPasswordField" autocomplete="new-password" required minlength="<?= $minLength ?>">
      </label>

      <div class="auth-note u-style-5435712713">
        <strong id="setupPasswordStrengthLabel"><?= e((string)__('auth.password_strength')) ?>:</strong>
        <span id="setupPasswordStrengthValue">-</span>
      </div>
      <ul class="auth-note u-style-8133255871" id="setupPasswordRules">
        <li data-rule="min_length"><?= e(t('auth.password_rule_length', ['count' => $minLength])) ?></li>
        <?php if ($requireLowercase): ?><li data-rule="lowercase"><?= e((string)__('auth.password_rule_lowercase')) ?></li><?php endif; ?>
        <?php if ($requireUppercase): ?><li data-rule="uppercase"><?= e((string)__('auth.password_rule_uppercase')) ?></li><?php endif; ?>
        <?php if ($requireNumber): ?><li data-rule="number"><?= e((string)__('auth.password_rule_number')) ?></li><?php endif; ?>
      </ul>

      <label class="auth-field">
        <span class="auth-label"><?= e((string)__('auth.confirm_password')) ?></span>
        <input class="auth-input" type="password" name="password_confirm" autocomplete="new-password" required minlength="<?= $minLength ?>">
      </label>

      <div class="auth-actions">
        <button class="auth-submit" type="submit"><?= e((string)__('account.setup.submit')) ?></button>
        <a href="/login" class="auth-forgot"><?= e((string)__('auth.back_to_login')) ?></a>
      </div>
    </form>

    <script>
    (function () {
      var field = document.getElementById('setupPasswordField');
      var label = document.getElementById('setupPasswordStrengthValue');
      var list = document.getElementById('setupPasswordRules');
      if (!field || !label || !list) return;

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

      function paint() {
        var result = checks(field.value);
        Array.prototype.forEach.call(list.querySelectorAll('[data-rule]'), function (node) {
          var key = node.getAttribute('data-rule');
          node.style.color = result[key] ? '#86efac' : '';
        });
        label.textContent = field.value === '' ? '-' : labelFor(score(result, field.value));
      }

      field.addEventListener('input', paint);
      paint();
    })();
    </script>
  <?php else: ?>
    <div class="auth-note"><?= e((string)__('account.setup.invalid')) ?></div>
    <div class="auth-actions">
      <a href="/login" class="auth-submit u-style-4d657dbfe1"><?= e((string)__('auth.back_to_login')) ?></a>
    </div>
  <?php endif; ?>
</section>
<?php require APP_ROOT . '/public/views/layouts/auth_footer.php'; ?>
