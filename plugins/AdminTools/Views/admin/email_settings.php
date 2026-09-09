<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$mailDashboard = is_array($mailDashboard ?? null) ? $mailDashboard : [];
$mailSettings = is_array($mailDashboard['settings'] ?? null) ? $mailDashboard['settings'] : [];
$mailMasked = is_array($mailDashboard['masked'] ?? null) ? $mailDashboard['masked'] : [];
$driverOptions = is_array($mailDashboard['driver_options'] ?? null) ? $mailDashboard['driver_options'] : [];
$encryptionOptions = is_array($mailDashboard['encryption_options'] ?? null) ? $mailDashboard['encryption_options'] : [];
$passwordPolicy = is_array($passwordPolicy ?? null) ? $passwordPolicy : [];
$message = (string)($message ?? '');
$error = (string)($error ?? '');
$testRecipient = (string)($testRecipient ?? '');
?>

<section class="card">
  <div class="compact-card-head">
    <div class="ui-block">
      <h2 class="u-style-1169661891"><?= t('admin.email_settings.title') ?></h2>
      <div class="muted"><?= t('admin.email_settings.subtitle') ?></div>
    </div>
    <a class="btn" href="/admin/system-tools"><?= t('admin.system_tools.nav.back') ?></a>
  </div>
</section>

<?php if ($message !== ''): ?>
  <section class="card u-style-a25b37e313"><?= e($message) ?></section>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <section class="card u-style-64e7e2c551"><?= e($error) ?></section>
<?php endif; ?>

<section class="card">
  <div class="compact-card-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891"><?= t('admin.email_settings.email.heading') ?></h3>
      <div class="muted"><?= t('admin.email_settings.email.subtitle') ?></div>
    </div>
    <span class="status-chip <?= !empty($mailDashboard['is_configured']) ? 'active' : 'pending' ?>"><?= !empty($mailDashboard['is_configured']) ? t('admin.email_settings.email.configured') : t('admin.email_settings.email.needs_setup') ?></span>
  </div>

  <div class="acb-help u-style-c5a49e8178">
    <?= t('admin.email_settings.email.config_priority') ?> Environment variables (ERP_MAIL_*) override database settings. See <a href="https://github.com/lazydeepak/OdareHub/blob/main/docs/MAIL-SERVICE-GUIDE.md" target="_blank">Mail Service Guide</a> for setup details.
  </div>

  <form method="post" action="/admin/system-tools/email-settings" class="admin-form-grid admin-form-grid-compact">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">

    <label><?= t('admin.email_settings.email.driver') ?>
      <select name="mail_driver">
        <?php foreach ($driverOptions as $key => $label): ?>
          <option value="<?= e((string)$key) ?>" <?= (string)($mailSettings['mail.driver'] ?? '') === (string)$key ? 'selected' : '' ?>><?= e((string)$label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><?= t('admin.email_settings.email.smtp_host') ?>
      <input class="input" type="text" name="mail_smtp_host" value="<?= e((string)($mailSettings['mail.smtp_host'] ?? '')) ?>" placeholder="<?= htmlspecialchars(t('admin.email_settings.email.smtp_host_placeholder')) ?>">
    </label>
    <label><?= t('admin.email_settings.email.smtp_port') ?>
      <input class="input" type="number" name="mail_smtp_port" value="<?= e((string)($mailSettings['mail.smtp_port'] ?? '587')) ?>" min="1" max="65535">
    </label>
    <label><?= t('admin.email_settings.email.encryption') ?>
      <select name="mail_smtp_encryption">
        <?php foreach ($encryptionOptions as $key => $label): ?>
          <option value="<?= e((string)$key) ?>" <?= (string)($mailSettings['mail.smtp_encryption'] ?? '') === (string)$key ? 'selected' : '' ?>><?= e((string)$label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><?= t('admin.email_settings.email.smtp_username') ?>
      <input class="input" type="text" name="mail_smtp_username" value="<?= e((string)($mailSettings['mail.smtp_username'] ?? '')) ?>" autocomplete="off">
    </label>
    <label><?= t('admin.email_settings.email.smtp_password') ?>
      <input class="input" type="password" name="mail_smtp_password" placeholder="<?= e((string)($mailMasked['mail.smtp_password_masked'] ?? '')) ?>" autocomplete="new-password">
      <small><?= t('admin.email_settings.email.smtp_password_hint') ?></small>
    </label>
    <label><?= t('admin.email_settings.email.from_email') ?>
      <input class="input" type="email" name="mail_from_email" value="<?= e((string)($mailSettings['mail.from_email'] ?? '')) ?>" required>
    </label>
    <label><?= t('admin.email_settings.email.from_name') ?>
      <input class="input" type="text" name="mail_from_name" value="<?= e((string)($mailSettings['mail.from_name'] ?? '')) ?>" maxlength="190">
    </label>
    <label><?= t('admin.email_settings.email.reply_to_email') ?>
      <input class="input" type="email" name="mail_reply_to_email" value="<?= e((string)($mailSettings['mail.reply_to_email'] ?? '')) ?>">
    </label>
    <label><?= t('admin.email_settings.email.reply_to_name') ?>
      <input class="input" type="text" name="mail_reply_to_name" value="<?= e((string)($mailSettings['mail.reply_to_name'] ?? '')) ?>" maxlength="190">
    </label>
    <div class="admin-action-row admin-form-span-full">
      <button class="btn ok" type="submit"><?= t('admin.email_settings.email.save_btn') ?></button>
    </div>
  </form>

  <div class="acb-help u-style-56f4356299">
    <?= t('admin.email_settings.email.saved_by') ?> <strong><?= e((string)($mailSettings['mail.updated_by'] ?? '-')) ?></strong>
    <span class="muted"><?= t('admin.email_settings.email.updated') ?> <?= e((string)($mailSettings['mail.updated_at'] ?? '-')) ?></span>
  </div>
</section>

<section class="card">
  <div class="compact-card-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891"><?= t('admin.email_settings.test.heading') ?></h3>
      <div class="muted"><?= t('admin.email_settings.test.subtitle') ?></div>
    </div>
  </div>

  <form method="post" action="/admin/system-tools/email-settings/test" class="admin-form-grid admin-form-grid-compact">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <label class="admin-form-span-2"><?= t('admin.email_settings.test.recipient') ?>
      <input class="input" type="email" name="test_email_to" value="<?= e($testRecipient) ?>" required placeholder="<?= htmlspecialchars(t('admin.email_settings.test.recipient_placeholder')) ?>">
    </label>
    <div class="admin-action-row admin-form-span-full">
      <button class="btn" type="submit"><?= t('admin.email_settings.test.btn') ?></button>
    </div>
  </form>
</section>

<section class="card">
  <div class="compact-card-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891"><?= t('admin.email_settings.policy.heading') ?></h3>
      <div class="muted"><?= t('admin.email_settings.policy.subtitle') ?></div>
    </div>
  </div>

  <form method="post" action="/admin/system-tools/password-policy" class="admin-form-grid admin-form-grid-compact">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <label><?= t('admin.email_settings.policy.min_length') ?>
      <input class="input" type="number" name="password_min_length" value="<?= e((string)($passwordPolicy['security.password_min_length'] ?? '10')) ?>" min="10" max="128">
    </label>
    <label>
      <span class="u-style-210f216977"><?= t('admin.email_settings.policy.require_lowercase') ?></span>
      <select name="password_require_lowercase">
        <option value="1" <?= (string)($passwordPolicy['security.password_require_lowercase'] ?? '1') === '1' ? 'selected' : '' ?>><?= t('admin.email_settings.policy.required') ?></option>
        <option value="0" <?= (string)($passwordPolicy['security.password_require_lowercase'] ?? '1') === '0' ? 'selected' : '' ?>><?= t('admin.email_settings.policy.not_required') ?></option>
      </select>
    </label>
    <label>
      <span class="u-style-210f216977"><?= t('admin.email_settings.policy.require_uppercase') ?></span>
      <select name="password_require_uppercase">
        <option value="1" <?= (string)($passwordPolicy['security.password_require_uppercase'] ?? '1') === '1' ? 'selected' : '' ?>><?= t('admin.email_settings.policy.required') ?></option>
        <option value="0" <?= (string)($passwordPolicy['security.password_require_uppercase'] ?? '1') === '0' ? 'selected' : '' ?>><?= t('admin.email_settings.policy.not_required') ?></option>
      </select>
    </label>
    <label>
      <span class="u-style-210f216977"><?= t('admin.email_settings.policy.require_number') ?></span>
      <select name="password_require_number">
        <option value="1" <?= (string)($passwordPolicy['security.password_require_number'] ?? '1') === '1' ? 'selected' : '' ?>><?= t('admin.email_settings.policy.required') ?></option>
        <option value="0" <?= (string)($passwordPolicy['security.password_require_number'] ?? '1') === '0' ? 'selected' : '' ?>><?= t('admin.email_settings.policy.not_required') ?></option>
      </select>
    </label>
    <label>
      <span class="u-style-210f216977"><?= t('admin.email_settings.policy.special_bonus') ?></span>
      <select name="password_special_bonus">
        <option value="1" <?= (string)($passwordPolicy['security.password_special_bonus'] ?? '1') === '1' ? 'selected' : '' ?>><?= t('admin.email_settings.policy.enabled') ?></option>
        <option value="0" <?= (string)($passwordPolicy['security.password_special_bonus'] ?? '1') === '0' ? 'selected' : '' ?>><?= t('admin.email_settings.policy.disabled') ?></option>
      </select>
    </label>
    <div class="admin-action-row admin-form-span-full">
      <button class="btn ok" type="submit"><?= t('admin.email_settings.policy.save_btn') ?></button>
    </div>
  </form>

  <div class="acb-help u-style-56f4356299">
    <?= t('admin.email_settings.policy.updated_by') ?> <strong><?= e((string)($passwordPolicy['security.password_updated_by'] ?? '-')) ?></strong>
    <span class="muted"><?= t('admin.email_settings.policy.updated') ?> <?= e((string)($passwordPolicy['security.password_updated_at'] ?? '-')) ?></span>
  </div>
</section>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
