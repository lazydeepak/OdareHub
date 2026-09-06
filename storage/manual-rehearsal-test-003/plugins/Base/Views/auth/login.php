<?php
declare(strict_types=1);
$envName = function_exists('app_env') ? app_env() : 'production';
$envLabel = match ($envName) {
  'production' => '',
  'staging' => 'STAGING',
  'development', 'dev', 'local', 'test' => 'DEV',
  default => strtoupper($envName),
};
$envProd = $envName === 'production';
?>
<?php require APP_ROOT . '/public/views/layouts/auth_header.php'; ?>
<section class="auth-card auth-login-card">

  <div class="auth-header">
    <div class="auth-header-top">
      <div class="auth-brand-block">
        <div class="auth-product-name"><?= e(t('app.name')) ?></div>
        <div class="auth-subtitle"><?= e(t('auth.platform_subtitle')) ?></div>
      </div>
      <?php if ($envLabel !== ''): ?>
        <span class="auth-badge <?= $envProd ? 'auth-badge-prod' : 'auth-badge-dev' ?>">
          <?= e($envLabel) ?>
        </span>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($error ?? '')): ?>
    <div class="auth-error" role="alert" aria-live="assertive">
      <?= e((string)$error) ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($notice ?? '')): ?>
    <div class="auth-note" role="status">
      <?= e((string)$notice) ?>
    </div>
  <?php endif; ?>

  <form method="post" action="/login" class="auth-form" id="loginForm" novalidate>
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <input type="hidden" name="redirect" value="<?= e((string)($redirect ?? '')) ?>">

    <label class="auth-field">
      <span class="auth-label"><?= e(t('auth.email')) ?></span>
      <input
        class="auth-input"
        type="email"
        name="email"
        id="loginEmail"
        value="<?= e((string)($email ?? '')) ?>"
        autocomplete="email"
        autofocus
        required
      >
    </label>

    <label class="auth-field">
      <span class="auth-label"><?= e(t('auth.password')) ?></span>
      <div class="auth-pw-wrap">
        <input
          class="auth-input"
          type="password"
          name="password"
          id="loginPassword"
          autocomplete="current-password"
          required
        >
        <button type="button" class="auth-pw-toggle" id="pwToggle" aria-label="<?= e(t('auth.password')) ?>" aria-pressed="false" tabindex="-1">
          <svg class="auth-pw-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="M10 4C5.5 4 1.7 7.1 1 10c.7 2.9 4.5 6 9 6s8.3-3.1 9-6c-.7-2.9-4.5-6-9-6z" stroke="currentColor" stroke-width="1.5"/>
            <circle cx="10" cy="10" r="2.5" stroke="currentColor" stroke-width="1.5"/>
            <path class="auth-pw-icon-slash" d="M3 3l14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
        </button>
      </div>
    </label>

    <div class="auth-actions">
      <button class="auth-submit" type="submit" id="loginBtn">
        <span class="auth-submit-label"><?= e(t('auth.sign_in')) ?></span>
        <span class="auth-submit-loading" aria-hidden="true" hidden><?= e(t('auth.signing_in')) ?></span>
      </button>
      <a href="/forgot-password" class="auth-forgot"><?= e(t('auth.forgot_password')) ?></a>
    </div>
  </form>

  <div class="auth-note u-style-d2c171b18b">
    <button type="button" id="passkeyLoginBtn" class="auth-forgot u-style-785038327f"><?= e((string)__('auth.sign_in_with_passkey')) ?></button>
    <span id="passkeyLoginStatus" class="muted u-style-6b5be2552e"></span>
  </div>
  <div class="u-style-8a3ba92f75" id="passkeyLoginError"></div>

  <?php if (\App\Core\Auth::noAdminExists()): ?>
    <div class="auth-note muted">
      <?= e(t('auth.no_admin_hint')) ?> <a href="/setup">/setup</a>
    </div>
  <?php endif; ?>

</section>

<script>
(function () {
  var toggle = document.getElementById('pwToggle');
  var pwField = document.getElementById('loginPassword');

  // Password show/hide toggle
  if (toggle && pwField) {
    toggle.addEventListener('click', function () {
      var isText = pwField.type === 'text';
      pwField.type = isText ? 'password' : 'text';
      toggle.querySelector('.auth-pw-icon').classList.toggle('is-password-visible', !isText);
      toggle.setAttribute('aria-pressed', isText ? 'false' : 'true');
    });
  }
})();
</script>
<script>
(function () {
  'use strict';

  function base64urlToBuffer(encodedValue) {
    var b64 = String(encodedValue || '');
    var mimeBinaryMatch = b64.match(/^=\?BINARY\?B\?([A-Za-z0-9+/=]+)\?=$/i);
    if (mimeBinaryMatch) {
      b64 = mimeBinaryMatch[1];
    }
    b64 = b64.replace(/-/g, '+').replace(/_/g, '/');
    while (b64.length % 4) { b64 += '='; }
    var binary = atob(b64);
    var buf = new Uint8Array(binary.length);
    for (var i = 0; i < binary.length; i++) { buf[i] = binary.charCodeAt(i); }
    return buf.buffer;
  }

  function bufferToBase64url(buffer) {
    var bytes = new Uint8Array(buffer);
    var binary = '';
    for (var i = 0; i < bytes.byteLength; i++) { binary += String.fromCharCode(bytes[i]); }
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  var passkeyBtn   = document.getElementById('passkeyLoginBtn');
  var statusEl     = document.getElementById('passkeyLoginStatus');
  var errorEl      = document.getElementById('passkeyLoginError');
  var emailInput   = document.getElementById('loginEmail');

  if (!passkeyBtn) { return; }

  if (!window.PublicKeyCredential) {
    passkeyBtn.disabled = true;
    passkeyBtn.style.opacity = '0.5';
    passkeyBtn.style.cursor = 'not-allowed';
    passkeyBtn.title = <?= json_encode((string)__('account.passkey.not_supported')) ?>;
    return;
  }

  function showError(msg) {
    errorEl.textContent = msg;
    errorEl.style.display = '';
    statusEl.style.display = 'none';
    passkeyBtn.disabled = false;
  }

  function showStatus(msg) {
    statusEl.textContent = msg;
    statusEl.style.display = '';
    errorEl.style.display = 'none';
  }

  passkeyBtn.addEventListener('click', async function () {
    passkeyBtn.disabled = true;
    errorEl.style.display = 'none';
    showStatus(<?= json_encode((string)__('account.passkey.status_requesting')) ?>);

    try {
      var email = emailInput ? emailInput.value.trim() : '';

      // 1. Get authentication challenge
      var challengeResp = await fetch('/passkey/challenge', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email })
      });
      var getOptions = await challengeResp.json();

      if (getOptions.error) {
        showError(getOptions.error);
        return;
      }

      // 2. Decode binary fields
      getOptions.publicKey.challenge = base64urlToBuffer(getOptions.publicKey.challenge);
      if (Array.isArray(getOptions.publicKey.allowCredentials)) {
        getOptions.publicKey.allowCredentials = getOptions.publicKey.allowCredentials.map(function (c) {
          return Object.assign({}, c, { id: base64urlToBuffer(c.id) });
        });
      }

      showStatus(<?= json_encode((string)__('account.passkey.status_follow_prompt')) ?>);

      // 3. Authenticate via browser
      var assertion = await navigator.credentials.get(getOptions);

      showStatus(<?= json_encode((string)__('account.passkey.status_verifying')) ?>);

      // 4. Send assertion to server
      var authResp = await fetch('/passkey/authenticate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          rawId:             bufferToBase64url(assertion.rawId),
          clientDataJSON:    bufferToBase64url(assertion.response.clientDataJSON),
          authenticatorData: bufferToBase64url(assertion.response.authenticatorData),
          signature:         bufferToBase64url(assertion.response.signature),
          userHandle:        assertion.response.userHandle ? bufferToBase64url(assertion.response.userHandle) : null
        })
      });
      var result = await authResp.json();

      if (result.ok) {
        window.location.href = result.redirect || '/';
      } else {
        showError(result.error || <?= json_encode((string)__('account.passkey.error_generic')) ?>);
      }

    } catch (err) {
      if (err.name === 'NotAllowedError') {
        showError(<?= json_encode((string)__('account.passkey.error_cancelled')) ?>);
      } else {
        showError(err.message || <?= json_encode((string)__('account.passkey.error_generic')) ?>);
      }
      passkeyBtn.disabled = false;
    }
  });
})();
</script>
<?php require APP_ROOT . '/public/views/layouts/auth_footer.php'; ?>
