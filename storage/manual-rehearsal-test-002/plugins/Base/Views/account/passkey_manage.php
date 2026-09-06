<?php
declare(strict_types=1);

$flash    = trim((string)($flash    ?? ''));
$error    = trim((string)($error    ?? ''));
$passkeys = is_array($passkeys ?? null) ? $passkeys : [];

$csrfToken = \App\Core\Auth::csrfToken();
?>

<div class="account-shell">
  <section class="card">
    <div class="account-kicker"><?= e((string)__('account.section.security')) ?></div>
    <h2 class="account-title"><?= e((string)__('account.passkey.manage_title')) ?></h2>
    <div class="muted"><?= e((string)__('account.passkey.manage_subtitle')) ?></div>
  </section>

  <?php if ($flash !== ''): ?>
    <section class="card ops-section-card ops-accent-green"><div class="ui-block"><?= e($flash) ?></div></section>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <section class="card ops-section-card ops-accent-red"><div class="ui-block"><?= e($error) ?></div></section>
  <?php endif; ?>

  <section class="account-grid">

    <!-- Registered passkeys -->
    <section class="card account-panel u-style-a66f410a61">
      <div class="account-kicker"><?= e((string)__('account.passkey.list_title')) ?></div>
      <?php if (empty($passkeys)): ?>
        <div class="muted u-style-21568317b9"><?= e((string)__('account.passkey.none_registered')) ?></div>
      <?php else: ?>
        <table class="data-table u-style-9202fe4c7e">
          <thead>
            <tr>
              <th><?= e((string)__('account.passkey.device_name')) ?></th>
              <th><?= e((string)__('account.passkey.registered_at')) ?></th>
              <th><?= e((string)__('account.passkey.last_used')) ?></th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($passkeys as $pk): ?>
              <tr>
                <td><?= e((string)($pk['device_name'] ?? '—')) ?></td>
                <td class="muted"><?= e((string)($pk['created_at']  ?? '')) ?></td>
                <td class="muted"><?= e((string)($pk['last_used_at'] ?? (string)__('account.passkey.never_used'))) ?></td>
                <td>
                  <form method="post" action="/account/passkey/delete" style="display:inline" onsubmit="return confirm(<?= e(json_encode((string)__('account.passkey.delete_confirm'))) ?>)">
                    <input type="hidden" name="csrf"       value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="passkey_id" value="<?= e((string)(int)($pk['id'] ?? 0)) ?>">
                    <button type="submit" class="btn btn-danger"><?= e((string)__('common.delete')) ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

    <!-- Register new passkey -->
    <section class="card account-panel u-style-a66f410a61">
      <div class="account-kicker"><?= e((string)__('account.passkey.add_title')) ?></div>
      <h3 class="account-title"><?= e((string)__('account.passkey.add_subtitle')) ?></h3>
      <div class="muted"><?= e((string)__('account.passkey.add_note')) ?></div>

      <div class="account-form u-style-d6f2af6e0a">
        <label><?= e((string)__('account.passkey.device_label')) ?>
          <input class="input" type="text" id="passkeyDeviceName" maxlength="190" placeholder="<?= e((string)__('account.passkey.device_placeholder')) ?>" autocomplete="off">
        </label>
        <div class="row u-style-9fc529715a">
          <button id="passkeyRegisterBtn" class="btn"><?= e((string)__('account.passkey.add_action')) ?></button>
          <span id="passkeyRegisterStatus" class="muted u-style-6b99de8b69"></span>
        </div>
        <div id="passkeyRegisterError" class="muted u-style-7c6c33fe2c"></div>
      </div>

      <div class="muted u-style-a0bb5873b8">
        <?= e((string)__('account.passkey.browser_support_note')) ?>
      </div>
    </section>

  </section>

  <section class="card">
    <div class="row">
      <a class="btn" href="/account"><?= e((string)__('common.back_to_account')) ?></a>
    </div>
  </section>
</div>

<script>
(function () {
  'use strict';

  // ── Base64url ↔ ArrayBuffer helpers ──────────────────────────────
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

  // ── Register passkey ─────────────────────────────────────────────
  var btn        = document.getElementById('passkeyRegisterBtn');
  var statusEl   = document.getElementById('passkeyRegisterStatus');
  var errorEl    = document.getElementById('passkeyRegisterError');
  var deviceInput= document.getElementById('passkeyDeviceName');

  if (!btn) { return; }

  function showError(msg) {
    errorEl.textContent = msg;
    errorEl.style.display = '';
    statusEl.style.display = 'none';
    btn.disabled = false;
  }

  function showStatus(msg) {
    statusEl.textContent = msg;
    statusEl.style.display = '';
    errorEl.style.display = 'none';
  }

  btn.addEventListener('click', async function () {
    if (!window.PublicKeyCredential) {
      showError(<?= json_encode((string)__('account.passkey.not_supported')) ?>);
      return;
    }

    btn.disabled = true;
    errorEl.style.display = 'none';
    showStatus(<?= json_encode((string)__('account.passkey.status_requesting')) ?>);

    try {
      // 1. Get challenge from server
      var challengeResp = await fetch('/account/passkey/challenge', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({})
      });
      var createOptions = await challengeResp.json();

      if (createOptions.error) {
        showError(createOptions.error);
        return;
      }

      // 2. Decode binary fields
      createOptions.publicKey.challenge = base64urlToBuffer(createOptions.publicKey.challenge);
      createOptions.publicKey.user.id   = base64urlToBuffer(createOptions.publicKey.user.id);
      if (Array.isArray(createOptions.publicKey.excludeCredentials)) {
        createOptions.publicKey.excludeCredentials = createOptions.publicKey.excludeCredentials.map(function (c) {
          return Object.assign({}, c, { id: base64urlToBuffer(c.id) });
        });
      }

      showStatus(<?= json_encode((string)__('account.passkey.status_follow_prompt')) ?>);

      // 3. Create credential via browser
      var credential = await navigator.credentials.create(createOptions);

      showStatus(<?= json_encode((string)__('account.passkey.status_verifying')) ?>);

      // 4. Send to server
      var regResp = await fetch('/account/passkey/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          device_name:       deviceInput ? deviceInput.value.trim() : '',
          clientDataJSON:    bufferToBase64url(credential.response.clientDataJSON),
          attestationObject: bufferToBase64url(credential.response.attestationObject)
        })
      });
      var result = await regResp.json();

      if (result.ok) {
        window.location.href = '/account/passkey/manage';
      } else {
        showError(result.error || <?= json_encode((string)__('account.passkey.error_generic')) ?>);
      }

    } catch (err) {
      if (err.name === 'NotAllowedError') {
        showError(<?= json_encode((string)__('account.passkey.error_cancelled')) ?>);
      } else {
        showError(err.message || <?= json_encode((string)__('account.passkey.error_generic')) ?>);
      }
      btn.disabled = false;
    }
  });
})();
</script>
