<?php
declare(strict_types=1);
$envName = function_exists('app_env') ? app_env() : 'production';
$envLabel = match ($envName) {
  'production' => '',
  'staging' => 'STAGING',
  'development', 'dev', 'local', 'test' => 'DEV',
  default => strtoupper($envName),
};
$envProd  = $envName === 'production';
$authLanguageLabel = 'Language';
?>
<?php require APP_ROOT . '/public/views/layouts/auth_header.php'; ?>
<section class="auth-card auth-login-card">
  <div class="auth-header">
    <div class="auth-header-top">
      <div class="auth-brand-block">
        <div class="auth-product-name"><?= e(t('app.name')) ?></div>
        <div class="auth-subtitle"><?= e(t('auth.reset_subtitle')) ?></div>
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

  <form method="post" action="/forgot-password" class="auth-form">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <label class="auth-field">
      <span class="auth-label"><?= e(t('auth.email')) ?></span>
      <input
        class="auth-input"
        type="email"
        name="email"
        value="<?= e((string)($email ?? '')) ?>"
        autocomplete="email"
        required
        autofocus
        placeholder="you@company.com"
      >
    </label>
    <div class="auth-actions">
      <button class="auth-submit" type="submit">
        <span class="auth-submit-label"><?= e(t('auth.send_reset_link')) ?></span>
        <span class="auth-submit-loading" aria-hidden="true" hidden><?= e(t('auth.sending')) ?></span>
      </button>
      <a href="/login" class="auth-forgot"><?= e(t('auth.back_to_login')) ?></a>
    </div>
  </form>
</section>
<?php require APP_ROOT . '/public/views/layouts/auth_footer.php'; ?>
