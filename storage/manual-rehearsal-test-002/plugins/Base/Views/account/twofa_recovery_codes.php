<?php
declare(strict_types=1);

$flash     = trim((string)($flash ?? ''));
$error     = trim((string)($error ?? ''));
$codes     = is_array($codes ?? null) ? $codes : [];
$remaining = (int)($remaining ?? 0);
$hasNew    = count($codes) > 0;
?>

<div class="account-shell">
  <section class="card">
    <div class="account-kicker"><?= e((string)__('account.section.security')) ?></div>
    <h2 class="account-title"><?= e((string)__('account.twofa.recovery_codes_title')) ?></h2>
    <div class="muted"><?= e((string)__('account.twofa.recovery_codes_subtitle')) ?></div>
  </section>

  <?php if ($flash !== ''): ?>
    <section class="card ops-section-card ops-accent-green"><div class="ui-block"><?= e($flash) ?></div></section>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <section class="card ops-section-card ops-accent-red"><div class="ui-block"><?= e($error) ?></div></section>
  <?php endif; ?>

  <?php if ($hasNew): ?>
    <section class="card ops-section-card ops-accent-yellow">
      <strong><?= e((string)__('account.twofa.recovery_codes_save_warning')) ?></strong>
      <div class="muted u-mt-6"><?= e((string)__('account.twofa.recovery_codes_save_note')) ?></div>
    </section>

    <section class="card account-panel">
      <div class="account-kicker"><?= e((string)__('account.twofa.recovery_codes_list_title')) ?></div>
      <div class="u-style-d49f02d976">
        <?php foreach ($codes as $c): ?>
          <div class="ui-block"><?= e((string)$c) ?></div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php else: ?>
    <section class="card account-panel">
      <div class="muted"><?= e((string)__('account.twofa.recovery_codes_already_shown')) ?></div>
      <div class="muted u-mt-6">
        <?= e((string)t('account.twofa.recovery_codes_remaining', ['count' => (string)$remaining])) ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="card">
    <div class="row">
      <a class="btn" href="/account"><?= e((string)__('common.back')) ?></a>
      <a class="btn" href="/account/2fa/manage"><?= e((string)__('account.twofa.manage_title')) ?></a>
    </div>
  </section>
</div>
