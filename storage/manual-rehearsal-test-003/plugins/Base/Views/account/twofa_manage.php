<?php
declare(strict_types=1);

$flash = trim((string)($flash ?? ''));
$error = trim((string)($error ?? ''));
$account = is_array($account ?? null) ? $account : [];
$remaining = (int)($remaining ?? 0);
?>

<div class="account-shell">
  <section class="card">
    <div class="account-kicker"><?= e((string)__('account.section.security')) ?></div>
    <h2 class="account-title"><?= e((string)__('account.twofa.manage_title')) ?></h2>
    <div class="muted"><?= e((string)__('account.twofa.manage_subtitle')) ?></div>
  </section>

  <?php if ($flash !== ''): ?>
    <section class="card ops-section-card ops-accent-green"><div class="ui-block"><?= e($flash) ?></div></section>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <section class="card ops-section-card ops-accent-red"><div class="ui-block"><?= e($error) ?></div></section>
  <?php endif; ?>

  <section class="account-grid">
    <section class="card account-panel">
      <div class="account-kicker"><?= e((string)__('account.twofa.disable_title')) ?></div>
      <h3 class="account-title"><?= e((string)__('account.twofa.disable_subtitle')) ?></h3>
      <div class="muted"><?= e((string)__('account.twofa.code_note')) ?></div>
      <form method="post" action="/account/2fa/disable" class="account-form" novalidate>
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <label><?= e((string)t('setup.two_fa.code')) ?>
          <input class="input" type="text" name="code" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" maxlength="20" required>
        </label>
        <div class="row">
          <button class="btn" type="submit"><?= e((string)__('account.action.disable_2fa')) ?></button>
        </div>
      </form>
    </section>

    <section class="card account-panel">
      <div class="account-kicker"><?= e((string)__('account.twofa.regenerate_title')) ?></div>
      <h3 class="account-title"><?= e((string)__('account.twofa.regenerate_subtitle')) ?></h3>
      <div class="muted"><?= e((string)__('account.twofa.regenerate_note')) ?></div>
      <form method="post" action="/account/2fa/regenerate" class="account-form" novalidate>
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <label><?= e((string)t('setup.two_fa.code')) ?>
          <input class="input" type="text" name="code" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" maxlength="20" required>
        </label>
        <div class="row">
          <button class="btn" type="submit"><?= e((string)__('account.action.regenerate_2fa')) ?></button>
        </div>
      </form>
    </section>

    <section class="card account-panel">
      <div class="account-kicker"><?= e((string)__('account.twofa.recovery_codes_title')) ?></div>
      <h3 class="account-title"><?= e((string)__('account.twofa.recovery_codes_manage_title')) ?></h3>
      <div class="muted">
        <?= e((string)t('account.twofa.recovery_codes_remaining', ['count' => (string)$remaining])) ?>
      </div>
      <div class="muted u-mt-6"><?= e((string)__('account.twofa.recovery_codes_regen_note')) ?></div>
      <form method="post" action="/account/2fa/recovery-codes/regenerate" class="account-form" novalidate>
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <label><?= e((string)t('setup.two_fa.code')) ?>
          <input class="input" type="text" name="code" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" maxlength="20" required>
        </label>
        <div class="row">
          <button class="btn" type="submit"><?= e((string)__('account.action.regenerate_recovery_codes')) ?></button>
        </div>
      </form>
    </section>
  </section>

  <section class="card">
    <div class="muted"><?= e((string)__('account.twofa.current_state')) ?>: <strong><?= e((string)($account['twofa_status_label'] ?? '')) ?></strong></div>
    <div class="row u-style-d2c171b18b">
      <a class="btn" href="/account"><?= e((string)__('common.back')) ?></a>
    </div>
  </section>
</div>
