<?php require APP_ROOT . '/public/views/layouts/auth_header.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php
$qrImageSrc = trim((string)($qrImageSrc ?? ''));
$qrImageError = trim((string)($qrImageError ?? ''));
$error = trim((string)($error ?? ''));
$setupStatus = is_array($setupStatus ?? null) ? $setupStatus : [];
require APP_ROOT . '/public/views/setup/_stage_chrome.php';
?>

<div class="setup-bridge">
  <section class="card setup-bridge-hero">
    <div class="setup-bridge-eyebrow"><?= e(t('setup.two_fa.hero_eyebrow')) ?></div>
    <h1 class="setup-bridge-title"><?= e(t('setup.two_fa.hero_title')) ?></h1>
    <p class="setup-bridge-subtitle"><?= e(t('setup.two_fa.hero_subtitle', ['email' => $email])) ?></p>
  </section>

  <section class="card setup-bridge-panel setup-bridge-progress-panel">
    <div class="setup-bridge-progress">
      <div class="setup-bridge-step" data-state="done">
        <div class="setup-bridge-step-num"> <?= e($tt('base.phase_1_message')) ?> </div>
        <div class="setup-bridge-step-label"><?= e(t('setup.two_fa.phase_one')) ?></div>
      </div>
      <div class="setup-bridge-step" data-state="active">
        <div class="setup-bridge-step-num"> <?= e($tt('base.phase_2_message')) ?> </div>
        <div class="setup-bridge-step-label"><?= e(t('setup.two_fa.phase_two')) ?></div>
      </div>
      <div class="setup-bridge-step" data-state="future">
        <div class="setup-bridge-step-num"> <?= e($tt('base.phase_3_message')) ?> </div>
        <div class="setup-bridge-step-label"><?= e(t('setup.two_fa.phase_three')) ?></div>
      </div>
    </div>
  </section>

  <?php if ($error !== ''): ?>
    <section class="card setup-bridge-panel setup-bridge-panel-danger">
      <div class="setup-bridge-error-title"><?= e(t('setup.two_fa.error_title')) ?></div>
      <div class="setup-bridge-error-copy"><?= e($error) ?></div>
    </section>
  <?php endif; ?>

  <section class="card setup-bridge-panel">
    <div class="setup-bridge-head">
      <h3><?= e(t('setup.two_fa.title')) ?></h3>
      <p><?= e(t('setup.two_fa.subtitle')) ?></p>
    </div>

    <div class="setup-bridge-grid">
      <div class="setup-bridge-card setup-bridge-qr-card">
        <h4 class="setup-bridge-card-heading"><?= e(t('setup.two_fa.scan_title')) ?></h4>
        <?php if ($qrImageSrc !== ''): ?>
          <img
            src="<?= e($qrImageSrc) ?>"
            alt="TOTP setup QR code"
            class="setup-bridge-qr"
            loading="eager"
          >
          <p class="setup-bridge-card-copy"><?= e(t('setup.two_fa.scan_desc')) ?></p>
        <?php else: ?>
          <p class="setup-bridge-card-copy setup-bridge-warning-copy"><?= e(t('setup.two_fa.qr_unavailable')) ?></p>
          <?php if ($qrImageError !== ''): ?>
            <div class="setup-bridge-note setup-bridge-warning-note"><?= e($qrImageError) ?></div>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <div class="setup-bridge-col-stack">
        <div class="setup-bridge-card">
          <h4><?= e(t('setup.two_fa.manual_title')) ?></h4>
          <p><?= e(t('setup.two_fa.manual_desc')) ?></p>
          <div class="setup-bridge-key"><?= e($secret) ?></div>
          <details class="setup-bridge-tech-details">
            <summary class="setup-bridge-note"><?= e(t('setup.two_fa.technical_uri')) ?></summary>
            <div class="setup-bridge-key setup-bridge-key-compact"><?= e($uri) ?></div>
          </details>
        </div>

        <div class="setup-bridge-card">
          <h4><?= e(t('setup.two_fa.verify_title')) ?></h4>
          <p><?= e(t('setup.two_fa.verify_desc')) ?></p>

          <form method="post" action="/setup/2fa" class="setup-bridge-form">
            <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
            <label><?= e(t('setup.two_fa.code')) ?>
              <input class="input" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
            </label>
            <div class="setup-note"><?= e(t('setup.two_fa.code_hint', ['email' => $email])) ?></div>
            <div class="setup-bridge-actions">
              <button class="btn ok" type="submit" data-processing-text="<?= e(t('auth.verifying')) ?>"><?= e(t('setup.common.verify_and_continue')) ?></button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>
</div>

<script>
  document.addEventListener('submit', function (event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const submitter = event.submitter instanceof HTMLButtonElement ? event.submitter : null;
    if (!submitter || submitter.dataset.processingApplied === '1') return;
    submitter.dataset.processingApplied = '1';
    submitter.textContent = submitter.dataset.processingText || <?= json_encode((string)t('setup.common.processing')) ?>;
    submitter.disabled = true;
  });
</script>

<?php require APP_ROOT . '/public/views/layouts/auth_footer.php'; ?>
