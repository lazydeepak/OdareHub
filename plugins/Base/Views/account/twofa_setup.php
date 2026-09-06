<?php
declare(strict_types=1);

$flash = trim((string)($flash ?? ''));
$error = trim((string)($error ?? ''));
$email = trim((string)($email ?? ''));
$secret = trim((string)($secret ?? ''));
$uri = trim((string)($uri ?? ''));
$qrPayload = is_array($qr_payload ?? null) ? $qr_payload : ['ok' => false, 'data_uri' => '', 'error' => ''];
?>

<div class="account-shell">
  <section class="card">
    <div class="account-kicker"><?= e((string)__('account.section.security')) ?></div>
    <h2 class="account-title"><?= e((string)t('setup.two_fa.title')) ?></h2>
    <div class="muted"><?= e((string)t('setup.two_fa.subtitle')) ?></div>
  </section>

  <?php if ($flash !== ''): ?>
    <section class="card ops-section-card ops-accent-green"><div class="ui-block"><?= e($flash) ?></div></section>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <section class="card ops-section-card ops-accent-red"><div class="ui-block"><?= e($error) ?></div></section>
  <?php endif; ?>

  <section class="account-grid">
    <section class="card account-panel">
      <div class="account-kicker"><?= e((string)t('setup.two_fa.scan_title')) ?></div>
      <h3 class="account-title"><?= e((string)t('setup.two_fa.scan_desc')) ?></h3>

      <?php if (!empty($qrPayload['ok']) && trim((string)($qrPayload['data_uri'] ?? '')) !== ''): ?>
        <div class="u-mt-10">
          <img src="<?= e((string)$qrPayload['data_uri']) ?>" alt="TOTP QR" width="220" height="220">
        </div>
      <?php else: ?>
        <div class="muted u-mt-10"><?= e((string)t('setup.two_fa.qr_unavailable')) ?></div>
      <?php endif; ?>

      <?php if ($secret !== ''): ?>
        <div class="account-kicker u-mt-10"><?= e((string)t('setup.two_fa.manual_title')) ?></div>
        <div class="account-meta-value"><?= e($secret) ?></div>
      <?php endif; ?>

      <?php if ($uri !== ''): ?>
        <details class="u-mt-10">
          <summary><?= e((string)t('setup.two_fa.technical_uri')) ?></summary>
          <div class="muted u-mt-6 u-style-7523973809"><?= e($uri) ?></div>
        </details>
      <?php endif; ?>

      <div class="muted u-mt-10"><?= e((string)t('setup.two_fa.code_hint', ['email' => $email])) ?></div>

      <form method="post" action="/account/2fa/setup" class="account-form u-mt-10" novalidate>
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <label><?= e((string)t('setup.two_fa.code')) ?>
          <input class="input" type="text" name="code" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
        </label>
        <div class="row">
          <button class="btn" type="submit"><?= e((string)t('setup.two_fa.verify_title')) ?></button>
          <a class="btn" href="/account"><?= e((string)__('common.back')) ?></a>
        </div>
      </form>
    </section>
  </section>
</div>
