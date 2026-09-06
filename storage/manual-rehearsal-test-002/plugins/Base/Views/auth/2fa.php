<?php require APP_ROOT . '/public/views/layouts/auth_header.php'; ?>

<section class="auth-card auth-2fa-card">

  <div class="auth-header">
    <div class="auth-product-name"><?= e(t('auth.two_factor_verification')) ?></div>
    <div class="auth-subtitle"><?= e(t('auth.2fa_helper')) ?></div>
  </div>

  <?php if (!empty($error ?? '')): ?>
    <div class="auth-error" role="alert" aria-live="assertive">
      <?= e((string)$error) ?>
    </div>
  <?php endif; ?>

  <form method="post" action="/2fa" class="auth-form" id="twoFaForm" novalidate>
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <input type="hidden" name="redirect" value="<?= e((string)($redirect ?? '')) ?>">

    <label class="auth-field">
      <span class="auth-label"><?= e(t('auth.authentication_code')) ?></span>
      <input
        id="code"
        class="auth-input auth-code-input"
        type="text"
        name="code"
        inputmode="numeric"
        autocomplete="one-time-code"
        pattern="[0-9]{6}"
        maxlength="6"
        placeholder="000 000"
        required
        autofocus
      >
    </label>

    <?php if (!empty($email)): ?>
      <p class="auth-helper"><?= e(t('auth.signed_in_as', ['email' => $email])) ?></p>
    <?php endif; ?>

    <div class="auth-actions">
      <button class="auth-submit" type="submit" id="twoFaBtn">
        <span class="auth-submit-label"><?= e(t('auth.verify_code')) ?></span>
        <span class="auth-submit-loading" aria-hidden="true" hidden><?= e(t('auth.verifying')) ?></span>
      </button>
      <a href="/logout" class="auth-back-link"><?= e(t('auth.back_to_login')) ?></a>
    </div>
  </form>

</section>

<?php require APP_ROOT . '/public/views/layouts/auth_footer.php'; ?>