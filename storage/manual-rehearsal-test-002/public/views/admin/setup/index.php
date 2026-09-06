<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$platformProfiles = is_array($platformProfiles ?? null) ? $platformProfiles : [];
$wizardCards = is_array($wizardCards ?? null) ? $wizardCards : [];
$csrf = (string)($csrf ?? '');
$setupStatusClass = static function (string $status): string {
    return match ($status) {
        'configured', 'verified', 'installed', 'created', 'applied' => 'setup-overview-status--ready',
        'warning', 'partial', 'needs_setup', 'pending', 'previewed' => 'setup-overview-status--warning',
        'failed', 'rolled_back' => 'setup-overview-status--error',
        'running' => 'setup-overview-status--active',
        default => 'setup-overview-status--neutral',
    };
};
?>

<?php $setupNavCurrent = 'overview'; require __DIR__ . '/_nav.php'; ?>
<?php require __DIR__ . '/_flash.php'; ?>

<div class="card">
  <h2 class="setup-overview-title"><?= e(t('setup.overview.title')) ?></h2>
  <div class="muted"><?= e(t('setup.overview.subtitle')) ?></div>
</div>

<div class="setup-overview-grid setup-overview-grid--cards">
  <?php foreach ($wizardCards as $card): ?>
    <div class="card setup-overview-card">
      <div class="setup-overview-head">
        <div>
          <h3 class="setup-overview-title"><?= e((string)($card['title'] ?? '')) ?></h3>
          <div class="muted"><?= e((string)($card['subtitle'] ?? '')) ?></div>
        </div>
        <span class="pill setup-overview-status <?= e($setupStatusClass((string)($card['status'] ?? ''))) ?>"><?= e((string)($card['status_label'] ?? '')) ?></span>
      </div>
      <div class="setup-overview-summary"><?= e((string)($card['summary'] ?? '')) ?></div>
      <div class="muted setup-overview-next"><?= e(t('setup.status.next_prefix')) ?> <?= e((string)($card['next_action'] ?? '')) ?></div>
      <?php if ((string)($card['hint'] ?? '') !== ''): ?>
        <div class="setup-overview-hint"><?= e((string)($card['hint'] ?? '')) ?></div>
      <?php endif; ?>
      <a class="btn ok" href="<?= e((string)($card['url'] ?? '#')) ?>"><?= e(t('common.open')) ?></a>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <h3 class="setup-overview-title"><?= e(t('setup.overview.profiles.title')) ?></h3>
  <div class="muted setup-overview-copy"><?= e(t('setup.overview.profiles.subtitle')) ?></div>
  <div class="setup-overview-grid setup-overview-grid--profiles">
    <?php foreach ($platformProfiles as $profile): ?>
      <form method="post" action="/admin/setup/profile" class="card setup-overview-card">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="profile_key" value="<?= e((string)($profile['key'] ?? '')) ?>">
        <strong><?= e((string)($profile['label'] ?? '')) ?></strong>
        <div class="muted setup-overview-profile-copy"><?= e((string)($profile['description'] ?? '')) ?></div>
        <button class="btn ok" type="submit"><?= e(t('setup.overview.profiles.run')) ?></button>
      </form>
    <?php endforeach; ?>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
